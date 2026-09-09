<?php

namespace App\Livewire;

use App\Enums\AccountCode;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Livewire\Concerns\WithNotices;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
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
                'mySubmissions' => $mySubmissions ?? collect(),
                'categories' => MaintenanceCategory::cases(),
                'priorities' => MaintenancePriority::cases(),
                'methods' => PaymentMethod::cases(),
            ])->layout('components.layouts.app');
        }

        $building = $current->get();

        return view('livewire.dashboard', [
            'isStaff' => true,
            'flats' => collect(),
            'selectedFlat' => null,
            'building' => $building,
            'totalReceivable' => $journal->balanceFor($journal->account(AccountCode::ServiceChargeReceivable)),
            'cash' => $journal->balanceFor($journal->account(AccountCode::CashInHand)),
            'bank' => $journal->balanceFor($journal->account(AccountCode::Bank)),
            'flatCount' => $building === null ? 0 : $building->activeFlats()->count(),
            'unpaidBills' => $building === null ? 0 : ServiceChargeBill::whereIn('status', ['unpaid', 'partially_paid'])
                ->whereIn('flat_id', $building->flats()->select('id'))
                ->count(),
            'pendingSubmissionsCount' => $building === null ? 0 : PaymentSubmission::where('building_id', $building->id)
                ->where('status', 'pending')
                ->count(),
            'needsChargeHeads' => $building !== null
                && ! ChargeHead::where('building_id', $building->id)->where('is_active', true)->exists(),
            'needsFlats' => $building !== null && $building->activeFlats()->count() === 0,
        ])->layout('components.layouts.app');
    }
}
