<?php

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service Details | Techwave')] class extends Component {
    public Service $service;
    public ?ServiceOption $serviceOption = null;
    public SiteSetting $siteSetting;
    public $entity;
    public $displayPlans;

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
            ->with(['category'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $optionSlug = request()->query('option');

        if ($optionSlug) {
            $this->serviceOption = ServiceOption::query()->with('activePlans')->where('service_id', $this->service->id)->where('slug', $optionSlug)->where('is_active', true)->firstOrFail();
        } else {
            $this->service->load('activePlans');
        }

        $this->otherServices = Service::query()->where('is_active', true)->where('id', '!=', $this->service->id)->latest()->limit(3)->get();
        $this->entity = $this->serviceOption ?? $this->service;
        $this->displayPlans = $this->serviceOption ? $this->serviceOption->activePlans : $this->service->activePlans;
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

        $plan = $this->displayPlans->firstWhere('id', $planId);

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

        $hasOneTimePrice = !empty($plan->has_one_time_price) && !empty($plan->price) && (float) $plan->price > 0;

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
        if (!$this->displayPlans->contains('id', $planId)) {
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
                'quote_phone.regex' => 'Please enter a valid Bangladeshi phone number.',
            ],
        );

        $selectedPackage = $this->parseSelectedPackage();

        $booking = Booking::create([
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

        BookingCreated::dispatch($booking);

        $this->dispatch('toast', message: 'Your booking request has been submitted successfully.', type: 'success');
    }

    public function serviceImage(): string
    {
        $image = $this->entity->image ?? $this->service->image;

        if ($image) {
            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return $image;
            }

            return asset('storage/' . $image);
        }

        return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1400&q=80';
    }

    public function whatsappLink(): string
    {
        $title = $this->entity->card_title;

        if ($this->siteSetting->whatsapp_url) {
            return $this->siteSetting->whatsapp_url . '?text=' . urlencode('Hello, I am interested in ' . $title);
        }

        $phone = preg_replace('/[^0-9]/', '', $this->siteSetting->phone ?: 'n/a');

        return 'https://wa.me/' . $phone . '?text=' . urlencode('Hello, I am interested in ' . $title);
    }
};
?>

