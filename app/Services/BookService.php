<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Book;
use App\Models\Category;
use App\Models\LoanItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** HU-03 y HU-04. */
final class BookService
{
    public function __construct(private readonly CoverStorage $covers)
    {
    }

    /** @return array{0:Collection<int,Book>,1:int} */
    public function search(?string $q, ?string $categoryId, int $page, int $size): array
    {
        $query = Book::query()->active()->with('category');

        if ($q !== null && $q !== '') {
            $like = '%'.addcslashes($q, '\\%_').'%';
            $query->where(fn ($w) => $w->where('title', 'ilike', $like)->orWhere('author', 'ilike', $like));
        }
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('title')->orderBy('id')->forPage($page, $size)->get();

        return [$items, $total];
    }

    public function find(string $id): Book
    {
        return Book::query()->active()->with('category')->find($id) ?? throw ApiException::notFound('Libro');
    }

    public function create(array $d): Book
    {
        $this->requireCategory($d['category_id']);

        if (Book::query()->active()->where('isbn', $d['isbn'])->exists()) {
            throw self::isbnTaken($d['isbn']);
        }

        try {
            $book = Book::create([
                'title' => $d['title'],
                'author' => $d['author'],
                'isbn' => $d['isbn'],
                'category_id' => $d['category_id'],
                'total_copies' => $d['total_copies'],
                'available_copies' => $d['total_copies'], // CA-03.1
            ]);
        } catch (UniqueConstraintViolationException) {
            throw self::isbnTaken($d['isbn']);
        }

        return $this->find($book->id);
    }

    public function update(string $id, array $d): Book
    {
        $this->requireCategory($d['category_id']);

        DB::transaction(function () use ($id, $d): void {
            $book = Book::query()->active()->lockForUpdate()->find($id) ?? throw ApiException::notFound('Libro');

            // CA-03.3: el total cambia y los disponibles se ajustan en la misma diferencia.
            $available = $book->available_copies + ($d['total_copies'] - $book->total_copies);
            if ($available < 0) {
                throw ApiException::unprocessable(
                    'VALIDATION_ERROR',
                    'No se puede reducir el total por debajo de los ejemplares que están prestados.',
                    [['field' => 'total_copies', 'issue' => 'menor que los ejemplares prestados']]
                );
            }

            $book->update([
                'title' => $d['title'],
                'author' => $d['author'],
                'category_id' => $d['category_id'],
                'total_copies' => $d['total_copies'],
                'available_copies' => $available,
            ]);
        });

        return $this->find($id);
    }

    /** Baja lógica (CA-03.5). */
    public function remove(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $book = Book::query()->active()->lockForUpdate()->find($id) ?? throw ApiException::notFound('Libro');

            if (LoanItem::query()->where('book_id', $id)->whereNull('returned_at')->exists()) {
                throw ApiException::conflict('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos abiertos y no se puede dar de baja.');
            }

            $book->update(['deleted_at' => \App\Support\Time::now()]);
        });
    }

    public function setCover(string $id, UploadedFile $file): Book
    {
        $book = Book::query()->active()->find($id) ?? throw ApiException::notFound('Libro');

        $ext = $file->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $key = Str::uuid().'.'.$ext;
        $old = $book->cover_key;

        $this->covers->put($key, $file->get());
        try {
            $book->update(['cover_key' => $key]);
        } catch (\Throwable $e) {
            $this->covers->delete($key);
            throw $e;
        }

        // Primero se confirma la base; después se borra el binario (data-model §7).
        if ($old !== null) {
            $this->covers->delete($old);
        }

        return $this->find($id);
    }

    public function removeCover(string $id): void
    {
        $book = Book::query()->active()->find($id) ?? throw ApiException::notFound('Libro');
        $old = $book->cover_key;

        $book->update(['cover_key' => null]); // nunca cadena vacía
        if ($old !== null) {
            $this->covers->delete($old);
        }
    }

    public function coverPath(string $id): array
    {
        $book = Book::query()->active()->find($id) ?? throw ApiException::notFound('Libro');
        if ($book->cover_key === null || ! $this->covers->exists($book->cover_key)) {
            throw ApiException::notFound('Portada');
        }

        return [$this->covers->path($book->cover_key), str_ends_with($book->cover_key, '.png') ? 'image/png' : 'image/jpeg'];
    }

    private function requireCategory(string $categoryId): void
    {
        if (! Category::query()->whereKey($categoryId)->exists()) {
            throw ApiException::unprocessable('VALIDATION_ERROR', 'La categoría indicada no existe.', [
                ['field' => 'category_id', 'issue' => 'no existe'],
            ]);
        }
    }

    private static function isbnTaken(string $isbn): ApiException
    {
        return ApiException::conflict('ISBN_TAKEN', "Ya existe un libro activo con el ISBN {$isbn}.");
    }
}
