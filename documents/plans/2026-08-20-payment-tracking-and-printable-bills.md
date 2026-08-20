# Payment Tracking and Printable Bills — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make dues, partial payments and carried-forward arrears visible to an operator, and produce a printable bill that shows the full working including meter readings and unit rates.

**Architecture:** One new service, `App\Services\Billing\BillSummary`, derives every "what is owed" figure from the ledger and returns a readonly `App\Support\BillSummaryData`. Every consumer — the printable bill, the batch print, the flat statement, the dues report — calls it, so no two surfaces can disagree. Carry-forward is presentation only: no new journal entries, no migrations, no cached balance columns.

**Tech Stack:** Laravel 13 / PHP 8.5, Livewire 4, Blade + Tailwind 4, PostgreSQL 18, PHPUnit 12, Pint, Larastan level 5.

**Spec:** `documents/specs/2026-08-20-payment-tracking-and-printable-bills-design.md`

## Global Constraints

- **Money is decimal strings.** Combine and compare with `bcadd` / `bcsub` / `bccomp` at explicit scale `2`. Never `+`, `-`, `==`, `round()` or `(float)` on money. Normalise anything entering the domain with `bcadd($value, '0', 2)`.
- **Quantities are scale 3, rates are scale 4.** Never put them through the scale-2 rules.
- **The ledger is the only source of truth for money.** No task here writes to `journal_entries` or `journal_lines`. Balances come from `JournalService::balanceFor()`. Never add a balance column.
- **No migrations in this plan.** If a task seems to need one, stop and re-open the design.
- **Bangla and English move together.** Every new key lands in both `lang/en/<file>.php` and `lang/bn/<file>.php` in the same commit. The two trees are at exact parity and must stay there.
- **Every Livewire component** renders with `->layout('components.layouts.app')` and calls `$this->authorize('viewAny', Model::class)` at the top of `render()`.
- **Build views from `resources/views/components/form/*` and `components/ui/*`.** Use `<x-money>` for every amount. Do not hand-roll markup.
- **Formatting:** run `vendor/bin/pint --dirty --format agent` before every commit.
- **Tests are PHPUnit classes** under `tests/Feature/...`, using `RefreshDatabase` and model factories, run against real PostgreSQL. Create with `php artisan make:test --phpunit {Name}`.
- Run a single test file with `php artisan test --compact tests/Feature/Path/Test.php`, or filter with `--filter=testName`.

---

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `app/Support/BillSummaryData.php` | Readonly carrier for one bill's derived figures |
| `app/Services/Billing/BillSummary.php` | The only place "what is owed on this bill" is computed |
| `app/Http/Controllers/BillController.php` | Serves the printable bill, single and batch |
| `resources/views/components/bill-sheet.blade.php` | One printable bill sheet; shared by single and batch |
| `resources/views/bills/print.blade.php` | Bare print page wrapping one or many sheets |
| `app/Livewire/PaymentList.php` | Payment history screen |
| `resources/views/livewire/payment-list.blade.php` | Its view |
| `tests/Feature/Billing/BillSummaryTest.php` | Reconciliation tests for the service |
| `tests/Feature/Billing/BillPrintTest.php` | Print routes, authorisation, meter detail |
| `tests/Feature/Billing/PaymentListTest.php` | Payment history screen |
| `tests/Feature/Billing/CollectDueTest.php` | Collect-due prefill and partial payment |

**Modified:**

| File | Change |
|---|---|
| `app/Policies/ServiceChargeBillPolicy.php` | `view()` gains the owner branch |
| `resources/views/components/bill-lines.blade.php` | Metered lines print their meter readings |
| `app/Livewire/FlatStatement.php` | Passes `BillSummaryData` instead of raw bills |
| `resources/views/livewire/flat-statement.blade.php` | Per-bill status, paid, outstanding, receipts, print link, collect button |
| `app/Livewire/RecordPaymentForm.php` | Accepts `?flat=`, prefills the outstanding amount |
| `resources/views/livewire/record-payment-form.blade.php` | Shows outstanding and advance for the chosen flat |
| `resources/views/livewire/reports/owner-dues.blade.php` | Collect action per row |
| `resources/views/livewire/generate-bills.blade.php` | "Print all bills" link after a run |
| `routes/web.php` | Three new routes |
| `app/Support/Navigation.php` | Payments menu entry |
| `lang/{en,bn}/{billing,nav,reports}.php` | New keys, both locales |
| `tests/Feature/RouteSmokeTest.php` | New routes in the role matrix |

---

### Task 1: `BillSummary` — the one place what-is-owed is computed

**Files:**
- Create: `app/Support/BillSummaryData.php`
- Create: `app/Services/Billing/BillSummary.php`
- Test: `tests/Feature/Billing/BillSummaryTest.php`

