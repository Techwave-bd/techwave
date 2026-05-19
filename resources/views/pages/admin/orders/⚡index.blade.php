<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Orders')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public string $orderType = 'all';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedOrderType(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function orders()
    {
        $search = trim($this->search);

        return Order::query()
            ->with(['booking', 'user', 'service.category', 'servicePlan', 'pricingPlan'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_no', 'like', '%' . $search . '%')
                        ->orWhere('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%')
                        ->orWhere('company_phone', 'like', '%' . $search . '%')
                        ->orWhere('company_email', 'like', '%' . $search . '%')
                        ->orWhere('plan_name', 'like', '%' . $search . '%')
                        ->orWhere('currency', 'like', '%' . $search . '%')
                        ->orWhereHas('booking', function ($bookingQuery) use ($search) {
                            $bookingQuery->where('booking_no', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('service', function ($serviceQuery) use ($search) {
                            $serviceQuery->where('card_title', 'like', '%' . $search . '%')->orWhere('detail_title', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('servicePlan', function ($servicePlanQuery) use ($search) {
                            $servicePlanQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('pricingPlan', function ($pricingPlanQuery) use ($search) {
                            $pricingPlanQuery->where('title', 'like', '%' . $search . '%')->orWhere('plan_type', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($this->status !== 'all', function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->orderType !== 'all', function ($query) {
                $query->where('order_type', $this->orderType);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function markAsAwaitingPayment(int $orderId): void
    {
        Order::query()
            ->findOrFail($orderId)
            ->update([
                'status' => 'awaiting_payment',
            ]);

        $this->dispatch('toast', message: 'Order marked as awaiting payment.', type: 'success');
    }

    public function markAsPaid(int $orderId): void
    {
        Order::query()
            ->findOrFail($orderId)
            ->update([
                'status' => 'paid',
            ]);

        $this->dispatch('toast', message: 'Order marked as paid.', type: 'success');
    }

    public function markAsActive(int $orderId): void
    {
        Order::query()
            ->findOrFail($orderId)
            ->update([
                'status' => 'active',
            ]);

        $this->dispatch('toast', message: 'Order marked as active.', type: 'success');
    }

    public function markAsCompleted(int $orderId): void
    {
        Order::query()
            ->findOrFail($orderId)
            ->update([
                'status' => 'completed',
            ]);

        $this->dispatch('toast', message: 'Order marked as completed.', type: 'success');
    }

    public function markAsCancelled(int $orderId): void
    {
        Order::query()
            ->findOrFail($orderId)
            ->update([
                'status' => 'cancelled',
            ]);

        $this->dispatch('toast', message: 'Order cancelled.', type: 'success');
    }

    public function delete(int $orderId): void
    {
        Order::query()->findOrFail($orderId)->delete();

        $this->dispatch('toast', message: 'Order deleted successfully.', type: 'success');
    }

    public function orderTitle($order): string
    {
        if ($order->order_type === 'pricing_plan') {
            return $order->pricingPlan?->title ?? ($order->plan_name ?? 'Pricing Plan Order');
        }

        return $order->service?->card_title ?? ($order->servicePlan?->name ?? ($order->plan_name ?? 'Service Order'));
    }

    public function orderSubtitle($order): ?string
    {
        if ($order->order_type === 'pricing_plan') {
            return $order->pricingPlan?->plan_type ? ucfirst($order->pricingPlan->plan_type) : 'Pricing Plan';
        }

        if ($order->servicePlan?->name) {
            return $order->servicePlan->name;
        }

        return $order->service?->category?->name;
    }

    public function displayBilling($order): string
    {
        return match ($order->billing_cycle) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'one_time' => 'One-time',
            'custom' => 'Custom',
            default => 'Negotiable',
        };
    }

    public function displayAmount($order): string
    {
        $amount = $order->amount ?? ($order->final_price ?? ($order->quoted_price ?? ($order->requested_price ?? $order->plan_price)));

        if (!$amount || (float) $amount <= 0) {
            return 'Negotiable';
        }

        $currency = $order->currency === 'BDT' || blank($order->currency) ? '৳' : $order->currency . ' ';

        return $currency . number_format((float) $amount, 2);
    }

    public function statusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
};
?>

<div>
    <div class="mx-auto w-full max-w-7xl space-y-stack-lg">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Orders
                </h2>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    Manage all confirmed customer orders from service bookings and pricing plan bookings.
                </p>
            </div>

            <div class="flex w-full flex-col gap-4 lg:w-auto lg:flex-row lg:items-center">
                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 lg:max-w-3xl">
                    <div class="relative">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            search
                        </span>

                        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search order..."
                            class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-label-md font-label-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    </div>

                    <div class="relative">
                        <select wire:model.live="orderType"
                            class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="all">All Types</option>
                            <option value="service">Service Order</option>
                            <option value="pricing_plan">Pricing Plan</option>
                        </select>

                        <span
                            class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            expand_more
                        </span>
                    </div>

                    <div class="relative">
                        <select wire:model.live="status"
                            class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="awaiting_payment">Awaiting Payment</option>
                            <option value="paid">Paid</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>

                        <span
                            class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            expand_more
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/50">
                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Order
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Customer
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Service / Plan
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Amount
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Status
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Created At
                            </th>

                            <th
                                class="px-6 py-4 text-right text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->orders() as $order)
                            <tr wire:key="order-{{ $order->id }}" class="transition-colors hover:bg-slate-50/80">
                                <td class="px-6 py-4">
                                    <div>
                                        <span class="block text-label-md font-label-md text-on-surface">
                                            {{ $order->order_no }}
                                        </span>

                                        @if ($order->booking?->booking_no)
                                            <span class="block font-mono text-[11px] text-slate-400">
                                                Booking: {{ $order->booking->booking_no }}
                                            </span>
                                        @endif

                                        <span @class([
                                            'mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                            'bg-cyan-100 text-cyan-700' => $order->order_type === 'service',
                                            'bg-purple-100 text-purple-700' => $order->order_type === 'pricing_plan',
                                        ])>
                                            {{ $order->order_type === 'pricing_plan' ? 'Pricing Plan' : 'Service' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="block text-body-sm text-on-surface">
                                        {{ $order->full_name ?: $order->user?->name ?: 'Guest Customer' }}
                                    </span>

                                    @if ($order->email ?: $order->user?->email)
                                        <a href="mailto:{{ $order->email ?: $order->user?->email }}"
                                            class="block text-xs text-slate-400 transition hover:text-primary">
                                            {{ $order->email ?: $order->user?->email }}
                                        </a>
                                    @endif

                                    @if ($order->phone)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->phone) }}"
                                            class="block text-xs text-slate-400 transition hover:text-primary">
                                            {{ $order->phone }}
                                        </a>
                                    @endif

                                    @if ($order->company_name)
                                        <span class="mt-1 block text-xs text-secondary">
                                            {{ $order->company_name }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <span class="block text-body-sm text-on-surface">
                                        {{ $this->orderTitle($order) }}
                                    </span>

                                    @if ($this->orderSubtitle($order))
                                        <span class="block text-xs capitalize text-secondary">
                                            {{ $this->orderSubtitle($order) }}
                                        </span>
                                    @endif

                                    <p class="block text-xs text-secondary">
                                        <span class="font-semibold">Cycle:</span> {{ $this->displayBilling($order) }}
                                    </p>
                                </td>

                                <td class="px-6 py-4">
                                    {{-- <span class="block font-mono text-body-sm text-on-surface">
                                        {{ $this->displayAmount($order) }}
                                    </span> --}}

                                    @if ($order->plan_price)
                                        <span class="block text-xs text-slate-400">
                                            Listed: ৳{{ number_format((float) $order->plan_price, 2) }}
                                        </span>
                                    @endif

                                    @if ($order->requested_price)
                                        <span class="block text-xs text-cyan-600">
                                            Requested: ৳{{ number_format((float) $order->requested_price, 2) }}
                                        </span>
                                    @endif

                                    @if ($order->quoted_price)
                                        <span class="block text-xs text-emerald-600">
                                            Quoted: ৳{{ number_format((float) $order->quoted_price, 2) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider',
                                        'bg-slate-100 text-slate-600' => $order->status === 'pending',
                                        'bg-amber-100 text-amber-700' => $order->status === 'awaiting_payment',
                                        'bg-emerald-100 text-emerald-700' => $order->status === 'paid',
                                        'bg-blue-100 text-blue-700' => $order->status === 'active',
                                        'bg-purple-100 text-purple-700' => $order->status === 'completed',
                                        'bg-red-100 text-red-700' => $order->status === 'cancelled',
                                    ])>
                                        {{ $this->statusLabel($order->status) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-body-sm text-secondary">
                                    {{ $order->created_at?->format('M d, Y h:i A') }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button type="button" @click="open = !open"
                                            class="text-slate-400 transition-colors hover:text-primary">
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>

                                        <div x-cloak x-show="open" @click.outside="open = false" x-transition
                                            class="absolute right-0 z-20 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">

                                            <a href="{{ route('admin.orders.edit', $order) }}" wire:navigate
                                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                Edit Order
                                            </a>

                                            @if ($order->status !== 'awaiting_payment')
                                                <button type="button"
                                                    wire:click="markAsAwaitingPayment({{ $order->id }})"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">pending</span>
                                                    Mark Awaiting Payment
                                                </button>
                                            @endif

                                            @if ($order->status !== 'paid')
                                                <button type="button" wire:click="markAsPaid({{ $order->id }})"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">task_alt</span>
                                                    Mark Paid
                                                </button>
                                            @endif

                                            @if ($order->status !== 'active')
                                                <button type="button" wire:click="markAsActive({{ $order->id }})"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span
                                                        class="material-symbols-outlined text-[18px]">play_circle</span>
                                                    Mark Active
                                                </button>
                                            @endif

                                            @if ($order->status !== 'completed')
                                                <button type="button"
                                                    wire:click="markAsCompleted({{ $order->id }})"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">verified</span>
                                                    Mark Completed
                                                </button>
                                            @endif

                                            @if ($order->status !== 'cancelled')
                                                <button type="button"
                                                    wire:click="markAsCancelled({{ $order->id }})"
                                                    wire:confirm="Are you sure you want to cancel this order?"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">cancel</span>
                                                    Mark Cancelled
                                                </button>
                                            @endif

                                            @if ($order->email ?: $order->user?->email)
                                                <div class="my-1 border-t border-slate-100"></div>

                                                <a href="mailto:{{ $order->email ?: $order->user?->email }}"
                                                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span
                                                        class="material-symbols-outlined text-[18px]">outgoing_mail</span>
                                                    Email Customer
                                                </a>
                                            @endif

                                            <div class="my-1 border-t border-slate-100"></div>

                                            <button type="button" wire:click="delete({{ $order->id }})"
                                                wire:confirm="Are you sure you want to delete this order?"
                                                @click="open = false"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 cursor-pointer">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div
                                            class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <span class="material-symbols-outlined">receipt_long</span>
                                        </div>

                                        <h3 class="text-base font-semibold text-on-surface">
                                            No orders found
                                        </h3>

                                        <p class="mt-1 text-sm text-secondary">
                                            Confirmed customer orders will appear here after booking approval.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/30 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-body-sm font-body-sm text-secondary">Per page</span>

                    <select wire:model.live="perPage"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 focus:border-primary focus:ring-primary/10">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div>
                    {{ $this->orders()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
