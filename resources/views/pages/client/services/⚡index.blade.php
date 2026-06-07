<?php

use App\Models\Service;
use App\Models\ServiceBooking;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Services | Techwave')] class extends Component {
    public int $perPage = 12;

    public string $full_name = '';
    public string $phone = '';
    public string $email = '';
    public string $company_name = '';
    public string $message = '';
    public string $service_id = '';

    public string $serviceSearch = '';

    public function getFilteredServicesProperty()
    {
        return Service::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->when($this->serviceSearch, function ($query) {
                $query->where('card_title', 'like', '%' . $this->serviceSearch . '%');
            })
            ->orderBy('card_title')
            ->limit(8)
            ->get(['id', 'category_id', 'card_title', 'slug']);
    }

    public function submitBooking(): void
    {
        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'service_id' => ['required', 'exists:services,id'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        ServiceBooking::create([
            'service_id' => $validated['service_id'] ?: null,
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
        ]);

        $this->reset(['full_name', 'phone', 'email', 'company_name', 'message', 'service_id', 'serviceSearch']);

        $this->dispatch('toast', message: 'Your service booking has been submitted successfully.', type: 'success');
    }

    public function loadMore(): void
    {
        $this->perPage += 12;
    }

    public function getServicesProperty()
    {
        return Service::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->latest()
            ->limit($this->perPage)
            ->get(['id', 'category_id', 'card_title', 'slug', 'icon', 'image', 'short_description', 'included_items', 'benefits']);
    }

    public function getTotalServicesProperty(): int
    {
        return Service::query()->where('is_active', true)->count();
    }

    public function serviceImage(Service $service): string
    {
        if ($service->image) {
            if (str_starts_with($service->image, 'http://') || str_starts_with($service->image, 'https://')) {
                return $service->image;
            }

            return asset('storage/' . $service->image);
        }

        return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=900&q=70';
    }

    public function serviceBullets(Service $service): array
    {
        if (!empty($service->included_items)) {
            return collect($service->included_items)
                ->take(3)
                ->map(function ($item) {
                    if (is_array($item)) {
                        return $item['title'] ?? ($item['name'] ?? ($item['text'] ?? null));
                    }

                    return $item;
                })
                ->filter()
                ->values()
                ->toArray();
        }

        if (!empty($service->benefits)) {
            return collect($service->benefits)
                ->take(3)
                ->map(function ($item) {
                    if (is_array($item)) {
                        return $item['title'] ?? ($item['name'] ?? null);
                    }

                    return $item;
                })
                ->filter()
                ->values()
                ->toArray();
        }

        return ['Professional setup', 'Reliable support', 'Business-ready solution'];
    }
};
?>

