<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    public Service $service;
    public SiteSetting $siteSetting;

    public $otherServices;

    public ?int $quote_service_plan_id = null;
    public string $quote_selected_package = '';
    public ?string $quote_billing_cycle = null;
    public ?float $quote_selected_plan_price = null;

    public string $quote_full_name = '';
    public string $quote_phone = '';
    public string $quote_email = '';
    public string $quote_company_name = '';
    public string $quote_message = '';

    public function mount(string $slug): void
    {
        if (auth()->check()) {
            $this->quote_email = auth()->user()->email;
            $this->quote_full_name = auth()->user()->name ?? '';
            $this->quote_phone = auth()->user()->phone ?? '';
        }

        $this->siteSetting = SiteSetting::current();

        $this->service = Service::query()
            ->with(['category', 'activePlans'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $this->otherServices = Service::query()->where('is_active', true)->where('id', '!=', $this->service->id)->latest()->limit(3)->get();
    }

    private function makeBookingNo(): string
    {
        do {
            $bookingNo = 'BK-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (Booking::query()->where('booking_no', $bookingNo)->exists());

        return $bookingNo;
    }

    private function finalPlanPrice($regularPrice, $discountPrice): ?float
    {
        if (empty($regularPrice) || (float) $regularPrice <= 0) {
            return null;
        }

        if (!empty($discountPrice) && (float) $discountPrice > 0 && (float) $discountPrice < (float) $regularPrice) {
            return (float) $discountPrice;
        }

        return (float) $regularPrice;
    }

    public function parseSelectedPackage(): array
    {
        if (blank($this->quote_selected_package)) {
            return [
                'plan' => null,
                'plan_id' => null,
                'plan_name' => null,
                'billing_cycle' => 'custom',
                'price' => null,
            ];
        }

        [$planId, $billingCycle] = array_pad(explode('|', $this->quote_selected_package), 2, null);

        $planId = $planId ? (int) $planId : null;

        if (!$planId) {
            return [
                'plan' => null,
                'plan_id' => null,
                'plan_name' => null,
                'billing_cycle' => 'custom',
                'price' => null,
            ];
        }

        $plan = $this->service->activePlans->firstWhere('id', $planId);

        if (!$plan) {
            return [
                'plan' => null,
                'plan_id' => null,
                'plan_name' => null,
                'billing_cycle' => 'custom',
                'price' => null,
            ];
        }

        $hasMonthlyPrice = !empty($plan->has_monthly_price) && !empty($plan->monthly_price) && (float) $plan->monthly_price > 0;

        $hasYearlyPrice = !empty($plan->has_yearly_price) && !empty($plan->yearly_price) && (float) $plan->yearly_price > 0;

        $hasOneTimePrice = !empty($plan->price) && (float) $plan->price > 0;

        if (!in_array($billingCycle, ['monthly', 'yearly', 'one_time', 'custom'], true)) {
            $billingCycle = match (true) {
                $hasMonthlyPrice => 'monthly',
                $hasYearlyPrice => 'yearly',
                $hasOneTimePrice => 'one_time',
                default => 'custom',
            };
        }

        if ($billingCycle === 'monthly' && !$hasMonthlyPrice) {
            $billingCycle = match (true) {
                $hasYearlyPrice => 'yearly',
                $hasOneTimePrice => 'one_time',
                default => 'custom',
            };
        }

        if ($billingCycle === 'yearly' && !$hasYearlyPrice) {
            $billingCycle = match (true) {
                $hasMonthlyPrice => 'monthly',
                $hasOneTimePrice => 'one_time',
                default => 'custom',
            };
        }

        $price = match ($billingCycle) {
            'monthly' => $this->finalPlanPrice($plan->monthly_price, $plan->monthly_discount_price),

            'yearly' => $this->finalPlanPrice($plan->yearly_price, $plan->yearly_discount_price),

            'one_time' => $this->finalPlanPrice($plan->price, $plan->discount_price),

            default => null,
        };

        return [
            'plan' => $plan,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'billing_cycle' => $billingCycle,
            'price' => $price,
        ];
    }

    public function updatedQuoteSelectedPackage(): void
    {
        $selectedPackage = $this->parseSelectedPackage();

        $this->quote_service_plan_id = $selectedPackage['plan_id'];
        $this->quote_billing_cycle = $selectedPackage['billing_cycle'];
        $this->quote_selected_plan_price = $selectedPackage['price'];
    }

    public function selectQuotePlan(int $planId, string $billingCycle = 'custom'): void
    {
        if (!$this->service->activePlans->contains('id', $planId)) {
            return;
        }

        $this->quote_selected_package = $planId . '|' . $billingCycle;

        $selectedPackage = $this->parseSelectedPackage();

        $this->quote_service_plan_id = $selectedPackage['plan_id'];
        $this->quote_selected_package = $selectedPackage['plan_id'] ? $selectedPackage['plan_id'] . '|' . $selectedPackage['billing_cycle'] : '';

        $this->quote_billing_cycle = $selectedPackage['billing_cycle'];
        $this->quote_selected_plan_price = $selectedPackage['price'];
    }

    public function submitQuoteRequest(): void
    {
        if (auth()->check()) {
            $this->quote_email = auth()->user()->email;
        }

        $validated = $this->validate(
            [
                'quote_selected_package' => ['nullable', 'string', 'max:100'],
                'quote_full_name' => ['required', 'string', 'max:255'],
                'quote_phone' => ['required', 'string', 'regex:/^(?:\+8801|8801|01)[3-9]\d{8}$/'],
                'quote_email' => ['nullable', 'email', 'max:255'],
                'quote_company_name' => ['nullable', 'string', 'max:255'],
                'quote_message' => ['nullable', 'string', 'max:3000'],
            ],
            [
                'quote_phone.regex' => 'Please enter a valid Bangladeshi phone number. Example: 017XXXXXXXX, +88017XXXXXXXX, or 88017XXXXXXXX.',
            ],
        );

        $selectedPackage = $this->parseSelectedPackage();

        Booking::create([
            'user_id' => auth()->id(),

            'booking_no' => $this->makeBookingNo(),
            'booking_type' => 'service',

            'service_id' => $this->service->id,
            'service_plan_id' => $selectedPackage['plan_id'],
            'pricing_plan_id' => null,

            'billing_cycle' => $selectedPackage['billing_cycle'],

            'full_name' => $validated['quote_full_name'],
            'phone' => $validated['quote_phone'],
            'email' => $validated['quote_email'] ?? null,

            'company_name' => $validated['quote_company_name'] ?? null,
            'company_phone' => null,
            'company_email' => null,

            'plan_name' => $selectedPackage['plan_name'],
            'plan_price' => $selectedPackage['price'],
            'requested_price' => null,
            'quoted_price' => null,
            'final_price' => $selectedPackage['price'],

            'currency' => 'BDT',

            'message' => $validated['quote_message'] ?? null,
            'user_note' => $validated['quote_message'] ?? null,
            'admin_note' => null,

            'status' => 'pending',

            'pricing_order_id' => null,
            'admin_read_at' => null,
        ]);

        $this->reset(['quote_service_plan_id', 'quote_selected_package', 'quote_billing_cycle', 'quote_selected_plan_price', 'quote_full_name', 'quote_phone', 'quote_company_name', 'quote_message']);

        if (auth()->check()) {
            $this->quote_email = auth()->user()->email;
            $this->quote_full_name = auth()->user()->name ?? '';
            $this->quote_phone = auth()->user()->phone ?? '';
        } else {
            $this->quote_email = '';
        }

        $this->dispatch('toast', message: 'Your booking request has been submitted successfully.', type: 'success');
    }

    public function serviceImage(): string
    {
        if ($this->service->image) {
            if (str_starts_with($this->service->image, 'http://') || str_starts_with($this->service->image, 'https://')) {
                return $this->service->image;
            }

            return asset('storage/' . $this->service->image);
        }

        return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1400&q=80';
    }

    public function whatsappLink(): string
    {
        if ($this->siteSetting->whatsapp_url) {
            return $this->siteSetting->whatsapp_url . '?text=' . urlencode('Hello, I am interested in ' . $this->service->card_title);
        }

        $phone = preg_replace('/[^0-9]/', '', $this->siteSetting->phone ?: 'n/a');

        return 'https://wa.me/' . $phone . '?text=' . urlencode('Hello, I am interested in ' . $this->service->card_title);
    }
};
?>

<div class="relative text-white">
    @push('meta')
        <meta name="title" content="{{ $service->meta_title ?: $service->card_title }}">
        <meta name="description" content="{{ $service->meta_description ?: $service->short_description }}">
        <meta name="keywords" content="{{ $service->meta_keywords }}">
    @endpush

    <!-- Hero -->
    <section class="relative overflow-hidden py-18 sm:py-22 lg:py-26">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute left-[8%] top-10 h-44 w-44 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute right-[10%] top-16 h-56 w-56 rounded-full bg-blue-500/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-14">
                <div class="max-w-3xl">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs sm:text-sm text-blue-100/85 backdrop-blur-xl">
                        <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                        {{ $service->category?->name ?? 'Service Details' }}
                    </div>

                    @php
                        $title = $service->detail_title ?: $service->card_title;
                    @endphp

                    <h1
                        class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl lg:text-7xl">
                        <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                            {{ $title }}
                        </span>
                    </h1>

                    @if ($service->short_description)
                        <p class="mt-6 max-w-2xl text-sm leading-7 text-blue-100/72 sm:text-base sm:leading-8">
                            {{ $service->short_description }}
                        </p>
                    @endif

                    @if (!empty($service->tags))
                        <div class="mt-8 flex flex-wrap gap-3">
                            @foreach ($service->tags as $tag)
                                <span
                                    class="rounded-full border border-white/10 bg-white/8 px-4 py-2 text-sm text-blue-100/80">
                                    {{ is_array($tag) ? $tag['name'] ?? ($tag['title'] ?? '') : $tag }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-8 flex flex-wrap gap-3">
                        @if ($service->activePlans->count())
                            <a href="#service-plans"
                                class="inline-flex items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5">
                                View Plans
                            </a>
                        @endif

                        <a href="#quote-form"
                            class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/8 px-6 py-3 text-sm font-semibold text-white backdrop-blur-xl transition hover:bg-white/12">
                            Request Quote
                        </a>
                    </div>
                </div>

                <div class="relative">
                    <div
                        class="relative overflow-hidden rounded-[30px] border border-white/15 bg-white/8 p-3 shadow-[0_25px_80px_rgba(0,0,0,0.24)] backdrop-blur-2xl">
                        <div class="absolute left-8 top-8 h-28 w-28 rounded-full bg-cyan-400/12 blur-3xl"></div>
                        <div class="absolute bottom-8 right-8 h-32 w-32 rounded-full bg-blue-500/12 blur-3xl"></div>

                        <div class="overflow-hidden rounded-3xl border border-white/10">
                            <img src="{{ $this->serviceImage() }}" alt="{{ $service->card_title }}"
                                class="h-80 w-full object-cover sm:h-100">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="relative overflow-hidden pb-20 sm:pb-24">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[1fr_380px] xl:grid-cols-[1fr_420px]">
                <!-- Left Content -->
                <div class="space-y-8">

                    <!-- Service Plans -->
                    @if ($service->activePlans->count())
                        @php
                            $planCount = $service->activePlans->count();

                            $planGridClass = match (true) {
                                $planCount === 1 => 'grid gap-6 md:grid-cols-1 md:max-w-md md:mx-auto pt-6',
                                $planCount === 2 => 'grid gap-6 md:grid-cols-2 md:max-w-5xl md:mx-auto pt-6',
                                default => 'grid gap-6 md:grid-cols-2 pt-6',
                            };

                            $hasAnyMonthlyPlan = $service->activePlans->contains(function ($plan) {
                                return !empty($plan->has_monthly_price) &&
                                    !empty($plan->monthly_price) &&
                                    (float) $plan->monthly_price > 0;
                            });

                            $hasAnyYearlyPlan = $service->activePlans->contains(function ($plan) {
                                return !empty($plan->has_yearly_price) &&
                                    !empty($plan->yearly_price) &&
                                    (float) $plan->yearly_price > 0;
                            });

                            $showGlobalBillingToggle = $hasAnyMonthlyPlan && $hasAnyYearlyPlan;
                            $defaultBilling = $hasAnyMonthlyPlan ? 'monthly' : 'yearly';
                        @endphp

                        <div id="service-plans" class="scroll-mt-28" x-data="{ billing: '{{ $defaultBilling }}' }">
                            <div class="mb-8">
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-4 py-2 text-xs text-blue-100/80 backdrop-blur-xl">
                                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                                    Service Plans
                                </div>

                                <div class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h2 class="text-2xl font-bold text-white sm:text-3xl">
                                            Choose the right package
                                        </h2>

                                        <p class="mt-3 max-w-2xl text-sm leading-7 text-blue-100/66">
                                            Select a suitable plan for {{ $service->card_title }} based on your
                                            business needs,
                                            budget, and support requirements.
                                        </p>
                                    </div>

                                    @if ($showGlobalBillingToggle)
                                        <div
                                            class="inline-grid grid-cols-2 rounded-full border border-white/10 bg-white/8 p-1 backdrop-blur-xl">
                                            <button type="button" @click="billing = 'monthly'"
                                                class="rounded-full px-5 py-2.5 text-sm font-semibold transition sm:px-6"
                                                :class="billing === 'monthly'
                                                    ?
                                                    'bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-400/20' :
                                                    'text-blue-100/70 hover:text-white'">
                                                Monthly
                                            </button>

                                            <button type="button" @click="billing = 'yearly'"
                                                class="rounded-full px-5 py-2.5 text-sm font-semibold transition sm:px-6"
                                                :class="billing === 'yearly'
                                                    ?
                                                    'bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-400/20' :
                                                    'text-blue-100/70 hover:text-white'">
                                                Yearly
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="{{ $planGridClass }}">
                                @foreach ($service->activePlans as $plan)
                                    @php
                                        $isPopular = $plan->badge && str_contains(strtolower($plan->badge), 'popular');

                                        $cardClass = $isPopular
                                            ? 'border-cyan-300/25 bg-linear-to-b from-blue-500/12 to-white/8 shadow-[0_25px_80px_rgba(0,0,0,0.24)]'
                                            : 'border-white/10 bg-white/6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]';

                                        $features = is_array($plan->features) ? $plan->features : [];

                                        $hasOneTimePrice = !empty($plan->price) && (float) $plan->price > 0;

                                        $hasMonthlyPrice =
                                            !empty($plan->has_monthly_price) &&
                                            !empty($plan->monthly_price) &&
                                            (float) $plan->monthly_price > 0;

                                        $hasYearlyPrice =
                                            !empty($plan->has_yearly_price) &&
                                            !empty($plan->yearly_price) &&
                                            (float) $plan->yearly_price > 0;

                                        $hasDiscount =
                                            $hasOneTimePrice &&
                                            !empty($plan->discount_price) &&
                                            (float) $plan->discount_price > 0 &&
                                            (float) $plan->discount_price < (float) $plan->price;

                                        $discountPercent = $hasDiscount
                                            ? round((1 - (float) $plan->discount_price / (float) $plan->price) * 100)
                                            : 0;

                                        $hasMonthlyDiscount =
                                            $hasMonthlyPrice &&
                                            !empty($plan->monthly_discount_price) &&
                                            (float) $plan->monthly_discount_price > 0 &&
                                            (float) $plan->monthly_discount_price < (float) $plan->monthly_price;

                                        $monthlyDiscountPercent = $hasMonthlyDiscount
                                            ? round(
                                                (1 -
                                                    (float) $plan->monthly_discount_price /
                                                        (float) $plan->monthly_price) *
                                                    100,
                                            )
                                            : 0;

                                        $hasYearlyDiscount =
                                            $hasYearlyPrice &&
                                            !empty($plan->yearly_discount_price) &&
                                            (float) $plan->yearly_discount_price > 0 &&
                                            (float) $plan->yearly_discount_price < (float) $plan->yearly_price;

                                        $yearlyDiscountPercent = $hasYearlyDiscount
                                            ? round(
                                                (1 -
                                                    (float) $plan->yearly_discount_price /
                                                        (float) $plan->yearly_price) *
                                                    100,
                                            )
                                            : 0;
                                    @endphp

                                    <div
                                        class="group relative rounded-[30px] border {{ $cardClass }} p-6 backdrop-blur-2xl transition duration-300 hover:-translate-y-1 hover:border-cyan-300/25">

                                        <div
                                            class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-cyan-300/70 to-transparent">
                                        </div>

                                        @if ($plan->badge)
                                            <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                                                <span
                                                    class="inline-flex rounded-full border border-cyan-300/70 bg-cyan-400 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-950 shadow-lg shadow-cyan-400/20">
                                                    {{ $plan->badge }}
                                                </span>
                                            </div>
                                        @endif

                                        <div
                                            class="flex items-start justify-between gap-4 {{ $plan->badge ? 'pt-4' : '' }}">
                                            <div>
                                                <p
                                                    class="text-xs font-medium uppercase tracking-[0.22em] text-cyan-200/80">
                                                    {{ $service->card_title }}
                                                </p>

                                                <h3 class="mt-2 text-2xl font-bold text-white">
                                                    {{ $plan->name }}
                                                </h3>
                                            </div>
                                        </div>

                                        @if ($plan->description)
                                            <p class="mt-4 text-sm leading-7 text-blue-100/68">
                                                {{ $plan->description }}
                                            </p>
                                        @else
                                            <p class="mt-4 text-sm leading-7 text-blue-100/68">
                                                Flexible service package designed for your business requirements.
                                            </p>
                                        @endif

                                        <div class="mt-6">
                                            {{-- Monthly price --}}
                                            @if ($hasMonthlyPrice)
                                                <div x-show="billing === 'monthly'" x-cloak>
                                                    @if ($hasMonthlyDiscount)
                                                        <div class="flex flex-wrap items-center gap-3">
                                                            <span class="text-4xl font-bold text-white">
                                                                ৳
                                                                {{ number_format((float) $plan->monthly_discount_price, 0) }}
                                                            </span>

                                                            <span
                                                                class="text-lg font-semibold text-blue-100/40 line-through">
                                                                ৳ {{ number_format((float) $plan->monthly_price, 0) }}
                                                            </span>

                                                            <span
                                                                class="inline-flex items-center gap-1 rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-bold text-cyan-200">
                                                                {{ $monthlyDiscountPercent }}% OFF
                                                            </span>
                                                        </div>
                                                    @else
                                                        <div class="flex items-end gap-2">
                                                            <span class="text-4xl font-bold text-white">
                                                                ৳ {{ number_format((float) $plan->monthly_price, 0) }}
                                                            </span>
                                                            <span class="pb-1 text-sm text-blue-100/60">/ month</span>
                                                        </div>
                                                    @endif

                                                    {{-- <p class="mt-2 text-sm font-medium text-blue-100/55">
                                                        Per month
                                                    </p> --}}
                                                </div>
                                            @endif

                                            {{-- Yearly price --}}
                                            @if ($hasYearlyPrice)
                                                <div x-show="billing === 'yearly'" x-cloak>
                                                    @if ($hasYearlyDiscount)
                                                        <div class="flex flex-wrap items-center gap-3">
                                                            <span class="text-4xl font-bold text-white">
                                                                ৳
                                                                {{ number_format((float) $plan->yearly_discount_price, 0) }}
                                                            </span>

                                                            <span
                                                                class="text-lg font-semibold text-blue-100/40 line-through">
                                                                ৳ {{ number_format((float) $plan->yearly_price, 0) }}
                                                            </span>

                                                            <span
                                                                class="inline-flex items-center gap-1 rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-bold text-cyan-200">
                                                                {{ $yearlyDiscountPercent }}% OFF
                                                            </span>
                                                        </div>
                                                    @else
                                                        <div class="flex items-end gap-2">
                                                            <span class="text-4xl font-bold text-white">
                                                                ৳ {{ number_format((float) $plan->yearly_price, 0) }}
                                                            </span>
                                                            <span class="pb-1 text-sm text-blue-100/60">/ year</span>
                                                        </div>
                                                    @endif

                                                    {{-- <p class="mt-2 text-sm font-medium text-blue-100/55">
                                                        Per year
                                                    </p> --}}
                                                </div>
                                            @endif

                                            {{-- Fallback when selected billing is not available for this card --}}
                                            @if ($hasMonthlyPrice && !$hasYearlyPrice)
                                                <div x-show="billing === 'yearly'" x-cloak>
                                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                                        <p class="text-lg font-bold text-white">
                                                            Yearly unavailable
                                                        </p>
                                                        <p class="mt-1 text-sm leading-6 text-blue-100/60">
                                                            This plan is available only as a monthly package.
                                                        </p>
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($hasYearlyPrice && !$hasMonthlyPrice)
                                                <div x-show="billing === 'monthly'" x-cloak>
                                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                                        <p class="text-lg font-bold text-white">
                                                            Monthly unavailable
                                                        </p>
                                                        <p class="mt-1 text-sm leading-6 text-blue-100/60">
                                                            This plan is available only as a yearly package.
                                                        </p>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- One-time price --}}
                                            @if (!$hasMonthlyPrice && !$hasYearlyPrice && $hasOneTimePrice)
                                                @if ($hasDiscount)
                                                    <div class="flex flex-wrap items-center gap-3">
                                                        <span class="text-4xl font-bold text-white">
                                                            ৳ {{ number_format((float) $plan->discount_price, 0) }}
                                                        </span>

                                                        <span
                                                            class="text-lg font-semibold text-blue-100/40 line-through">
                                                            ৳ {{ number_format((float) $plan->price, 0) }}
                                                        </span>

                                                        <span
                                                            class="inline-flex items-center gap-1 rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-bold text-cyan-200">
                                                            {{ $discountPercent }}% OFF
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="flex items-end gap-2">
                                                        <span class="text-4xl font-bold text-white">
                                                            ৳ {{ number_format((float) $plan->price, 0) }}
                                                        </span>
                                                    </div>
                                                @endif

                                                {{-- <p class="mt-2 text-sm font-medium text-blue-100/55">
                                                    One-time
                                                </p> --}}
                                            @endif

                                            {{-- Custom price --}}
                                            @if (!$hasMonthlyPrice && !$hasYearlyPrice && !$hasOneTimePrice)
                                                <span class="text-3xl font-bold text-white">Custom</span>
                                            @endif
                                        </div>

                                        @if ($plan->buy_url)
                                            <a href="{{ $plan->buy_url }}" target="_blank"
                                                class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3.5 font-semibold text-white shadow-lg shadow-blue-500/30 backdrop-blur-xl transition hover:-translate-y-0.5">
                                                Choose Plan
                                            </a>
                                        @else
                                            <a href="#quote-form"
                                                @click="$wire.selectQuotePlan({{ $plan->id }}, billing)"
                                                class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3.5 font-semibold text-white shadow-lg shadow-blue-500/30 backdrop-blur-xl transition hover:-translate-y-0.5">
                                                Choose Plan
                                            </a>
                                        @endif

                                        <ul class="mt-7 space-y-3 text-sm text-blue-50/85">
                                            @forelse ($features as $feature)
                                                <li class="pricing-li">
                                                    <span>
                                                        {{ is_array($feature) ? $feature['title'] ?? ($feature['name'] ?? ($feature['text'] ?? '')) : $feature }}
                                                    </span>
                                                </li>
                                            @empty
                                                <li class="pricing-li">
                                                    <span
                                                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-cyan-400/15 text-cyan-200">
                                                        <span
                                                            class="material-symbols-outlined text-[16px]">check</span>
                                                    </span>

                                                    <span>
                                                        Custom features available on request
                                                    </span>
                                                </li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Overview -->
                    @if (!empty($service->overview))
                        <div class="service-detail-card">
                            <h2 class="text-2xl font-bold text-white sm:text-3xl">Service Overview</h2>

                            <div class="service-rich-content mt-5">
                                {!! $service->overview !!}
                            </div>
                        </div>
                    @endif

                    <!-- Benefits -->
                    @if ($service->benefits)
                        <div class="service-detail-card">
                            <h2 class="text-2xl font-bold text-white sm:text-3xl">Key Benefits</h2>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                @foreach ($service->benefits as $benefit)
                                    <div class="benefit-box">
                                        <h3 class="text-lg font-semibold text-white">
                                            {{ is_array($benefit) ? $benefit['title'] ?? ($benefit['name'] ?? 'Benefit') : $benefit }}
                                        </h3>

                                        @if (is_array($benefit) && !empty($benefit['description']))
                                            <p class="mt-2 text-sm leading-6 text-blue-100/66">
                                                {{ $benefit['description'] }}
                                            </p>
                                        @elseif (is_array($benefit) && !empty($benefit['detail']))
                                            <p class="mt-2 text-sm leading-6 text-blue-100/66">
                                                {{ $benefit['detail'] }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Features -->
                    @if (!empty($service->included_items))
                        <div class="service-detail-card">
                            <h2 class="text-2xl font-bold text-white sm:text-3xl">What’s Included</h2>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                @foreach ($service->included_items as $item)
                                    <div class="feature-line">
                                        {{ is_array($item) ? $item['title'] ?? ($item['name'] ?? ($item['text'] ?? '')) : $item }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Ideal For -->
                    @if ($service->audience_title || $service->audience_detail)
                        <div class="service-detail-card">
                            <h2 class="text-2xl font-bold text-white sm:text-3xl">
                                {{ $service->audience_title ?: 'Who This Service Is For' }}
                            </h2>

                            @if ($service->audience_detail)
                                <div class="mt-5 space-y-4 text-sm leading-7 text-blue-100/72 sm:text-base">
                                    @foreach (preg_split('/\r\n|\r|\n/', $service->audience_detail) as $line)
                                        @if (trim($line))
                                            <p>{{ $line }}</p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Other Services -->
                    @if ($otherServices->count())
                        <div class="service-detail-card">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h2 class="text-2xl font-bold text-white sm:text-3xl">Other Services</h2>
                                    <p class="mt-2 text-sm text-blue-100/66">Explore other solutions we offer.</p>
                                </div>

                                <a href="{{ route('client.services') }}" wire:navigate
                                    class="hidden sm:inline-flex items-center rounded-full border border-white/10 bg-white/8 px-4 py-2 text-sm font-medium text-white backdrop-blur-xl transition hover:bg-white/12">
                                    View All
                                </a>
                            </div>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($otherServices as $otherService)
                                    <a href="{{ route('client.services.details', $otherService->slug) }}"
                                        wire:navigate
                                        class="other-service-card {{ $loop->last ? 'sm:col-span-2 xl:col-span-1' : '' }}">
                                        <div class="other-service-icon bg-cyan-500/15 text-cyan-200">
                                            @if ($otherService->icon)
                                                <span class="material-symbols-outlined">
                                                    {{ $otherService->icon }}
                                                </span>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                    stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 4.5h16.5v10.5H3.75zM7.5 20.25h9" />
                                                </svg>
                                            @endif
                                        </div>

                                        <h3 class="mt-4 text-lg font-semibold text-white">
                                            {{ $otherService->card_title }}
                                        </h3>

                                        <p class="mt-2 text-sm leading-6 text-blue-100/64">
                                            {{ Str::limit($otherService->short_description, 90) }}
                                        </p>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Right Sidebar -->
                <aside class="space-y-6">
                    <!-- Contact Card -->
                    <div class="sidebar-service-card">
                        <h3 class="text-2xl font-bold text-white">Need Help Fast?</h3>
                        <p class="mt-3 text-sm leading-7 text-blue-100/68">
                            Contact our team directly for quick guidance about this service.
                        </p>

                        <div class="mt-6 space-y-4">
                            <div class="contact-side-box">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Call Us</p>
                                <p class="mt-2 text-base font-semibold text-white">{{ $this->siteSetting->phone }}</p>
                            </div>

                            <a href="{{ $this->whatsappLink() }}" target="_blank"
                                class="inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-emerald-500 to-green-400 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5">
                                Chat on WhatsApp
                            </a>
                        </div>
                    </div>

                    <!-- Quote Form -->
                    <div id="quote-form" class="sidebar-service-card scroll-mt-28">
                        <h3 class="text-2xl font-bold text-white">Request a Quote</h3>
                        <p class="mt-3 text-sm leading-7 text-blue-100/68">
                            Share your requirements and we’ll get back to you with the right solution.
                        </p>

                        <form wire:submit.prevent="submitQuoteRequest" class="mt-6 space-y-4">
                            <input type="hidden" value="{{ $service->id }}">

                            @if ($service->activePlans->count())
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-blue-50/85">
                                        Select Service Plan
                                    </label>

                                    <div class="relative">
                                        <select wire:model.live="quote_selected_package"
                                            class="contact-input appearance-none pr-10 bg-slate-950/80 text-white [color-scheme:dark]">
                                            <option class="bg-slate-950 text-white" value="">
                                                Select a plan or request custom quote
                                            </option>

                                            @foreach ($service->activePlans as $plan)
                                                @php
                                                    $hasOneTimePrice = !empty($plan->price) && (float) $plan->price > 0;

                                                    $hasMonthlyPrice =
                                                        !empty($plan->has_monthly_price) &&
                                                        !empty($plan->monthly_price) &&
                                                        (float) $plan->monthly_price > 0;

                                                    $hasYearlyPrice =
                                                        !empty($plan->has_yearly_price) &&
                                                        !empty($plan->yearly_price) &&
                                                        (float) $plan->yearly_price > 0;

                                                    $hasDiscount =
                                                        $hasOneTimePrice &&
                                                        !empty($plan->discount_price) &&
                                                        (float) $plan->discount_price > 0 &&
                                                        (float) $plan->discount_price < (float) $plan->price;

                                                    $hasMonthlyDiscount =
                                                        $hasMonthlyPrice &&
                                                        !empty($plan->monthly_discount_price) &&
                                                        (float) $plan->monthly_discount_price > 0 &&
                                                        (float) $plan->monthly_discount_price <
                                                            (float) $plan->monthly_price;

                                                    $hasYearlyDiscount =
                                                        $hasYearlyPrice &&
                                                        !empty($plan->yearly_discount_price) &&
                                                        (float) $plan->yearly_discount_price > 0 &&
                                                        (float) $plan->yearly_discount_price <
                                                            (float) $plan->yearly_price;

                                                    $oneTimePrice = $hasDiscount ? $plan->discount_price : $plan->price;
                                                    $monthlyPrice = $hasMonthlyDiscount
                                                        ? $plan->monthly_discount_price
                                                        : $plan->monthly_price;
                                                    $yearlyPrice = $hasYearlyDiscount
                                                        ? $plan->yearly_discount_price
                                                        : $plan->yearly_price;
                                                @endphp

                                                @if ($hasMonthlyPrice)
                                                    <option class="bg-slate-950 text-white"
                                                        value="{{ $plan->id }}|monthly">
                                                        {{ $plan->name }} - Monthly -
                                                        ৳{{ number_format((float) $monthlyPrice, 0) }}
                                                    </option>
                                                @endif

                                                @if ($hasYearlyPrice)
                                                    <option class="bg-slate-950 text-white"
                                                        value="{{ $plan->id }}|yearly">
                                                        {{ $plan->name }} - Yearly -
                                                        ৳{{ number_format((float) $yearlyPrice, 0) }}
                                                    </option>
                                                @endif

                                                @if (!$hasMonthlyPrice && !$hasYearlyPrice && $hasOneTimePrice)
                                                    <option class="bg-slate-950 text-white"
                                                        value="{{ $plan->id }}|one_time">
                                                        {{ $plan->name }} - One-time -
                                                        ৳{{ number_format((float) $oneTimePrice, 0) }}
                                                    </option>
                                                @endif

                                                @if (!$hasMonthlyPrice && !$hasYearlyPrice && !$hasOneTimePrice)
                                                    <option class="bg-slate-950 text-white"
                                                        value="{{ $plan->id }}|custom">
                                                        {{ $plan->name }} - Custom Price
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>

                                        <span
                                            class="material-symbols-outlined pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-blue-100/45">
                                            expand_more
                                        </span>
                                    </div>

                                    @error('quote_selected_package')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror

                                    @if ($quote_selected_package)
                                        @php
                                            $selectedPackage = $this->parseSelectedPackage();

                                            $selectedBillingLabel = match ($selectedPackage['billing_cycle']) {
                                                'monthly' => 'Monthly',
                                                'yearly' => 'Yearly',
                                                'one_time' => 'One-time',
                                                'custom' => 'Custom',
                                                default => 'Custom',
                                            };
                                        @endphp

                                        {{-- <div class="mt-3 rounded-2xl border border-cyan-300/15 bg-cyan-400/10 p-4">
                                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-200/70">
                                                Selected Package
                                            </p>

                                            <p class="mt-2 text-sm font-semibold text-white">
                                                {{ $selectedPackage['plan_name'] ?? 'Custom Package' }}
                                            </p>

                                            <p class="mt-1 text-sm text-blue-100/70">
                                                {{ $selectedBillingLabel }}

                                                @if ($selectedPackage['price'])
                                                    - ৳{{ number_format((float) $selectedPackage['price'], 0) }}
                                                @else
                                                    - Custom Price
                                                @endif
                                            </p>
                                        </div> --}}
                                    @endif
                                </div>
                            @endif

                            <div>
                                <label class="mb-2 block text-sm font-medium text-blue-50/85">Full Name</label>
                                <input type="text" wire:model="quote_full_name" placeholder="Enter your name"
                                    class="contact-input">

                                @error('quote_full_name')
                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-blue-50/85">Phone Number</label>
                                <input type="text" wire:model="quote_phone" placeholder="017XXXXXXXX"
                                    class="contact-input">

                                @error('quote_phone')
                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-blue-50/85">Email Address</label>

                                <input type="email" wire:model="quote_email" placeholder="Enter your email"
                                    @auth readonly @endauth
                                    class="contact-input {{ auth()->check() ? 'cursor-not-allowed opacity-80' : '' }}">

                                @auth
                                    <p class="mt-1 text-xs text-blue-100/45">
                                        Your login email will be used for this request.
                                    </p>
                                @endauth

                                @error('quote_email')
                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-blue-50/85">Company Name</label>
                                <input type="text" wire:model="quote_company_name"
                                    placeholder="Enter your company name" class="contact-input">

                                @error('quote_company_name')
                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-blue-50/85">Project Details</label>
                                <textarea rows="5" wire:model="quote_message"
                                    placeholder="Tell us what you need for {{ $service->card_title }}" class="contact-input resize-none"></textarea>

                                @error('quote_message')
                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" wire:loading.attr="disabled" wire:target="submitQuoteRequest"
                                class="inline-flex w-full cursor-pointer items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">
                                <span wire:loading.remove wire:target="submitQuoteRequest">
                                    Send Quote Request
                                </span>

                                <span wire:loading wire:target="submitQuoteRequest">
                                    Sending...
                                </span>
                            </button>
                        </form>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
