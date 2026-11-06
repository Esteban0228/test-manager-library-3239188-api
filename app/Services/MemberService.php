<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** HU-05. */
final class MemberService
{
    public function search(?string $q, int $page, int $size): array
    {
        $query = Member::query();
        if ($q !== null && $q !== '') {
            $like = '%'.addcslashes($q, '\\%_').'%';
            $query->where(fn ($w) => $w->where('full_name', 'ilike', $like)->orWhere('document_number', 'ilike', $like));
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('full_name')->orderBy('id')->forPage($page, $size)->get();

        return [$items, $total];
    }

    /** @return array{0:Member,1:int} socio y conteo de líneas sin devolver */
    public function find(string $id): array
    {
        $member = Member::find($id) ?? throw ApiException::notFound('Socio');

        $active = DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.member_id', $id)
            ->whereNull('li.returned_at')
            ->count();

        return [$member, $active];
    }

    public function create(string $fullName, string $documentNumber): Member
    {
        if (Member::query()->where('document_number', $documentNumber)->exists()) {
            throw self::documentTaken();
        }

        try {
            return Member::create(['full_name' => $fullName, 'document_number' => $documentNumber]);
        } catch (UniqueConstraintViolationException) {
            throw self::documentTaken();
        }
    }

    private static function documentTaken(): ApiException
    {
        return ApiException::conflict('DOCUMENT_TAKEN', 'Ya existe un socio con ese número de documento.');
    }
}
