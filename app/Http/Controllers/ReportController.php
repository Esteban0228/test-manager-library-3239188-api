<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function mostBorrowed(Request $request)
    {
        $d = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($d['to'] < $d['from']) {
            throw ApiException::unprocessable('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.'); // CA-10.2
        }

        return response()->json([
            'from' => $d['from'],
            'to' => $d['to'],
            'rows' => $this->reports->mostBorrowed($d['from'], $d['to']),
        ]);
    }
}
