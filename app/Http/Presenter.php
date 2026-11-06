<?php

namespace App\Http;

use App\Domain\LoanStatus;
use App\Models\Book;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\User;
use App\Support\Time;

/** Forma exacta de las respuestas de api-contract.md §3. Nunca expone password_hash. */
final class Presenter
{
    public static function user(User $u): array
    {
        return ['id' => $u->id, 'username' => $u->username, 'role' => $u->role];
    }

    public static function book(Book $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title,
            'author' => $b->author,
            'isbn' => $b->isbn,
            'category' => ['id' => $b->category->id, 'name' => $b->category->name],
            'total_copies' => $b->total_copies,
            'available_copies' => $b->available_copies,
            'cover_key' => $b->cover_key,
        ];
    }

    public static function member(Member $m, ?int $activeLoans = null): array
    {
        $data = ['id' => $m->id, 'full_name' => $m->full_name, 'document_number' => $m->document_number];
        if ($activeLoans !== null) {
            $data['active_loans'] = $activeLoans;
        }

        return $data;
    }

    public static function item(LoanItem $i): array
    {
        return [
            'id' => $i->id,
            'book_id' => $i->book_id,
            'book_title' => $i->book_title,
            'isbn' => $i->isbn,
            'category_name' => $i->category_name,
            'due_at' => Time::iso($i->due_at),
            'returned_at' => Time::iso($i->returned_at),
        ];
    }

    public static function loan(Loan $l): array
    {
        $status = LoanStatus::compute(
            $l->items->map(fn (LoanItem $i) => ['due_at' => $i->due_at, 'returned_at' => $i->returned_at]),
            Time::now()
        );

        return [
            'id' => $l->id,
            'member_id' => $l->member_id,
            'loaned_at' => Time::iso($l->loaned_at),
            'status' => $status->value,
            'items' => $l->items->map(fn (LoanItem $i) => self::item($i))->values()->all(),
        ];
    }
}
