<?php

namespace App\Console\Commands;

use App\Enums\BillStatus;
use App\Enums\NotificationTriggerEvent;
use App\Enums\NotificationType;
use App\Models\Building;
use App\Models\NotificationRule;
use App\Models\ServiceChargeBill;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TemplateParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBillRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-bill-reminders
                            {--dry-run : Evaluate and preview notifications without persisting or sending}
                            {--building= : Filter by specific building ID}
                            {--date= : Target date (YYYY-MM-DD), defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated push & in-app reminders for upcoming, due, and overdue bills';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $dateOption = $this->option('date');

        try {
            $targetDate = $dateOption === null
                ? Carbon::now()->startOfDay()
                : Carbon::parse($dateOption)->startOfDay();
        } catch (\Throwable) {
            $this->error("Invalid date format for --date=\"{$dateOption}\". Expected YYYY-MM-DD.");

            return self::FAILURE;
        }

        $targetDateString = $targetDate->toDateString();
        $buildingOption = $this->option('building');

        $buildings = Building::query()
            ->when($buildingOption, fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        if ($buildings->isEmpty()) {
            $this->info('No buildings found matching criteria.');

            return self::SUCCESS;
        }

        $billTriggerEvents = [
            NotificationTriggerEvent::BillDueUpcoming,
            NotificationTriggerEvent::BillDueToday,
            NotificationTriggerEvent::BillOverdue,
        ];

        $allRules = NotificationRule::query()
            ->whereIn('trigger_event', $billTriggerEvents)
            ->get();

        $totalEvaluatedRules = 0;
        $totalEvaluatedBills = 0;
        $totalDispatchedNotifications = 0;
        $previewRows = [];

        foreach ($buildings as $building) {
            foreach ($billTriggerEvents as $event) {
                // Building-specific override has precedence over global rule
                $buildingRule = $allRules->first(fn (NotificationRule $r) => $r->trigger_event === $event && $r->building_id === $building->id);
                $rule = $buildingRule ?? $allRules->first(fn (NotificationRule $r) => $r->trigger_event === $event && $r->building_id === null);

                if ($rule === null || ! $rule->is_active) {
                    continue;
                }

                $totalEvaluatedRules++;
                $targetDueDate = $targetDate->copy()->subDays($rule->days_offset)->toDateString();

                $bills = ServiceChargeBill::query()
                    ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
                    ->whereDate('due_date', $targetDueDate)
                    ->whereHas('flat', fn ($q) => $q->where('building_id', $building->id))
                    ->with(['flat.building', 'flat.owner.user', 'flat.tenants.user'])
                    ->get();

                foreach ($bills as $bill) {
                    $totalEvaluatedBills++;
                    $flat = $bill->flat;

                    if ($flat === null) {
                        continue;
                    }

                    $recipients = collect();

                    if ($flat->owner?->user) {
                        $recipients->push($flat->owner->user);
                    }

                    foreach ($flat->tenants as $tenant) {
                        if ($tenant->user) {
                            $leaseStarted = $tenant->lease_started_on === null || $tenant->lease_started_on->toDateString() <= $targetDateString;
                            $leaseEnded = $tenant->lease_ended_on === null || $tenant->lease_ended_on->toDateString() >= $targetDateString;

                            if ($leaseStarted && $leaseEnded) {
                                $recipients->push($tenant->user);
                            }
                        }
                    }

                    $recipients = $recipients->unique('id');

                    foreach ($recipients as $user) {
                        $tokenData = [
                            'resident_name' => $user->name,
                            'flat_number' => $flat->number,
                            'amount' => $bill->total_amount,
                            'due_date' => $bill->due_date,
                            'billing_month' => $bill->billing_month,
                            'building_name' => $building->name,
                        ];

                        $title = TemplateParser::render($rule->title_template, $tokenData);
                        $body = TemplateParser::render($rule->body_template, $tokenData);

                        $type = $rule->trigger_event === NotificationTriggerEvent::BillOverdue
                            ? NotificationType::BillOverdue
                            : NotificationType::BillDueReminder;

                        $idempotencyKey = "bill_reminder:{$bill->id}:{$rule->id}:{$targetDateString}";

                        if ($dryRun) {
                            $totalDispatchedNotifications++;
                            $previewRows[] = [
                                $building->name,
                                $flat->number,
                                $bill->bill_no ?? "#{$bill->id}",
                                $user->name,
                                $rule->trigger_event->value,
                                $title,
                            ];
                        } else {
                            $payloadData = array_merge($tokenData, [
                                'bill_id' => $bill->id,
                                'screen' => $type->screen(),
                                'rule_id' => $rule->id,
                                'trigger_event' => $rule->trigger_event->value,
                            ]);

                            $sent = $notificationService->send(
                                $user,
                                $type,
                                $title,
                                $body,
                                $payloadData,
                                'service_charge_bill',
                                $bill->id,
                                $idempotencyKey,
                            );

                            if ($sent->isNotEmpty()) {
                                $totalDispatchedNotifications++;
                            }
                        }
                    }
                }
            }
        }

        if ($dryRun) {
            if (! empty($previewRows)) {
                $this->table(['Building', 'Flat', 'Bill', 'Recipient', 'Event', 'Rendered Title'], $previewRows);
            }

            $this->info("[DRY RUN] Would send {$totalDispatchedNotifications} notification(s) across {$totalEvaluatedBills} bill(s) evaluated.");
        } else {
            $this->info("Processed {$totalEvaluatedRules} rule(s) across {$buildings->count()} building(s).");
            $this->info("Evaluated {$totalEvaluatedBills} bill(s), sent {$totalDispatchedNotifications} notification(s).");
        }

        return self::SUCCESS;
    }
}
