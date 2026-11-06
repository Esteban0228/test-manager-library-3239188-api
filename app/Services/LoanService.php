<?php

namespace App\Services;

use App\Domain\LoanRules;
use App\Exceptions\ApiException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\User;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** HU-06 a HU-09: prestar, devolver, listar y vencidos. */
final class LoanService
{
    /**
     * @param list<string> $bookIds ids en el orden de la petición
     */
    public function lend(User $by, string $memberId, array $bookIds): Loan
    {
        // CA-06.2
        foreach ($bookIds as $i => $id) {
            if (array_search($id, $bookIds, true) !== $i) {
                throw ApiException::unprocessable('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir el mismo libro en un préstamo.', [
                    ['field' => "items[{$i}].book_id", 'issue' => 'repetido'],
                ]);
            }
        }

        $now = Time::now();

        $loanId = DB::transaction(function () use ($by, $memberId, $bookIds, $now): string {
            // Serializa los préstamos de un mismo socio para que el tope de 3 no se burle en paralelo.
            Member::query()->lockForUpdate()->find($memberId) ?? throw ApiException::notFound('Socio');

            $open = DB::table('library.loan_item as li')
                ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
                ->where('l.member_id', $memberId)
                ->whereNull('li.returned_at');

            // CA-06.5 / RN-3
            if ((clone $open)->where('li.due_at', '<', Time::sql($now))->exists()) {
                throw ApiException::conflict('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y no puede pedir otros hasta devolverlos.');
            }

            // CA-06.4 / RN-2
            $openCount = (clone $open)->count();
            if ($openCount + count($bookIds) > LoanRules::MAX_OPEN_ITEMS_PER_MEMBER) {
                throw ApiException::unprocessable(
                    'MEMBER_LIMIT_EXCEEDED',
                    "El socio ya tiene {$openCount} libro(s) sin devolver; el máximo es ".LoanRules::MAX_OPEN_ITEMS_PER_MEMBER.'.',
                    [['field' => 'items', 'issue' => "sin devolver: {$openCount}"]]
                );
            }

            $books = Book::query()->active()->with('category')->whereIn('id', $bookIds)->get()->keyBy('id');
            foreach ($bookIds as $i => $id) {
                if (! $books->has($id)) {
                    throw ApiException::notFound('Libro');
                }
            }

            // Orden estable por id para que dos préstamos concurrentes no se bloqueen en círculo.
            $sorted = $bookIds;
            sort($sorted);
            foreach ($sorted as $id) {
                // Descuento atómico: solo prospera si queda al menos un ejemplar (RNF-2, ADR-002).
                $affected = DB::table('library.book')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->where('available_copies', '>', 0)
                    ->decrement('available_copies');

                if ($affected === 0) {
                    $idx = array_search($id, $bookIds, true);
                    throw ApiException::conflict('BOOK_NOT_AVAILABLE', "No quedan ejemplares disponibles de «{$books[$id]->title}».", [
                        ['field' => "items[{$idx}].book_id", 'issue' => 'sin ejemplares'],
                    ]);
                }
            }

            $loan = Loan::create(['member_id' => $memberId, 'loaned_by_user_id' => $by->id, 'loaned_at' => $now]);

            foreach ($bookIds as $id) {
                $book = $books[$id];
                LoanItem::create([
                    'loan_id' => $loan->id,
                    'book_id' => $id,
                    'book_title' => $book->title,          // congelado (RN-4)
                    'isbn' => $book->isbn,                 // congelado
                    'category_name' => $book->category->name, // congelado
                    'due_at' => $now->addDays(LoanRules::LOAN_DAYS),
                    'returned_at' => null,
                ]);
            }

            return $loan->id;
        });

        return $this->find($loanId);
    }

    public function find(string $id): Loan
    {
        return Loan::query()->with('items')->find($id) ?? throw ApiException::notFound('Préstamo');
    }

    public function returnItem(string $loanId, string $itemId): LoanItem
    {
        return DB::transaction(function () use ($loanId, $itemId): LoanItem {
            $item = LoanItem::query()->where('id', $itemId)->where('loan_id', $loanId)->lockForUpdate()->first()
                ?? throw ApiException::notFound('Línea de préstamo');

            if ($item->returned_at !== null) {
                throw ApiException::conflict('ITEM_ALREADY_RETURNED', 'Esta línea ya fue devuelta.'); // CA-07.2
            }

            $item->returned_at = Time::now();
            $item->save();

            // Si esto supera el total, salta ck_book_available_range: sería un defecto, no un caso de negocio.
            DB::table('library.book')->where('id', $item->book_id)->increment('available_copies');

            return $item->refresh();
        });
    }

    /** @return array{0:\Illuminate\Database\Eloquent\Collection<int,Loan>,1:int} */
    public function list(array $f, int $page, int $size): array
    {
        $now = Time::sql(Time::now());
        $query = Loan::query()->with('items');

        if (! empty($f['from'])) {
            $query->where('loaned_at', '>=', Time::sql(CarbonImmutable::createFromFormat('Y-m-d', $f['from'], 'UTC')->startOfDay()));
        }
        if (! empty($f['to'])) {
            $query->where('loaned_at', '<', Time::sql(CarbonImmutable::createFromFormat('Y-m-d', $f['to'], 'UTC')->startOfDay()->addDay()));
        }
        if (! empty($f['member_id'])) {
            $query->where('member_id', $f['member_id']);
        }

        $unreturned = fn ($s) => $s->selectRaw('1')->from('library.loan_item as li')
            ->whereColumn('li.loan_id', 'library.loan.id')->whereNull('li.returned_at');
        $overdue = fn ($s) => $unreturned($s)->where('li.due_at', '<', $now);

        match ($f['status'] ?? null) {
            'closed' => $query->whereNotExists($unreturned),
            'overdue' => $query->whereExists($overdue),
            'open' => $query->whereExists($unreturned)->whereNotExists($overdue),
            default => null,
        };

        $total = (clone $query)->count();
        $items = $query->orderByDesc('loaned_at')->orderBy('id')->forPage($page, $size)->get();

        return [$items, $total];
    }

    /** HU-09: líneas vencidas, las de más retraso primero. */
    public function overdue(int $page, int $size): array
    {
        $now = Time::now();

        $query = DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('li.returned_at')
            ->where('li.due_at', '<', Time::sql($now));

        $total = (clone $query)->count();
        $rows = $query->orderBy('li.due_at')->orderBy('li.id')
            ->select('l.id as loan_id', 'li.id as item_id', 'li.book_title', 'm.full_name as member_name', 'li.due_at')
            ->forPage($page, $size)->get();

        $items = $rows->map(function ($r) use ($now) {
            $due = Time::parse($r->due_at);

            return [
                'loan_id' => $r->loan_id,
                'item_id' => $r->item_id,
                'book_title' => $r->book_title,
                'member_name' => $r->member_name,
                'due_at' => Time::iso($due),
                'days_late' => max(0, intdiv($now->getTimestamp() - $due->getTimestamp(), 86400)),
            ];
        })->all();

        return [$items, $total];
    }
}