**Interfaces:**
- Consumes: `App\Services\JournalService::balanceFor(Account|int, ?int $flatId, ?Carbon $asOf): string`, `JournalService::account(AccountCode): Account`, `ServiceChargeBill::allocatedAmount(): string`, `ServiceChargeBill::outstandingAmount(): string`.
- Produces: `App\Services\Billing\BillSummary::for(ServiceChargeBill $bill): BillSummaryData`, and the readonly properties of `App\Support\BillSummaryData` listed in Step 3. Tasks 3, 4 and 6 depend on both.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Billing/BillSummaryTest.php`:

```php
<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\BillSummary;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);
    }

    private function generate(string $month): ServiceChargeBill
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));

        return ServiceChargeBill::where('flat_id', $this->flat->id)
            ->whereDate('billing_month', Carbon::parse($month)->startOfMonth())
            ->firstOrFail();
    }

    private function pay(string $amount, string $on): void
    {
        app(RecordPayment::class)->handle(
            $this->flat,
            $amount,
            PaymentMethod::Cash,
            Carbon::parse($on),
        );
    }

    public function test_a_first_bill_has_nothing_brought_forward(): void
    {
        $bill = $this->generate('2026-06-01');

        $summary = app(BillSummary::class)->for($bill);

        $this->assertSame(0, bccomp($summary->broughtForward, '0.00', 2));
        $this->assertSame(0, bccomp($summary->monthCharges, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->paidOnThisBill, '0.00', 2));
        $this->assertSame(0, bccomp($summary->thisBillOutstanding, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '3000.00', 2));
        $this->assertSame([], $summary->priorOpenBills);
    }

    public function test_an_unpaid_prior_bill_is_carried_forward_and_listed(): void
    {
        $june = $this->generate('2026-06-01');
        $july = $this->generate('2026-07-01');

        $summary = app(BillSummary::class)->for($july);

        $this->assertSame(0, bccomp($summary->broughtForward, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->monthCharges, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '6000.00', 2));
        $this->assertCount(1, $summary->priorOpenBills);
        $this->assertSame($june->id, $summary->priorOpenBills[0]['bill']->id);
        $this->assertSame(0, bccomp($summary->priorOpenBills[0]['outstanding'], '3000.00', 2));
    }

    public function test_a_partial_payment_reduces_the_bill_it_settles(): void
    {
        $june = $this->generate('2026-06-01');
        $this->pay('1200.00', '2026-06-12');

        $summary = app(BillSummary::class)->for($june->fresh());

        $this->assertSame(0, bccomp($summary->paidOnThisBill, '1200.00', 2));
        $this->assertSame(0, bccomp($summary->thisBillOutstanding, '1800.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '1800.00', 2));
        $this->assertSame(0, bccomp($summary->broughtForward, '0.00', 2));
        $this->assertCount(1, $summary->paymentsReceived);
        $this->assertSame(0, bccomp($summary->paymentsReceived[0]->amount, '1200.00', 2));
    }

    public function test_brought_forward_plus_this_bill_always_equals_the_ledger_due(): void
    {
        $this->generate('2026-06-01');
        $this->pay('1000.00', '2026-06-12');
        $july = $this->generate('2026-07-01');

        $summary = app(BillSummary::class)->for($july);

        $this->assertSame(
            0,
            bccomp(bcadd($summary->broughtForward, $summary->thisBillOutstanding, 2), $summary->totalDue, 2),
            'brought forward plus this bill must reconcile to the ledger receivable',
        );
    }

    public function test_an_overpayment_is_reported_as_an_advance(): void
    {
        $june = $this->generate('2026-06-01');
        $this->pay('4000.00', '2026-06-12');

        $summary = app(BillSummary::class)->for($june->fresh());

        $this->assertSame(0, bccomp($summary->thisBillOutstanding, '0.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '0.00', 2));
        $this->assertSame(0, bccomp($summary->advanceHeld, '1000.00', 2));
    }

    public function test_a_settled_prior_bill_is_not_listed(): void
    {
        $this->generate('2026-06-01');
        $this->pay('3000.00', '2026-06-12');
        $july = $this->generate('2026-07-01');

        $summary = app(BillSummary::class)->for($july);

        $this->assertSame([], $summary->priorOpenBills);
        $this->assertSame(0, bccomp($summary->broughtForward, '0.00', 2));
    }

    public function test_unbilled_arrears_absorb_a_receivable_with_no_bill_behind_it(): void
    {
        // An opening balance posts a receivable with no bill; the sheet must still add up.
        $this->postOpeningReceivable('500.00');

        $june = $this->generate('2026-06-01');

        $summary = app(BillSummary::class)->for($june);

        $this->assertSame(0, bccomp($summary->totalDue, '3500.00', 2));
        $this->assertSame(0, bccomp($summary->broughtForward, '500.00', 2));
        $this->assertSame([], $summary->priorOpenBills);
        $this->assertSame(0, bccomp($summary->unbilledArrears, '500.00', 2));
    }

    private function postOpeningReceivable(string $amount): void
    {
        $journal = app(\App\Services\JournalService::class);

        $journal->post(
            Carbon::parse('2026-05-31'),
            'Opening receivable',
            [
                \App\Support\JournalLineData::debit(
                    $journal->account(\App\Enums\AccountCode::ServiceChargeReceivable),
                    $amount,
                    $this->flat->id,
                ),
                \App\Support\JournalLineData::credit(
                    $journal->account(\App\Enums\AccountCode::GeneralFund),
                    $amount,
                ),
            ],
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Billing/BillSummaryTest.php`
Expected: FAIL — `Class "App\Services\Billing\BillSummary" not found`.

- [ ] **Step 3: Write `BillSummaryData`**

Create `app/Support/BillSummaryData.php`:

```php
<?php

namespace App\Support;

use App\Models\PaymentAllocation;
use App\Models\ServiceChargeBill;

/**
 * What one bill actually asks an owner to pay, with the working shown.
 *
 * `broughtForward` is derived by subtracting this bill's outstanding from the flat's
 * ledger receivable, never by summing older bills. That makes
 * `broughtForward + thisBillOutstanding === totalDue` true by construction, so a printed
 * bill can never disagree with the dues report or the trial balance.
 *
 * `priorOpenBills` is therefore a *detail listing*, not the source of the figure.
 * `unbilledArrears` is whatever the listing does not account for — a receivable posted
 * with no bill behind it, such as an opening balance — so the column still adds up.
 */
final readonly class BillSummaryData
{
    /**
     * @param  list<array{bill: ServiceChargeBill, outstanding: string}>  $priorOpenBills
     * @param  list<PaymentAllocation>  $paymentsReceived  each with its `payment` loaded
     */
    public function __construct(
        public ServiceChargeBill $bill,
        public string $monthCharges,
        public string $paidOnThisBill,
        public string $thisBillOutstanding,
        public string $broughtForward,
        public string $totalDue,
        public array $priorOpenBills,
        public string $unbilledArrears,
        public array $paymentsReceived,
        public string $advanceHeld,
    ) {}

    public function hasArrears(): bool
    {
        return bccomp($this->broughtForward, '0', 2) !== 0;
    }

    public function hasUnbilledArrears(): bool
    {
        return bccomp($this->unbilledArrears, '0', 2) !== 0;
    }

    public function hasAdvance(): bool
    {
        return bccomp($this->advanceHeld, '0', 2) > 0;
    }

    public function hasPayments(): bool
    {
        return $this->paymentsReceived !== [];
    }
}
```

- [ ] **Step 4: Write `BillSummary`**

Create `app/Services/Billing/BillSummary.php`:

```php
<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Models\PaymentAllocation;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\BillSummaryData;

/**
 * The only place a bill's "total payable now" is worked out.
 *
 * Bill generation, the printable bill, the batch print and the flat statement all read
 * this, for the same reason ChargeCalculator is the only place a monthly charge is
 * computed: a second implementation would eventually disagree in front of an owner
 * holding a printed bill.
 */
class BillSummary
{
    public function __construct(private readonly JournalService $journal) {}

    public function for(ServiceChargeBill $bill): BillSummaryData
    {
        $bill->loadMissing(['items', 'flat.owner', 'flat.building']);

        $monthCharges = bcadd((string) $bill->total_amount, '0', 2);
        $paid = $bill->allocatedAmount();
        $thisBillOutstanding = bcsub($monthCharges, $paid, 2);

        $totalDue = $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $bill->flat_id,
        );

        $broughtForward = bcsub($totalDue, $thisBillOutstanding, 2);

        [$priorOpenBills, $priorTotal] = $this->priorOpenBills($bill);

        return new BillSummaryData(
            bill: $bill,
            monthCharges: $monthCharges,
            paidOnThisBill: $paid,
            thisBillOutstanding: $thisBillOutstanding,
            broughtForward: $broughtForward,
            totalDue: $totalDue,
            priorOpenBills: $priorOpenBills,
            unbilledArrears: bcsub($broughtForward, $priorTotal, 2),
            paymentsReceived: $this->paymentsReceived($bill),
            advanceHeld: $this->journal->balanceFor(
                $this->journal->account(AccountCode::AdvanceFromOwners),
                $bill->flat_id,
            ),
        );
    }

    /**
     * The flat's still-open bills older than this one, oldest first, with their total.
     *
     * @return array{0: list<array{bill: ServiceChargeBill, outstanding: string}>, 1: string}
     */
    private function priorOpenBills(ServiceChargeBill $bill): array
    {
        $earlier = ServiceChargeBill::query()
            ->where('flat_id', $bill->flat_id)
            ->where('id', '!=', $bill->id)
            ->where(function ($query) use ($bill): void {
                $query->whereDate('billing_month', '<', $bill->billing_month)
                    ->orWhere(function ($same) use ($bill): void {
                        // Same month, earlier row — possible only for legacy data, but
                        // ordering must still be total or the listing is unstable.
                        $same->whereDate('billing_month', $bill->billing_month)
                            ->where('id', '<', $bill->id);
                    });
            })
            ->orderBy('billing_month')
            ->orderBy('id')
            ->get();

        $rows = [];
        $total = '0.00';

        foreach ($earlier as $old) {
            $outstanding = $old->outstandingAmount();

            if (bccomp($outstanding, '0', 2) <= 0) {
                continue;
            }

            $rows[] = ['bill' => $old, 'outstanding' => $outstanding];
            $total = bcadd($total, $outstanding, 2);
        }

        return [$rows, $total];
    }

    /**
     * Allocations against this bill, oldest receipt first.
     *
     * @return list<PaymentAllocation>
     */
    private function paymentsReceived(ServiceChargeBill $bill): array
    {
        return PaymentAllocation::query()
            ->with('payment')
            ->where('service_charge_bill_id', $bill->id)
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->orderBy('payments.received_on')
            ->orderBy('payments.id')
            ->select('payment_allocations.*')
            ->get()
            ->all();
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Billing/BillSummaryTest.php`
Expected: PASS, 7 tests.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/BillSummaryData.php app/Services/Billing/BillSummary.php tests/Feature/Billing/BillSummaryTest.php
git commit -m "add BillSummary as the one place what-is-owed is computed"
```

---

### Task 2: Bill document language keys and the owner's right to read their bill

**Files:**
- Modify: `app/Policies/ServiceChargeBillPolicy.php:18-21`
- Modify: `lang/en/billing.php`, `lang/bn/billing.php`
- Test: `tests/Feature/Billing/BillPrintTest.php` (created here, grown in Task 3)

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `ServiceChargeBillPolicy::view(User, ServiceChargeBill): bool` now true for the owning owner. The `billing.*` keys listed in Step 3, used by Tasks 3–7.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Billing/BillPrintTest.php`:

```php
<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillPrintTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private ServiceChargeBill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));

        $this->bill = ServiceChargeBill::where('flat_id', $this->flat->id)->firstOrFail();
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_the_owning_owner_may_read_their_own_bill(): void
    {
        $user = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $this->flat->update(['owner_id' => $owner->id]);

        $this->assertTrue($user->can('view', $this->bill->fresh()));
    }

    public function test_another_owner_may_not_read_it(): void
    {
        $user = $this->userWithRole(Role::Owner);
        Owner::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->can('view', $this->bill));
    }

    public function test_staff_may_read_any_bill(): void
    {
        $this->assertTrue($this->userWithRole(Role::Committee)->can('view', $this->bill));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Billing/BillPrintTest.php`
Expected: FAIL on `test_the_owning_owner_may_read_their_own_bill` — the policy returns `isStaff()` only.

- [ ] **Step 3: Widen the policy**

In `app/Policies/ServiceChargeBillPolicy.php`, replace the `view` method with:

```php
    /**
     * Staff read every bill; an owner reads only their own flat's, which is what makes
     * the printable bill reachable from the owner's statement. Same shape as
     * PaymentPolicy::view().
     */
    public function view(User $user, ServiceChargeBill $bill): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->owner !== null
            && $bill->flat !== null
            && $bill->flat->owner_id === $user->owner->id;
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Billing/BillPrintTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 5: Add the English keys**

In `lang/en/billing.php`, insert before the `'methods' => [` entry:

```php
    'bill' => 'Bill',
    'bill_no' => 'Bill no',
    'billing_month' => 'Billing month',
    'due_date' => 'Due date',
    'previous_dues' => 'Previous dues (brought forward)',
    'other_previous_charges' => 'Other previous charges',
    'this_month_charges' => 'This month’s charges',
    'month_total' => 'Month total',
    'paid_on_this_bill' => 'Paid against this bill',
    'payments_received' => 'Payments received',
    'no_payments_yet' => 'No payment received against this bill yet.',
    'total_payable_now' => 'Total payable now',
    'print_bill' => 'Print bill',
    'print_all_bills' => 'Print all bills',
    'bills_for_month' => 'Bills — :month',
    'no_bills_for_month' => 'No bills were generated for that month in this building.',
    'computer_generated_bill' => 'This is a computer-generated bill.',
    'reading_previous' => 'Previous reading',
    'reading_current' => 'Current reading',
    'consumption' => 'Consumption',
    'estimated_reading' => 'Estimated',
    'collect_due' => 'Collect due',
    'payments' => 'Payments',
    'payment_history_hint' => 'Every receipt taken, and what it settled.',
    'received_by' => 'Received by',
    'no_payments' => 'No payments found.',
    'search_payment' => 'Search receipt no or reference',
    'all_flats' => 'All flats',
    'all_methods' => 'All methods',
    'outstanding_now' => 'Outstanding now',
    'statuses' => [
        'unpaid' => 'Unpaid',
        'partially_paid' => 'Partly paid',
        'paid' => 'Paid',
    ],
```

- [ ] **Step 6: Add the Bangla keys**

In `lang/bn/billing.php`, insert before the `'methods' => [` entry:

```php
    'bill' => 'বিল',
    'bill_no' => 'বিল নম্বর',
    'billing_month' => 'বিলের মাস',
    'due_date' => 'পরিশোধের শেষ তারিখ',
    'previous_dues' => 'পূর্বের বকেয়া (জের)',
    'other_previous_charges' => 'পূর্বের অন্যান্য চার্জ',
    'this_month_charges' => 'এই মাসের চার্জ',
    'month_total' => 'মাসের মোট',
    'paid_on_this_bill' => 'এই বিলে পরিশোধিত',
    'payments_received' => 'প্রাপ্ত পেমেন্ট',
    'no_payments_yet' => 'এই বিলে এখনো কোনো পেমেন্ট পাওয়া যায়নি।',
    'total_payable_now' => 'এখন মোট পরিশোধযোগ্য',
    'print_bill' => 'বিল প্রিন্ট',
    'print_all_bills' => 'সব বিল প্রিন্ট',
    'bills_for_month' => 'বিল — :month',
    'no_bills_for_month' => 'এই ভবনে ঐ মাসের কোনো বিল তৈরি হয়নি।',
    'computer_generated_bill' => 'এটি কম্পিউটারে তৈরি বিল।',
    'reading_previous' => 'পূর্ববর্তী রিডিং',
    'reading_current' => 'বর্তমান রিডিং',
    'consumption' => 'ব্যবহার',
    'estimated_reading' => 'আনুমানিক',
    'collect_due' => 'বকেয়া আদায়',
    'payments' => 'পেমেন্টসমূহ',
    'payment_history_hint' => 'গৃহীত প্রতিটি রসিদ এবং তা যে বিল পরিশোধ করেছে।',
    'received_by' => 'গ্রহণকারী',
    'no_payments' => 'কোনো পেমেন্ট পাওয়া যায়নি।',
    'search_payment' => 'রসিদ নম্বর বা রেফারেন্স খুঁজুন',
    'all_flats' => 'সব ফ্ল্যাট',
    'all_methods' => 'সব মাধ্যম',
    'outstanding_now' => 'বর্তমান বকেয়া',
    'statuses' => [
        'unpaid' => 'বকেয়া',
        'partially_paid' => 'আংশিক পরিশোধিত',
        'paid' => 'পরিশোধিত',
    ],
```

- [ ] **Step 7: Verify locale parity**

Run:

```bash
php -r '$en=require "lang/en/billing.php"; $bn=require "lang/bn/billing.php"; $d=array_merge(array_diff_key($en,$bn),array_diff_key($bn,$en)); echo $d===[] ? "parity ok\n" : "MISMATCH: ".implode(",",array_keys($d))."\n";'
```

Expected: `parity ok`.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/ServiceChargeBillPolicy.php lang/en/billing.php lang/bn/billing.php tests/Feature/Billing/BillPrintTest.php
git commit -m "let an owner read their own bill, and add bill document strings"
```

---

### Task 3: The printable bill

**Files:**
- Create: `app/Http/Controllers/BillController.php`
- Create: `resources/views/components/bill-sheet.blade.php`
- Create: `resources/views/bills/print.blade.php`
- Modify: `resources/views/components/bill-lines.blade.php`
- Modify: `routes/web.php` (beside the `flats.statement` route)
- Modify: `tests/Feature/RouteSmokeTest.php`
- Test: `tests/Feature/Billing/BillPrintTest.php` (grown)

**Interfaces:**
- Consumes: `BillSummary::for(ServiceChargeBill): BillSummaryData` and all `BillSummaryData` properties from Task 1; `ServiceChargeBillPolicy::view()` and the `billing.*` keys from Task 2.
- Produces: route `bills.print` taking a `ServiceChargeBill`; the `<x-bill-sheet :summary="..." />` component; the `resources/views/bills/print.blade.php` view, which expects `$summaries` (a `list<BillSummaryData>`) and `$title` (string). Task 4 reuses both.

- [ ] **Step 1: Write the failing tests**

Append these methods to `tests/Feature/Billing/BillPrintTest.php`:

```php
    public function test_it_prints_the_bill_with_its_charges(): void
    {
        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print', $this->bill))
            ->assertSuccessful()
            ->assertSee($this->bill->bill_no)
            ->assertSee('A-4')
            ->assertSee($this->building->name)
            ->assertSee(__('billing.total_payable_now'))
            ->assertSee('3,000.00');
    }

    public function test_it_shows_previous_dues_brought_forward(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-07-01'));

        $july = ServiceChargeBill::where('flat_id', $this->flat->id)
            ->whereDate('billing_month', '2026-07-01')
            ->firstOrFail();

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print', $july))
            ->assertSuccessful()
            ->assertSee(__('billing.previous_dues'))
            ->assertSee($this->bill->bill_no)
            ->assertSee('6,000.00');
    }

    public function test_a_bill_with_no_arrears_omits_the_brought_forward_block(): void
    {
        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print', $this->bill))
            ->assertSuccessful()
            ->assertDontSee(__('billing.previous_dues'));
    }

    public function test_an_owner_may_print_their_own_bill_but_not_anothers(): void
    {
        $user = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $this->flat->update(['owner_id' => $owner->id]);

        $this->actingAs($user)->get(route('bills.print', $this->bill))->assertSuccessful();

        $other = Flat::factory()->for($this->building)->create();
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        $otherBill = ServiceChargeBill::where('flat_id', $other->id)->firstOrFail();

        $this->actingAs($user)->get(route('bills.print', $otherBill))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('bills.print', $this->bill))->assertRedirect(route('login'));
    }

    public function test_it_renders_in_bangla(): void
    {
        $this->actingAs($this->userWithRole(Role::Accountant))
            ->withSession(['locale' => 'bn'])
            ->get(route('bills.print', $this->bill))
            ->assertSuccessful()
            ->assertSee(__('billing.total_payable_now', [], 'bn'));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Billing/BillPrintTest.php`
Expected: FAIL — `Route [bills.print] not defined`.

- [ ] **Step 3: Teach `x-bill-lines` to show the meter reading**

Replace the contents of `resources/views/components/bill-lines.blade.php` with:

```blade
@props(['bill'])

{{--
    A bill's lines, with the working shown for anything metered.

    The quantity, rate and unit come off the line itself rather than being looked up:
    tariffs are versioned and utility names change, but a bill an owner already holds
    must render the same way forever.

    The meter readings behind a metered line come from the line's `source`, which bill
    generation stamped as the MeterReading. That is what lets an owner check the bill
    against the meter on their wall.
--}}
<table class="min-w-full text-sm">
    <tbody class="divide-y divide-slate-100">
        @foreach ($bill->items as $item)
            @php($reading = $item->source instanceof App\Models\MeterReading ? $item->source : null)
            <tr>
                <td class="py-1.5 pr-4">
                    {{ $item->description }}
                    @if ($item->isMetered())
                        <span class="block text-xs text-slate-500">
                            {{ rtrim(rtrim($item->quantity, '0'), '.') }} {{ $item->unit_label }}
                            &times; {{ rtrim(rtrim($item->unit_rate, '0'), '.') }}
                        </span>
                    @endif
                    @if ($reading !== null)
                        <span class="block text-xs text-slate-500">
                            {{ __('billing.reading_previous') }}
                            {{ rtrim(rtrim($reading->previous_reading, '0'), '.') }}
                            &rarr;
                            {{ __('billing.reading_current') }}
                            {{ rtrim(rtrim($reading->current_reading, '0'), '.') }}
                            &middot;
                            {{ __('billing.consumption') }}
                            {{ rtrim(rtrim($reading->consumption, '0'), '.') }} {{ $item->unit_label }}
                            @if ($reading->is_estimated)
                                &middot; {{ __('billing.estimated_reading') }}
                            @endif
                        </span>
                    @endif
                </td>
                <td class="py-1.5 text-right tabular-nums"><x-money :amount="$item->amount" /></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="border-t-2 border-slate-900">
            <td class="py-2 pr-4 font-semibold">{{ __('billing.month_total') }}</td>
            <td class="py-2 text-right text-base font-semibold tabular-nums">
                <x-money :amount="$bill->total_amount" />
            </td>
        </tr>
    </tfoot>
</table>
```

- [ ] **Step 4: Write the bill sheet component**

Create `resources/views/components/bill-sheet.blade.php`:

```blade
@props(['summary'])

@php
    $bill = $summary->bill;
    $flat = $bill->flat;
@endphp

<div class="sheet mx-auto mb-8 max-w-2xl bg-white p-8 shadow break-after-page last:mb-0 last:break-after-auto">
    <header class="flex items-start justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-lg font-semibold">{{ $flat->building->displayName() }}</h1>
            @if ($flat->building->address)
                <p class="mt-1 text-sm text-slate-500">{{ $flat->building->address }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.bill') }}</p>
            <p class="font-mono text-sm font-semibold">{{ $bill->bill_no }}</p>
        </div>
    </header>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 border-b border-slate-200 py-4 text-sm">
        <div>
            <dt class="text-slate-500">{{ __('billing.flat') }}</dt>
            <dd class="font-medium">{{ $flat->number }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">{{ __('billing.billing_month') }}</dt>
            <dd class="font-medium">{{ $bill->billing_month->format('F Y') }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">{{ __('billing.owner') }}</dt>
            <dd class="font-medium">{{ $flat->owner?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">{{ __('billing.due_date') }}</dt>
            <dd class="font-medium">{{ $bill->due_date->format('d M Y') }}</dd>
        </div>
    </dl>

    @if ($summary->hasArrears())
        <div class="border-b border-slate-200 py-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.previous_dues') }}</p>

            <table class="mt-2 w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($summary->priorOpenBills as $prior)
                        <tr>
                            <td class="py-1.5 font-mono text-xs">{{ $prior['bill']->bill_no }}</td>
                            <td class="py-1.5 text-slate-500">{{ $prior['bill']->billing_month->format('F Y') }}</td>
                            <td class="py-1.5 text-right"><x-money :amount="$prior['outstanding']" /></td>
                        </tr>
                    @endforeach

                    @if ($summary->hasUnbilledArrears())
                        <tr>
                            <td class="py-1.5 text-slate-500" colspan="2">{{ __('billing.other_previous_charges') }}</td>
                            <td class="py-1.5 text-right"><x-money :amount="$summary->unbilledArrears" /></td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-300">
                        <td class="py-1.5 font-medium" colspan="2">{{ __('billing.previous_dues') }}</td>
                        <td class="py-1.5 text-right font-medium"><x-money :amount="$summary->broughtForward" /></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <div class="border-b border-slate-200 py-4">
        <p class="mb-2 text-xs uppercase tracking-wide text-slate-500">{{ __('billing.this_month_charges') }}</p>

        <x-bill-lines :bill="$bill" />
    </div>

    <div class="border-b border-slate-200 py-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.payments_received') }}</p>

        @if ($summary->hasPayments())
            <table class="mt-2 w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($summary->paymentsReceived as $allocation)
                        <tr>
                            <td class="py-1.5 font-mono text-xs">{{ $allocation->payment->receipt_no }}</td>
                            <td class="py-1.5 text-slate-500">{{ $allocation->payment->received_on->format('d M Y') }}</td>
                            <td class="py-1.5 text-slate-500">{{ __('billing.methods.'.$allocation->payment->method->value) }}</td>
                            <td class="py-1.5 text-right"><x-money :amount="$allocation->amount" /></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-300">
                        <td class="py-1.5 font-medium" colspan="3">{{ __('billing.paid_on_this_bill') }}</td>
                        <td class="py-1.5 text-right font-medium"><x-money :amount="$summary->paidOnThisBill" /></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <p class="mt-2 text-sm text-slate-500">{{ __('billing.no_payments_yet') }}</p>
        @endif
    </div>

    <div class="flex items-center justify-between border-t-2 border-slate-900 py-3">
        <span class="font-semibold">{{ __('billing.total_payable_now') }}</span>
        <span class="text-lg font-semibold"><x-money :amount="$summary->totalDue" /></span>
    </div>

    @if ($summary->hasAdvance())
        <div class="flex items-center justify-between border-t border-slate-200 pt-3 text-sm">
            <span class="text-slate-500">{{ __('billing.advance_held') }}</span>
            <span class="font-medium"><x-money :amount="$summary->advanceHeld" /></span>
        </div>
    @endif

    <p class="mt-6 text-center text-xs text-slate-400">{{ __('billing.computer_generated_bill') }}</p>
</div>
```

- [ ] **Step 5: Write the print page**

Create `resources/views/bills/print.blade.php`. It takes `$summaries` (a `list<BillSummaryData>`) and `$title`, so one view serves both the single bill and the whole month:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0 auto; max-width: none; }
        }
    </style>
</head>
<body class="bg-slate-100 py-8 text-slate-900">
    <div class="no-print mx-auto mb-4 flex max-w-2xl justify-end gap-2 px-6">
        <button type="button" onclick="window.print()"
                class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
            {{ __('billing.print') }}
        </button>
    </div>

    @forelse ($summaries as $summary)
        <x-bill-sheet :summary="$summary" />
    @empty
        <p class="mx-auto max-w-2xl bg-white p-8 text-center text-sm text-slate-500 shadow">
            {{ __('billing.no_bills_for_month') }}
        </p>
    @endforelse
</body>
</html>
```

- [ ] **Step 6: Write the controller**

Create `app/Http/Controllers/BillController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ServiceChargeBill;
use App\Services\Billing\BillSummary;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The printable bill.
 *
 * A plain controller rather than a Livewire screen, for the same reason as
 * ReceiptController: there is nothing interactive on a print page, and it wants its own
 * bare layout. Single and batch render the same sheet component, so they cannot diverge.
 */
class BillController extends Controller
{
    public function __construct(private readonly BillSummary $summaries) {}

    public function show(ServiceChargeBill $bill): View
    {
        Gate::authorize('view', $bill);

        return view('bills.print', [
            'summaries' => [$this->summaries->for($bill)],
            'title' => __('billing.bill').' — '.$bill->bill_no,
        ]);
    }
}
```

- [ ] **Step 7: Register the route**

In `routes/web.php`, add the import `use App\Http\Controllers\BillController;` alongside the other controller imports, then add this route directly beneath the `payments.receipt` route (outside every `role:` group, so the policy alone decides):

```php
    // And the bill itself: ServiceChargeBillPolicy lets an owner print their own.
    Route::get('bills/{bill}/print', [BillController::class, 'show'])->name('bills.print');
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Billing/BillPrintTest.php`
Expected: PASS, 9 tests.

- [ ] **Step 9: Check the metered case still renders**

The existing suite covers metered bill lines. Run:

Run: `php artisan test --compact tests/Feature/Billing/BillPresentationTest.php tests/Feature/Billing/MeteredBillingTest.php`
Expected: PASS. If a test asserted the old `reports.total` label in `x-bill-lines`, update that assertion to `billing.month_total` — the label changed in Step 3.

- [ ] **Step 10: Add the route to the smoke test**

In `tests/Feature/RouteSmokeTest.php`, the guest-redirect list and role matrix are built from route-name arrays. `bills.print` needs a bound model, so it does not belong in those flat lists — it is covered by `BillPrintTest` instead. Add a comment above the arrays recording that, so a later reader does not think it was forgotten:

```php
        // bills.print and payments.receipt need a bound model, so their auth is covered
        // by BillPrintTest and ReceiptTest rather than by this name-only matrix.
```

- [ ] **Step 11: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/BillController.php resources/views/bills resources/views/components/bill-sheet.blade.php resources/views/components/bill-lines.blade.php routes/web.php tests/Feature/Billing/BillPrintTest.php tests/Feature/RouteSmokeTest.php
git commit -m "add the printable bill with brought-forward dues and meter readings"
```

---

### Task 4: Print every bill for a month

**Files:**
- Modify: `app/Http/Controllers/BillController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/livewire/generate-bills.blade.php`
- Modify: `tests/Feature/RouteSmokeTest.php:73-78`
- Test: `tests/Feature/Billing/BillPrintTest.php` (grown)

**Interfaces:**
- Consumes: `BillController`, `bills.print.blade.php` and `<x-bill-sheet>` from Task 3; `App\Support\CurrentBuilding::get(): ?Building`.
- Produces: route `bills.print-month`, accepting an optional `?month=YYYY-MM` query parameter.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Billing/BillPrintTest.php`:

```php
    public function test_it_prints_every_bill_for_the_month_in_the_current_building(): void
    {
        $second = Flat::factory()->for($this->building)->create(['number' => 'B-1']);

        $other = Building::factory()->flatRate('1500.00')->create();
        Flat::factory()->for($other)->create(['number' => 'Z-9']);
        app(GenerateMonthlyBills::class)->handle($other, Carbon::parse('2026-06-01'));

        // The second flat was created after June's run, so bill it for June too.
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));

        app(\App\Support\CurrentBuilding::class)->set($this->building->id);

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print-month', ['month' => '2026-06']))
            ->assertSuccessful()
            ->assertSee('A-4')
            ->assertSee($second->number)
            ->assertDontSee('Z-9');
    }

    public function test_a_month_with_no_bills_says_so(): void
    {
        app(\App\Support\CurrentBuilding::class)->set($this->building->id);

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print-month', ['month' => '2026-01']))
            ->assertSuccessful()
            ->assertSee(__('billing.no_bills_for_month'));
    }

    public function test_a_malformed_month_is_rejected(): void
    {
        app(\App\Support\CurrentBuilding::class)->set($this->building->id);

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print-month', ['month' => 'June']))
            ->assertSessionHasErrors('month');
    }

    public function test_an_owner_may_not_batch_print(): void
    {
        $user = $this->userWithRole(Role::Owner);
        Owner::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('bills.print-month', ['month' => '2026-06']))
            ->assertForbidden();
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=month tests/Feature/Billing/BillPrintTest.php`
Expected: FAIL — `Route [bills.print-month] not defined`.

- [ ] **Step 3: Add the batch method to the controller**

Add these imports to `app/Http/Controllers/BillController.php`:

```php
use App\Support\CurrentBuilding;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
```

Then add the method:

```php
    /**
     * Every bill for one month in the building being worked in, one sheet per page.
     *
     * Building scope comes from CurrentBuilding rather than a query parameter: a batch
     * print is an operational action, and operational screens are always scoped to the
     * building the operator has selected.
     */
    public function month(Request $request, CurrentBuilding $current): View
    {
        Gate::authorize('viewAny', ServiceChargeBill::class);

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = Carbon::parse(($validated['month'] ?? now()->format('Y-m')).'-01')->startOfMonth();

        $building = $current->get();

        $bills = $building === null
            ? collect()
            : ServiceChargeBill::query()
                ->whereIn('flat_id', $building->flats()->select('id'))
                ->whereDate('billing_month', $month)
                ->with(['items', 'flat.owner', 'flat.building'])
                ->join('flats', 'flats.id', '=', 'service_charge_bills.flat_id')
                ->orderBy('flats.number')
                ->select('service_charge_bills.*')
                ->get();

        return view('bills.print', [
            'summaries' => $bills->map(fn (ServiceChargeBill $bill) => $this->summaries->for($bill))->all(),
            'title' => __('billing.bills_for_month', ['month' => $month->format('F Y')]),
        ]);
    }
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, inside the `Route::middleware('role:admin|accountant|committee')` group, beside the other billing routes:

```php
        Route::get('billing/bills/print', [BillController::class, 'month'])->name('bills.print-month');
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Billing/BillPrintTest.php`
Expected: PASS, 13 tests.

- [ ] **Step 6: Link to it from the bill generation screen**

`App\Livewire\GenerateBills` already exposes `public string $month = ''`, set to `now()->format('Y-m')` in `mount()` — the exact format `bills.print-month` expects, so no conversion is needed. In `resources/views/livewire/generate-bills.blade.php`, add this beside the Generate button so an operator can print what they just made:

```blade
        @if ($month !== '')
            <x-ui.button variant="secondary" :href="route('bills.print-month', ['month' => $month])">
                {{ __('billing.print_all_bills') }}
            </x-ui.button>
        @endif
```

`GenerateBills.php` itself needs no change.

- [ ] **Step 7: Add the route to the smoke test matrix**

In `tests/Feature/RouteSmokeTest.php`, add `'bills.print-month'` to the staff route list alongside `'reports.collections'` (line ~63) so guest-redirect and role gating are covered.

- [ ] **Step 8: Run the affected tests**

Run: `php artisan test --compact tests/Feature/RouteSmokeTest.php tests/Feature/Billing/BillPrintTest.php tests/Feature/Livewire/BillingScreensTest.php`
Expected: PASS.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/BillController.php routes/web.php resources/views/livewire/generate-bills.blade.php tests/Feature/Billing/BillPrintTest.php tests/Feature/RouteSmokeTest.php
git commit -m "print every bill for a month in one pass"
```

---

### Task 5: The payments screen — who gave what amount

**Files:**
- Create: `app/Livewire/PaymentList.php`
- Create: `resources/views/livewire/payment-list.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Support/Navigation.php:38-44`
- Modify: `lang/en/nav.php`, `lang/bn/nav.php`
- Modify: `tests/Feature/RouteSmokeTest.php`
- Test: `tests/Feature/Billing/PaymentListTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment`, `PaymentPolicy::viewAny()`, `App\Support\CurrentBuilding`, the `billing.*` keys from Task 2.
- Produces: route `payments.index`; nav key `nav.payments`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Billing/PaymentListTest.php`:

```php
<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Livewire\PaymentList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private Flat $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);
        $this->other = Flat::factory()->for($this->building)->create(['number' => 'B-2']);

        app(CurrentBuilding::class)->set($this->building->id);
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
    }

    private function accountant(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Accountant->value]);

        return $user;
    }

    private function pay(Flat $flat, string $amount, string $on, PaymentMethod $method = PaymentMethod::Cash): void
    {
        app(RecordPayment::class)->handle($flat, $amount, $method, Carbon::parse($on), 'TRX-'.$amount);
    }

    public function test_it_lists_payments_with_what_they_settled(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->assertSee('A-4')
            ->assertSee('1,200.00')
            ->assertSee('TRX-1200.00')
            ->assertSee('BILL-');
    }

    public function test_an_unallocated_payment_reads_as_an_advance(): void
    {
        $spare = Flat::factory()->for($this->building)->create(['number' => 'C-9']);
        $this->pay($spare, '500.00', '2026-06-12');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->assertSee(__('billing.held_as_advance'));
    }

    public function test_it_filters_by_flat(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');
        $this->pay($this->other, '900.00', '2026-06-13');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->set('flatId', $this->flat->id)
            ->assertSee('1,200.00')
            ->assertDontSee('900.00');
    }

    public function test_it_filters_by_date_range_and_method(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');
        $this->pay($this->other, '900.00', '2026-07-13', PaymentMethod::Bkash);

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->set('from', '2026-07-01')
            ->set('to', '2026-07-31')
            ->assertSee('900.00')
            ->assertDontSee('1,200.00')
            ->set('from', '2026-06-01')
            ->set('to', '2026-07-31')
            ->set('method', PaymentMethod::Bkash->value)
            ->assertSee('900.00')
            ->assertDontSee('1,200.00');
    }

    public function test_it_searches_receipt_no_and_reference(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');
        $this->pay($this->other, '900.00', '2026-06-13');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->set('search', 'TRX-900')
            ->assertSee('900.00')
            ->assertDontSee('1,200.00');
    }

    public function test_an_owner_may_not_open_it(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Owner->value]);
        Owner::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Billing/PaymentListTest.php`
Expected: FAIL — `Class "App\Livewire\PaymentList" not found`.

- [ ] **Step 3: Write the component**

Create `app/Livewire/PaymentList.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Models\Flat;
use App\Models\Payment;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payment history: every receipt taken, and what each one settled.
 *
 * The Collection Report answers "how much came in this month, by method". This answers
 * "who paid what, and against which bill" — the question an operator asks when an owner
 * disputes a balance.
 */
class PaymentList extends Component
{
    use WithPagination;

    public ?int $flatId = null;

    public string $method = '';

    public string $from = '';

    public string $to = '';

    public string $search = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function updatingFlatId(): void
    {
        $this->resetPage();
    }

    public function updatingMethod(): void
    {
        $this->resetPage();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, Flat>
     */
    private function flatsInBuilding(): Collection
    {
        $building = app(CurrentBuilding::class)->get();

        return Flat::query()
            ->when($building !== null, fn ($query) => $query->where('building_id', $building->id))
            ->orderBy('number')
            ->get();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Payment::class);

        $flats = $this->flatsInBuilding();

        $payments = Payment::query()
            ->with(['flat.owner', 'allocations.bill'])
            ->whereIn('flat_id', $flats->pluck('id'))
            ->when($this->flatId !== null, fn ($query) => $query->where('flat_id', $this->flatId))
            ->when($this->method !== '', fn ($query) => $query->where('method', $this->method))
            ->when($this->from !== '', fn ($query) => $query->whereDate('received_on', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('received_on', '<=', $this->to))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';

                $query->where(function ($inner) use ($term): void {
                    $inner->where('receipt_no', 'ilike', $term)
                        ->orWhere('reference', 'ilike', $term);
                });
            })
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.payment-list', [
            'payments' => $payments,
            'flats' => $flats,
            'methods' => PaymentMethod::cases(),
        ])->layout('components.layouts.app');
    }
}
```

- [ ] **Step 4: Write the view**

Create `resources/views/livewire/payment-list.blade.php`:

```blade
<div class="space-y-6">
    <x-ui.page-header :title="__('billing.payments')" :description="__('billing.payment_history_hint')" />

    <div class="flex flex-wrap items-end justify-end gap-3 text-sm">
        <label>
            <span class="mr-2 text-slate-600">{{ __('billing.flat') }}</span>
            <select wire:model.live="flatId" class="rounded-md border-slate-300 text-sm shadow-sm">
                <option value="">{{ __('billing.all_flats') }}</option>
                @foreach ($flats as $flat)
                    <option value="{{ $flat->id }}">{{ $flat->number }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.method') }}</span>
            <select wire:model.live="method" class="rounded-md border-slate-300 text-sm shadow-sm">
                <option value="">{{ __('billing.all_methods') }}</option>
                @foreach ($methods as $option)
                    <option value="{{ $option->value }}">{{ __('billing.methods.'.$option->value) }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>

        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>

        <input type="search" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('billing.search_payment') }}"
               class="w-full rounded-md border-slate-300 text-sm shadow-sm sm:w-64">
    </div>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('reports.date') }}</th>
            <th class="px-4 py-3">{{ __('reports.receipt_no') }}</th>
            <th class="px-4 py-3">{{ __('billing.flat') }}</th>
            <th class="px-4 py-3">{{ __('billing.owner') }}</th>
            <th class="px-4 py-3">{{ __('reports.method') }}</th>
            <th class="px-4 py-3">{{ __('billing.reference') }}</th>
            <th class="px-4 py-3">{{ __('billing.allocated_to') }}</th>
            <th class="px-4 py-3 text-right">{{ __('billing.amount') }}</th>
        </x-slot:head>

        @forelse ($payments as $payment)
            <tr>
                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $payment->received_on->format('d M Y') }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('payments.receipt', $payment) }}"
                       class="font-mono text-xs text-slate-600 underline hover:text-slate-900">{{ $payment->receipt_no }}</a>
                </td>
                <td class="px-4 py-2">
                    <a href="{{ route('flats.statement', $payment->flat) }}" class="font-medium hover:underline">
                        {{ $payment->flat->number }}
                    </a>
                </td>
                <td class="px-4 py-2 text-slate-600">{{ $payment->flat->owner?->name ?? '—' }}</td>
                <td class="px-4 py-2 text-slate-600">{{ __('billing.methods.'.$payment->method->value) }}</td>
                <td class="px-4 py-2 text-slate-500">{{ $payment->reference ?? '—' }}</td>
                <td class="px-4 py-2 text-xs text-slate-500">
                    @if ($payment->allocations->isEmpty())
                        {{ __('billing.held_as_advance') }}
                    @else
                        @foreach ($payment->allocations as $allocation)
                            <span class="block">
                                {{ $allocation->bill->bill_no }}
                                <x-money :amount="$allocation->amount" class="ml-1 text-slate-700" />
                            </span>
                        @endforeach
                    @endif
                </td>
                <td class="px-4 py-2 text-right font-medium"><x-money :amount="$payment->amount" /></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">{{ __('billing.no_payments') }}</td></tr>
        @endforelse
    </x-ui.table>

    {{ $payments->links() }}
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add `use App\Livewire\PaymentList;` to the imports, then inside the `Route::middleware('role:admin|accountant|committee')` group:

