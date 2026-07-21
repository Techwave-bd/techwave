<?php

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServicePlan;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Checkout')] class extends Component {
    public Service $service;
    public ServicePlan $plan;
    public SiteSetting $siteSetting;

    #[Url(as: 'billing')]
    public string $billingCycle = 'monthly';

    public array $selectedAddonIds = [];
    public array $addonPrices = [];

    public string $full_name = '';
    public string $phone = '';
    public string $email = '';
    public string $company_name = '';
    public string $company_phone = '';
    public string $customer_address = '';
    public string $sender_bkash = '';
    public string $transaction_id = '';

    public function mount(string $slug, ServicePlan $plan): void
    {
        $this->siteSetting = SiteSetting::current();

        $this->service = Service::query()->with('activePlans')->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $this->plan = $plan->load('addons');

        if (!$this->service->activePlans->contains('id', $plan->id)) {
            abort(404);
        }

        if (!in_array($this->billingCycle, ['monthly', 'yearly', 'one_time'], true)) {
            $this->billingCycle = $this->resolveBillingCycle();
        }

        $this->fillUserAndCompanyInfo();
    }

    private function resolveBillingCycle(): string
    {
        $hasMonthly = $this->planHasMonthly();
        $hasYearly = $this->planHasYearly();
        $hasOneTime = $this->planHasOneTime();

        if ($hasMonthly) {
            return 'monthly';
        }
        if ($hasYearly) {
            return 'yearly';
        }
        if ($hasOneTime) {
            return 'one_time';
        }

        return 'custom';
    }

    public function planHasMonthly(): bool
    {
        return !empty($this->plan->has_monthly_price) && !empty($this->plan->monthly_price) && (float) $this->plan->monthly_price > 0;
    }

    public function planHasYearly(): bool
    {
        return !empty($this->plan->has_yearly_price) && !empty($this->plan->yearly_price) && (float) $this->plan->yearly_price > 0;
    }

    public function planHasOneTime(): bool
    {
        return !empty($this->plan->has_one_time_price) && !empty($this->plan->price) && (float) $this->plan->price > 0;
    }

    public function planPrice(): ?float
    {
        return match ($this->billingCycle) {
            'monthly' => $this->finalPrice($this->plan->monthly_price, $this->plan->monthly_discount_price),
            'yearly' => $this->finalPrice($this->plan->yearly_price, $this->plan->yearly_discount_price),
            'one_time' => $this->finalPrice($this->plan->price, $this->plan->discount_price),
            default => null,
        };
    }

    public function planOriginalPrice(): ?float
    {
        return match ($this->billingCycle) {
            'monthly' => $this->plan->monthly_price ? (float) $this->plan->monthly_price : null,
            'yearly' => $this->plan->yearly_price ? (float) $this->plan->yearly_price : null,
            'one_time' => $this->plan->price ? (float) $this->plan->price : null,
            default => null,
        };
    }

    public function hasDiscount(): bool
    {
        return match ($this->billingCycle) {
            'monthly' => $this->hasDiscountOn($this->plan->monthly_price, $this->plan->monthly_discount_price),
            'yearly' => $this->hasDiscountOn($this->plan->yearly_price, $this->plan->yearly_discount_price),
            'one_time' => $this->hasDiscountOn($this->plan->price, $this->plan->discount_price),
            default => false,
        };
    }

    private function hasDiscountOn($regular, $discount): bool
    {
        return !empty($regular) && (float) $regular > 0 && !empty($discount) && (float) $discount > 0 && (float) $discount < (float) $regular;
    }

    private function finalPrice($regular, $discount): ?float
    {
        if (empty($regular) || (float) $regular <= 0) {
            return null;
        }

        if (!empty($discount) && (float) $discount > 0 && (float) $discount < (float) $regular) {
            return (float) $discount;
        }

        return (float) $regular;
    }

    public function addonPrice($addon): ?float
    {
        $pivotPrice = match ($this->billingCycle) {
            'monthly' => $addon->pivot->monthly_price,
            'yearly' => $addon->pivot->yearly_price,
            default => $addon->pivot->price,
        };

        if ($pivotPrice !== null) {
            return (float) $pivotPrice;
        }

        $addonDefaultPrice = match ($this->billingCycle) {
            'monthly' => $addon->monthly_price,
            'yearly' => $addon->yearly_price,
            default => $addon->price,
        };

        return $addonDefaultPrice !== null ? (float) $addonDefaultPrice : null;
    }

    public function buyUrl(): ?string
    {
        return match ($this->billingCycle) {
            'monthly' => $this->plan->monthly_buy_url ?: $this->plan->buy_url,
            'yearly' => $this->plan->yearly_buy_url ?: $this->plan->buy_url,
            'one_time' => $this->plan->buy_url,
            default => $this->plan->buy_url,
        };
    }

    public function hasBuyUrl(): bool
    {
        return filled($this->buyUrl());
    }

    public function updatedBillingCycle(): void
    {
        $this->resetValidation();
    }

    public function fillUserAndCompanyInfo(): void
    {
        $user = auth()->user();

        if (!$user) {
            return;
        }

        $this->full_name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';

        if (method_exists($user, 'isCompanyAccount') && $user->isCompanyAccount() && $user->company) {
            $this->company_name = $user->company->company_name ?? '';
            $this->company_phone = $user->company->phone ?? '';
            $this->customer_address = $user->company->address ?? '';

            return;
        }

        $this->company_name = '';
        $this->company_phone = '';
        $this->customer_address = '';
    }

    public function addonTotal(): float
    {
        $total = 0;

        foreach ($this->plan->addons as $addon) {
            if (in_array($addon->id, $this->selectedAddonIds)) {
                $price = $this->addonPrice($addon);

                if ($price !== null) {
                    $total += $price;
                }
            }
        }

        return $total;
    }

    public function grandTotal(): ?float
    {
        $planPrice = $this->planPrice();

        if ($planPrice === null) {
            return null;
        }

        return $planPrice + $this->addonTotal();
    }

    private function makeBookingNo(): string
    {
        do {
            $bookingNo = 'BK-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (Booking::query()->where('booking_no', $bookingNo)->exists());

        return $bookingNo;
    }

    public function submit(): void
    {
        if (!auth()->check()) {
            $this->dispatch('toast', message: 'Please login first to book a plan.', type: 'error');

            return;
        }

        $validated = $this->validate(
            [
                'billingCycle' => ['required', 'in:monthly,yearly,one_time'],
                'full_name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],
                'email' => ['required', 'email', 'max:255'],
                'company_name' => ['required', 'string', 'max:255'],
                'company_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],
                'customer_address' => ['required', 'string', 'max:500'],
                'sender_bkash' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],
                'transaction_id' => ['required', 'string', 'max:50'],
                'selectedAddonIds' => ['nullable', 'array'],
                'selectedAddonIds.*' => ['integer', 'exists:plan_addons,id'],
            ],
            [
                'phone.regex' => 'Please enter a valid Bangladeshi phone number.',
                'company_phone.regex' => 'Please enter a valid Bangladeshi company phone number.',
                'customer_address.required' => 'Please enter your company address.',
                'sender_bkash.required' => 'Please enter your bKash number.',
                'sender_bkash.regex' => 'Please enter a valid Bangladeshi bKash number.',
                'transaction_id.required' => 'Please enter the transaction ID.',
            ],
        );

        $selectedAddons = [];

        foreach ($this->plan->addons as $addon) {
            if (in_array($addon->id, $this->selectedAddonIds)) {
                $selectedAddons[] = [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => $this->addonPrice($addon),
                ];
            }
        }

        $booking = Booking::create([
            'user_id' => auth()->id(),
            'booking_no' => $this->makeBookingNo(),
            'booking_type' => 'service',
            'service_id' => $this->service->id,
            'service_plan_id' => $this->plan->id,
            'pricing_plan_id' => null,
            'billing_cycle' => $this->billingCycle,
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'company_phone' => $validated['company_phone'] ?? null,
            'company_email' => null,
            'plan_name' => $this->plan->name,
            'plan_price' => $this->planPrice(),
            'quoted_price' => null,
            'final_price' => $this->grandTotal(),
            'addons' => $selectedAddons,
            'sender_bkash' => $validated['sender_bkash'],
            'transaction_id' => $validated['transaction_id'],
            'currency' => 'BDT',
            'message' => $this->buildMessageWithAddress($validated['customer_address']),
            'admin_note' => null,
            'status' => 'pending',
        ]);

        BookingCreated::dispatch($booking);

        $this->dispatch('toast', message: 'Your booking request has been submitted successfully. Our team will review your request and convert it to an order upon approval.', type: 'success');

        $this->redirectRoute('client.services.booking-success', ['booking' => $booking], navigate: true);
    }

    private function buildMessageWithAddress(string $address): string
    {
        return "Company Address:\n" . $address;
    }
};
?>

