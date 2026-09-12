<?php

namespace App\Livewire\Billing;

use App\Enums\PaymentSubmissionStatus;
use App\Jobs\SendResidentPushNotificationJob;
use App\Livewire\Concerns\WithNotices;
use App\Models\PaymentSubmission;
use App\Services\Billing\RecordPayment;
use App\Support\CurrentBuilding;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentSubmissionList extends Component
{
    use WithNotices;
    use WithPagination;

    public string $statusFilter = 'pending';

    public string $search = '';

    public bool $showRejectModal = false;

    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public bool $showDetailModal = false;

    public ?int $viewingId = null;

    public bool $showApproveModal = false;

    public ?int $approvingId = null;

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openApproveModal(int $submissionId): void
    {
        $building = app(CurrentBuilding::class)->get();

        $submission = PaymentSubmission::query()
            ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
            ->findOrFail($submissionId);

        $this->authorize('manage', $submission);

        if ($submission->status !== PaymentSubmissionStatus::Pending) {
            $this->notifyError(__('billing.rejected'));

            return;
        }

        $this->approvingId = $submission->id;
        $this->showApproveModal = true;
    }

    public function closeApproveModal(): void
    {
        $this->showApproveModal = false;
        $this->approvingId = null;
    }

    public function approve(?int $submissionId = null): void
    {
        $id = $submissionId ?? $this->approvingId;
        if ($id === null) {
            return;
        }

        $building = app(CurrentBuilding::class)->get();

        try {
            $payment = DB::transaction(function () use ($building, $id) {
                $submission = PaymentSubmission::query()
                    ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
                    ->with(['flat'])
                    ->lockForUpdate()
                    ->findOrFail($id);

                $this->authorize('manage', $submission);

                if ($submission->status !== PaymentSubmissionStatus::Pending) {
                    throw new \DomainException('Submission has already been processed.');
                }

                $payment = app(RecordPayment::class)->handle(
                    $submission->flat,
                    $submission->amount,
                    $submission->payment_method,
                    $submission->payment_date,
                    $submission->reference_number,
                );

                $submission->update([
                    'status' => PaymentSubmissionStatus::Approved,
                    'payment_id' => $payment->id,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);

                SendResidentPushNotificationJob::dispatch(
                    [$submission->user_id],
                    'Payment Approved',
                    "Your payment of BDT {$submission->amount} has been approved. Receipt: {$payment->receipt_no}",
                    ['type' => 'payment_approved', 'payment_id' => $payment->id]
                );

                return $payment;
            });

            $this->closeApproveModal();
            $this->notify(__('billing.payment_recorded', ['receipt' => $payment->receipt_no]));
        } catch (\DomainException $e) {
            $this->closeApproveModal();
            $this->notifyError($e->getMessage());
        }
    }

    public function openRejectModal(int $submissionId): void
    {
        $building = app(CurrentBuilding::class)->get();

        $submission = PaymentSubmission::query()
            ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
            ->findOrFail($submissionId);

        $this->authorize('manage', $submission);

        $this->rejectingId = $submission->id;
        $this->rejectionReason = '';
        $this->resetValidation();
        $this->showRejectModal = true;
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
        $this->rejectingId = null;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function reject(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:1000'],
        ]);

        $building = app(CurrentBuilding::class)->get();

        $submission = PaymentSubmission::query()
            ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
            ->findOrFail($this->rejectingId);

        $this->authorize('manage', $submission);

        $submission->update([
            'status' => PaymentSubmissionStatus::Rejected,
            'rejection_reason' => $this->rejectionReason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        SendResidentPushNotificationJob::dispatch(
            [$submission->user_id],
            'Payment Rejected',
            "Your payment submission of BDT {$submission->amount} was rejected: {$this->rejectionReason}",
            ['type' => 'payment_rejected', 'submission_id' => $submission->id]
        );

        $this->closeRejectModal();
        $this->notify(__('billing.rejected'));
    }

    public function openDetailModal(int $submissionId): void
    {
        $building = app(CurrentBuilding::class)->get();

        $submission = PaymentSubmission::query()
            ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
            ->findOrFail($submissionId);

        $this->authorize('view', $submission);

        $this->viewingId = $submission->id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->viewingId = null;
    }

    public function cancel(): void
    {
        $this->closeRejectModal();
        $this->closeDetailModal();
    }

    public function render(): View
    {
        $this->authorize('viewAny', PaymentSubmission::class);

        $building = app(CurrentBuilding::class)->get();

        $submissions = PaymentSubmission::query()
            ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
            ->with(['flat.owner', 'user', 'reviewer', 'payment'])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('reference_number', 'ilike', $term)
                        ->orWhereHas('flat', fn ($f) => $f->where('number', 'ilike', $term))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $term));
                });
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(15);

        $viewingSubmission = $this->viewingId !== null
            ? PaymentSubmission::with(['flat.owner', 'user', 'reviewer', 'payment'])->find($this->viewingId)
            : null;

        return view('livewire.billing.payment-submission-list', [
            'submissions' => $submissions,
            'viewingSubmission' => $viewingSubmission,
        ])->layout('components.layouts.app');
    }
}