```php
        Route::get('payments', PaymentList::class)->name('payments.index');
```

- [ ] **Step 6: Add the menu entry**

In `app/Support/Navigation.php`, inside the `nav.billing` group's `items` array, after the `nav.record_payment` entry:

```php
                ['label' => 'nav.payments', 'route' => 'payments.index', 'access' => self::STAFF],
```

Then add `'payments' => 'Payments',` to `lang/en/nav.php` and `'payments' => 'পেমেন্টসমূহ',` to `lang/bn/nav.php`.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Billing/PaymentListTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 8: Add the route to the smoke test matrix**

In `tests/Feature/RouteSmokeTest.php`, add `'payments.index'` to the staff route list beside `'reports.collections'`.

Run: `php artisan test --compact tests/Feature/RouteSmokeTest.php`
Expected: PASS.

- [ ] **Step 9: Verify locale parity**

```bash
php -r '$en=require "lang/en/nav.php"; $bn=require "lang/bn/nav.php"; $d=array_merge(array_diff_key($en,$bn),array_diff_key($bn,$en)); echo $d===[] ? "parity ok\n" : "MISMATCH: ".implode(",",array_keys($d))."\n";'
```

Expected: `parity ok`.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/PaymentList.php resources/views/livewire/payment-list.blade.php routes/web.php app/Support/Navigation.php lang/en/nav.php lang/bn/nav.php tests/Feature/Billing/PaymentListTest.php tests/Feature/RouteSmokeTest.php
git commit -m "add a payments screen showing who paid what and against which bill"
```

---

### Task 6: Per-bill payment detail on the flat statement

**Files:**
- Modify: `app/Livewire/FlatStatement.php:36-72`
- Modify: `resources/views/livewire/flat-statement.blade.php:63-84`
- Test: `tests/Feature/FlatStatementTest.php` (grown)

**Interfaces:**
- Consumes: `BillSummary::for()` and `BillSummaryData` from Task 1; route `bills.print` from Task 3; the `billing.statuses.*`, `billing.print_bill` and `billing.collect_due` keys from Task 2.
- Produces: the statement view now receives `summaries` (a `list<BillSummaryData>`) instead of `bills`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/FlatStatementTest.php` already has a `setUp()` creating `$this->building` (flat rate `3000.00`, due day 10) and `$this->flat`, plus a `private function actingAsAccountant(): void` that calls `$this->actingAs(...)`. Reuse both — do not add a second helper. Append two helpers and three tests:

