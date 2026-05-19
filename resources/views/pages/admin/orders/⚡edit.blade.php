<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin-app')] #[Title('Edit Order')] class extends Component {
    public Order $order;

    public string $status = 'awaiting_payment';
    public string $billing_cycle = 'monthly';

    public string $amount = '';
    public string $plan_price = '';
    public string $requested_price = '';
    public string $quoted_price = '';
    public string $final_price = '';

    public string $start_date = '';
    public string $end_date = '';

    public string $admin_note = '';

    public function mount(Order $order): void
    {
        $this->order = $order->load(['booking', 'user', 'service.category', 'servicePlan', 'pricingPlan']);

        $this->status = $order->status ?? 'awaiting_payment';
        $this->billing_cycle = $order->billing_cycle ?? 'monthly';

        $this->amount = $order->amount !== null ? (string) $order->amount : '';
        $this->plan_price = $order->plan_price !== null ? (string) $order->plan_price : '';
        $this->requested_price = $order->requested_price !== null ? (string) $order->requested_price : '';
        $this->quoted_price = $order->quoted_price !== null ? (string) $order->quoted_price : '';
        $this->final_price = $order->final_price !== null ? (string) $order->final_price : '';

        $this->start_date = $order->start_date?->format('Y-m-d') ?? '';
        $this->end_date = $order->end_date?->format('Y-m-d') ?? '';

        $this->admin_note = $order->admin_note ?? '';
    }

    protected function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,awaiting_payment,paid,active,completed,cancelled'],
            'billing_cycle' => ['nullable', 'in:one_time,monthly,yearly,custom'],

            'amount' => ['required', 'numeric', 'min:0'],
            'plan_price' => ['nullable', 'numeric', 'min:0'],
            'requested_price' => ['nullable', 'numeric', 'min:0'],
            'quoted_price' => ['nullable', 'numeric', 'min:0'],
            'final_price' => ['nullable', 'numeric', 'min:0'],

            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            'admin_note' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'amount.required' => 'Order amount is required.',
            'amount.numeric' => 'Order amount must be a valid number.',
            'end_date.after_or_equal' => 'End date must be same as or after start date.',
        ];
    }

    public function autoSetDates(): void
    {
        $this->start_date = now()->toDateString();

        $this->end_date = match ($this->billing_cycle) {
            'monthly' => now()->addMonth()->toDateString(),
            'yearly' => now()->addYear()->toDateString(),
            default => '',
        };

        $this->dispatch('toast', message: 'Dates updated based on billing cycle.', type: 'success');
    }

    public function save(): void
    {
        $validated = $this->validate();

        $this->order->update([
            'status' => $validated['status'],
            'billing_cycle' => $validated['billing_cycle'],

            'amount' => $validated['amount'],
            'plan_price' => $validated['plan_price'] !== '' ? $validated['plan_price'] : null,
            'requested_price' => $validated['requested_price'] !== '' ? $validated['requested_price'] : null,
            'quoted_price' => $validated['quoted_price'] !== '' ? $validated['quoted_price'] : null,
            'final_price' => $validated['final_price'] !== '' ? $validated['final_price'] : null,

            'start_date' => $validated['start_date'] ?: null,
            'end_date' => $validated['end_date'] ?: null,

            'admin_note' => $validated['admin_note'] ?: null,
        ]);

        $this->order = $this->order->fresh(['booking', 'user', 'service.category', 'servicePlan', 'pricingPlan']);

        $this->dispatch('toast', message: 'Order updated successfully.', type: 'success');
    }

    public function orderTitle(): string
    {
        if ($this->order->order_type === 'pricing_plan') {
            return $this->order->pricingPlan?->title ?? ($this->order->plan_name ?? 'Pricing Plan Order');
        }

        return $this->order->service?->card_title ?? ($this->order->servicePlan?->name ?? ($this->order->plan_name ?? 'Service Order'));
    }

    public function orderSubtitle(): ?string
    {
        if ($this->order->order_type === 'pricing_plan') {
            return $this->order->pricingPlan?->plan_type ? ucfirst($this->order->pricingPlan->plan_type) : 'Pricing Plan';
        }

        if ($this->order->servicePlan?->name) {
            return $this->order->servicePlan->name;
        }

        return $this->order->service?->category?->name;
    }

    public function statusClass(): string
    {
        return match ($this->order->status) {
            'pending' => 'bg-slate-100 text-slate-700',
            'awaiting_payment' => 'bg-amber-100 text-amber-700',
            'paid' => 'bg-emerald-100 text-emerald-700',
            'active' => 'bg-blue-100 text-blue-700',
            'completed' => 'bg-purple-100 text-purple-700',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
};
?>

<div>
    <div class="mx-auto w-full space-y-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider {{ $this->statusClass() }}">
                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>

                    <span
                        class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                        {{ $order->order_no }}
                    </span>

                    @if ($order->booking?->booking_no)
                        <span
                            class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-blue-700">
                            Booking: {{ $order->booking->booking_no }}
                        </span>
                    @endif
                </div>

                <h1 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Edit Order
                </h1>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    Update order status, billing cycle, price, start date, and end date manually.
                </p>
            </div>

            <a href="{{ route('admin.orders.index') }}" wire:navigate
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Back to Orders
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
            <form wire:submit.prevent="save" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-on-surface">
                        Order Settings
                    </h2>

                    <p class="mt-1 text-sm text-secondary">
                        Change order billing and lifecycle details.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Status
                        </label>

                        <select wire:model="status"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="pending">Pending</option>
                            <option value="awaiting_payment">Awaiting Payment</option>
                            <option value="paid">Paid</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>

                        @error('status')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Billing Cycle
                        </label>

                        <select wire:model="billing_cycle"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="one_time">One-time</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                            <option value="custom">Custom</option>
                        </select>

                        @error('billing_cycle')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Start Date
                        </label>

                        <input type="date" wire:model="start_date"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">

                        @error('start_date')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            End Date
                        </label>

                        <input type="date" wire:model="end_date"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">

                        @error('end_date')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <button type="button" wire:click="autoSetDates"
                            class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                            <span class="material-symbols-outlined text-lg">event_repeat</span>
                            Auto Set Dates From Billing Cycle
                        </button>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Listed Price
                        </label>

                        <input type="number" step="0.01" wire:model="plan_price"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">

                        @error('plan_price')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Requested Price
                        </label>

                        <input type="number" step="0.01" wire:model="requested_price"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">

                        @error('requested_price')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Quoted Price
                        </label>

                        <input type="number" step="0.01" wire:model="quoted_price"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">

                        @error('quoted_price')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Final Price
                        </label>

                        <input type="number" step="0.01" wire:model="final_price"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">

                        @error('final_price')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Order Amount
                        </label>

                        <input type="number" step="0.01" wire:model="amount"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">

                        @error('amount')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-semibold text-on-surface">
                            Admin Note
                        </label>

                        <textarea rows="5" wire:model="admin_note"
                            class="w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="Write internal order note, delivery note, renewal note, or payment note..."></textarea>

                        @error('admin_note')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('admin.orders.index') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg border border-outline-variant bg-white px-5 py-2.5 text-sm font-semibold text-on-surface transition hover:bg-slate-50">
                        Cancel
                    </a>

                    <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save Changes</span>

                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            <span
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>

            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-bold text-on-surface">
                        Order Summary
                    </h3>

                    <div class="mt-5 space-y-4">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-secondary">Order No</p>
                            <p class="mt-1 font-mono text-sm font-bold text-on-surface">
                                {{ $order->order_no }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-wider text-secondary">Item</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">
                                {{ $this->orderTitle() }}
                            </p>

                            @if ($this->orderSubtitle())
                                <p class="mt-1 text-xs text-secondary">
                                    {{ $this->orderSubtitle() }}
                                </p>
                            @endif
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-wider text-secondary">Customer</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">
                                {{ $order->full_name ?: $order->user?->name ?: 'Guest Customer' }}
                            </p>

                            @if ($order->email ?: $order->user?->email)
                                <p class="mt-1 text-xs text-secondary">
                                    {{ $order->email ?: $order->user?->email }}
                                </p>
                            @endif
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-wider text-secondary">Company</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">
                                {{ $order->company_name ?: 'N/A' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-wider text-secondary">Current Period</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">
                                {{ $order->start_date?->format('M d, Y') ?? 'N/A' }}
                                -
                                {{ $order->end_date?->format('M d, Y') ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-blue-100 bg-blue-50 p-6">
                    <div class="flex gap-3">
                        <span class="material-symbols-outlined text-blue-700">info</span>

                        <div>
                            <h4 class="font-bold text-blue-900">
                                Date Rules
                            </h4>

                            <p class="mt-2 text-sm leading-6 text-blue-800">
                                Monthly orders should normally have a 1 month period. Yearly orders should normally have
                                a 1 year period. You can manually change both dates anytime.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
