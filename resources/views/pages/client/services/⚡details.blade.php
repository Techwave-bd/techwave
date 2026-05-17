<?php

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public Service $service;
    public SiteSetting $siteSetting;

    public $otherServices;

    public ?int $quote_service_plan_id = null;

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

    public function selectQuotePlan(int $planId): void
    {
        if (!$this->service->activePlans->contains('id', $planId)) {
            return;
        }

        $this->quote_service_plan_id = $planId;
    }

    public function submitQuoteRequest(): void
    {
        if (auth()->check()) {
            $this->quote_email = auth()->user()->email;
        }

        $validated = $this->validate(
            [
                'quote_service_plan_id' => ['nullable', 'integer', Rule::exists('service_plans', 'id')->where('service_id', $this->service->id)],
                'quote_full_name' => ['required', 'string', 'max:255'],
                'quote_phone' => ['required', 'string', 'regex:/^(?:\+8801|8801|01)[3-9]\d{8}$/'],
                'quote_email' => ['nullable', 'email', 'max:255'],
                'quote_company_name' => ['nullable', 'string', 'max:255'],
                'quote_message' => ['nullable', 'string', 'max:3000'],
            ],
            [
                'quote_phone.regex' => 'Please enter a valid Bangladeshi phone number. Example: 017XXXXXXXX, +88017XXXXXXXX, or 88017XXXXXXXX.',
                'quote_service_plan_id.exists' => 'Please select a valid service plan.',
            ],
        );

        ServiceBooking::create([
            'service_id' => $this->service->id,
            'service_plan_id' => $validated['quote_service_plan_id'] ?? null,
            'full_name' => $validated['quote_full_name'],
            'phone' => $validated['quote_phone'],
            'email' => $validated['quote_email'] ?? null,
            'company_name' => $validated['quote_company_name'] ?? null,
            'message' => $validated['quote_message'] ?? null,
            'status' => 'pending',
        ]);

        $this->reset(['quote_service_plan_id', 'quote_full_name', 'quote_phone', 'quote_company_name', 'quote_message']);

        if (auth()->check()) {
            $this->quote_email = auth()->user()->email;
            $this->quote_full_name = auth()->user()->name ?? '';
            $this->quote_phone = auth()->user()->phone ?? '';
        } else {
            $this->quote_email = '';
        }

        $this->dispatch('toast', message: 'Your quote request has been submitted successfully.', type: 'success');
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
                        $mainTitle = $service->card_title;
                        $detailTitle = $service->detail_title ?: $service->card_title;

                        $gradientTitle = trim(str_replace($mainTitle, '', $detailTitle));

                        if (blank($gradientTitle) && $service->short_description) {
                            $gradientTitle = null;
                        }
                    @endphp

                    <h1
                        class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl lg:text-7xl">
                        {{ $mainTitle }}

                        @if ($gradientTitle)
                            <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                                {{ $gradientTitle }}
                            </span>
                        @endif
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
                        <div id="service-plans" class="service-detail-card scroll-mt-28">
                            <div class="mb-8">
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-4 py-2 text-xs text-blue-100/80 backdrop-blur-xl">
                                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                                    Service Plans
                                </div>

                                <h2 class="mt-5 text-2xl font-bold text-white sm:text-3xl">
                                    Choose the right package
                                </h2>

                                <p class="mt-3 max-w-2xl text-sm leading-7 text-blue-100/66">
                                    Select a suitable plan for {{ $service->card_title }} based on your business needs,
                                    budget, and support requirements.
                                </p>
                            </div>

                            @php
                                $planCount = $service->activePlans->count();

                                $planGridClass = match (true) {
                                    $planCount === 1 => 'grid gap-6 md:grid-cols-1 md:max-w-md md:mx-auto',
                                    $planCount === 2 => 'grid gap-6 md:grid-cols-2 md:max-w-5xl md:mx-auto',
                                    default => 'grid gap-6 md:grid-cols-2 xl:grid-cols-3',
                                };
                            @endphp

                            <div class="{{ $planGridClass }}">
                                @foreach ($service->activePlans as $plan)
                                    @php
                                        $isPopular = $plan->badge && str_contains(strtolower($plan->badge), 'popular');

                                        $cardClass = $isPopular
                                            ? 'border-cyan-300/25 bg-linear-to-b from-blue-500/12 to-white/8 shadow-[0_25px_80px_rgba(0,0,0,0.24)]'
                                            : 'border-white/10 bg-white/6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]';

                                        $features = is_array($plan->features) ? $plan->features : [];

                                        $hasDiscount =
                                            !empty($plan->discount_price) &&
                                            (float) $plan->discount_price > 0 &&
                                            (float) $plan->discount_price < (float) $plan->price;

                                        $discountPercent = $hasDiscount
                                            ? round((1 - (float) $plan->discount_price / (float) $plan->price) * 100)
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
                                            @if ($plan->price)
                                                @if ($hasDiscount)
                                                    <div class="flex flex-wrap items-end gap-3">
                                                        <span class="text-4xl font-bold text-white">
                                                            ৳ {{ number_format((float) $plan->discount_price, 0) }}
                                                        </span>

                                                        <span
                                                            class="pb-1 text-lg font-semibold text-blue-100/40 line-through">
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
                                            @else
                                                <span class="text-3xl font-bold text-white">Custom</span>
                                            @endif
                                        </div>

                                        @if ($plan->buy_url)
                                            <a href="{{ $plan->buy_url }}" target="_blank"
                                                class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3.5 font-semibold text-white shadow-lg shadow-blue-500/30 backdrop-blur-xl transition hover:-translate-y-0.5">
                                                Choose Plan
                                            </a>
                                        @else
                                            <a href="#quote-form" wire:click="selectQuotePlan({{ $plan->id }})"
                                                class="mt-6 inline-flex w-full items-center justify-center rounded-full border border-white/15 bg-white/8 px-6 py-3.5 font-semibold text-white backdrop-blur-xl transition hover:bg-white/12">
                                                Request This Plan
                                            </a>
                                        @endif

                                        <ul class="mt-7 space-y-3 text-sm text-blue-50/85">
                                            @forelse ($features as $feature)
                                                <li class="flex gap-3">
                                                    <span
                                                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-cyan-400/15 text-cyan-200">
                                                        <span class="material-symbols-outlined text-[16px]">check</span>
                                                    </span>

                                                    <span>
                                                        {{ is_array($feature) ? $feature['title'] ?? ($feature['name'] ?? ($feature['text'] ?? '')) : $feature }}
                                                    </span>
                                                </li>
                                            @empty
                                                <li class="flex gap-3">
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
                                        <select wire:model="quote_service_plan_id"
                                            class="contact-input appearance-none pr-10 bg-slate-950/80 text-white [color-scheme:dark]">
                                            <option class="bg-slate-950 text-white" value="">
                                                Select a plan or request custom quote
                                            </option>

                                            @foreach ($service->activePlans as $plan)
                                                @php
                                                    $hasDiscount =
                                                        !empty($plan->discount_price) &&
                                                        (float) $plan->discount_price > 0 &&
                                                        (float) $plan->discount_price < (float) $plan->price;

                                                    $displayPrice = $plan->price
                                                        ? ' - ৳' .
                                                            number_format(
                                                                (float) ($hasDiscount
                                                                    ? $plan->discount_price
                                                                    : $plan->price),
                                                                0,
                                                            )
                                                        : ' - Custom';
                                                @endphp

                                                <option class="bg-slate-950 text-white" value="{{ $plan->id }}">
                                                    {{ $plan->name }}{{ $displayPrice }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <span
                                            class="material-symbols-outlined pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-blue-100/45">
                                            expand_more
                                        </span>
                                    </div>

                                    @error('quote_service_plan_id')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
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
