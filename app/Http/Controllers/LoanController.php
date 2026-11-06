<?php

namespace App\Http\Controllers;

use App\Domain\LoanRules;
use App\Exceptions\ApiException;
use App\Http\Presenter;
use App\Services\LoanService;
use App\Support\Paging;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans)
    {
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:'.LoanRules::MAX_ITEMS_PER_LOAN],
            'items.*.book_id' => ['required', 'uuid'],
        ]);

        $bookIds = array_map(fn (array $i) => strtolower($i['book_id']), array_values($d['items']));
        $loan = $this->loans->lend($request->attributes->get('auth_user'), strtolower($d['member_id']), $bookIds);

        return response()->json(Presenter::loan($loan), 201);
    }

    public function index(Request $request)
    {
        [$page, $size] = Paging::params($request);
        $f = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:open,overdue,closed'],
            'member_id' => ['nullable', 'uuid'],
        ]);

        if (! empty($f['from']) && ! empty($f['to']) && $f['to'] < $f['from']) {
            throw ApiException::unprocessable('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.');
        }

        [$items, $total] = $this->loans->list($f, $page, $size);

        return response()->json(Paging::envelope($items->map(fn ($l) => Presenter::loan($l))->values()->all(), $page, $size, $total));
    }

    public function show(string $id)
    {
        return response()->json(Presenter::loan($this->loans->find($id)));
    }

    public function returnItem(string $loanId, string $itemId)
    {
        return response()->json(Presenter::item($this->loans->returnItem($loanId, $itemId)));
    }

    public function overdue(Request $request)
    {
        [$page, $size] = Paging::params($request);
        [$items, $total] = $this->loans->overdue($page, $size);

        return response()->json(Paging::envelope($items, $page, $size, $total));
    }
}
