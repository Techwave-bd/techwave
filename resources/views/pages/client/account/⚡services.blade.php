<?php

use App\Models\Booking;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My Services')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $activeTab = 'services';

    public function mount(): void
    {
        abort_if(!Auth::check(), 403);

        $tab = request()->query('tab', 'services');

        $this->activeTab = in_array($tab, ['services', 'plans'], true) ? $tab : 'services';
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['services', 'plans'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->resetPage();
    }

    public function formatDate($date): string
    {
        if (!$date) {
            return 'N/A';
        }

        return Carbon::parse($date)->format('d M Y');
    }

    public function statusClass(?string $status): string
    {
        return match ($status) {
            'paid' => 'border-emerald-300/20 bg-emerald-400/10 text-emerald-200',
            'active' => 'border-blue-300/20 bg-blue-400/10 text-blue-200',
            'completed' => 'border-purple-300/20 bg-purple-400/10 text-purple-200',
            'awaiting_payment' => 'border-amber-300/20 bg-amber-400/10 text-amber-200',
            'pending' => 'border-slate-300/20 bg-slate-400/10 text-slate-200',
            'cancelled' => 'border-rose-300/20 bg-rose-400/10 text-rose-200',
            default => 'border-blue-300/20 bg-blue-400/10 text-blue-200',
        };
    }

    public function bookingStatusClass(?string $status): string
    {
        return match ($status) {
            'pending' => 'border-amber-300/20 bg-amber-400/10 text-amber-200',
            'quoted' => 'border-blue-300/20 bg-blue-400/10 text-blue-200',
            'accepted' => 'border-emerald-300/20 bg-emerald-400/10 text-emerald-200',
            'converted' => 'border-purple-300/20 bg-purple-400/10 text-purple-200',
            'rejected' => 'border-rose-300/20 bg-rose-400/10 text-rose-200',
            'cancelled' => 'border-slate-300/20 bg-slate-400/10 text-slate-200',
            default => 'border-blue-300/20 bg-blue-400/10 text-blue-200',
        };
    }

    public function statusLabel(?string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status ?: 'pending'));
    }

    public function billingLabel(?string $billing): string
    {
        return match ($billing) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'one_time' => 'One-time',
            'custom' => 'Custom',
            default => 'Negotiable',
        };
    }

    public function displayAmount($item): string
    {
        $amount = $item->amount ?? ($item->final_price ?? ($item->quoted_price ?? ($item->requested_price ?? ($item->plan_price ?? 0))));

        if (!$amount || (float) $amount <= 0) {
            return 'Negotiable';
        }

        $currency = ($item->currency ?? 'BDT') === 'BDT' ? '৳' : $item->currency . ' ';

        return $currency . number_format((float) $amount, 2);
    }

    public function orderTitle($order): string
    {
        if ($order->order_type === 'pricing_plan') {
            return $order->pricingPlan?->title ?? ($order->plan_name ?? 'IT Plan');
        }

        return $order->service?->card_title ?? ($order->servicePlan?->name ?? ($order->plan_name ?? 'Service'));
    }

    public function orderDescription($order): ?string
    {
        if ($order->order_type === 'pricing_plan') {
            return $order->pricingPlan?->description;
        }

        return $order->servicePlan?->description ?? ($order->service?->short_description ?? null);
    }

    public function bookingTitle($booking): string
    {
        if ($booking->booking_type === 'pricing_plan') {
            return $booking->pricingPlan?->title ?? ($booking->plan_name ?? 'IT Plan Booking');
        }

        return $booking->service?->card_title ?? ($booking->servicePlan?->name ?? ($booking->plan_name ?? 'Service Booking'));
    }

    public function bookingDescription($booking): ?string
    {
        if ($booking->booking_type === 'pricing_plan') {
            return $booking->pricingPlan?->description;
        }

        return $booking->servicePlan?->description ?? ($booking->service?->short_description ?? null);
    }

    public function updatePlanUrl($order): string
    {
        $pricingPlan = $order->pricingPlan;

        if (!$pricingPlan) {
            return Route::has('client.pricing') ? route('client.pricing') : url('/pricing');
        }

        $billing = in_array($order->billing_cycle, ['monthly', 'yearly'], true) ? $order->billing_cycle : 'monthly';

        if (Route::has('client.checkout.pricing')) {
            return route('client.checkout.pricing', [
                'pricingPlan' => $pricingPlan,
                'billing' => $billing,
            ]);
        }

        return url('/checkout/pricing/' . $pricingPlan->id . '?billing=' . $billing);
    }

    public function with(): array
    {
        $userId = Auth::id();

        $serviceOrders = Order::query()
            ->with(['service.category', 'servicePlan', 'booking'])
            ->where('user_id', $userId)
            ->where('order_type', 'service')
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->where('order_no', 'like', '%' . $this->search . '%')
                        ->orWhere('billing_cycle', 'like', '%' . $this->search . '%')
                        ->orWhere('status', 'like', '%' . $this->search . '%')
                        ->orWhere('plan_name', 'like', '%' . $this->search . '%')
                        ->orWhere('message', 'like', '%' . $this->search . '%')
                        ->orWhere('user_note', 'like', '%' . $this->search . '%')
                        ->orWhereHas('service', function ($serviceQuery) {
                            $serviceQuery->where('card_title', 'like', '%' . $this->search . '%')->orWhere('detail_title', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('servicePlan', function ($servicePlanQuery) {
                            $servicePlanQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->latest()
            ->paginate(8);

        $pricingOrders = Order::query()
            ->with(['pricingPlan', 'booking'])
            ->where('user_id', $userId)
            ->where('order_type', 'pricing_plan')
            ->latest()
            ->get();

        $pricingBookings = Booking::query()
            ->with(['pricingPlan', 'order'])
            ->where('user_id', $userId)
            ->where('booking_type', 'pricing_plan')
            ->whereDoesntHave('order')
            ->latest()
            ->get();

        $totalServices = Order::query()->where('user_id', $userId)->where('order_type', 'service')->count();

        $activeServices = Order::query()
            ->where('user_id', $userId)
            ->where('order_type', 'service')
            ->whereIn('status', ['paid', 'active', 'completed'])
            ->count();

        $pendingServices = Order::query()
            ->where('user_id', $userId)
            ->where('order_type', 'service')
            ->whereIn('status', ['pending', 'awaiting_payment'])
            ->count();

        $totalPlans = $pricingOrders->count() + $pricingBookings->count();

        $activePlans = $pricingOrders->whereIn('status', ['paid', 'active', 'completed'])->count();

        $pendingPlans = $pricingOrders->whereIn('status', ['pending', 'awaiting_payment'])->count() + $pricingBookings->whereIn('status', ['pending', 'quoted', 'accepted'])->count();

        return [
            'serviceOrders' => $serviceOrders,

            'pricingOrders' => $pricingOrders,
            'pricingBookings' => $pricingBookings,

            'totalServices' => $totalServices,
            'activeServices' => $activeServices,
            'pendingServices' => $pendingServices,

            'totalPlans' => $totalPlans,
            'activePlans' => $activePlans,
            'pendingPlans' => $pendingPlans,
        ];
    }
};
?>

<div x-data="{
    sidebarOpen: false,
    showToast: {{ session('success') ? 'true' : 'false' }}
}" class="relative min-h-screen text-white">
    @if (session('success'))
        <div x-show="showToast" x-init="setTimeout(() => showToast = false, 4500)" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-3 scale-95"
            class="fixed right-5 top-5 z-100 max-w-md rounded-3xl border border-emerald-300/20 bg-emerald-500/15 p-4 text-emerald-50 shadow-[0_18px_60px_rgba(0,0,0,0.35)] backdrop-blur-2xl">
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-emerald-300/20 bg-emerald-400/15 text-emerald-200">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>

                <div class="min-w-0">
                    <p class="font-bold text-white">Success</p>
                    <p class="mt-1 text-sm leading-6 text-emerald-50/80">
                        {{ session('success') }}
                    </p>
                </div>

                <button type="button" @click="showToast = false"
                    class="ml-2 rounded-xl p-1 text-emerald-100/70 transition hover:bg-white/10 hover:text-white">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>
    @endif

    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">
        <div
            class="rounded-[34px] border border-white/10 bg-white/6 shadow-[0_20px_80px_rgba(0,0,0,0.22)] backdrop-blur-2xl">
            <div class="flex min-h-[calc(100vh-3rem)]">

                <div x-show="sidebarOpen" x-transition.opacity
                    class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"
                    style="display:none;">
                </div>

                <livewire:shared.user-sidebar />

                <div class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">

                    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <button @click="sidebarOpen = true"
                                class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-white shadow-[0_10px_30px_rgba(0,0,0,0.18)] backdrop-blur-xl transition hover:bg-white/12 lg:hidden">
                                <span class="material-symbols-outlined">menu</span>
                            </button>

                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Client Dashboard</p>
                                <h1 class="mt-1 text-2xl font-bold text-white sm:text-3xl">My Services</h1>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/8 px-4 py-3 backdrop-blur-xl">
                            <span class="material-symbols-outlined text-cyan-200">workspace_premium</span>
                            <div>
                                <p class="text-xs text-blue-100/45">Services & IT Plans</p>
                                <p class="text-sm font-semibold text-white">
                                    {{ $totalServices + $totalPlans }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 grid gap-5 md:grid-cols-3">
                        <div
                            class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Total Items</p>
                            <h3 class="mt-5 text-4xl font-bold text-white">
                                {{ str_pad($totalServices + $totalPlans, 2, '0', STR_PAD_LEFT) }}
                            </h3>
                            <p class="mt-2 text-sm text-blue-100/60">Services and IT plans</p>
                        </div>

                        <div
                            class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Active / Paid</p>
                            <h3 class="mt-5 text-4xl font-bold text-emerald-300">
                                {{ str_pad($activeServices + $activePlans, 2, '0', STR_PAD_LEFT) }}
                            </h3>
                            <p class="mt-2 text-sm text-blue-100/60">Running services and paid plans</p>
                        </div>

                        <div
                            class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Pending</p>
                            <h3 class="mt-5 text-4xl font-bold text-amber-300">
                                {{ str_pad($pendingServices + $pendingPlans, 2, '0', STR_PAD_LEFT) }}
                            </h3>
                            <p class="mt-2 text-sm text-blue-100/60">Waiting for process or payment</p>
                        </div>
                    </div>

                    <div
                        class="mb-6 rounded-[28px] border border-white/10 bg-white/8 p-5 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">

                        <div class="mb-5 grid gap-3 sm:grid-cols-2">
                            <button type="button" wire:click="setTab('services')"
                                class="group flex items-center justify-between rounded-2xl border px-5 py-4 text-left transition
                                {{ $activeTab === 'services'
                                    ? 'border-cyan-300/50 bg-cyan-400/10 text-white shadow-lg shadow-cyan-500/10'
                                    : 'border-white/10 bg-white/6 text-blue-100/65 hover:border-white/20 hover:bg-white/10 hover:text-white' }}">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl border
                                        {{ $activeTab === 'services'
                                            ? 'border-cyan-300/30 bg-cyan-300/15 text-cyan-200'
                                            : 'border-white/10 bg-white/8 text-blue-100/55' }}">
                                        <span class="material-symbols-outlined">design_services</span>
                                    </div>

                                    <div>
                                        <p class="font-bold">Service Orders</p>
                                        <p class="mt-1 text-xs text-blue-100/45">
                                            {{ $totalServices }} service{{ $totalServices > 1 ? 's' : '' }}
                                        </p>
                                    </div>
                                </div>

                                <span
                                    class="material-symbols-outlined transition {{ $activeTab === 'services' ? 'text-cyan-200' : 'text-blue-100/35 group-hover:text-white' }}">
                                    arrow_forward
                                </span>
                            </button>

                            <button type="button" wire:click="setTab('plans')"
                                class="group flex items-center justify-between rounded-2xl border px-5 py-4 text-left transition
                                {{ $activeTab === 'plans'
                                    ? 'border-blue-300/50 bg-blue-400/10 text-white shadow-lg shadow-blue-500/10'
                                    : 'border-white/10 bg-white/6 text-blue-100/65 hover:border-white/20 hover:bg-white/10 hover:text-white' }}">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl border
                                        {{ $activeTab === 'plans'
                                            ? 'border-blue-300/30 bg-blue-300/15 text-blue-200'
                                            : 'border-white/10 bg-white/8 text-blue-100/55' }}">
                                        <span class="material-symbols-outlined">workspace_premium</span>
                                    </div>

                                    <div>
                                        <p class="font-bold">IT Plans</p>
                                        <p class="mt-1 text-xs text-blue-100/45">
                                            {{ $totalPlans }} plan{{ $totalPlans > 1 ? 's' : '' }}
                                        </p>
                                    </div>
                                </div>

                                <span
                                    class="material-symbols-outlined transition {{ $activeTab === 'plans' ? 'text-blue-200' : 'text-blue-100/35 group-hover:text-white' }}">
                                    arrow_forward
                                </span>
                            </button>
                        </div>

                        @if ($activeTab === 'services')
                            <div class="grid gap-4 lg:grid-cols-[1fr_220px_auto]">
                                <div class="relative">
                                    <input type="text" wire:model.live.debounce.400ms="search"
                                        placeholder="Search service, status, billing cycle..."
                                        class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 pl-12 pr-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40">

                                    <span
                                        class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-blue-100/45">
                                        search
                                    </span>
                                </div>

                                <select wire:model.live="status"
                                    class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white outline-none backdrop-blur-xl focus:border-cyan-300/40">
                                    <option value="" class="bg-slate-900">All Status</option>
                                    <option value="awaiting_payment" class="bg-slate-900">Awaiting Payment</option>
                                    <option value="paid" class="bg-slate-900">Paid</option>
                                    <option value="active" class="bg-slate-900">Active</option>
                                    <option value="completed" class="bg-slate-900">Completed</option>
                                    <option value="cancelled" class="bg-slate-900">Cancelled</option>
                                </select>

                                <button type="button" wire:click="clearFilters"
                                    class="inline-flex h-12 items-center justify-center rounded-2xl border border-white/10 bg-white/8 px-5 text-sm font-semibold text-white transition hover:bg-white/12">
                                    Clear
                                </button>
                            </div>
                        @else
                            <div
                                class="rounded-2xl border border-blue-300/15 bg-blue-400/10 p-4 text-sm leading-6 text-blue-50/75">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-blue-200">info</span>
                                    <p>
                                        This tab shows your IT plan orders and pending plan booking requests. Use Update
                                        Plan to select a new pricing plan.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($activeTab === 'plans')
                        <div
                            class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">IT Plans</p>
                                    <h2 class="mt-2 text-2xl font-bold text-white">Your plan orders & bookings</h2>
                                </div>

                                @if (Route::has('client.pricing'))
                                    <a href="{{ route('client.pricing') }}" wire:navigate
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-blue-300/20 bg-blue-400/10 px-5 py-3 text-sm font-semibold text-blue-100 transition hover:bg-blue-400/15">
                                        <span class="material-symbols-outlined text-lg">add</span>
                                        Browse Plans
                                    </a>
                                @endif
                            </div>

                            @if ($pricingOrders->count() || $pricingBookings->count())
                                <div class="grid gap-5 lg:grid-cols-2">

                                    @foreach ($pricingOrders as $order)
                                        @php
                                            $plan = $order->pricingPlan;
                                            $planTitle = $this->orderTitle($order);
                                            $planDescription = $this->orderDescription($order);
                                        @endphp

                                        <div
                                            class="group rounded-[26px] border border-white/10 bg-white/7 p-5 shadow-[0_14px_40px_rgba(0,0,0,0.16)] transition hover:-translate-y-1 hover:bg-white/10">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex items-start gap-4">
                                                    <div
                                                        class="flex h-13 w-13 shrink-0 items-center justify-center rounded-2xl border border-blue-300/20 bg-blue-400/10 text-blue-200">
                                                        <span
                                                            class="material-symbols-outlined">workspace_premium</span>
                                                    </div>

                                                    <div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h3 class="text-lg font-bold text-white">
                                                                {{ $planTitle }}
                                                            </h3>

                                                            <span
                                                                class="rounded-full border border-blue-300/20 bg-blue-400/10 px-3 py-1 text-xs font-semibold text-blue-200">
                                                                Order
                                                            </span>
                                                        </div>

                                                        @if ($planDescription)
                                                            <p
                                                                class="mt-2 line-clamp-2 text-sm leading-6 text-blue-100/55">
                                                                {{ $planDescription }}
                                                            </p>
                                                        @else
                                                            <p class="mt-2 text-sm leading-6 text-blue-100/55">
                                                                Plan details are available in your account.
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>

                                                <span
                                                    class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold {{ $this->statusClass($order->status) }}">
                                                    {{ $this->statusLabel($order->status) }}
                                                </span>
                                            </div>

                                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Order No</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $order->order_no ?? 'N/A' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Billing Cycle</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->billingLabel($order->billing_cycle) }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Amount</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->displayAmount($order) }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Date</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->formatDate($order->created_at) }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                                <a href="{{ $this->updatePlanUrl($order) }}" wire:navigate
                                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl border border-cyan-300/20 bg-cyan-400/10 px-4 py-3 text-sm font-bold text-cyan-100 transition hover:bg-cyan-400/15">
                                                    <span class="material-symbols-outlined text-lg">upgrade</span>
                                                    Update Plan
                                                </a>

                                                @if ($order->email)
                                                    <a href="mailto:{{ $order->email }}"
                                                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/8 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/12">
                                                        <span
                                                            class="material-symbols-outlined text-lg">support_agent</span>
                                                        Contact Support
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach ($pricingBookings as $booking)
                                        @php
                                            $plan = $booking->pricingPlan;
                                            $planTitle = $this->bookingTitle($booking);
                                            $planDescription = $this->bookingDescription($booking);
                                        @endphp

                                        <div
                                            class="group rounded-[26px] border border-white/10 bg-white/7 p-5 shadow-[0_14px_40px_rgba(0,0,0,0.16)] transition hover:-translate-y-1 hover:bg-white/10">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex items-start gap-4">
                                                    <div
                                                        class="flex h-13 w-13 shrink-0 items-center justify-center rounded-2xl border border-amber-300/20 bg-amber-400/10 text-amber-200">
                                                        <span class="material-symbols-outlined">contract</span>
                                                    </div>

                                                    <div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h3 class="text-lg font-bold text-white">
                                                                {{ $planTitle }}
                                                            </h3>

                                                            <span
                                                                class="rounded-full border border-amber-300/20 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">
                                                                Booking
                                                            </span>
                                                        </div>

                                                        @if ($planDescription)
                                                            <p
                                                                class="mt-2 line-clamp-2 text-sm leading-6 text-blue-100/55">
                                                                {{ $planDescription }}
                                                            </p>
                                                        @else
                                                            <p class="mt-2 text-sm leading-6 text-blue-100/55">
                                                                Booking details are available in your account.
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>

                                                <span
                                                    class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold {{ $this->bookingStatusClass($booking->status) }}">
                                                    {{ $this->statusLabel($booking->status) }}
                                                </span>
                                            </div>

                                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Booking No</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $booking->booking_no ?? 'N/A' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Billing Cycle</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->billingLabel($booking->billing_cycle) }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Listed Price</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->displayAmount($booking) }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">
                                                        {{ $booking->quoted_price ? 'Quoted Price' : 'Requested Price' }}
                                                    </p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        @if ($booking->quoted_price)
                                                            ৳{{ number_format((float) $booking->quoted_price, 2) }}
                                                        @elseif ($booking->requested_price)
                                                            ৳{{ number_format((float) $booking->requested_price, 2) }}
                                                        @else
                                                            Negotiable
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>

                                            @if ($booking->user_note)
                                                <div class="mt-4 rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Your Requirement</p>
                                                    <p class="mt-2 text-sm leading-6 text-blue-50/80">
                                                        {{ $booking->user_note }}
                                                    </p>
                                                </div>
                                            @endif

                                            @if ($booking->admin_note)
                                                <div
                                                    class="mt-4 rounded-2xl border border-cyan-300/15 bg-cyan-400/10 p-4">
                                                    <p class="text-xs text-cyan-100/60">Admin Note</p>
                                                    <p class="mt-2 text-sm leading-6 text-cyan-50/90">
                                                        {{ $booking->admin_note }}
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div
                                    class="flex flex-col items-center justify-center rounded-[26px] border border-dashed border-white/15 bg-white/5 px-6 py-12 text-center">
                                    <div
                                        class="flex h-16 w-16 items-center justify-center rounded-3xl border border-blue-300/20 bg-blue-400/10 text-blue-200">
                                        <span class="material-symbols-outlined text-4xl">workspace_premium</span>
                                    </div>

                                    <h3 class="mt-5 text-xl font-bold text-white">No IT plans found</h3>

                                    <p class="mt-2 max-w-md text-sm leading-7 text-blue-100/55">
                                        Your purchased plans and booking requests will appear here.
                                    </p>

                                    @if (Route::has('client.pricing'))
                                        <a href="{{ route('client.pricing') }}" wire:navigate
                                            class="mt-5 inline-flex items-center justify-center gap-2 rounded-full border border-blue-300/20 bg-blue-400/10 px-5 py-3 text-sm font-semibold text-blue-100 transition hover:bg-blue-400/15">
                                            <span class="material-symbols-outlined text-lg">add</span>
                                            Browse Plans
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($activeTab === 'services')
                        <div
                            class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Service Orders</p>
                                    <h2 class="mt-2 text-2xl font-bold text-white">Your service list</h2>
                                </div>
                            </div>

                            @if ($serviceOrders->count())
                                <div class="grid gap-5 lg:grid-cols-2">
                                    @foreach ($serviceOrders as $order)
                                        @php
                                            $serviceName = $this->orderTitle($order);
                                            $serviceDescription = $this->orderDescription($order);
                                        @endphp

                                        <div
                                            class="group rounded-[26px] border border-white/10 bg-white/7 p-5 shadow-[0_14px_40px_rgba(0,0,0,0.16)] transition hover:-translate-y-1 hover:bg-white/10">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex items-start gap-4">
                                                    <div
                                                        class="flex h-13 w-13 shrink-0 items-center justify-center rounded-2xl border border-cyan-300/20 bg-cyan-400/10 text-cyan-200">
                                                        <span class="material-symbols-outlined">design_services</span>
                                                    </div>

                                                    <div>
                                                        <h3 class="text-lg font-bold text-white">
                                                            {{ $serviceName }}
                                                        </h3>

                                                        @if ($serviceDescription)
                                                            <p
                                                                class="mt-2 line-clamp-2 text-sm leading-6 text-blue-100/55">
                                                                {{ $serviceDescription }}
                                                            </p>
                                                        @else
                                                            <p class="mt-2 text-sm leading-6 text-blue-100/55">
                                                                Service details are available in your account.
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>

                                                <span
                                                    class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold {{ $this->statusClass($order->status) }}">
                                                    {{ $this->statusLabel($order->status) }}
                                                </span>
                                            </div>

                                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Order No</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $order->order_no ?? 'N/A' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Price</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->displayAmount($order) }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Billing Cycle</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->billingLabel($order->billing_cycle) }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Created Date</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $this->formatDate($order->created_at) }}
                                                    </p>
                                                </div>
                                            </div>

                                            @if ($order->admin_note || $order->message)
                                                <div class="mt-4 rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-blue-100/40">Notes</p>
                                                    <p class="mt-2 text-sm leading-6 text-blue-50/80">
                                                        {{ $order->admin_note ?: $order->message }}
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-6">
                                    {{ $serviceOrders->links() }}
                                </div>
                            @else
                                <div
                                    class="flex flex-col items-center justify-center rounded-[26px] border border-dashed border-white/15 bg-white/5 px-6 py-14 text-center">
                                    <div
                                        class="flex h-16 w-16 items-center justify-center rounded-3xl border border-cyan-300/20 bg-cyan-400/10 text-cyan-200">
                                        <span class="material-symbols-outlined text-4xl">inventory_2</span>
                                    </div>

                                    <h3 class="mt-5 text-xl font-bold text-white">No services found</h3>

                                    <p class="mt-2 max-w-md text-sm leading-7 text-blue-100/55">
                                        You do not have any confirmed service orders yet. Once admin confirms your
                                        booking, it will appear here.
                                    </p>

                                    @if ($search || $status)
                                        <button type="button" wire:click="clearFilters"
                                            class="mt-5 inline-flex items-center justify-center rounded-full border border-white/10 bg-white/8 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/12">
                                            Clear Filters
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>
