<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Prefijo /api/v1 definido en bootstrap/app.php (api-contract §1).

Route::get('health', HealthController::class);
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function () {
    Route::post('users', [AuthController::class, 'createUser'])->middleware('role:admin');

    Route::get('categories', [CategoryController::class, 'index']);

    Route::get('books', [BookController::class, 'index']);
    Route::post('books', [BookController::class, 'store'])->middleware('role:admin');
    Route::get('books/{id}', [BookController::class, 'show'])->whereUuid('id');
    Route::put('books/{id}', [BookController::class, 'update'])->whereUuid('id')->middleware('role:admin');
    Route::delete('books/{id}', [BookController::class, 'destroy'])->whereUuid('id')->middleware('role:admin');
    Route::get('books/{id}/cover', [BookController::class, 'cover'])->whereUuid('id');
    Route::put('books/{id}/cover', [BookController::class, 'setCover'])->whereUuid('id')->middleware('role:admin');
    Route::delete('books/{id}/cover', [BookController::class, 'removeCover'])->whereUuid('id')->middleware('role:admin');

    Route::middleware('role:admin,librarian')->group(function () {
        Route::get('members', [MemberController::class, 'index']);
        Route::post('members', [MemberController::class, 'store']);
        Route::get('members/{id}', [MemberController::class, 'show'])->whereUuid('id');

        Route::post('loans', [LoanController::class, 'store']);
        Route::get('loans', [LoanController::class, 'index']);
        Route::get('loans/overdue', [LoanController::class, 'overdue']); // antes de loans/{id}
        Route::get('loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
        Route::post('loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem'])
            ->whereUuid(['loan_id', 'item_id']);
    });

    Route::get('reports/most-borrowed', [ReportController::class, 'mostBorrowed'])->middleware('role:admin');
});
