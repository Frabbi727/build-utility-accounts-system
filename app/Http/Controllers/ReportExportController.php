<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Reporting\ReportCsvService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $type, ReportCsvService $csvService): StreamedResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : null;
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : null;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->string('as_of')->toString()) : null;

        $filename = "{$type}-".now()->format('Y-m-d').'.csv';
        $content = '';

        switch ($type) {
            case 'trial-balance':
                $content = $csvService->trialBalance($from, $to ?? $asOf);
                break;

            case 'owner-dues':
                $content = $csvService->ownerDues($asOf);
                break;

            case 'income-expenditure':
                $from = $from ?? now()->startOfMonth();
                $to = $to ?? now()->endOfMonth();
                $content = $csvService->incomeExpenditure($from, $to);
                break;

            case 'balance-sheet':
                $content = $csvService->balanceSheet($asOf);
                break;

            case 'cash-book':
                $accountId = $request->integer('account_id');
                $account = Account::findOrFail($accountId);
                $from = $from ?? now()->startOfMonth();
                $to = $to ?? now()->endOfMonth();
                $content = $csvService->cashBook($account, $from, $to);
                $filename = "cash-book-{$account->code}-".now()->format('Y-m-d').'.csv';
                break;

            case 'collections':
                $content = $csvService->collections($from, $to);
                break;

            case 'expense-by-category':
                $from = $from ?? now()->startOfMonth();
                $to = $to ?? now()->endOfMonth();
                $content = $csvService->expenseByCategory($from, $to);
                break;

            default:
                abort(404);
        }

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
