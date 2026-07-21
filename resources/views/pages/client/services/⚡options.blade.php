<?php

use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service Options | Techwave')] class extends Component {
    public Service $service;
    public SiteSetting $siteSetting;

    public function mount(string $slug): void
    {
        $this->siteSetting = SiteSetting::current();

        $this->service = Service::query()
            ->with(['category', 'serviceOptions' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function optionImage($option): string
    {
        if ($option->image) {
            if (str_starts_with($option->image, 'http://') || str_starts_with($option->image, 'https://')) {
                return $option->image;
            }

            return asset('storage/' . $option->image);
        }

        return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80';
    }

    public function serviceImage(): string
    {
        if ($this->service->image) {
            if (str_starts_with($this->service->image, 'http://') || str_starts_with($this->service->image, 'https://')) {
                return $this->service->image;
            }

            return asset('storage/' . $this->service->image);
        }

        return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80';
    }
};
?>

<div class="relative text-white">
    @push('meta')
        <meta name="title" content="{{ $service->meta_title ?: $service->card_title . ' Options' }}">
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
                        {{ $service->category?->name ?? 'Service' }}
                    </div>

                    <h1
                        class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl lg:text-7xl">
                        <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                            {{ $service->card_title }}
                        </span>
                    </h1>

                    @if ($service->short_description)
                        <p class="mt-6 max-w-2xl text-sm leading-7 text-blue-100/72 sm:text-base sm:leading-8">
                            {{ $service->short_description }}
                        </p>
                    @endif

                    <p class="mt-4 text-sm text-blue-100/55">
                        Choose a specific option below to view plans and details.
                    </p>
                </div>

                <div class="relative hidden lg:block">
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

    <!-- Options Grid -->
    <section class="relative overflow-hidden pb-20 sm:pb-24">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="space-y-10">

                @if ($service->serviceOptions->count())
                    <div>
                        <div class="mb-8">
                            {{-- <div
                                class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-4 py-2 text-xs text-blue-100/80 backdrop-blur-xl">
                                <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                                Available Options
                            </div> --}}

                            <h2 class="mt-5 text-2xl font-bold text-white sm:text-3xl">
                                Choose an service for {{ $service->card_title }}
                            </h2>

                            <p class="mt-3 max-w-2xl text-sm leading-7 text-blue-100/66">
                                Select the service that best fits your requirements to view detailed plans and pricing.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($service->serviceOptions as $option)
                                <a href="{{ route('client.services.details', ['slug' => $service->slug, 'option' => $option->slug]) }}"
                                    wire:navigate
                                    class="group relative min-h-[430px] overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-2xl shadow-blue-950/20 transition-all duration-300 hover:-translate-y-1 hover:border-cyan-300/30 hover:shadow-cyan-950/30">

                                    <img src="{{ $this->optionImage($option) }}"
                                        alt="{{ $option->card_title }}"
                                        class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-110">

                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/75 to-blue-950/20"></div>
                                    <div class="absolute inset-0 bg-linear-to-br from-cyan-500/20 via-transparent to-blue-700/20"></div>

                                    <div class="relative z-10 flex h-full min-h-[430px] flex-col justify-between p-6">
                                        <div class="flex items-start justify-between gap-4">
                                            <span class="inline-flex items-center rounded-full border border-white/10 bg-slate-950/30 px-3 py-1 text-xs font-semibold text-cyan-100 backdrop-blur-md">
                                                {{ $service->category?->name ?? 'Service' }}
                                            </span>

                                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-slate-950/30 text-cyan-200 backdrop-blur-md">
                                                @if ($option->icon)
                                                    <span class="material-symbols-outlined">{{ $option->icon }}</span>
                                                @else
                                                    <span class="material-symbols-outlined">apps</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div>
                                            <h3 class="text-2xl font-bold text-white">
                                                {{ $option->card_title }}
                                            </h3>

                                            <p class="mt-3 text-sm leading-7 text-blue-100/75">
                                                {{ Str::limit($option->short_description, 145) }}
                                            </p>

                                            @if ($option->tags && count($option->tags))
                                                <ul class="mt-6 space-y-3 text-sm text-blue-50/85">
                                                    @foreach (array_slice($option->tags, 0, 3) as $tag)
                                                        <li class="service-bullet">{{ is_array($tag) ? $tag['title'] ?? ($tag['name'] ?? '') : $tag }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif

                                            <div class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-cyan-100">
                                                Explore Option
                                                <span class="material-symbols-outlined text-[18px] transition-transform duration-300 group-hover:translate-x-1">
                                                    arrow_forward
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-10 text-center">
                        <span class="material-symbols-outlined text-5xl text-blue-100/30">inventory_2</span>
                        <h3 class="mt-4 text-xl font-bold text-white">No options available</h3>
                        <p class="mt-2 text-sm text-blue-100/60">
                            No active service options found for this service yet.
                        </p>
                        <a href="{{ route('client.services') }}" wire:navigate
                            class="mt-6 inline-flex items-center gap-2 rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5">
                            Browse Other Services
                        </a>
                    </div>
                @endif

            </div>
        </div>
    </section>
</div>
