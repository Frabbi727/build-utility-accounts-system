# Project rules

Settled decisions, non-obvious traps and standing constraints. Read every rule file
whose globs cover the path you are about to touch, then `grep -rin '<keyword>' .ai/rules`
to catch what a path match alone misses.

| Rule file | Globs |
|---|---|
| [accounting-ledger.md](accounting-ledger.md) | `app/Services/**`, `app/Models/Journal*.php`, `app/Support/JournalLineData.php`, `database/migrations/**` |
| [money-arithmetic.md](money-arithmetic.md) | `app/**`, `database/**`, `tests/**` |
| [billing-charge-heads.md](billing-charge-heads.md) | `app/Services/Billing/**`, `app/Models/{Building,ChargeHead,Flat,FlatChargeOverride}.php`, `app/Livewire/Masters/**` |
| [single-building.md](single-building.md) | `app/Services/Reporting/**`, `app/Livewire/Reports/**`, `database/migrations/**` |
| [admin-screens.md](admin-screens.md) | `app/Livewire/**`, `app/Policies/**`, `resources/views/**` |
| [metered-utilities.md](metered-utilities.md) | `app/Services/Billing/**`, `app/Models/{Utility,Meter,MeterReading,UtilityTariff,UtilityTariffSlab,UnitType,CostDistribution,CostDistributionLine}.php`, `app/Livewire/Utilities/**`, `app/Livewire/Billing/**` |
| [localization.md](localization.md) | `lang/**`, `resources/views/**`, `app/Livewire/**` |
| [payment-tracking.md](payment-tracking.md) | `app/Services/Billing/BillSummary.php`, `app/Support/BillSummaryData.php`, `app/Http/Controllers/BillController.php`, `app/Livewire/PaymentList.php` |