```php
    private function generateJune(): ServiceChargeBill
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));

        return ServiceChargeBill::where('flat_id', $this->flat->id)->firstOrFail();
    }

    private function payPartially(): void
    {
        app(RecordPayment::class)->handle(
            $this->flat,
            '1200.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-06-12'),
        );
    }

    public function test_each_bill_shows_its_status_and_what_has_been_paid(): void
    {
        $this->actingAsAccountant();

        $bill = $this->generateJune();
        $this->payPartially();

        Livewire::test(FlatStatement::class, ['flat' => $this->flat])
            ->assertSee($bill->bill_no)
            ->assertSee(__('billing.statuses.partially_paid'))
            ->assertSee('1,200.00')
            ->assertSee('1,800.00')
            ->assertSee(__('billing.print_bill'));
    }

    public function test_a_money_handler_sees_the_collect_due_action(): void
    {
        $this->actingAsAccountant();
        $this->generateJune();

        Livewire::test(FlatStatement::class, ['flat' => $this->flat])
            ->assertSee(__('billing.collect_due'));
    }

    public function test_an_owner_does_not_see_the_collect_due_action(): void
    {
        $this->generateJune();

        $user = User::factory()->create();
        $user->assignRole('owner');
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $this->flat->update(['owner_id' => $owner->id]);

        $this->actingAs($user);

        Livewire::test(FlatStatement::class, ['flat' => $this->flat->fresh()])
            ->assertDontSee(__('billing.collect_due'));
    }
```

