<?php

namespace App\Livewire;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\Expenses\PayVendorBill;
use App\Services\Expenses\RecordVendorBill;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class VendorBillList extends Component
{
    use WithPagination;

    public ?int $vendorId = null;

    public ?int $accountId = null;

    public string $amount = '';

    public string $billDate = '';

    public string $dueDate = '';

    public string $description = '';

    /** The bill currently being settled, if any. */
    public ?int $payingBillId = null;

    public string $payAmount = '';

    public string $payMethod = 'bank';

    public string $payDate = '';

    public function mount(): void
    {
        $this->billDate = now()->toDateString();
        $this->dueDate = now()->addDays(30)->toDateString();
        $this->payDate = now()->toDateString();
    }

    public function save(RecordVendorBill $recorder): void
    {
        $this->authorize('create', VendorBill::class);

        $this->validate([
            'vendorId' => ['required', 'integer', 'exists:vendors,id'],
            'accountId' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'billDate' => ['required', 'date'],
            'dueDate' => ['required', 'date', 'after_or_equal:billDate'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $bill = $recorder->handle(
            Vendor::findOrFail($this->vendorId),
            Carbon::parse($this->billDate),
            Carbon::parse($this->dueDate),
            $this->description,
            [[
                'account_id' => $this->accountId,
                'description' => $this->description,
                'amount' => $this->amount,
            ]],
        );

        session()->flash('status', __('expenses.vendor_bill_recorded', ['bill' => $bill->bill_no]));

        $this->reset(['amount', 'description']);
        $this->resetPage();
    }

    public function startPayment(int $billId): void
    {
        $this->authorize('create', VendorBill::class);

        $bill = VendorBill::findOrFail($billId);

        $this->payingBillId = $bill->id;
        $this->payAmount = $bill->outstandingAmount();
        $this->payDate = now()->toDateString();
    }

    public function cancelPayment(): void
    {
        $this->reset(['payingBillId', 'payAmount']);
    }

    public function pay(PayVendorBill $payer): void
    {
        $this->authorize('create', VendorBill::class);

        $this->validate([
            'payingBillId' => ['required', 'integer', 'exists:vendor_bills,id'],
            'payAmount' => ['required', 'numeric', 'gt:0'],
            'payMethod' => ['required', 'string', 'in:cash,bank,bkash,nagad'],
            'payDate' => ['required', 'date'],
        ]);

        $bill = VendorBill::findOrFail($this->payingBillId);

        $payment = $payer->handle(
            $bill,
            $this->payAmount,
            PaymentMethod::from($this->payMethod),
            Carbon::parse($this->payDate),
        );

        session()->flash('status', __('expenses.vendor_bill_settled', ['voucher' => $payment->voucher_no]));

        $this->reset(['payingBillId', 'payAmount']);
    }

    public function render(): View
    {
        $this->authorize('viewAny', VendorBill::class);

        return view('livewire.vendor-bill-list', [
            'bills' => VendorBill::with(['vendor', 'items.account'])
                ->orderByDesc('bill_date')
                ->orderByDesc('id')
                ->paginate(20),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(),
            'expenseAccounts' => Account::where('is_postable', true)
                ->where('type', AccountType::Expense)
                ->orderBy('code')
                ->get(),
            'methods' => PaymentMethod::cases(),
        ])->layout('components.layouts.app');
    }
}
