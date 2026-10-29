<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Models\FinancialTransaction;
use App\Services\Finance\FinanceService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceSummaryController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function export(ListFinanceRequest $request): StreamedResponse
    {
        $team = $request->team();
        Gate::authorize('viewAny', [FinancialTransaction::class, $team]);
        $rows = $this->finance->transactionExport($request->user(), $team, $request->validated());

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Tidak dapat membuka ekspor CSV.');
            }
            fputcsv($output, ['id', 'farm_id', 'planting_cycle_id', 'category_id', 'transaction_date', 'transaction_type', 'amount', 'description'], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($output, $row, ',', '"', '');
            }
            fclose($output);
        }, 'finance-transactions.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
