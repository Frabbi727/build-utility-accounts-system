<?php

namespace Database\Seeders;

use App\Enums\NotificationTriggerEvent;
use App\Models\NotificationRule;
use Illuminate\Database\Seeder;

class NotificationRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultRules = [
            [
                'building_id' => null,
                'trigger_event' => NotificationTriggerEvent::BillDueUpcoming,
                'days_offset' => -3,
                'title_template' => 'Payment Reminder: Bill for Flat {flat_number}',
                'body_template' => 'Hello {resident_name}, your utility bill for {billing_month} of ৳{amount} is due on {due_date}. Please pay to avoid late fees.',
                'is_active' => true,
                'channels' => ['push', 'in_app'],
            ],
            [
                'building_id' => null,
                'trigger_event' => NotificationTriggerEvent::BillDueToday,
                'days_offset' => 0,
                'title_template' => 'Bill Due Today: Flat {flat_number}',
                'body_template' => 'Hello {resident_name}, your utility bill of ৳{amount} is due today ({due_date}). Tap to make your payment.',
                'is_active' => true,
                'channels' => ['push', 'in_app'],
            ],
            [
                'building_id' => null,
                'trigger_event' => NotificationTriggerEvent::BillOverdue,
                'days_offset' => 2,
                'title_template' => 'Overdue Payment Notice: Flat {flat_number}',
                'body_template' => 'Attention {resident_name}: Your utility bill of ৳{amount} is overdue since {due_date}. Please settle promptly to prevent late fees.',
                'is_active' => true,
                'channels' => ['push', 'in_app'],
            ],
            [
                'building_id' => null,
                'trigger_event' => NotificationTriggerEvent::MaintenanceStatusChanged,
                'days_offset' => 0,
                'title_template' => 'Maintenance Ticket Updated: #{ticket_id}',
                'body_template' => 'Your ticket "{ticket_title}" status changed to {ticket_status}. {resolution_notes}',
                'is_active' => true,
                'channels' => ['push', 'in_app'],
            ],
            [
                'building_id' => null,
                'trigger_event' => NotificationTriggerEvent::MaintenanceAssigned,
                'days_offset' => 0,
                'title_template' => 'Technician Assigned: #{ticket_id}',
                'body_template' => 'Technician {assigned_to} has been assigned to your ticket "{ticket_title}".',
                'is_active' => true,
                'channels' => ['push', 'in_app'],
            ],
        ];

        foreach ($defaultRules as $rule) {
            NotificationRule::updateOrCreate(
                [
                    'building_id' => $rule['building_id'],
                    'trigger_event' => $rule['trigger_event'],
                ],
                $rule
            );
        }
    }
}
