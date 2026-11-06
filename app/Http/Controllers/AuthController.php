<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function login(Request $request)
    {
        $d = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        return response()->json($this->auth->login($d['username'], $d['password']));
    }

    public function createUser(Request $request)
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => AuthService::normalize($request->input('username'))]);
        }

        $d = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'role' => ['required', 'in:librarian'], // pedir admin por la API → 422 (CA-02.3)
        ]);

        return response()->json(Presenter::user($this->auth->createLibrarian($d['username'], $d['password'])), 201);
    }
}