`PaymentMethod`, `Owner`, `User`, `Livewire`, `Carbon`, `GenerateMonthlyBills` and `RecordPayment` are already imported at the top of the file. Add only `use App\Models\ServiceChargeBill;`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/FlatStatementTest.php`
Expected: FAIL — the status label and print link are not rendered.

- [ ] **Step 3: Pass summaries from the component**

In `app/Livewire/FlatStatement.php`, add the import `use App\Services\Billing\BillSummary;`, change the `render` signature to `public function render(JournalService $journal, BillSummary $summaries): View`, and replace the `'bills' => ...` entry in the view data with:

```php
            // The breakdown behind each accrual: what the owner is actually being asked
            // to pay for, what has been paid against it, and the working on any metered
            // line. BillSummary is the only place those figures are computed.
            'summaries' => ServiceChargeBill::with('items')
                ->where('flat_id', $this->flat->id)
                ->orderByDesc('billing_month')
                ->orderByDesc('id')
                ->limit(12)
                ->get()
                ->map(fn (ServiceChargeBill $bill) => $summaries->for($bill))
                ->all(),
```

- [ ] **Step 4: Render the detail**

In `resources/views/livewire/flat-statement.blade.php`, replace the whole `@if ($bills->isNotEmpty()) ... @endif` block at the bottom with:

```blade
    @if ($summaries !== [])
        <div class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('reports.bill_breakdown') }}</h2>

                @can('create', App\Models\Payment::class)
                    <x-ui.button :href="route('payments.create', ['flat' => $flat->id])">
                        {{ __('billing.collect_due') }}
                    </x-ui.button>
                @endcan
            </div>

            <div class="space-y-4">
                @foreach ($summaries as $summary)
                    @php($bill = $summary->bill)
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-slate-900">{{ $bill->bill_no }}</p>
                                <x-ui.badge :variant="match ($bill->status->value) {
                                    'paid' => 'success',
                                    'partially_paid' => 'warning',
                                    default => 'danger',
                                }">{{ __('billing.statuses.'.$bill->status->value) }}</x-ui.badge>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <span>{{ $bill->billing_month->format('F Y') }}</span>
                                <a href="{{ route('bills.print', $bill) }}"
                                   class="font-medium text-slate-600 underline hover:text-slate-900">
                                    {{ __('billing.print_bill') }}
                                </a>
                            </div>
                        </div>

                        <x-bill-lines :bill="$bill" />

                        <dl class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-200 pt-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-slate-500">{{ __('billing.paid_on_this_bill') }}</dt>
                                <dd class="font-medium"><x-money :amount="$summary->paidOnThisBill" /></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">{{ __('reports.outstanding') }}</dt>
                                <dd class="font-medium"><x-money :amount="$summary->thisBillOutstanding" /></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">{{ __('billing.total_payable_now') }}</dt>
                                <dd class="font-medium"><x-money :amount="$summary->totalDue" /></dd>
                            </div>
                        </dl>

                        @if ($summary->hasPayments())
                            <div class="mt-3 border-t border-slate-200 pt-3">
                                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.payments_received') }}</p>
                                <ul class="mt-1 space-y-1 text-sm">
                                    @foreach ($summary->paymentsReceived as $allocation)
                                        <li class="flex items-center justify-between">
                                            <span>
                                                <a href="{{ route('payments.receipt', $allocation->payment) }}"
                                                   class="font-mono text-xs text-slate-600 underline hover:text-slate-900">
                                                    {{ $allocation->payment->receipt_no }}
                                                </a>
                                                <span class="ml-2 text-slate-500">
                                                    {{ $allocation->payment->received_on->format('d M Y') }}
                                                </span>
                                            </span>
                                            <x-money :amount="$allocation->amount" />
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/FlatStatementTest.php tests/Feature/Billing/BillPresentationTest.php`
Expected: PASS. `BillPresentationTest` asserts on the statement's bill breakdown — if it referenced `$bills`, update it to the new shape.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/FlatStatement.php resources/views/livewire/flat-statement.blade.php tests/Feature/FlatStatementTest.php tests/Feature/Billing/BillPresentationTest.php
git commit -m "show per-bill status, payments and print link on the flat statement"
```

