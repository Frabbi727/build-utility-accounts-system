<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait to automatically record field-level changes on Eloquent model lifecycle events.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            if (! $model->shouldAudit('create')) {
                return;
            }

            $auditService = app(AuditService::class);
            $newValues = $model->getAuditAttributes();

            $auditService->record(
                action: 'CREATE',
                module: $model->getAuditModule(),
                entity: $model,
                description: $model->getAuditDescription('CREATE'),
                oldValues: null,
                newValues: $newValues,
                changedFields: array_keys($newValues),
            );
        });

        static::updated(function (self $model): void {
            if (! $model->shouldAudit('update')) {
                return;
            }

            $dirty = $model->getDirty();
            $ignored = array_merge(
                ['updated_at', 'created_at'],
                $model->getAuditExcluded(),
            );

            $dirty = array_diff_key($dirty, array_flip($ignored));

            // Skip if no meaningful fields changed
            if (empty($dirty)) {
                return;
            }

            $oldValues = [];
            $newValues = [];
            $changedFields = [];

            foreach ($dirty as $key => $newValue) {
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $newValue;
                $changedFields[] = (string) $key;
            }

            $auditService = app(AuditService::class);

            $auditService->record(
                action: 'UPDATE',
                module: $model->getAuditModule(),
                entity: $model,
                description: $model->getAuditDescription('UPDATE'),
                oldValues: $oldValues,
                newValues: $newValues,
                changedFields: $changedFields,
            );
        });

        static::deleted(function (self $model): void {
            if (! $model->shouldAudit('delete')) {
                return;
            }

            $auditService = app(AuditService::class);
            $oldValues = $model->getAuditAttributes();

            $auditService->record(
                action: 'DELETE',
                module: $model->getAuditModule(),
                entity: $model,
                description: $model->getAuditDescription('DELETE'),
                oldValues: $oldValues,
                newValues: null,
                changedFields: array_keys($oldValues),
            );
        });
    }

    public function shouldAudit(string $action): bool
    {
        return true;
    }

    public function getAuditModule(): string
    {
        if (property_exists($this, 'auditModule') && is_string($this->auditModule)) {
            return $this->auditModule;
        }

        return match (class_basename($this)) {
            'ServiceChargeBill', 'BillItem' => 'bills',
            'Payment', 'PaymentAllocation', 'PaymentSubmission' => 'payments',
            'User' => 'users',
            'Expense', 'VendorBill', 'VendorBillItem', 'VendorBillPayment' => 'expenses',
            'CostDistribution', 'CostDistributionLine' => 'shared_costs',
            'Meter', 'MeterReading', 'UtilityTariff', 'Utility' => 'utilities',
            'Account', 'AccountingPeriod', 'JournalEntry', 'JournalLine' => 'accounting',
            'MaintenanceRequest' => 'maintenance',
            'Notice' => 'notices',
            'Flat', 'Building', 'Floor', 'Owner', 'Tenant', 'Vendor', 'Staff', 'ChargeHead' => 'masters',
            default => Str::snake(Str::plural(class_basename($this))),
        };
    }

    /**
     * @return list<string>
     */
    public function getAuditExcluded(): array
    {
        return [
            'password',
            'remember_token',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuditAttributes(): array
    {
        $attributes = $this->attributesToArray();
        $ignored = array_merge(['created_at', 'updated_at'], $this->getAuditExcluded());

        return array_diff_key($attributes, array_flip($ignored));
    }

    public function getAuditDescription(string $action): string
    {
        $name = class_basename($this);
        $id = $this->getKey();

        return match ($action) {
            'CREATE' => "Created {$name} #{$id}",
            'UPDATE' => "Updated {$name} #{$id}",
            'DELETE' => "Deleted {$name} #{$id}",
            default => "{$action} on {$name} #{$id}",
        };
    }
}
