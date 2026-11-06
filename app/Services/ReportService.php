<?php

namespace App\Services;

use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** HU-10 / ADR-004: una consulta agregada sobre los valores congelados en la línea. */
final class ReportService
{
    /** @return list<array{book_id:string,book_title:string,category_name:string,times_borrowed:int}> */
    public function mostBorrowed(string $from, string $to): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $from, 'UTC')->startOfDay();
        $endExclusive = CarbonImmutable::createFromFormat('Y-m-d', $to, 'UTC')->startOfDay()->addDay(); // «to» inclusivo

        return DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.loaned_at', '>=', Time::sql($start))
            ->where('l.loaned_at', '<', Time::sql($endExclusive))
            ->groupBy('li.book_id', 'li.book_title', 'li.category_name')
            ->selectRaw('li.book_id, li.book_title, li.category_name, count(*) as times_borrowed')
            ->orderByDesc('times_borrowed')
            ->orderBy('li.book_title')
            ->orderBy('li.category_name')
            ->get()
            ->map(fn ($r) => [
                'book_id' => $r->book_id,
                'book_title' => $r->book_title,
                'category_name' => $r->category_name,
                'times_borrowed' => (int) $r->times_borrowed,
            ])->all();
    }
}