---

### Task 7: Collect due — prefilled from the dues report and the statement

**Files:**
- Modify: `app/Livewire/RecordPaymentForm.php:31-52`
- Modify: `resources/views/livewire/record-payment-form.blade.php`
- Modify: `resources/views/livewire/reports/owner-dues.blade.php`
- Test: `tests/Feature/Billing/CollectDueTest.php`

**Interfaces:**
- Consumes: `JournalService::balanceFor()`, `AccountCode::ServiceChargeReceivable` / `AdvanceFromOwners`, the `billing.collect_due` and `billing.outstanding_now` keys from Task 2.
- Produces: `payments.create` now accepts a `flat` query parameter; `RecordPaymentForm::mount(?int $flat = null)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Billing/CollectDueTest.php`:

```php
<?php

namespace Tests\Feature\Billing;

use App\Enums\BillStatus;
use App\Enums\Role;
use App\Livewire\RecordPaymentForm;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CollectDueTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);

        app(CurrentBuilding::class)->set($this->building->id);
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
    }

    private function accountant(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Accountant->value]);

        return $user;
    }

    public function test_opening_the_form_for_a_flat_prefills_the_outstanding_amount(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(RecordPaymentForm::class, ['flat' => $this->flat->id])
            ->assertSet('flatId', $this->flat->id)
            ->assertSet('amount', '3000.00');
    }

    public function test_choosing_a_flat_prefills_the_outstanding_amount(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(RecordPaymentForm::class)
            ->set('flatId', $this->flat->id)
            ->assertSet('amount', '3000.00');
    }

    public function test_the_prefilled_amount_can_be_reduced_to_a_partial_payment(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(RecordPaymentForm::class, ['flat' => $this->flat->id])
            ->set('amount', '1000.00')
            ->set('receivedOn', '2026-06-12')
            ->call('save')
            ->assertHasNoErrors();

        $bill = ServiceChargeBill::where('flat_id', $this->flat->id)->firstOrFail();

        $this->assertSame(BillStatus::PartiallyPaid, $bill->status);
        $this->assertSame(0, bccomp($bill->outstandingAmount(), '2000.00', 2));
    }

    public function test_a_flat_outside_the_current_building_is_not_prefilled(): void
    {
        $other = Flat::factory()->for(Building::factory()->create())->create();

        Livewire::actingAs($this->accountant())
            ->test(RecordPaymentForm::class, ['flat' => $other->id])
            ->assertSet('flatId', null)
            ->assertSet('amount', '');
    }

    public function test_the_dues_report_links_to_collect(): void
    {
        $this->actingAs($this->accountant())
            ->get(route('reports.owner-dues'))
            ->assertSuccessful()
            ->assertSee(__('billing.collect_due'))
            ->assertSee(route('payments.create', ['flat' => $this->flat->id]), escape: false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Billing/CollectDueTest.php`
