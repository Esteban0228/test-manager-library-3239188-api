<?php

namespace App\Http\Controllers;

use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Category::query()->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()
        );
    }
}
