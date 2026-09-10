<?php

namespace App\Http\Controllers\Api\V1\Resident;

use App\Enums\AccountCode;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\Billing\BillSummary;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function flats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        $data = $flats->map(fn (Flat $flat): array => [
            'id' => $flat->id,
            'number' => $flat->number,
            'floor' => $flat->floor !== null ? $flat->floor->name : '',
            'building_id' => $flat->building_id,
            'building_name' => $flat->building !== null ? $flat->building->name : '',
        ])->values();

        return ApiResponse::success($data, 'Flats retrieved successfully');
    }

    public function index(Request $request, JournalService $journal, BillSummary $billSummary): JsonResponse
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

        $latestBill = ServiceChargeBill::where('flat_id', $flat->id)
            ->with(['items', 'flat.building', 'flat.owner'])
            ->latest('billing_month')
            ->first();

        $serviceChargeReceivable = $journal->account(AccountCode::ServiceChargeReceivable);
        $advanceAccount = $journal->account(AccountCode::AdvanceFromOwners);

        $totalDue = $journal->balanceFor($serviceChargeReceivable, $flat->id);
        $advanceHeld = $journal->balanceFor($advanceAccount, $flat->id);

        if ($latestBill !== null) {
            $summaryData = $billSummary->for($latestBill);
            $currentMonthCharges = $summaryData->monthCharges;
            $arrears = $summaryData->broughtForward;
        } else {
            $currentMonthCharges = '0.00';
            $arrears = $totalDue;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Payment> $payments */
        $payments = Payment::where('flat_id', $flat->id)
            ->latest('received_on')
            ->latest('id')
            ->take(5)
            ->get();

        $recentPayments = $payments->map(fn (Payment $p): array => [
            'id' => $p->id,
            'receipt_no' => $p->receipt_no,
            'amount' => $p->amount,
            'method' => $p->method->value,
            'reference' => $p->reference ?? '',
            'received_on' => $p->received_on->toDateString(),
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Notice> $notices */
        $notices = Notice::where('building_id', $flat->building_id)
            ->active()
            ->latest('is_pinned')
            ->latest('published_at')
            ->take(4)
            ->get();

        $activeNotices = $notices->map(fn (Notice $n): array => [
            'id' => $n->id,
            'title' => $n->title,
            'content' => $n->content,
            'type' => $n->type->value,
            'is_pinned' => $n->is_pinned,
            'published_at' => $n->published_at->toIso8601String(),
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, MaintenanceRequest> $tickets */
        $tickets = MaintenanceRequest::where('flat_id', $flat->id)
            ->latest('created_at')
            ->take(5)
            ->get();

        $myTickets = $tickets->map(fn (MaintenanceRequest $m): array => [
            'id' => $m->id,
            'title' => $m->title,
            'category' => $m->category->value,
            'priority' => $m->priority->value,
            'status' => $m->status->value,
            'created_at' => $m->created_at->toIso8601String(),
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, PaymentSubmission> $submissions */
        $submissions = PaymentSubmission::where('flat_id', $flat->id)
            ->latest('created_at')
            ->take(5)
            ->get();

        $recentSubmissions = $submissions->map(fn (PaymentSubmission $s): array => [
            'id' => $s->id,
            'amount' => $s->amount,
            'payment_method' => $s->payment_method->value,
            'reference_number' => $s->reference_number,
            'payment_date' => $s->payment_date->toDateString(),
            'status' => $s->status->value,
            'created_at' => $s->created_at->toIso8601String(),
        ]);

        $latestBillData = null;
        if ($latestBill !== null) {
            $latestBillData = [
                'id' => $latestBill->id,
                'bill_no' => $latestBill->bill_no,
                'billing_month' => $latestBill->billing_month->format('Y-m'),
                'total_amount' => $latestBill->total_amount,
                'due_date' => $latestBill->due_date->toDateString(),
                'status' => $latestBill->status->value,
            ];
        }

        return ApiResponse::success([
            'flat' => [
                'id' => $flat->id,
                'number' => $flat->number,
                'floor' => $flat->floor !== null ? $flat->floor->name : '',
                'building_id' => $flat->building_id,
                'building_name' => $flat->building !== null ? $flat->building->name : '',
            ],
            'balances' => [
                'total_due' => $totalDue,
                'advance_held' => $advanceHeld,
                'current_month_charges' => $currentMonthCharges,
                'arrears' => $arrears,
            ],
            'latest_bill' => $latestBillData,
            'active_notices' => $activeNotices,
            'recent_payments' => $recentPayments,
            'recent_submissions' => $recentSubmissions,
            'my_tickets' => $myTickets,
        ], 'Dashboard retrieved successfully');
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
