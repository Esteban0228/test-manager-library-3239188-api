<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Services\BookService;
use App\Support\Paging;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function __construct(private readonly BookService $books)
    {
    }

    public function index(Request $request)
    {
        [$page, $size] = Paging::params($request);
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'uuid'],
        ]);

        [$items, $total] = $this->books->search($f['q'] ?? null, $f['category_id'] ?? null, $page, $size);

        return response()->json(Paging::envelope($items->map(fn ($b) => Presenter::book($b))->values()->all(), $page, $size, $total));
    }

    public function show(string $id)
    {
        return response()->json(Presenter::book($this->books->find($id)));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'isbn' => ['required', 'string', 'regex:/^\d{13}$/'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(Presenter::book($this->books->create($d)), 201);
    }

    public function update(Request $request, string $id)
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(Presenter::book($this->books->update($id, $d)));
    }

    public function destroy(string $id)
    {
        $this->books->remove($id);

        return response()->noContent();
    }

    public function setCover(Request $request, string $id)
    {
        $request->validate([
            // El tipo se valida por contenido, no por extensión; máx. 2 MB (api-contract §4).
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png', 'max:2048'],
        ]);

        return response()->json(Presenter::book($this->books->setCover($id, $request->file('file'))));
    }

    public function removeCover(string $id)
    {
        $this->books->removeCover($id);

        return response()->noContent();
    }

    public function cover(string $id)
    {
        [$path, $mime] = $this->books->coverPath($id);

        return response()->file($path, ['Content-Type' => $mime]);
    }
}
