<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Services\MemberService;
use App\Support\Paging;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private readonly MemberService $members)
    {
    }

    public function index(Request $request)
    {
        [$page, $size] = Paging::params($request);
        $f = $request->validate(['q' => ['nullable', 'string', 'max:200']]);

        [$items, $total] = $this->members->search($f['q'] ?? null, $page, $size);

        return response()->json(Paging::envelope($items->map(fn ($m) => Presenter::member($m))->values()->all(), $page, $size, $total));
    }

    public function show(string $id)
    {
        [$member, $active] = $this->members->find($id);

        return response()->json(Presenter::member($member, $active));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'document_number' => ['required', 'string', 'max:30'],
        ]);

        return response()->json(Presenter::member($this->members->create($d['full_name'], $d['document_number'])), 201);
    }
}