Expected: FAIL — `mount()` takes no arguments and does not prefill.

- [ ] **Step 3: Prefill in the component**

In `app/Livewire/RecordPaymentForm.php`, add the imports:

```php
use App\Enums\AccountCode;
use App\Services\JournalService;
```

Replace `mount()` with:

```php
    /**
     * `?flat=` lets the dues report and the flat statement hand an operator straight to
     * the money, with the amount already filled in. The amount stays editable, so a
     * part payment is one keystroke rather than a fresh calculation.
     */
    public function mount(?int $flat = null): void
    {
        $this->receivedOn = now()->toDateString();

        if ($flat === null) {
            return;
        }

        $this->flatId = $flat;
        $this->prefillOutstanding();
    }

    public function updatedFlatId(): void
    {
        $this->prefillOutstanding();
    }

    /**
     * Default the amount to whatever the ledger says the flat owes.
     *
     * A flat outside the building being worked in resolves to null and clears the field,
     * so a stale link cannot quietly target the wrong flat.
     */
    private function prefillOutstanding(): void
    {
        $flat = $this->currentFlat();

        if ($flat === null) {
            $this->flatId = null;
            $this->amount = '';

            return;
        }

        $outstanding = $this->outstandingFor($flat);

        $this->amount = bccomp($outstanding, '0', 2) > 0 ? $outstanding : '';
    }

    /**
     * @return numeric-string
     */
    public function outstandingFor(Flat $flat): string
    {
        $journal = app(JournalService::class);

        return $journal->balanceFor(
            $journal->account(AccountCode::ServiceChargeReceivable),
            $flat->id,
        );
    }

    /**
     * @return numeric-string
     */
    public function advanceFor(Flat $flat): string
    {
        $journal = app(JournalService::class);

        return $journal->balanceFor(
            $journal->account(AccountCode::AdvanceFromOwners),
            $flat->id,
        );
    }
```

