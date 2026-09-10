<?php

namespace App\Http\Controllers\Api\V1\Resident;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentSubmissionApiController extends Controller
{
    public function submissions(Request $request): JsonResponse
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

        /** @var LengthAwarePaginator<int, PaymentSubmission> $paginator */
        $paginator = PaymentSubmission::where('flat_id', $flat->id)
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->latest('created_at')
            ->paginate($perPage);

        /** @var LengthAwarePaginator<array-key, mixed> $transformed */
        $transformed = $paginator->through(fn (PaymentSubmission $s): array => [
            'id' => $s->id,
            'amount' => $s->amount,
            'payment_method' => $s->payment_method->value,
            'reference_number' => $s->reference_number,
            'payment_date' => $s->payment_date->toDateString(),
            'slip_url' => $s->slip_path !== null ? Storage::disk('public')->url($s->slip_path) : null,
            'resident_notes' => $s->resident_notes,
            'status' => $s->status->value,
            'rejection_reason' => $s->rejection_reason,
            'created_at' => $s->created_at->toIso8601String(),
        ]);

        return ApiResponse::paginated($transformed, 'Submissions retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flat_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_number' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'resident_notes' => ['nullable', 'string', 'max:1000'],
            'slip' => ['nullable', 'image', 'max:5120'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);
        $flat = $flats->firstWhere('id', (int) $validated['flat_id']);

        if ($flat === null) {
            return ApiResponse::error('You are not authorized to submit payments for this flat.', 403);
        }

        $slipPath = null;
        if ($request->hasFile('slip')) {
            $slipPath = $request->file('slip')?->store('payment_slips', 'public');
        }

        $submission = PaymentSubmission::create([
            'building_id' => $flat->building_id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'payment_method' => PaymentMethod::from($validated['payment_method']),
            'reference_number' => $validated['reference_number'],
            'payment_date' => $validated['payment_date'],
            'slip_path' => $slipPath,
            'resident_notes' => $validated['resident_notes'] ?? null,
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        return ApiResponse::success([
            'id' => $submission->id,
            'amount' => $submission->amount,
            'payment_method' => $submission->payment_method->value,
            'reference_number' => $submission->reference_number,
            'payment_date' => $submission->payment_date->toDateString(),
            'slip_url' => $submission->slip_path !== null ? Storage::disk('public')->url($submission->slip_path) : null,
            'status' => $submission->status->value,
            'created_at' => $submission->created_at->toIso8601String(),
        ], 'Payment submitted successfully', 201);
    }

    public function payments(Request $request): JsonResponse
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

        /** @var LengthAwarePaginator<int, Payment> $paginator */
        $paginator = Payment::where('flat_id', $flat->id)
            ->when($request->filled('from_date'), function (Builder $query) use ($request): void {
                $query->where('received_on', '>=', $request->string('from_date')->toString());
            })
            ->when($request->filled('to_date'), function (Builder $query) use ($request): void {
                $query->where('received_on', '<=', $request->string('to_date')->toString());
            })
            ->latest('received_on')
            ->latest('id')
            ->paginate($perPage);

        /** @var LengthAwarePaginator<array-key, mixed> $transformed */
        $transformed = $paginator->through(fn (Payment $p): array => [
            'id' => $p->id,
            'receipt_no' => $p->receipt_no,
            'amount' => $p->amount,
            'method' => $p->method->value,
            'reference' => $p->reference ?? '',
            'received_on' => $p->received_on->toDateString(),
            'receipt_url' => route('payments.receipt', $p),
            'created_at' => $p->created_at->toIso8601String(),
        ]);

        return ApiResponse::paginated($transformed, 'Payments retrieved successfully');
    }

    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        if (! $flats->contains('id', $payment->flat_id)) {
            return ApiResponse::error('You are not authorized to access this payment receipt.', 403);
        }

        return ApiResponse::success([
            'id' => $payment->id,
            'receipt_no' => $payment->receipt_no,
            'amount' => $payment->amount,
            'method' => $payment->method->value,
            'reference' => $payment->reference ?? '',
            'received_on' => $payment->received_on->toDateString(),
            'receipt_url' => route('payments.receipt', $payment),
        ], 'Receipt retrieved successfully');
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