<div class="relative text-white">

    <!-- Main Services -->
    <section id="service-list" class="relative overflow-hidden py-20 sm:py-24">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="mb-14 text-center lg:mb-18">
                <div
                    class="mx-auto mb-5 inline-flex items-center justify-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                    Core Service Areas
                </div>

                <h2 class="text-3xl font-bold text-white sm:text-4xl lg:text-5xl">
                    Tailored services for
                    <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        growth, security, and stability
                    </span>
                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-blue-100/70 sm:text-base">
                    From foundational IT support to advanced enterprise protection, we design solutions that fit
                    your business stage and operational needs.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">

                @php
                    $services = $this->services;
                    $totalServices = $this->totalServices;
                @endphp

                @forelse ($services as $service)
                    <a href="{{ route('client.services.details', ['slug' => $service->slug]) }}" wire:navigate
                        class="group relative min-h-107.5 overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-2xl shadow-blue-950/20 transition-all duration-300 hover:-translate-y-1 hover:border-cyan-300/30 hover:shadow-cyan-950/30">

                        <img src="{{ $this->serviceImage($service) }}" alt="{{ $service->card_title }}" width="900"
                            height="650" loading="lazy" decoding="async"
                            class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-110">

                        <div class="absolute inset-0 bg-linear-to-t from-slate-950 via-slate-950/75 to-blue-950/20">
                        </div>
                        <div class="absolute inset-0 bg-linear-to-br from-cyan-500/20 via-transparent to-blue-700/20">
                        </div>

                        <div class="relative z-10 flex h-full min-h-107.5 flex-col justify-between p-6">
                            <div class="flex items-start justify-between gap-4">
                                <span
                                    class="inline-flex items-center rounded-full border border-white/10 bg-slate-950/30 px-3 py-1 text-xs font-semibold text-cyan-100 backdrop-blur-md">
                                    {{ $service->category?->name ?? 'Service' }}
                                </span>

                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-slate-950/30 text-cyan-200 backdrop-blur-md">
                                    @if ($service->icon)
                                        <span class="material-symbols-outlined">{{ $service->icon }}</span>
                                    @else
                                        <span class="material-symbols-outlined">apps</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <h3 class="text-2xl font-bold text-white">
                                    {{ $service->card_title }}
                                </h3>

                                <p class="mt-3 text-sm leading-7 text-blue-100/75">
                                    {{ Str::limit($service->short_description, 145) }}
                                </p>

                                <ul class="mt-6 space-y-3 text-sm text-blue-50/85">
                                    @foreach ($this->serviceBullets($service) as $bullet)
                                        <li class="service-bullet">{{ $bullet }}</li>
                                    @endforeach
                                </ul>

                                <div class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-cyan-100">
                                    Explore Service
                                    <span
                                        class="material-symbols-outlined text-[18px] transition-transform duration-300 group-hover:translate-x-1">
                                        arrow_forward
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-10 text-center">
                        <h3 class="text-2xl font-bold text-white">No services found</h3>
                        <p class="mt-3 text-sm text-blue-100/70">
                            Please add active services from your admin panel.
                        </p>
                    </div>
                @endforelse

            </div>

            @if ($services->count() < $totalServices)
                <div class="mt-12 flex justify-center">
                    <button type="button" wire:click="loadMore" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/8 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/12 disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">

                        <span wire:loading.remove wire:target="loadMore">Load More Services</span>
                        <span wire:loading wire:target="loadMore">Loading...</span>

                        <span wire:loading.remove wire:target="loadMore" class="material-symbols-outlined text-[18px]">
                            expand_more
                        </span>
                    </button>
                </div>
            @endif
        </div>
    </section>

    <!-- Process -->
    <section class="relative overflow-hidden py-20 sm:py-24">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute left-[8%] top-10 h-40 w-40 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute right-[10%] bottom-8 h-52 w-52 rounded-full bg-blue-500/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="mb-14 text-center lg:mb-18">
                <div
                    class="mx-auto mb-5 inline-flex items-center justify-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                    How We Work
                </div>

                <h2 class="text-3xl font-bold text-white sm:text-4xl lg:text-5xl">
                    A refined process that turns
                    <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        complexity into clarity
                    </span>
                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-blue-100/70 sm:text-base">
                    We combine business understanding, technical precision, and structured execution to deliver
                    solutions
                    that feel smooth from planning to long-term support.
                </p>
            </div>

            <div class="relative">
                <div
                    class="absolute left-1/2 top-0 hidden h-full w-px -translate-x-1/2 bg-linear-to-b from-cyan-300/0 via-cyan-300/30 to-cyan-300/0 lg:block">
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="lg:pr-8">
                        <div class="process-premium-card lg:mr-8">
                            <div class="process-premium-top">
                                <div class="process-premium-step">01</div>
                                <div class="process-premium-icon bg-cyan-500/15 text-cyan-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M7.5 12h9m-9 4.5h5.25M6 3.75h12A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6A2.25 2.25 0 016 3.75z" />
                                    </svg>
                                </div>
                            </div>

                            <h3 class="mt-6 text-2xl font-bold text-white">Assess & Understand</h3>
                            <p class="mt-3 text-sm leading-7 text-blue-100/68">
                                We review your current setup, risks, pain points, business priorities, and growth
                                objectives to understand the full picture before making decisions.
                            </p>
                        </div>
                    </div>

                    <div class="lg:pt-16 lg:pl-8">
                        <div class="process-premium-card lg:ml-8">
                            <div class="process-premium-top">
                                <div class="process-premium-step">02</div>
                                <div class="process-premium-icon bg-blue-500/15 text-blue-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 6.75h16.5M3.75 12h10.5M3.75 17.25h6.75" />
                                    </svg>
                                </div>
                            </div>

                            <h3 class="mt-6 text-2xl font-bold text-white">Plan & Architect</h3>
                            <p class="mt-3 text-sm leading-7 text-blue-100/68">
                                We design the right structure, choose the best-fit technologies, and define the
                                implementation flow for stability, usability, and scale.
                            </p>
                        </div>
                    </div>

                    <div class="lg:-mt-2 lg:pr-8">
                        <div class="process-premium-card lg:mr-8">
                            <div class="process-premium-top">
                                <div class="process-premium-step">03</div>
                                <div class="process-premium-icon bg-sky-500/15 text-sky-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" />
                                    </svg>
                                </div>
                            </div>

                            <h3 class="mt-6 text-2xl font-bold text-white">Implement & Optimize</h3>
                            <p class="mt-3 text-sm leading-7 text-blue-100/68">
                                Our team builds, configures, secures, and tests the solution carefully so it
                                performs well
                                in real business conditions.
                            </p>
                        </div>
                    </div>

                    <div class="lg:pt-14 lg:pl-8">
                        <div class="process-premium-card lg:ml-8">
                            <div class="process-premium-top">
                                <div class="process-premium-step">04</div>
                                <div class="process-premium-icon bg-violet-500/15 text-violet-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M18 8.25V6.75A2.25 2.25 0 0015.75 4.5h-7.5A2.25 2.25 0 006 6.75v10.5A2.25 2.25 0 008.25 19.5h7.5A2.25 2.25 0 0018 17.25v-1.5" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 12H9m0 0 2.25-2.25M9 12l2.25 2.25" />
                                    </svg>
                                </div>
                            </div>

                            <h3 class="mt-6 text-2xl font-bold text-white">Support & Evolve</h3>
                            <p class="mt-3 text-sm leading-7 text-blue-100/68">
                                After launch, we stay involved with monitoring, improvements, maintenance, and
                                strategic
                                guidance so your systems stay reliable as you grow.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="relative overflow-hidden py-20 sm:py-24">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute left-[5%] bottom-10 h-44 w-44 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute right-[8%] top-8 h-56 w-56 rounded-full bg-blue-500/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="mb-14 text-center">
                <div
                    class="mx-auto mb-5 inline-flex items-center justify-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                    Why Choose Us
                </div>

                <h2 class="text-3xl font-bold text-white sm:text-4xl lg:text-5xl">
                    More than service delivery —
                    <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        we build dependable partnerships
                    </span>
                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-blue-100/70 sm:text-base">
                    We bring together business thinking, execution quality, and technical depth so your company gets
                    solutions that are practical, secure, and built to last.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="why-premium-card">
                    <div class="why-premium-icon bg-cyan-500/15 text-cyan-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 7.5l9-4.5 9 4.5m-18 0 9 4.5m-9-4.5V16.5l9 4.5m9-13.5v9l-9 4.5m0-9V21" />
                        </svg>
                    </div>

                    <h3 class="mt-6 text-xl font-bold text-white">Business-Driven Strategy</h3>
                    <p class="mt-3 text-sm leading-7 text-blue-100/68">
                        We shape every solution around business performance, operational clarity, and future growth.
                    </p>
                </div>

                <div class="why-premium-card why-premium-card-featured">
                    <div class="why-premium-icon bg-blue-500/15 text-blue-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" />
                        </svg>
                    </div>

                    <h3 class="mt-6 text-xl font-bold text-white">Reliable Delivery</h3>
                    <p class="mt-3 text-sm leading-7 text-blue-100/68">
                        Clear communication, disciplined execution, and dependable support from first discussion to
                        final rollout.
                    </p>
                </div>

                <div class="why-premium-card">
                    <div class="why-premium-icon bg-sky-500/15 text-sky-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </div>

                    <h3 class="mt-6 text-xl font-bold text-white">Security by Design</h3>
                    <p class="mt-3 text-sm leading-7 text-blue-100/68">
                        Security is built into planning, access control, systems architecture, and support
                        workflows.
                    </p>
                </div>

                <div class="why-premium-card">
                    <div class="why-premium-icon bg-violet-500/15 text-violet-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>

                    <h3 class="mt-6 text-xl font-bold text-white">Scalable Thinking</h3>
                    <p class="mt-3 text-sm leading-7 text-blue-100/68">
                        Our work is designed to support where your business is today and where it needs to go next.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact CTA Form -->
    <section class="relative overflow-hidden py-20 sm:py-24">

        <div class="relative mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div
                class="overflow-hidden rounded-[36px] border border-white/15 bg-white/8 shadow-[0_30px_100px_rgba(0,0,0,0.28)] backdrop-blur-2xl">
                <div class="grid grid-cols-1 lg:grid-cols-[1.05fr_0.95fr]">
                    <div class="px-6 py-10 sm:px-8 sm:py-12 lg:px-12 lg:py-14">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/6 px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                            <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                            Contact Us
                        </div>

                        <h2 class="mt-6 text-3xl font-bold text-white sm:text-4xl lg:text-5xl">
                            Let’s talk about your
                            <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                                next IT solution
                            </span>
                        </h2>

                        <p class="mt-5 max-w-xl text-sm leading-7 text-blue-100/70 sm:text-base">
                            Tell us what you need — whether it is support, infrastructure, cybersecurity, web
                            development,
                            or a full business IT setup — and our team will get back to you.
                        </p>

                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            <div class="contact-info-card">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Email</p>

                                @if ($siteSetting->email)
                                    <a href="mailto:{{ $siteSetting->email }}"
                                        class="mt-2 text-sm font-semibold text-white">
                                        {{ $siteSetting->email }}
                                    </a>
                                @else
                                    <p class="mt-2 text-sm font-semibold text-white">
                                        info@example.com
                                    </p>
                                @endif
                            </div>

                            <div class="contact-info-card">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Phone</p>

                                @if ($siteSetting->phone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $siteSetting->phone) }}"
                                        class="mt-2 text-sm font-semibold text-white">
                                        {{ $siteSetting->phone }}
                                    </a>
                                @else
                                    <p class="mt-2 text-sm font-semibold text-white">
                                        +8809638-101601
                                    </p>
                                @endif
                            </div>

                            <div class="contact-info-card sm:col-span-2">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Location</p>
                                <p class="mt-2 text-sm font-semibold text-white">
                                    {{ $siteSetting->location ?: 'Business hours support with priority response options' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="border-t border-white/10 bg-slate-950/20 px-6 py-10 sm:px-8 sm:py-12 lg:border-l lg:border-t-0 lg:px-10 lg:py-14">

                        <form wire:submit.prevent="submitBooking" class="space-y-5">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-blue-50/85">Full Name</label>
                                    <input type="text" wire:model="full_name" placeholder="Enter your name"
                                        class="contact-input">
                                    @error('full_name')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-blue-50/85">Phone</label>
                                    <input type="text" wire:model="phone" placeholder="Enter your phone"
                                        class="contact-input">
                                    @error('phone')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-blue-50/85">Email
                                        Address</label>
                                    <input type="email" wire:model="email" placeholder="Enter your email"
                                        class="contact-input">
                                    @error('email')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-blue-50/85">
                                        Service Needed
                                    </label>

                                    <div x-data="{
                                        open: false,
                                        selectedService: @entangle('serviceSearch').live,
                                    }" class="relative">
                                        <input type="text" wire:model.live.debounce.300ms="serviceSearch"
                                            @focus="open = true" @click="open = true" @keydown.escape="open = false"
                                            placeholder="Search service..." class="contact-input pr-12">

                                        <input type="hidden" wire:model="service_id">

                                        <!-- Search Icon -->
                                        <div
                                            class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-blue-100/60">
                                            <span class="material-symbols-outlined text-[20px]">
                                                search
                                            </span>
                                        </div>

                                        @if ($serviceSearch)
                                            <button type="button" wire:click="$set('serviceSearch', '')"
                                                wire:click.prevent="$set('service_id', '')"
                                                class="absolute inset-y-0 right-12 flex items-center text-blue-100/60 transition hover:text-white">
                                                <span class="material-symbols-outlined text-[18px]">
                                                    close
                                                </span>
                                            </button>
                                        @endif

                                        <div x-show="open" @click.outside="open = false" x-transition
                                            class="absolute left-0 right-0 z-30 mt-2 max-h-64 overflow-y-auto rounded-2xl border border-white/10 bg-slate-950/95 p-2 shadow-2xl shadow-blue-950/40 backdrop-blur-2xl  [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:bg-gray-300
     hover:[&::-webkit-scrollbar-thumb]:bg-gray-400 [&::-webkit-scrollbar-thumb]:rounded-full"
                                            style="display: none;">
                                            @forelse ($this->filteredServices as $service)
                                                <button type="button"
                                                    wire:click="$set('service_id', '{{ $service->id }}'); $set('serviceSearch', '{{ addslashes($service->card_title) }}')"
                                                    @click="open = false"
                                                    class="w-full rounded-xl px-4 py-3 text-left transition hover:bg-white/10">
                                                    <span class="block text-sm font-semibold text-white">
                                                        {{ $service->card_title }}
                                                    </span>

                                                    @if ($service->category)
                                                        <span class="mt-1 block text-xs text-blue-100/55">
                                                            {{ $service->category->name }}
                                                        </span>
                                                    @endif
                                                </button>
                                            @empty
                                                <div class="px-4 py-4 text-sm text-blue-100/60">
                                                    No service found.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>

                                    @error('service_id')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-blue-50/85">Company Name</label>
                                <input type="text" wire:model="company_name" placeholder="Enter your company name"
                                    class="contact-input">
                                @error('company_name')
                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-blue-50/85">Your Message</label>
                                <textarea rows="5" wire:model="message" placeholder="Tell us about your requirements"
                                    class="contact-input resize-none"></textarea>
                                @error('message')
                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" wire:loading.attr="disabled" wire:target="submitBooking"
                                class="inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-4 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">

                                <span wire:loading.remove wire:target="submitBooking">
                                    Send Inquiry
                                </span>

                                <span wire:loading wire:target="submitBooking">
                                    Sending...
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