<div class="relative text-white">
    @push('meta')
        <meta name="title" content="{{ $entity->meta_title ?: $entity->card_title }}">
        <meta name="description" content="{{ $entity->meta_description ?: $entity->short_description }}">
        <meta name="keywords" content="{{ $entity->meta_keywords }}">
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

                    @if ($serviceOption)
                        <a href="{{ route('client.services.options', ['slug' => $service->slug]) }}" wire:navigate
                            class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-cyan-300/80 transition hover:text-cyan-200">
                            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                            Back to {{ $service->card_title }} options
                        </a>
                    @endif

                    @php
                        $title = $entity->detail_title ?: $entity->card_title;
                    @endphp

                    <h1
                        class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl lg:text-7xl">
                        <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                            {{ $title }}
                        </span>
                    </h1>

                    @if ($entity->short_description)
                        <p class="mt-6 max-w-2xl text-sm leading-7 text-blue-100/72 sm:text-base sm:leading-8">
                            {{ $entity->short_description }}
                        </p>
                    @endif

                    @if (!empty($entity->tags))
                        <div class="mt-8 flex flex-wrap gap-3">
                            @foreach ($entity->tags as $tag)
                                <span
                                    class="rounded-full border border-white/10 bg-white/8 px-4 py-2 text-sm text-blue-100/80">
                                    {{ is_array($tag) ? $tag['name'] ?? ($tag['title'] ?? '') : $tag }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-8 flex flex-wrap gap-3">
                        @if ($displayPlans->count())
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
                            <img src="{{ $this->serviceImage() }}" alt="{{ $entity->card_title }}"
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
            <div class="space-y-10">

                <!-- Service Plans -->
                @if ($displayPlans->count())
                    @php
                        $planCount = $displayPlans->count();

                        $planGridClass = match (true) {
                            $planCount === 1 => 'grid gap-6 md:grid-cols-1 md:max-w-md md:mx-auto pt-6',
                            $planCount === 2 => 'grid gap-6 md:grid-cols-2 md:max-w-5xl md:mx-auto pt-6',
                            default => 'grid gap-6 md:grid-cols-2 xl:grid-cols-3 pt-6',
                        };

                        $hasAnyMonthlyPlan = $displayPlans->contains(function ($plan) {
                            return !empty($plan->has_monthly_price) &&
                                !empty($plan->monthly_price) &&
                                (float) $plan->monthly_price > 0;
                        });

                        $hasAnyYearlyPlan = $displayPlans->contains(function ($plan) {
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
                                        Select a suitable plan for {{ $entity->card_title }} based on your
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
                            @foreach ($displayPlans as $plan)
                                @php
                                    $isPopular = $plan->badge && str_contains(strtolower($plan->badge), 'popular');

                                    $cardClass = $isPopular
                                        ? 'border-cyan-300/25 bg-linear-to-b from-blue-500/12 to-white/8 shadow-[0_25px_80px_rgba(0,0,0,0.24)]'
                                        : 'border-white/10 bg-white/6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]';

                                    $features = is_array($plan->features) ? $plan->features : [];

                                    $hasOneTimePrice = !empty($plan->has_one_time_price) && !empty($plan->price) && (float) $plan->price > 0;

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
                                            (1 - (float) $plan->monthly_discount_price / (float) $plan->monthly_price) *
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
                                            (1 - (float) $plan->yearly_discount_price / (float) $plan->yearly_price) *
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
                                            <p class="text-xs font-medium uppercase tracking-[0.22em] text-cyan-200/80">
                                                {{ $entity->card_title }}
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

                                                    <span class="text-lg font-semibold text-blue-100/40 line-through">
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

                                    @if ($hasMonthlyPrice)
                                        <div x-show="billing === 'monthly'" x-cloak>
                                            <a href="{{ route('client.services.checkout', [$service->slug, $plan->slug]) }}?billing=monthly"
                                                wire:navigate
                                                class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3.5 font-semibold text-white shadow-lg shadow-blue-500/30 backdrop-blur-xl transition hover:-translate-y-0.5">
                                                Choose Plan
                                            </a>
                                        </div>
                                    @endif

                                    @if ($hasYearlyPrice)
                                        <div x-show="billing === 'yearly'" x-cloak>
                                            <a href="{{ route('client.services.checkout', [$service->slug, $plan->slug]) }}?billing=yearly"
                                                wire:navigate
                                                class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3.5 font-semibold text-white shadow-lg shadow-blue-500/30 backdrop-blur-xl transition hover:-translate-y-0.5">
                                                Choose Plan
                                            </a>
                                        </div>
                                    @endif

                                    @if ($hasMonthlyPrice && !$hasYearlyPrice)
                                        <div x-show="billing === 'yearly'" x-cloak>
                                            <button type="button" disabled
                                                class="mt-6 inline-flex w-full cursor-not-allowed items-center justify-center rounded-full border border-white/10 bg-white/8 px-6 py-3.5 font-semibold text-blue-100/45 backdrop-blur-xl">
                                                Choose Plan
                                            </button>
                                        </div>
                                    @endif

                                    @if ($hasYearlyPrice && !$hasMonthlyPrice)
                                        <div x-show="billing === 'monthly'" x-cloak>
                                            <button type="button" disabled
                                                class="mt-6 inline-flex w-full cursor-not-allowed items-center justify-center rounded-full border border-white/10 bg-white/8 px-6 py-3.5 font-semibold text-blue-100/45 backdrop-blur-xl">
                                                Choose Plan
                                            </button>
                                        </div>
                                    @endif

                                    @if (!$hasMonthlyPrice && !$hasYearlyPrice)
                                        <a href="{{ route('client.services.checkout', [$service->slug, $plan->slug]) }}?billing=one_time"
                                            wire:navigate
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
                                                    <span class="material-symbols-outlined text-[16px]">check</span>
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

                <!-- Service Information -->
                <div
                    class="relative overflow-hidden rounded-[36px] border border-white/10 bg-white/[0.035] p-5 shadow-[0_25px_90px_rgba(0,0,0,0.22)] backdrop-blur-2xl sm:p-7 lg:p-8">
                    <div
                        class="pointer-events-none absolute -left-24 top-10 h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl">
                    </div>
                    <div
                        class="pointer-events-none absolute -right-20 bottom-10 h-72 w-72 rounded-full bg-blue-500/10 blur-3xl">
                    </div>

                    <div class="space-y-6">
                        <!-- Overview -->
                        @if (!empty($entity->overview))
                            <div
                                class="relative overflow-hidden rounded-[30px] border border-white/10 bg-slate-950/35 p-6 sm:p-7">
                                <div
                                    class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-cyan-300/70 to-transparent">
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-200/75">
                                            Overview
                                        </p>

                                        <h3 class="mt-2 text-2xl font-bold text-white">
                                            Service Overview
                                        </h3>
                                    </div>

                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-cyan-300/15 bg-cyan-400/10 text-cyan-200">
                                        <span class="material-symbols-outlined">overview</span>
                                    </div>
                                </div>

                                <div class="service-rich-content mt-5 max-w-none">
                                    {!! $entity->overview !!}
                                </div>
                            </div>
                        @endif

                        <!-- Benefits -->
                        @if ($entity->benefits)
                            <div
                                class="relative overflow-hidden rounded-[30px] border border-white/10 bg-white/[0.045] p-6 sm:p-7">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-200/75">
                                            Benefits
                                        </p>

                                        <h3 class="mt-2 text-2xl font-bold text-white">
                                            Key Benefits
                                        </h3>
                                    </div>

                                    <p class="max-w-md text-sm leading-6 text-blue-100/58">
                                        Practical advantages your business will receive from this service.
                                    </p>
                                </div>

                                <div class="mt-6 grid gap-4 md:grid-cols-2">
                                    @foreach ($entity->benefits as $benefit)
                                        <div
                                            class="group relative overflow-hidden rounded-3xl border border-white/10 bg-slate-950/30 p-5 transition duration-300 hover:-translate-y-1 hover:border-cyan-300/25 hover:bg-cyan-400/[0.06]">
                                            <div
                                                class="absolute -right-10 -top-10 h-24 w-24 rounded-full bg-cyan-400/10 blur-2xl transition group-hover:bg-cyan-300/15">
                                            </div>

                                            <div class="relative flex gap-4">
                                                <div
                                                    class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-cyan-300/15 bg-cyan-400/10 text-cyan-200">
                                                    <span class="material-symbols-outlined text-[20px]">verified</span>
                                                </div>

                                                <div>
                                                    <h4 class="text-lg font-semibold text-white">
                                                        {{ is_array($benefit) ? $benefit['title'] ?? ($benefit['name'] ?? 'Benefit') : $benefit }}
                                                    </h4>

                                                    @if (is_array($benefit) && !empty($benefit['description']))
                                                        <p class="mt-2 text-sm leading-6 text-blue-100/64">
                                                            {{ $benefit['description'] }}
                                                        </p>
                                                    @elseif (is_array($benefit) && !empty($benefit['detail']))
                                                        <p class="mt-2 text-sm leading-6 text-blue-100/64">
                                                            {{ $benefit['detail'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Features -->
                        @if (!empty($entity->included_items))
                            <div
                                class="relative overflow-hidden rounded-[30px] border border-white/10 bg-slate-950/35 p-6 sm:p-7">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-200/75">
                                            Included
                                        </p>

                                        <h3 class="mt-2 text-2xl font-bold text-white">
                                            What’s Included
                                        </h3>
                                    </div>

                                    <div
                                        class="hidden rounded-full border border-white/10 bg-white/6 px-4 py-2 text-xs font-medium text-blue-100/60 sm:inline-flex">
                                        {{ count($entity->included_items) }} items
                                    </div>
                                </div>

                                <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($entity->included_items as $item)
                                        <div
                                            class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-sm leading-6 text-blue-50/82 transition hover:border-cyan-300/20 hover:bg-white/[0.07]">
                                            <span
                                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-400/12 text-cyan-200">
                                                <span class="material-symbols-outlined text-[16px]">done</span>
                                            </span>

                                            <span>
                                                {{ is_array($item) ? $item['title'] ?? ($item['name'] ?? ($item['text'] ?? '')) : $item }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Ideal For -->
                        @if ($entity->audience_title || $entity->audience_detail)
                            <div
                                class="relative overflow-hidden rounded-[30px] border border-cyan-300/15 bg-linear-to-br from-cyan-400/10 via-blue-500/8 to-white/[0.04] p-6 sm:p-7">
                                <div class="absolute right-8 top-8 h-28 w-28 rounded-full bg-cyan-300/10 blur-3xl">
                                </div>

                                <div class="relative grid gap-6 lg:grid-cols-[0.35fr_1fr] lg:items-start">
                                    <div>
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-300/20 bg-cyan-400/10 text-cyan-200">
                                            <span class="material-symbols-outlined">groups</span>
                                        </div>

                                        <h3 class="mt-4 text-2xl font-bold text-white">
                                            {{ $entity->audience_title ?: 'Who This Service Is For' }}
                                        </h3>
                                    </div>

                                    @if ($entity->audience_detail)
                                        <div class="space-y-4 text-sm leading-7 text-blue-100/72 sm:text-base">
                                            @foreach (preg_split('/\r\n|\r|\n/', $entity->audience_detail) as $line)
                                                @if (trim($line))
                                                    <p>{{ $line }}</p>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Other Services -->
                @if ($otherServices->count())
                    <div
                        class="relative overflow-hidden rounded-[36px] border border-white/10 bg-white/[0.035] p-5 shadow-[0_25px_90px_rgba(0,0,0,0.18)] backdrop-blur-2xl sm:p-7 lg:p-8">
                        <div
                            class="pointer-events-none absolute left-10 top-0 h-52 w-52 rounded-full bg-blue-500/10 blur-3xl">
                        </div>

                        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-4 py-2 text-xs text-blue-100/80 backdrop-blur-xl">
                                    <span class="h-2 w-2 rounded-full bg-cyan-300"></span>
                                    Related Solutions
                                </div>

                                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl">
                                    Other Services
                                </h2>

                                <p class="mt-2 text-sm leading-6 text-blue-100/66">
                                    Explore other solutions that can work well with this service.
                                </p>
                            </div>

                            <a href="{{ route('client.services') }}" wire:navigate
                                class="inline-flex w-fit items-center rounded-full border border-white/10 bg-white/8 px-5 py-2.5 text-sm font-medium text-white backdrop-blur-xl transition hover:bg-white/12">
                                View All
                            </a>
                        </div>

                        <div class="relative mt-6 grid gap-4 md:grid-cols-3">
                            @foreach ($otherServices as $otherService)
                                <a href="{{ route('client.services.details', $otherService->slug) }}" wire:navigate
                                    class="group relative overflow-hidden rounded-[28px] border border-white/10 bg-slate-950/30 p-5 transition duration-300 hover:-translate-y-1 hover:border-cyan-300/25 hover:bg-cyan-400/[0.055]">
                                    <div
                                        class="absolute -right-12 -top-12 h-28 w-28 rounded-full bg-cyan-400/10 blur-2xl transition group-hover:bg-cyan-300/15">
                                    </div>

                                    <div class="relative">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-300/15 bg-cyan-500/15 text-cyan-200">
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
                                            {{ Str::limit($otherService->short_description, 95) }}
                                        </p>

                                        <div
                                            class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-cyan-200/85">
                                            Learn more
                                            <span
                                                class="material-symbols-outlined text-[18px] transition group-hover:translate-x-1">
                                                arrow_forward
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Bottom Contact + Quote Section -->
                <div
                    class="relative overflow-hidden rounded-[38px] border border-white/10 bg-white/[0.04] p-4 shadow-[0_30px_100px_rgba(0,0,0,0.24)] backdrop-blur-2xl sm:p-6 lg:p-8">
                    <div
                        class="pointer-events-none absolute -left-24 top-10 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl">
                    </div>
                    <div
                        class="pointer-events-none absolute -right-24 bottom-10 h-80 w-80 rounded-full bg-blue-500/10 blur-3xl">
                    </div>
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-cyan-300/60 to-transparent">
                    </div>

                    <div class="relative grid gap-6 xl:grid-cols-[0.75fr_1.25fr]">
                        <!-- Contact / CTA Side -->
                        <div
                            class="relative overflow-hidden rounded-[32px] border border-cyan-300/15 bg-linear-to-br from-cyan-400/12 via-white/[0.045] to-blue-500/10 p-6 sm:p-8">
                            <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-cyan-300/10 blur-2xl">
                            </div>

                            <div class="relative">
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">
                                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                                    Quick Support
                                </div>

                                <h2 class="mt-5 text-3xl font-bold leading-tight text-white sm:text-4xl">
                                    Need help choosing the right plan?
                                </h2>

                                <p class="mt-4 text-sm leading-7 text-blue-100/68 sm:text-base">
                                    Share your requirements with us. We’ll review your needs and suggest the most
                                    suitable service package for your business.
                                </p>

                                <div class="mt-7 grid gap-4">
                                    <div class="rounded-[24px] border border-white/10 bg-slate-950/30 p-5">
                                        <div class="flex gap-4">
                                            <div
                                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-cyan-300/15 bg-cyan-400/10 text-cyan-200">
                                                <span class="material-symbols-outlined text-[24px]">call</span>
                                            </div>

                                            <div>
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-100/45">
                                                    Call Us
                                                </p>

                                                <p class="mt-2 text-lg font-bold text-white">
                                                    {{ $this->siteSetting->phone }}
                                                </p>

                                                <p class="mt-1 text-sm leading-6 text-blue-100/55">
                                                    Talk directly with our support team.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-[24px] border border-white/10 bg-slate-950/30 p-5">
                                        <div class="flex gap-4">
                                            <div
                                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-emerald-300/15 bg-emerald-400/10 text-emerald-200">
                                                <span class="material-symbols-outlined text-[24px]">forum</span>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-100/45">
                                                    WhatsApp
                                                </p>

                                                <p class="mt-2 text-lg font-bold text-white">
                                                    Fast Response
                                                </p>

                                                <p class="mt-1 text-sm leading-6 text-blue-100/55">
                                                    Send us your requirement instantly.
                                                </p>
                                            </div>
                                        </div>

                                        <a href="{{ $this->whatsappLink() }}" target="_blank"
                                            class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-linear-to-r from-emerald-500 to-green-400 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5">
                                            Chat on WhatsApp
                                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                                        </a>
                                    </div>
                                </div>

                                <div class="mt-7 rounded-[24px] border border-white/10 bg-white/[0.045] p-5">
                                    <div class="flex gap-3">
                                        <span
                                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-400/10 text-cyan-200">
                                            <span class="material-symbols-outlined text-[17px]">verified</span>
                                        </span>

                                        <p class="text-sm leading-7 text-blue-100/65">
                                            We’ll contact you after reviewing your selected plan, requirements, budget,
                                            and project details.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quote Form -->
                        <div id="quote-form"
                            class="relative scroll-mt-28 overflow-hidden rounded-[32px] border border-white/10 bg-slate-950/35 p-6 sm:p-8">
                            <div
                                class="pointer-events-none absolute -right-12 top-10 h-48 w-48 rounded-full bg-blue-500/10 blur-3xl">
                            </div>

                            <div class="relative">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div
                                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/7 px-4 py-2 text-xs font-medium text-blue-100/75">
                                            <span
                                                class="material-symbols-outlined text-[18px] text-cyan-200">request_quote</span>
                                            Quote Request
                                        </div>

                                        <h3 class="mt-4 text-2xl font-bold text-white sm:text-3xl">
                                            Request a custom quote
                                        </h3>

                                        <p class="mt-3 max-w-2xl text-sm leading-7 text-blue-100/62">
                                            Fill out the form and our team will get back to you with the right solution.
                                        </p>
                                    </div>

                                    <div
                                        class="hidden rounded-2xl border border-cyan-300/15 bg-cyan-400/10 px-4 py-3 text-cyan-100 sm:block">
                                        <span class="material-symbols-outlined text-[28px]">support_agent</span>
                                    </div>
                                </div>

                                <form wire:submit.prevent="submitQuoteRequest" class="mt-7">
                                    <input type="hidden" value="{{ $service->id }}">

                                    <div class="grid gap-5 md:grid-cols-2">
                                        @if ($displayPlans->count())
                                            <div class="md:col-span-2">
                                                <label class="mb-2 block text-sm font-medium text-blue-50/85">
                                                    Select Service Plan
                                                </label>

                                                <div class="relative">
                                                    <select wire:model.live="quote_selected_package"
                                                        class="contact-input appearance-none pr-10 bg-slate-950/80 text-white [color-scheme:dark]">
                                                        <option class="bg-slate-950 text-white" value="">
                                                            Select a plan or request custom quote
                                                        </option>

                                                        @foreach ($displayPlans as $plan)
                                                            @php
                                                                $hasOneTimePrice =
                                                                    !empty($plan->has_one_time_price) && !empty($plan->price) && (float) $plan->price > 0;

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
                                                                    (float) $plan->discount_price <
                                                                        (float) $plan->price;

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

                                                                $oneTimePrice = $hasDiscount
                                                                    ? $plan->discount_price
                                                                    : $plan->price;
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
                                            </div>
                                        @endif

                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-blue-50/85">Full
                                                Name</label>
                                            <input type="text" wire:model="quote_full_name"
                                                placeholder="Enter your name" class="contact-input">

                                            @error('quote_full_name')
                                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-blue-50/85">Phone
                                                Number</label>
                                            <input type="text" wire:model="quote_phone" placeholder="017XXXXXXXX"
                                                class="contact-input">

                                            @error('quote_phone')
                                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-blue-50/85">Email
                                                Address</label>

                                            <input type="email" wire:model="quote_email"
                                                placeholder="Enter your email" @auth readonly @endauth
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
                                            <label class="mb-2 block text-sm font-medium text-blue-50/85">Company
                                                Name</label>
                                            <input type="text" wire:model="quote_company_name"
                                                placeholder="Enter your company name" class="contact-input">

                                            @error('quote_company_name')
                                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="mb-2 block text-sm font-medium text-blue-50/85">Project
                                                Details</label>
                                            <textarea rows="5" wire:model="quote_message"
                                                placeholder="Tell us what you need for {{ $entity->card_title }}" class="contact-input resize-none"></textarea>

                                            @error('quote_message')
                                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div
                                        class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-xs leading-6 text-blue-100/45">
                                            By submitting this form, our team will review your request and contact you
                                            shortly.
                                        </p>

                                        <button type="submit" wire:loading.attr="disabled"
                                            wire:target="submitQuoteRequest"
                                            class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-7 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60 sm:min-w-52">
                                            <span wire:loading.remove wire:target="submitQuoteRequest">
                                                Send Quote Request
                                            </span>

                                            <span wire:loading wire:target="submitQuoteRequest">
                                                Sending...
                                            </span>

                                            <span wire:loading.remove wire:target="submitQuoteRequest"
                                                class="material-symbols-outlined text-[18px]">
                                                arrow_forward
                                            </span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