<div>
    <section class="min-h-screen pb-10 text-white">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">

            <div class="mb-8 flex items-center justify-between">
                <a href="{{ route('client.services.details', $service->slug) }}" wire:navigate
                    class="inline-flex items-center gap-2 text-sm text-blue-100/60 transition hover:text-cyan-200">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Go Back
                </a>

                {{-- @if ($plan->badge)
                    <span
                        class="shrink-0 rounded-full bg-cyan-400 px-3 py-1 text-xs font-bold uppercase tracking-wider text-slate-950">
                        {{ $plan->badge }}
                    </span>
                @endif --}}
            </div>

            {{-- Header --}}
            <div class="mb-10 text-center">
                <p class="text-sm font-medium md:font-semibold uppercase tracking-[0.28em] text-cyan-300">
                    {{ $service->card_title }}
                </p>

                <h1 class="mt-3 text-3xl font-bold sm:text-4xl lg:text-5xl">{{ $plan->name }}</h1>

                @if ($plan->description)
                    <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-blue-100/60 sm:text-base">
                        {{ $plan->description }}
                    </p>
                @endif
            </div>

            <form wire:submit.prevent="submit" novalidate>

                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-red-300/20 bg-red-400/10 p-4 text-sm text-red-100">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-red-200">error</span>

                            <div>
                                <p class="font-semibold">Please fix the following errors:</p>

                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid gap-8 lg:grid-cols-[1fr_420px]">

                    {{-- Left --}}
                    <div class="space-y-6">

                        {{-- Billing Option --}}
                        <div
                            class="rounded-3xl border border-blue-100/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-8">
                            <div class="mb-6 flex items-start gap-4">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-blue-300/20 bg-blue-300/10 text-blue-100">
                                    <span class="material-symbols-outlined">calendar_month</span>
                                </div>

                                <div>
                                    <h2 class="text-xl font-bold">Billing Cycle</h2>
                                    <p class="mt-1 text-sm text-blue-100/55">
                                        Choose your preferred billing cycle.
                                    </p>
                                </div>
                            </div>

                            @php
                                $hasMonthly = $this->planHasMonthly();
                                $hasYearly = $this->planHasYearly();
                                $hasOneTime = $this->planHasOneTime();
                            @endphp

                            <div class="grid gap-4 sm:grid-cols-3">
                                @if ($hasMonthly)
                                    <button type="button" wire:click="$set('billingCycle', 'monthly')"
                                        class="group cursor-pointer rounded-2xl border p-5 text-left transition
                                        {{ $billingCycle === 'monthly'
                                            ? 'border-cyan-300/60 bg-cyan-300/10 shadow-lg shadow-cyan-500/10'
                                            : 'border-blue-100/10 bg-white/5 hover:border-blue-200/15 hover:bg-white/10' }}">
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <p class="font-bold text-white">Monthly</p>
                                            </div>

                                            <div
                                                class="flex h-6 w-6 items-center justify-center rounded-full border
                                                {{ $billingCycle === 'monthly' ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-blue-200/15 text-transparent' }}">
                                                <span class="material-symbols-outlined text-base">check</span>
                                            </div>
                                        </div>

                                        <p class="mt-4 text-2xl font-bold">
                                            ৳{{ number_format((float) $plan->monthly_price, 0) }}
                                        </p>
                                    </button>
                                @endif

                                @if ($hasYearly)
                                    <button type="button" wire:click="$set('billingCycle', 'yearly')"
                                        class="group cursor-pointer rounded-2xl border p-5 text-left transition
                                        {{ $billingCycle === 'yearly'
                                            ? 'border-cyan-300/60 bg-cyan-300/10 shadow-lg shadow-cyan-500/10'
                                            : 'border-blue-100/10 bg-white/5 hover:border-blue-200/15 hover:bg-white/10' }}">
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <p class="font-bold text-white">Yearly</p>
                                            </div>

                                            <div
                                                class="flex h-6 w-6 items-center justify-center rounded-full border
                                                {{ $billingCycle === 'yearly' ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-blue-200/15 text-transparent' }}">
                                                <span class="material-symbols-outlined text-base">check</span>
                                            </div>
                                        </div>

                                        <p class="mt-4 text-2xl font-bold">
                                            ৳{{ number_format((float) $plan->yearly_price, 0) }}
                                        </p>
                                    </button>
                                @endif

                                @if ($hasOneTime)
                                    <button type="button" wire:click="$set('billingCycle', 'one_time')"
                                        class="group cursor-pointer rounded-2xl border p-5 text-left transition
                                        {{ $billingCycle === 'one_time'
                                            ? 'border-cyan-300/60 bg-cyan-300/10 shadow-lg shadow-cyan-500/10'
                                            : 'border-blue-100/10 bg-white/5 hover:border-blue-200/15 hover:bg-white/10' }}">
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <p class="font-bold text-white">One Time</p>
                                            </div>

                                            <div
                                                class="flex h-6 w-6 items-center justify-center rounded-full border
                                                {{ $billingCycle === 'one_time' ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-blue-200/15 text-transparent' }}">
                                                <span class="material-symbols-outlined text-base">check</span>
                                            </div>
                                        </div>

                                        <p class="mt-4 text-2xl font-bold">
                                            ৳{{ number_format((float) $plan->price, 0) }}
                                        </p>
                                    </button>
                                @endif
                            </div>

                            @error('billingCycle')
                                <p class="mt-3 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Addons --}}
                        @if ($plan->addons->count())
                            <div
                                class="rounded-3xl border border-blue-100/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-8">
                                <div class="mb-6 flex items-start gap-4">
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-violet-500 to-purple-600 text-white shadow-lg shadow-violet-500/20">
                                        <span class="material-symbols-outlined">extension</span>
                                    </div>

                                    <div>
                                        <h2 class="text-xl font-bold">Optional Addons</h2>
                                        <p class="mt-1 text-sm text-blue-100/55">
                                            Enhance your plan with these extras.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($plan->addons as $addon)
                                        @php
                                            $aPrice = $this->addonPrice($addon);
                                            $isSelected = in_array($addon->id, $selectedAddonIds);
                                        @endphp
                                        <label wire:key="addon-{{ $addon->id }}"
                                            class="group relative flex cursor-pointer items-center gap-4 rounded-2xl border p-4 transition all duration-300"
                                            @class([
                                                'border-purple-300/40 bg-purple-300/15 shadow-lg shadow-purple-500/10' => $isSelected,
                                                'border-blue-100/10 bg-white/[0.04] hover:border-blue-200/15 hover:bg-white/10' => !$isSelected,
                                            ])>
                                            <div class="relative flex h-6 w-6 shrink-0 items-center justify-center">
                                                <input type="checkbox" wire:model.live="selectedAddonIds"
                                                    value="{{ $addon->id }}" class="peer sr-only" />
                                                <div @class([
                                                    'flex h-6 w-6 items-center justify-center rounded-lg border-2 transition-all duration-200',
                                                    'border-purple-400 bg-purple-500 text-white scale-110 shadow-md shadow-purple-500/30' => $isSelected,
                                                    'border-blue-200/15 bg-white/5 group-hover:border-blue-200/30' => !$isSelected,
                                                ])>
                                                    @if ($isSelected)
                                                        <span class="material-symbols-outlined text-[16px]">check</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <span @class([
                                                    'block text-sm font-semibold transition',
                                                    'text-white' => $isSelected,
                                                    'text-blue-100/80 group-hover:text-white' => !$isSelected,
                                                ])>{{ $addon->name }}</span>
                                            </div>
                                            <div class="text-right shrink-0">
                                                @if ($aPrice !== null)
                                                    <span
                                                        @class([
                                                            'text-sm font-bold transition',
                                                            'text-purple-200' => $isSelected,
                                                            'text-blue-100/60 group-hover:text-blue-100/80' => !$isSelected,
                                                        ])>+৳{{ number_format($aPrice, 0) }}</span>
                                                @else
                                                    <span class="text-sm text-blue-100/50">Custom</span>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div
                            class="rounded-3xl border border-blue-100/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-8">
                            <div class="mb-6 flex items-start gap-4">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-cyan-400 to-blue-500 text-white shadow-lg shadow-cyan-500/20">
                                    <span class="material-symbols-outlined">assignment_ind</span>
                                </div>

                                <div>
                                    <h2 class="text-xl font-bold">Information</h2>
                                    <p class="mt-1 text-sm text-blue-100/55">
                                        We need your details so we can reach out and personalise your experience.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="full_name" class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Full Name <span class="text-red-300">*</span>
                                    </label>

                                    <input id="full_name" type="text" wire:model.live="full_name"
                                        placeholder="Your full name"
                                        class="w-full rounded-2xl border border-blue-100/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('full_name')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="phone" class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Phone Number <span class="text-red-300">*</span>
                                    </label>

                                    <input id="phone" type="text" wire:model.live="phone"
                                        placeholder="Enter phone number"
                                        class="w-full rounded-2xl border border-blue-100/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('phone')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="email" class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Email Address <span class="text-red-300">*</span>
                                    </label>

                                    <input id="email" type="email" wire:model.live="email"
                                        @auth readonly @endauth placeholder="Enter your email address"
                                        class="w-full rounded-2xl border border-blue-100/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15 {{ auth()->check() ? 'cursor-not-allowed opacity-80' : '' }}">

                                    @error('email')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="company_name"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Name <span class="text-red-300">*</span>
                                    </label>

                                    <input id="company_name" type="text" wire:model.live="company_name"
                                        placeholder="Your company name"
                                        class="w-full rounded-2xl border border-blue-100/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('company_name')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="company_phone"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Phone <span class="text-red-300">*</span>
                                    </label>

                                    <input id="company_phone" type="text" wire:model.live="company_phone"
                                        placeholder="Company phone number"
                                        class="w-full rounded-2xl border border-blue-100/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('company_phone')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="customer_address"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Address <span class="text-red-300">*</span>
                                    </label>

                                    <textarea id="customer_address" wire:model.live="customer_address" rows="3" placeholder="House / Road / Area"
                                        class="w-full resize-none rounded-2xl border border-blue-100/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15"></textarea>

                                    @error('customer_address')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- bKash Payment --}}
                        <div
                            class="rounded-[2rem] border border-white/15 bg-white/[0.07] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-2xl sm:p-8">
                            <h2 class="text-xl font-bold text-white">Pay with bKash</h2>
                            <p class="mt-1 text-sm text-blue-100/50">
                                Send the exact amount to our bKash number and submit your transaction details.
                            </p>

                            {{-- bKash Payment Info --}}
                            <div class="mt-6 overflow-hidden rounded-2xl border border-cyan-400/15 bg-cyan-400/5">
                                <div class="bg-cyan-400/10 px-5 py-3">
                                    <p class="text-xs font-bold uppercase tracking-wider text-cyan-300">Send Money
                                        To
                                    </p>
                                </div>
                                <div class="p-5">
                                    <p class="text-2xl font-extrabold tracking-wider text-white">
                                        {{ $siteSetting->bkash_number ?? 'Not set' }}</p>
                                    <p class="mt-1 text-sm text-blue-100/50">bKash Merchant Number</p>
                                    <div class="mt-4 flex items-baseline gap-1">
                                        <span
                                            class="text-3xl font-extrabold text-white">৳{{ number_format($this->grandTotal() ?? 0, 0) }}</span>
                                        <span class="text-sm text-blue-100/45">BDT</span>
                                    </div>
                                </div>
                            </div>

                            {{-- How to pay tutorial (click to reveal) --}}
                            @if ($siteSetting->bkash_instructions)
                                <div x-data="{ show: false }"
                                    class="mt-4 overflow-hidden rounded-xl border border-white/10 bg-black/10">
                                    <button type="button" @click="show = !show"
                                        class="flex w-full items-center gap-2 px-4 py-3 text-left transition hover:bg-white/5 cursor-pointer">
                                        <span class="material-symbols-outlined text-base text-cyan-300">info</span>
                                        <span
                                            class="text-xs font-semibold uppercase tracking-wider text-cyan-300/70">How
                                            to
                                            pay</span>
                                        <span class="ml-auto text-blue-100/40 transition"
                                            :class="{ 'rotate-180': show }">
                                            <span class="material-symbols-outlined text-base">expand_more</span>
                                        </span>
                                    </button>
                                    <div x-show="show" x-transition
                                        class="border-t border-white/10 px-4 py-3 space-y-1">
                                        @foreach (explode("\n", $siteSetting->bkash_instructions) as $line)
                                            @if (trim($line))
                                                <p class="text-xs text-blue-100/60">{{ $line }}</p>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="mt-6 space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-blue-100/80">Your bKash Number</label>
                                    <input type="text" wire:model.live="sender_bkash"
                                        class="w-full rounded-xl border border-white/15 bg-white/8 px-4 py-3 text-white placeholder-blue-100/30 outline-none transition focus:border-cyan-400/50 focus:ring-2 focus:ring-cyan-400/10"
                                        placeholder="01XXXXXXXXX" />
                                    @error('sender_bkash')
                                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-blue-100/80">bKash Transaction ID
                                        (TrxID)</label>
                                    <input type="text" wire:model.live="transaction_id"
                                        class="w-full rounded-xl border border-white/15 bg-white/8 px-4 py-3 text-white placeholder-blue-100/30 outline-none transition focus:border-cyan-400/50 focus:ring-2 focus:ring-cyan-400/10"
                                        placeholder="Enter the TrxID from your bKash app" />
                                    @error('transaction_id')
                                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Summary --}}
                    <div class="lg:sticky lg:top-24 lg:self-start">
                        <div
                            class="overflow-hidden rounded-3xl border border-blue-100/10 bg-white/5 shadow-2xl shadow-blue-950/30 backdrop-blur-xl">
                            <div class="border-b border-blue-100/10 p-6 sm:p-8">
                                <div class="flex items-start gap-4">
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-blue-500 to-cyan-400 text-white shadow-lg shadow-blue-500/20">
                                        <span class="material-symbols-outlined">receipt_long</span>
                                    </div>

                                    <div>
                                        <h2 class="text-xl font-bold">Order Summary</h2>
                                        <p class="mt-1 text-sm text-blue-100/55">
                                            Review your order before submission.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4 p-6 sm:p-8">
                                <div class="rounded-2xl border border-blue-100/10 bg-white/5 p-5">
                                    <p class="text-sm text-blue-100/55">Selected Plan</p>
                                    <h3 class="mt-1 text-xl font-bold text-white">{{ $plan->name }}</h3>

                                    @if ($plan->description)
                                        <p class="mt-2 line-clamp-2 text-sm text-blue-100/50">{{ $plan->description }}
                                        </p>
                                    @endif
                                </div>

                                <div class="space-y-3 rounded-2xl border border-blue-100/10 bg-white/5 p-5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <span class="text-blue-100/60">Billing Type</span>
                                        <span class="font-semibold capitalize text-white">{{ $billingCycle }}</span>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <span class="text-blue-100/60">Plan Price</span>
                                        <span class="font-semibold text-white">
                                            @if ($this->planPrice() !== null)
                                                ৳{{ number_format($this->planPrice(), 0) }}
                                            @else
                                                <span class="text-blue-100/50">Custom</span>
                                            @endif
                                        </span>
                                    </div>

                                    @if ($this->hasDiscount())
                                        <div class="flex justify-between gap-4">
                                            <span class="text-blue-100/60">Original Price</span>
                                            <span
                                                class="text-blue-100/40 line-through">৳{{ number_format($this->planOriginalPrice(), 0) }}</span>
                                        </div>
                                    @endif

                                    @if ($plan->addons->count() && count($selectedAddonIds))
                                        <div class="border-t border-blue-100/10 pt-3">
                                            <p
                                                class="mb-2 text-xs font-semibold uppercase tracking-wider text-blue-100/50">
                                                Addons</p>
                                            @foreach ($plan->addons as $addon)
                                                @if (in_array($addon->id, $selectedAddonIds))
                                                    @php $aPrice = $this->addonPrice($addon); @endphp
                                                    <div class="flex items-center justify-between py-1"
                                                        wire:key="summary-{{ $addon->id }}">
                                                        <span
                                                            class="text-sm text-blue-100/70">{{ $addon->name }}</span>
                                                        <span class="text-sm font-medium text-white">
                                                            @if ($aPrice !== null)
                                                                ৳{{ number_format($aPrice, 0) }}
                                                            @else
                                                                <span class="text-blue-100/50">Custom</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="border-t border-blue-100/10 pt-3">
                                        <div class="flex justify-between gap-4">
                                            <span class="text-base font-bold text-white">Total</span>
                                            <span class="text-xl font-bold text-cyan-200">
                                                @if ($this->grandTotal() !== null)
                                                    ৳{{ number_format($this->grandTotal(), 0) }}
                                                @else
                                                    <span class="text-base text-blue-100/50">Custom</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                </div>

                                @auth
                                    <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-6 py-4 font-bold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 hover:shadow-blue-500/40 disabled:cursor-not-allowed disabled:opacity-60">
                                        <span wire:loading.remove wire:target="submit"
                                            class="inline-flex items-center gap-2">
                                            <span class="material-symbols-outlined text-xl">send</span>
                                            SUBMIT BOOKING REQUEST
                                        </span>

                                        <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                                            <span
                                                class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200/30 border-t-white"></span>
                                            SUBMITTING...
                                        </span>
                                    </button>
                                @else
                                    <button type="button" x-data
                                        @click="window.dispatchEvent(new CustomEvent('open-auth', { detail: { mode: 'login' } }))"
                                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-6 py-4 font-bold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 hover:shadow-blue-500/40">
                                        <span class="material-symbols-outlined text-xl">lock</span>
                                        SUBMIT BOOKING REQUEST
                                    </button>
                                @endauth

                                <div
                                    class="flex items-center justify-center gap-2 text-center text-xs text-blue-100/50">
                                    <span class="material-symbols-outlined text-base">support_agent</span>
                                    Our team will contact you after review.
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </form>
        </div>
    </section>
</div>
