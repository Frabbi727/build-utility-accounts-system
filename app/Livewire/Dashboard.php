<?php

namespace App\Livewire;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Livewire\Concerns\WithNotices;
use App\Models\ChargeHead;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\Billing\BillSummary;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class Dashboard extends Component
{
    use WithFileUploads;
    use WithNotices;

    public ?int $selectedFlatId = null;

    public bool $showTicketModal = false;

    public string $ticketTitle = '';

    public string $ticketCategory = 'other';

    public string $ticketPriority = 'medium';

    public string $ticketDescription = '';

    public ?Notice $viewingNotice = null;

    public bool $showNoticeModal = false;

    public bool $showPaymentModal = false;

    public string $submissionAmount = '';

    public string $submissionMethod = 'bkash';

    public string $submissionReference = '';

    public string $submissionDate = '';

    public string $submissionNotes = '';

    public mixed $submissionSlip = null;

    /**
     * @return Collection<int, Flat>
     */
    private function getResidentFlats(User $user): Collection
    {
        $flats = collect();

        if ($user->owner) {
            $flats = $user->owner->flats()->with(['building'])->orderBy('number')->get();
        }

        if ($user->tenant?->flat) {
            $tenantFlat = $user->tenant->flat;
            $tenantFlat->loadMissing('building');
            if (! $flats->contains('id', $tenantFlat->id)) {
                $flats->push($tenantFlat);
            }
        }

        return $flats;
    }

    public function selectFlat(int $flatId): void
    {
        $user = auth()->user();
        $flats = $this->getResidentFlats($user);

        if ($flats->contains('id', $flatId)) {
            $this->selectedFlatId = $flatId;
        }
    }

    public function openTicketModal(): void
    {
        $this->ticketTitle = '';
        $this->ticketCategory = MaintenanceCategory::Other->value;
        $this->ticketPriority = MaintenancePriority::Medium->value;
        $this->ticketDescription = '';
        $this->resetValidation();
        $this->showTicketModal = true;
    }

    public function closeTicketModal(): void
    {
        $this->showTicketModal = false;
    }

    public function submitTicket(): void
    {
        $this->validate([
            'ticketTitle' => ['required', 'string', 'max:255'],
            'ticketCategory' => ['required', Rule::enum(MaintenanceCategory::class)],
            'ticketPriority' => ['required', Rule::enum(MaintenancePriority::class)],
            'ticketDescription' => ['required', 'string'],
        ]);

        $user = auth()->user();
        $flats = $this->getResidentFlats($user);
        $flat = $flats->firstWhere('id', $this->selectedFlatId);

        if ($flat === null) {
            return;
        }

        MaintenanceRequest::create([
            'building_id' => $flat->building_id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'title' => $this->ticketTitle,
            'description' => $this->ticketDescription,
            'category' => MaintenanceCategory::from($this->ticketCategory),
            'priority' => MaintenancePriority::from($this->ticketPriority),
            'status' => MaintenanceStatus::Open,
        ]);

        $this->showTicketModal = false;
        $this->notify(__('maintenance.request_submitted'));
    }

    public function openNoticeModal(int $noticeId): void
    {
        $this->viewingNotice = Notice::findOrFail($noticeId);
        $this->showNoticeModal = true;
    }

    public function closeNoticeModal(): void
    {
        $this->showNoticeModal = false;
        $this->viewingNotice = null;
    }

    public function openPaymentModal(?string $amount = null): void
    {
        $this->resetValidation();
        $this->submissionAmount = $amount ?? '';
        $this->submissionMethod = PaymentMethod::Bkash->value;
        $this->submissionReference = '';
        $this->submissionDate = now()->toDateString();
        $this->submissionNotes = '';
        $this->submissionSlip = null;
        $this->showPaymentModal = true;
    }

    public function cancel(): void
    {
        $this->closeTicketModal();
        $this->closeNoticeModal();
        $this->cancelPaymentModal();
    }

    public function cancelPaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->resetValidation();
    }

    public function closePaymentModal(): void
    {
        $this->cancelPaymentModal();
    }

    public function submitPayment(): void
    {
        $this->validate([
            'submissionAmount' => ['required', 'numeric', 'min:0.01'],
            'submissionMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'submissionReference' => ['required', 'string', 'max:255'],
            'submissionDate' => ['required', 'date'],
            'submissionNotes' => ['nullable', 'string', 'max:1000'],
            'submissionSlip' => ['nullable', 'image', 'max:5120'],
        ]);

        $user = auth()->user();
        $flats = $this->getResidentFlats($user);
        $flat = $flats->firstWhere('id', $this->selectedFlatId);

        if ($flat === null) {
            return;
        }

        $slipPath = null;
        if ($this->submissionSlip) {
            $slipPath = $this->submissionSlip->store('payment_slips', 'public');
        }

        PaymentSubmission::create([
            'building_id' => $flat->building_id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'amount' => number_format((float) $this->submissionAmount, 2, '.', ''),
            'payment_method' => PaymentMethod::from($this->submissionMethod),
            'reference_number' => $this->submissionReference,
            'payment_date' => $this->submissionDate,
            'slip_path' => $slipPath,
            'resident_notes' => $this->submissionNotes ?: null,
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        $this->showPaymentModal = false;
        $this->notify(__('billing.submission_recorded'));
    }

    public function render(JournalService $journal, BillSummary $billSummary, CurrentBuilding $current): View
    {
        $user = auth()->user();

        if (! $user->isStaff()) {
            $flats = $this->getResidentFlats($user);

            if ($this->selectedFlatId === null || ! $flats->contains('id', $this->selectedFlatId)) {
                $this->selectedFlatId = $flats->first()?->id;
            }

            $selectedFlat = $flats->firstWhere('id', $this->selectedFlatId);

            $latestBill = null;
            $billSummaryData = null;
            $totalDue = '0.00';
            $advanceHeld = '0.00';
            $currentMonthCharges = '0.00';
            $arrears = '0.00';
            $recentPayments = collect();
            $activeNotices = collect();
            $myTickets = collect();
            $mySubmissions = collect();

            if ($selectedFlat !== null) {
                $latestBill = ServiceChargeBill::where('flat_id', $selectedFlat->id)
                    ->with(['items', 'flat.building', 'flat.owner'])
                    ->latest('billing_month')
                    ->first();

                $serviceChargeReceivable = $journal->account(AccountCode::ServiceChargeReceivable);
                $advanceAccount = $journal->account(AccountCode::AdvanceFromOwners);

                $totalDue = $journal->balanceFor($serviceChargeReceivable, $selectedFlat->id);
                $advanceHeld = $journal->balanceFor($advanceAccount, $selectedFlat->id);

                if ($latestBill !== null) {
                    $billSummaryData = $billSummary->for($latestBill);
                    $currentMonthCharges = $billSummaryData->monthCharges;
                    $arrears = $billSummaryData->broughtForward;
                } else {
                    $arrears = $totalDue;
                }

                $recentPayments = Payment::where('flat_id', $selectedFlat->id)
                    ->latest('received_on')
                    ->latest('id')
                    ->take(5)
                    ->get();

                $activeNotices = Notice::where('building_id', $selectedFlat->building_id)
                    ->active()
                    ->latest('is_pinned')
                    ->latest('published_at')
                    ->take(4)
                    ->get();

                $myTickets = MaintenanceRequest::where('flat_id', $selectedFlat->id)
                    ->latest('created_at')
                    ->take(5)
                    ->get();

                $mySubmissions = PaymentSubmission::where('flat_id', $selectedFlat->id)
                    ->latest('created_at')
                    ->take(5)
                    ->get();
            }

            return view('livewire.dashboard', [
                'isStaff' => false,
                'flats' => $flats,
                'selectedFlat' => $selectedFlat,
                'latestBill' => $latestBill,
                'billSummary' => $billSummaryData,
                'totalDue' => $totalDue,
                'advanceHeld' => $advanceHeld,
                'currentMonthCharges' => $currentMonthCharges,
                'arrears' => $arrears,
                'recentPayments' => $recentPayments,
                'activeNotices' => $activeNotices,
                'myTickets' => $myTickets,
                'mySubmissions' => $mySubmissions,
                'categories' => MaintenanceCategory::cases(),
                'priorities' => MaintenancePriority::cases(),
                'methods' => PaymentMethod::cases(),
            ])->layout('components.layouts.app');
        }

        $building = $current->get();

        $totalReceivable = '0.00';
        $cash = '0.00';
        $bank = '0.00';
        $totalLiquid = '0.00';
        $flatCount = 0;
        $unpaidBills = 0;
        $pendingSubmissionsCount = 0;
        $needsChargeHeads = false;
        $needsFlats = false;

        $billedThisMonth = '0.00';
        $collectedThisMonth = '0.00';
        $collectionPercentage = 0;
        $expensesThisMonth = '0.00';
        $netCashflow = '0.00';
        $isSurplus = true;

        $flatsBilledCount = 0;
        $isBillingCompleteForMonth = false;
        $paidBillsCount = 0;
        $partialBillsCount = 0;
        $unpaidBillsCount = 0;

        $totalMetersCount = 0;
        $recordedMetersCount = 0;
        $isMeterReadingComplete = true;

        $openTicketsCount = 0;
        $urgentTicketsCount = 0;

        $topOverdueFlats = collect();
        $recentStaffPayments = collect();
        $recentStaffTickets = collect();

        if ($building !== null) {
            $activeFlats = $building->activeFlats()->with('owner.user')->get();
            $activeFlatIds = $activeFlats->pluck('id');
            $flatCount = $activeFlats->count();
            $needsFlats = $flatCount === 0;
            $needsChargeHeads = ! ChargeHead::where('building_id', $building->id)->where('is_active', true)->exists();

            $totalReceivable = $journal->balanceFor($journal->account(AccountCode::ServiceChargeReceivable));
            $cash = $journal->balanceFor($journal->account(AccountCode::CashInHand));
            $bank = $journal->balanceFor($journal->account(AccountCode::Bank));
            $totalLiquid = bcadd($cash, $bank, 2);

            $unpaidBills = ServiceChargeBill::whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
                ->whereIn('flat_id', $activeFlatIds)
                ->count();

            $pendingSubmissionsCount = PaymentSubmission::where('building_id', $building->id)
                ->where('status', PaymentSubmissionStatus::Pending)
                ->count();

            $currentMonthStart = now()->startOfMonth()->toDateString();
            $currentMonthEnd = now()->endOfMonth()->toDateString();

            // Monthly Bills
            $monthBills = ServiceChargeBill::whereIn('flat_id', $activeFlatIds)
                ->whereDate('billing_month', '>=', $currentMonthStart)
                ->whereDate('billing_month', '<=', $currentMonthEnd)
                ->get();

            $billedThisMonth = number_format((float) $monthBills->sum('total_amount'), 2, '.', '');
            $flatsBilledCount = $monthBills->count();
            $isBillingCompleteForMonth = $flatCount > 0 && $flatsBilledCount >= $flatCount;

            $paidBillsCount = $monthBills->where('status', BillStatus::Paid)->count();
            $partialBillsCount = $monthBills->where('status', BillStatus::PartiallyPaid)->count();
            $unpaidBillsCount = $monthBills->where('status', BillStatus::Unpaid)->count();

            // Monthly Collections
            $collectedSum = Payment::whereIn('flat_id', $activeFlatIds)
                ->whereBetween('received_on', [$currentMonthStart, $currentMonthEnd])
                ->sum('amount');
            $collectedThisMonth = number_format((float) $collectedSum, 2, '.', '');

            $collectionPercentage = bccomp($billedThisMonth, '0.00', 2) > 0
                ? min(100, (int) round(((float) $collectedThisMonth / (float) $billedThisMonth) * 100))
                : (bccomp($collectedThisMonth, '0.00', 2) > 0 ? 100 : 0);

            // Monthly Expenses
            $expenseSum = Expense::where('building_id', $building->id)
                ->whereBetween('spent_on', [$currentMonthStart, $currentMonthEnd])
                ->sum('amount');
            $expensesThisMonth = number_format((float) $expenseSum, 2, '.', '');

            $netCashflow = bcsub($collectedThisMonth, $expensesThisMonth, 2);
            $isSurplus = bccomp($netCashflow, '0.00', 2) >= 0;

            // Meters
            $activeMeters = Meter::where('building_id', $building->id)->where('is_active', true)->get();
            $totalMetersCount = $activeMeters->count();
            $recordedMetersCount = $totalMetersCount > 0
                ? MeterReading::whereIn('meter_id', $activeMeters->pluck('id'))
                    ->whereDate('billing_month', '>=', $currentMonthStart)
                    ->whereDate('billing_month', '<=', $currentMonthEnd)
                    ->distinct('meter_id')
                    ->count('meter_id')
                : 0;
            $isMeterReadingComplete = $totalMetersCount === 0 || $recordedMetersCount >= $totalMetersCount;

            // Maintenance Requests
            $openTickets = MaintenanceRequest::where('building_id', $building->id)
                ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
                ->get();
            $openTicketsCount = $openTickets->count();
            $urgentTicketsCount = $openTickets->whereIn('priority', [MaintenancePriority::High, MaintenancePriority::Emergency])->count();

            // Top Overdue Flats
            $receivableAccount = $journal->account(AccountCode::ServiceChargeReceivable);
            $overdueList = collect();
            foreach ($activeFlats as $flat) {
                $due = $journal->balanceFor($receivableAccount, $flat->id);
                if (bccomp($due, '0.00', 2) > 0) {
                    $overdueList->push([
                        'flat' => $flat,
                        'due' => $due,
                    ]);
                }
            }
            $topOverdueFlats = $overdueList->sortByDesc(fn ($item) => (float) $item['due'])->take(5)->values();

            // Recent Staff Feeds
            $recentStaffPayments = Payment::whereIn('flat_id', $activeFlatIds)
                ->with(['flat.owner.user'])
                ->latest('received_on')
                ->latest('id')
                ->take(5)
                ->get();

            $recentStaffTickets = MaintenanceRequest::where('building_id', $building->id)
                ->with(['flat', 'user'])
                ->latest('created_at')
                ->take(5)
                ->get();
        }

        return view('livewire.dashboard', [
            'isStaff' => true,
            'flats' => collect(),
            'selectedFlat' => null,
            'building' => $building,
            'totalReceivable' => $totalReceivable,
            'cash' => $cash,
            'bank' => $bank,
            'totalLiquid' => $totalLiquid,
            'flatCount' => $flatCount,
            'unpaidBills' => $unpaidBills,
            'pendingSubmissionsCount' => $pendingSubmissionsCount,
            'needsChargeHeads' => $needsChargeHeads,
            'needsFlats' => $needsFlats,
            'billedThisMonth' => $billedThisMonth,
            'collectedThisMonth' => $collectedThisMonth,
            'collectionPercentage' => $collectionPercentage,
            'expensesThisMonth' => $expensesThisMonth,
            'netCashflow' => $netCashflow,
            'isSurplus' => $isSurplus,
            'flatsBilledCount' => $flatsBilledCount,
            'isBillingCompleteForMonth' => $isBillingCompleteForMonth,
            'paidBillsCount' => $paidBillsCount,
            'partialBillsCount' => $partialBillsCount,
            'unpaidBillsCount' => $unpaidBillsCount,
            'totalMetersCount' => $totalMetersCount,
            'recordedMetersCount' => $recordedMetersCount,
            'isMeterReadingComplete' => $isMeterReadingComplete,
            'openTicketsCount' => $openTicketsCount,
            'urgentTicketsCount' => $urgentTicketsCount,
            'topOverdueFlats' => $topOverdueFlats,
            'recentStaffPayments' => $recentStaffPayments,
            'recentStaffTickets' => $recentStaffTickets,
        ])->layout('components.layouts.app');
    }
}
