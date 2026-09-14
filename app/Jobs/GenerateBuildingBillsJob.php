<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\GenerateMonthlyBills;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Queued background job to generate monthly service charge bills for a building.
 */
class GenerateBuildingBillsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Building $building,
        public readonly Carbon $month,
        public readonly ?int $dispatchedByUserId = null,
    ) {}

    /**
     * @return Collection<int, ServiceChargeBill>
     */
    public function handle(GenerateMonthlyBills $generator): Collection
    {
        if ($this->dispatchedByUserId !== null && ! Auth::check()) {
            Auth::loginUsingId($this->dispatchedByUserId);
        }

        $bills = $generator->handle($this->building, $this->month);

        if ($bills->isNotEmpty()) {
            $flatIds = $bills->pluck('flat_id')->unique();
            $flats = Flat::whereIn('id', $flatIds)->with(['owner', 'tenants'])->get();
            $userIds = [];

            foreach ($flats as $flat) {
                if ($flat->owner?->user_id) {
                    $userIds[] = $flat->owner->user_id;
                }
                foreach ($flat->tenants as $tenant) {
                    if ($tenant->user_id) {
                        $userIds[] = $tenant->user_id;
                    }
                }
            }

            $userIds = array_values(array_unique($userIds));

            if (! empty($userIds)) {
                SendResidentPushNotificationJob::dispatch(
                    $userIds,
                    'New Monthly Bill Issued',
                    "Your service charge bill for {$this->month->format('F Y')} has been issued.",
                    ['type' => 'new_bill', 'month' => $this->month->format('Y-m')],
                    NotificationType::BillGenerated,
                    'bill',
                    null,
                    "bill-generated-{$this->month->format('Y-m')}",
                );
            }
        }

        return $bills;
    }
}