Then, in `render()`, add the selected flat and its balances to the view data:

```php
        $selected = $this->currentFlat();

        return view('livewire.record-payment-form', [
            'flats' => $this->flatsInBuilding(),
            'methods' => PaymentMethod::cases(),
            'selectedFlat' => $selected,
            'selectedOutstanding' => $selected !== null ? $this->outstandingFor($selected) : '0.00',
            'selectedAdvance' => $selected !== null ? $this->advanceFor($selected) : '0.00',
        ])->layout('components.layouts.app');
```

- [ ] **Step 4: Show the balances on the form**

In `resources/views/livewire/record-payment-form.blade.php`, add this directly beneath the flat select field:

```blade
        @if ($selectedFlat !== null)
            <dl class="grid grid-cols-2 gap-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm sm:col-span-2">
                <div>
                    <dt class="text-slate-500">{{ __('billing.outstanding_now') }}</dt>
                    <dd class="font-medium"><x-money :amount="$selectedOutstanding" /></dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('billing.advance_held') }}</dt>
                    <dd class="font-medium"><x-money :amount="$selectedAdvance" /></dd>
                </div>
            </dl>
        @endif
```

Place it inside the same grid container as the other fields; check the surrounding markup and match its column classes.

- [ ] **Step 5: Add the collect action to the dues report**

In `resources/views/livewire/reports/owner-dues.blade.php`, add a header cell after the outstanding column:

```blade
                    <th class="px-4 py-3"><span class="sr-only">{{ __('billing.collect_due') }}</span></th>
```

a matching body cell at the end of each row:

```blade
                        <td class="px-4 py-2 text-right">
                            @can('create', App\Models\Payment::class)
                                <a href="{{ route('payments.create', ['flat' => $row['flat']->id]) }}"
                                   class="text-sm font-medium text-slate-600 underline hover:text-slate-900">
                                    {{ __('billing.collect_due') }}
                                </a>
                            @endcan
                        </td>
```

and bump both `colspan="7"` (the empty row) and the footer's `colspan="2"`-plus-cells to account for the new column: change the empty row to `colspan="8"` and add an empty `<td class="px-4 py-3"></td>` at the end of the `<tfoot>` row.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Billing/CollectDueTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 7: Run the neighbouring suites**

Run: `php artisan test --compact tests/Feature/Billing/RecordPaymentTest.php tests/Feature/Reports/OwnerDuesTest.php tests/Feature/Livewire/BillingScreensTest.php tests/Feature/Livewire/ConfirmationGuardsTest.php`
Expected: PASS. The allocation confirmation is unchanged; if a test asserted `amount` starts empty, update it to reflect the prefill.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/RecordPaymentForm.php resources/views/livewire/record-payment-form.blade.php resources/views/livewire/reports/owner-dues.blade.php tests/Feature/Billing/CollectDueTest.php
git commit -m "collect a due in one click from the dues report and flat statement"
```

---

### Task 8: Full verification

**Files:** none created or modified unless the gate finds something.

- [ ] **Step 1: Run the full gate**

Run: `composer check`
Expected: Pint clean, Larastan level 5 clean, full PHPUnit suite green.

- [ ] **Step 2: Fix whatever it reports**

Common cases and their fixes:
- *Larastan: property `$quantity` has no type* — add the `@property string|null` docblock, never a cast change.
- *Larastan: `float` inferred on a money property* — declare `@property string` on the model.
- *Pint diff* — run `vendor/bin/pint --format agent`.
- *A test asserting the old `x-bill-lines` total label* — it changed from `reports.total` to `billing.month_total` in Task 3.

Re-run `composer check` until clean.

- [ ] **Step 3: Confirm locale parity across every touched file**

```bash
for f in billing nav reports; do
  php -r "\$en=require 'lang/en/$f.php'; \$bn=require 'lang/bn/$f.php'; \$d=array_merge(array_diff_key(\$en,\$bn),array_diff_key(\$bn,\$en)); echo '$f: '.(\$d===[] ? 'parity ok' : 'MISMATCH '.implode(',',array_keys(\$d)))._PHP_EOL;"
done
```

Expected: `parity ok` for all three.

- [ ] **Step 4: Report the real output**

Paste the actual `composer check` output. Do not claim the work is done without it.

- [ ] **Step 5: Commit any fixes**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "satisfy the full check gate"
```

---

## Out of scope

Do not build these here; they are separate work:

- Voiding, editing or reversing a recorded payment
- Emailing or SMS-ing bills
- An owner portal beyond the existing statement, receipt and bill
- Any change to how charges are calculated or posted to the ledger
