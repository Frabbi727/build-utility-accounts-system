<?php

namespace App\Http\Controllers\Api\V1\Resident;

use App\Enums\BillStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BillItem;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\Billing\BillSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BillApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        if ($flats->isEmpty()) {
            return ApiResponse::error('No flat linked to this resident account.', 404);
        }

        $flatId = $request->filled('flat_id')
            ? $request->integer('flat_id')
            : $flats->first()->id;

        $flat = $flats->firstWhere('id', $flatId);

        if ($flat === null) {
            return ApiResponse::error('You are not authorized to access this flat.', 403);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        /** @var LengthAwarePaginator<int, ServiceChargeBill> $paginator */
        $paginator = ServiceChargeBill::where('flat_id', $flat->id)
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('year'), function (Builder $query) use ($request): void {
                $query->whereYear('billing_month', $request->integer('year'));
            })
            ->when($request->filled('month'), function (Builder $query) use ($request): void {
                $query->whereMonth('billing_month', $request->integer('month'));
            })
            ->latest('billing_month')
            ->paginate($perPage);

        $transformed = $paginator->through(fn (ServiceChargeBill $bill): array => [
            'id' => $bill->id,
            'bill_no' => $bill->bill_no,
            'billing_month' => $bill->billing_month->format('Y-m'),
            'billing_month_formatted' => $bill->billing_month->format('F Y'),
            'total_amount' => $bill->total_amount,
            'paid_amount' => $bill->allocatedAmount(),
            'due_amount' => $bill->outstandingAmount(),
            'due_date' => $bill->due_date->toDateString(),
            'status' => $bill->status->value,
            'is_overdue' => $bill->status !== BillStatus::Paid && $bill->due_date->isPast(),
            'download_pdf_url' => route('bills.print', $bill),
            'created_at' => $bill->created_at->toIso8601String(),
        ]);

        return ApiResponse::paginated($transformed, 'Bills retrieved successfully');
    }

    public function show(Request $request, ServiceChargeBill $bill, BillSummary $billSummary): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        if (! $flats->contains('id', $bill->flat_id)) {
            return ApiResponse::error('You are not authorized to access this bill.', 403);
        }

        $bill->loadMissing(['items', 'flat.building', 'flat.owner']);
        $summary = $billSummary->for($bill);

        /** @var \Illuminate\Database\Eloquent\Collection<int, BillItem> $items */
        $items = $bill->items;

        $itemsData = $items->map(fn (BillItem $item): array => [
            'id' => $item->id,
            'description' => $item->description,
            'charge_head_name' => $item->description,
            'amount' => $item->amount,
            'quantity' => $item->quantity,
            'unit_rate' => $item->unit_rate,
            'unit_label' => $item->unit_label,
        ]);

        return ApiResponse::success([
            'id' => $bill->id,
            'bill_no' => $bill->bill_no,
            'billing_month' => $bill->billing_month->format('Y-m'),
            'billing_month_formatted' => $bill->billing_month->format('F Y'),
            'total_amount' => $bill->total_amount,
            'month_charges' => $summary->monthCharges,
            'paid_amount' => $summary->paidOnThisBill,
            'due_amount' => $summary->thisBillOutstanding,
            'total_due' => $summary->totalDue,
            'advance_held' => $summary->advanceHeld,
            'arrears' => $summary->broughtForward,
            'due_date' => $bill->due_date->toDateString(),
            'status' => $bill->status->value,
            'is_overdue' => $bill->status !== BillStatus::Paid && $bill->due_date->isPast(),
            'download_pdf_url' => route('bills.print', $bill),
            'items' => $itemsData,
            'created_at' => $bill->created_at->toIso8601String(),
        ], 'Bill details retrieved successfully');
    }

    public function pdf(Request $request, ServiceChargeBill $bill): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        if (! $flats->contains('id', $bill->flat_id)) {
            return ApiResponse::error('You are not authorized to access this bill.', 403);
        }

        return ApiResponse::success([
            'bill_no' => $bill->bill_no,
            'url' => route('bills.print', $bill),
        ], 'PDF link generated');
    }

    /**
     * @return Collection<int, Flat>
     */
    private function getResidentFlats(User $user): Collection
    {
        $flats = collect();

        if ($user->owner) {
            $flats = $user->owner->flats()->with(['building', 'floor'])->orderBy('number')->get();
        }

        if ($user->tenant?->flat) {
            $tenantFlat = $user->tenant->flat;
            $tenantFlat->loadMissing(['building', 'floor']);
            if (! $flats->contains('id', $tenantFlat->id)) {
                $flats->push($tenantFlat);
            }
        }

        return $flats;
    }
}
