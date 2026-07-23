<?php

use App\Models\Blog;
use App\Models\CompanyLogo;
use App\Models\PricingPlan;
use App\Models\Project;
use App\Models\Service;
use App\Models\HomePageSetting;
use App\Support\FeaturedServiceGrid;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Techwave | Complete IT Solutions in Bangladesh – Web, Email, Network &amp; Cybersecurity Experts')] class extends Component {
    public array $homePageSettings = [];

    public function mount(): void
    {
        $this->homePageSettings = HomePageSetting::resolved();
    }

    public function companyLogos()
    {
        return CompanyLogo::query()->where('is_active', true)->orderBy('sort_order')->latest()->get();
    }

    public function services()
    {
        $style = FeaturedServiceGrid::normalizeStyle(data_get($this->homePageSettings, 'services.layout_style', 'original_bento'));

        $count = FeaturedServiceGrid::countForStyle($style);

        return Service::query()->with('category')->where('is_active', true)->where('is_featured', true)->latest()->limit($count)->get();
    }

    public function homeSettingImage(?string $image, string $fallback): string
    {
        if (blank($image)) {
            return $fallback;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return asset('storage/' . $image);
    }

    public function pricingPlans()
    {
        return PricingPlan::query()
            ->where('status', 'active')
            ->orderByRaw(
                "
            CASE plan_type
                WHEN 'startup' THEN 1
                WHEN 'business' THEN 2
                WHEN 'enterprise' THEN 3
                ELSE 4
            END
        ",
            )
            ->latest()
            ->take(3)
            ->get();
    }

    public function getProjectsProperty()
    {
        return Project::query()->with('category')->where('is_active', true)->where('is_featured', true)->latest('completed_at')->latest()->limit(6)->get();
    }

    public function projectImage(Project $project): string
    {
        if ($project->thumbnail) {
            if (str_starts_with($project->thumbnail, 'http://') || str_starts_with($project->thumbnail, 'https://')) {
                return $project->thumbnail;
            }

            return asset('storage/' . $project->thumbnail);
        }

        return 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1400&q=80';
    }

    public function projectType(Project $project): string
    {
        return $project->project_type ?: $project->category?->name ?: 'Project';
    }

    public function projectTechnologies(Project $project, int $limit = 3): array
    {
        if (empty($project->technologies)) {
            return [];
        }

        return collect($project->technologies)
            ->take($limit)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['name'] ?? ($item['title'] ?? null);
                }

                return $item;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    public function getFeaturedBlogsProperty()
    {
        return Blog::query()->with('category')->where('is_active', true)->where('is_featured', true)->latest('published_at')->limit(3)->get();
    }

    public function blogImage(Blog $blog): ?string
    {
        if (blank($blog->thumbnail)) {
            return null;
        }

        if (str_starts_with($blog->thumbnail, 'http://') || str_starts_with($blog->thumbnail, 'https://')) {
            return $blog->thumbnail;
        }

        return asset('storage/' . $blog->thumbnail);
    }
};
?>

<div>
    {{-- Hero Section --}}
    @php
        $heroSettings = $homePageSettings['hero'];
    @endphp

    @if ($heroSettings['enabled'])
        <section x-data="{ mobileMenu: false }"
            class="relative min-h-[calc(100vh-100px)] md:min-h-screen overflow-hidden text-white">
            <div class="relative z-10">
                <div class="max-w-350 mx-auto px-4 sm:px-6 py-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-12 items-center">
                        <div class="lg:max-w-162.5 order-1 lg:order-1 text-center lg:text-left">
                            <h1
                                class="text-[34px] leading-[1.05] sm:text-[48px] md:text-[64px] lg:text-[78px] font-extrabold tracking-tight text-white">
                                {{ $heroSettings['title'] }}<br>
                                <span class="text-blue-300">{{ $heroSettings['highlighted_title'] }}</span>
                            </h1>

                            <p class="mt-6 sm:mt-8 leading-[1.7] text-blue-50/80 max-w-2xl">
                                {{ $heroSettings['description'] }}
                            </p>

                            <div class="mt-8 sm:mt-10 flex flex-wrap items-center justify-center lg:justify-start gap-4">
                                @if (filled($heroSettings['primary_button_text']) && filled($heroSettings['primary_button_url']))
                                    <a href="{{ $heroSettings['primary_button_url'] }}"
                                        class="inline-flex items-center justify-center px-6 py-3 md:px-8 md:py-4 rounded-full bg-linear-to-r from-blue-500 to-sky-400 text-white font-semibold shadow-lg shadow-blue-500/30 hover:-translate-y-0.5 transition">
                                        {{ $heroSettings['primary_button_text'] }}
                                    </a>
                                @endif

                                @if (filled($heroSettings['secondary_button_text']) && filled($heroSettings['secondary_button_url']))
                                    <a href="{{ $heroSettings['secondary_button_url'] }}"
                                        class="inline-flex items-center justify-center gap-2 px-6 py-3 md:px-8 md:py-4 rounded-full bg-white/10 backdrop-blur-xl border border-white/20 text-white font-semibold hover:bg-white/15 transition">
                                        {{ $heroSettings['secondary_button_text'] }}
                                        <span class="material-symbols-outlined text-[18px]">north_east</span>
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="relative order-1 lg:order-2">
                            <div class="relative mx-auto h-125 w-full max-w-155 sm:h-140">
                                <div
                                    class="absolute left-1/2 top-1/2 w-[80%] md:w-[72%] -translate-x-1/2 -translate-y-1/2 animate-[floatY_6s_ease-in-out_infinite] rounded-[28px] border border-white/15 bg-white/10 p-3 shadow-[0_20px_60px_rgba(0,0,0,0.35)] backdrop-blur-2xl">
                                    <div class="overflow-hidden rounded-[22px]">
                                        <img src="{{ $this->homeSettingImage($heroSettings['main_image'], 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1200&q=80') }}"
                                            alt="{{ $heroSettings['title'] }}"
                                            class="h-70 w-full object-cover sm:h-100" />
                                    </div>
                                </div>

                                <div
                                    class="absolute left-0 top-10 w-32 animate-[floatY_5s_ease-in-out_infinite] rounded-xl md:rounded-[22px] border border-white/15 bg-white/10 p-2 shadow-[0_15px_40px_rgba(0,0,0,0.28)] backdrop-blur-xl sm:w-48">
                                    <div class="overflow-hidden rounded-xl md:rounded-[18px]">
                                        <img src="{{ $this->homeSettingImage($heroSettings['top_left_image'], 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=800&q=80') }}"
                                            alt="{{ $heroSettings['top_left_title'] }}"
                                            class="h-20 w-full object-cover sm:h-32" />
                                    </div>
                                    <div class="md:p-3">
                                        <p class="text-xs md:text-sm font-semibold text-white">
                                            {{ $heroSettings['top_left_title'] }}</p>
                                        <p class="mt-1 text-[10px] md:text-xs text-blue-100/65">
                                            {{ $heroSettings['top_left_description'] }}</p>
                                    </div>
                                </div>

                                <div
                                    class="absolute right-0 top-4 w-30 md:w-44 animate-[floatY_7s_ease-in-out_infinite] rounded-xl lg:rounded-[22px] border border-white/15 bg-white/10 p-2 shadow-[0_15px_40px_rgba(0,0,0,0.28)] backdrop-blur-xl sm:w-52">
                                    <div class="overflow-hidden rounded-lg md:rounded-[18px]">
                                        <img src="{{ $this->homeSettingImage($heroSettings['top_right_image'], 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800&q=80') }}"
                                            alt="{{ $heroSettings['top_right_title'] }}"
                                            class="h-20 w-full object-cover sm:h-36" />
                                    </div>
                                    <div class="md:p-3">
                                        <p class="text-xs md:text-sm font-semibold text-white">
                                            {{ $heroSettings['top_right_title'] }}</p>
                                        <p class="mt-1 text-[10px] md:text-xs text-blue-100/65">
                                            {{ $heroSettings['top_right_description'] }}</p>
                                    </div>
                                </div>

                                <div
                                    class="absolute bottom-10 left-2 md:left-6 w-28 md:w-36 animate-[floatY_6.5s_ease-in-out_infinite] rounded-xl md:rounded-[20px] border border-white/15 bg-white/10 p-2 shadow-[0_15px_35px_rgba(0,0,0,0.25)] backdrop-blur-xl sm:w-40">
                                    <div class="overflow-hidden rounded-lg md:rounded-2xl">
                                        <img src="{{ $this->homeSettingImage($heroSettings['bottom_left_image'], 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80') }}"
                                            alt="Analytics dashboard" class="h-18 w-full object-cover sm:h-28" />
                                    </div>
                                </div>

                                <div
                                    class="absolute bottom-6 right-3 md:right-8 w-30 animate-[floatY_5.5s_ease-in-out_infinite] rounded-xl md:rounded-[20px] border border-white/15 bg-white/10 p-2 shadow-[0_15px_35px_rgba(0,0,0,0.25)] backdrop-blur-xl sm:w-40">
                                    <div class="overflow-hidden rounded-lg md:rounded-2xl">
                                        <img src="{{ $this->homeSettingImage($heroSettings['bottom_right_image'], 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80') }}"
                                            alt="Technology development" class="h-20 w-full object-cover sm:h-28" />
                                    </div>
                                </div>

                                <div class="absolute left-10 top-24 h-24 w-24 rounded-full bg-cyan-400/20 blur-3xl">
                                </div>
                                <div class="absolute bottom-16 right-10 h-28 w-28 rounded-full bg-blue-500/20 blur-3xl">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($heroSettings['show_trusted_logos'])
                <div class="mt-16 sm:mt-20" x-data="logoMarquee()" x-init="init()">
                    <p
                        class="text-center text-blue-100/65 text-xs sm:text-sm font-semibold tracking-[0.28em] uppercase mb-6">
                        {{ $heroSettings['trusted_title'] }}
                    </p>
                    <div class="relative" x-ref="slider" @mouseenter="paused = true" @mouseleave="paused = false">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 z-10 w-16 bg-linear-to-r from-slate-950/90 to-transparent">
                        </div>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 z-10 w-16 bg-linear-to-l from-slate-950/90 to-transparent">
                        </div>
                        <div x-ref="track" class="flex w-max items-center gap-4 will-change-transform">
                            @forelse ($this->companyLogos() as $logo)
                                <div class="logo-card group">
                                    <img src="{{ Storage::url($logo->logo) }}" alt="{{ $logo->name }}"
                                        class="logo-img" />
                                    @if ($logo->website_url)
                                        <a href="{{ $logo->website_url }}" target="_blank"
                                            class="absolute inset-0 z-10"></a>
                                    @endif
                                </div>
                            @empty
                                <div class="logo-card group">
                                    <img src="https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/google/wordmark.svg"
                                        alt="Google" class="logo-img">
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </section>
    @endif

    {{-- Services --}}
    @php
        $serviceSettings = $homePageSettings['services'];
        $featuredServices = $this->services()->values();
        $serviceLayoutStyle = FeaturedServiceGrid::normalizeStyle($serviceSettings['layout_style'] ?? 'original_bento');
        $serviceGridCount = FeaturedServiceGrid::countForStyle($serviceLayoutStyle);
        $isCompactServiceLayout = FeaturedServiceGrid::isCompactStyle($serviceLayoutStyle);
        $isDashboardServiceLayout = FeaturedServiceGrid::isDashboardStyle($serviceLayoutStyle);
        $isStatsBoardLayout = $serviceLayoutStyle === 'stats_board';
    @endphp

    @if ($serviceSettings['enabled'])
        <section class="relative overflow-hidden pt-20 sm:py-24" id="services">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute left-[5%] top-[10%] h-72 w-72 rounded-full bg-violet-500/5 blur-[120px]"></div>
                <div class="absolute bottom-[5%] right-[5%] h-80 w-80 rounded-full bg-cyan-500/5 blur-[130px]"></div>
            </div>

            <div class="relative mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
                <div class="mb-14 text-center lg:mb-20">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full glass-chip px-4 py-2 text-xs text-blue-100/85 sm:text-sm">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-cyan-300"></span>
                        {{ $serviceSettings['badge'] }}
                    </div>

                    <h2 class="text-3xl font-bold text-white sm:text-4xl lg:text-5xl">
                        {{ $serviceSettings['title'] }}

                        @if (filled($serviceSettings['highlighted_title']))
                            <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                                {{ $serviceSettings['highlighted_title'] }}
                            </span>
                        @endif
                    </h2>

                    <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-blue-100/70 md:text-base">
                        {{ $serviceSettings['description'] }}
                    </p>
                </div>

                <div class="{{ FeaturedServiceGrid::gridClass($serviceLayoutStyle, $serviceGridCount) }}">
                    @forelse ($featuredServices as $service)
                        @php
                            $serviceSpanClass = FeaturedServiceGrid::cardClass(
                                $serviceLayoutStyle,
                                $loop->index,
                                $serviceGridCount,
                            );

                            $isLargeServiceCard = FeaturedServiceGrid::isLarge(
                                $serviceLayoutStyle,
                                $loop->index,
                                $serviceGridCount,
                            );

                            $serviceImage = $service->image
                                ? asset('storage/' . $service->image)
                                : 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80';

                            $descriptionLimit = $isLargeServiceCard ? 130 : ($isCompactServiceLayout ? 72 : 95);
                            $isStatsBoardShortCard = $isStatsBoardLayout && in_array($loop->index, [1, 6], true);
                        @endphp

                        <a href="{{ route('client.services.details', $service->slug) }}" wire:navigate
                            class="group relative min-w-0 w-full overflow-hidden border border-white/8 transition duration-500
                                {{ $serviceSpanClass }}
                                {{ $isCompactServiceLayout
                                    ? 'min-h-[250px] rounded-[24px] bg-[#080b18]/95 p-5 shadow-[0_14px_45px_rgba(0,0,0,0.35)] hover:-translate-y-1 hover:border-cyan-300/30 hover:shadow-[0_24px_75px_rgba(8,47,73,0.42)]'
                                    : ($isStatsBoardLayout
                                        ? 'min-h-[250px] md:min-h-0 rounded-[22px] bg-[#080b18]/95 shadow-[0_12px_42px_rgba(0,0,0,0.42)] hover:-translate-y-1 hover:border-violet-300/25 hover:shadow-[0_24px_80px_rgba(76,29,149,0.22)]'
                                        : ($isDashboardServiceLayout
                                            ? 'min-h-[300px] rounded-[22px] bg-[#080b18]/95 shadow-[0_12px_42px_rgba(0,0,0,0.42)] hover:-translate-y-1 hover:border-violet-300/25 hover:shadow-[0_24px_80px_rgba(76,29,149,0.22)]'
                                            : 'min-h-[310px] rounded-[28px] bg-slate-950/55 shadow-[0_20px_60px_rgba(2,8,23,0.35)] ring-1 ring-cyan-300/10 hover:-translate-y-1 hover:border-cyan-300/30 hover:ring-cyan-300/25 hover:shadow-[0_30px_90px_rgba(8,47,73,0.50)]')) }}">
                            <img src="{{ $serviceImage }}" alt="{{ $service->card_title }}" loading="lazy"
                                class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105
                                    {{ $isCompactServiceLayout
                                        ? 'opacity-20 group-hover:opacity-35'
                                        : ($isDashboardServiceLayout
                                            ? 'opacity-75 group-hover:opacity-90'
                                            : '') }}">

                            <div
                                class="absolute inset-0
                                    {{ $isCompactServiceLayout
                                        ? 'bg-linear-to-br from-violet-500/10 via-slate-950/92 to-cyan-500/10'
                                        : ($isDashboardServiceLayout
                                            ? 'bg-linear-to-t from-[#080b18] via-[#080b18]/65 to-slate-950/10'
                                            : ($isLargeServiceCard
                                                ? 'bg-linear-to-r from-slate-950/95 via-slate-950/65 to-slate-900/15'
                                                : 'bg-linear-to-t from-slate-950/95 via-slate-950/55 to-slate-900/10')) }}">
                            </div>

                            <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-500 group-hover:opacity-100"
                                style="background: radial-gradient(circle at 50% 10%, rgba(168,85,247,.18), transparent 42%), radial-gradient(circle at 85% 85%, rgba(34,211,238,.14), transparent 34%);">
                            </div>

                            <div
                                class="pointer-events-none absolute inset-px rounded-[inherit] ring-1 ring-inset ring-white/5">
                            </div>

                            @if ($isCompactServiceLayout)
                                <div class="relative z-10 flex h-full min-h-[210px] flex-col">
                                    <div class="flex items-start justify-between gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-xl border border-cyan-300/15 bg-cyan-300/10 text-cyan-200 shadow-[0_0_30px_rgba(34,211,238,0.08)]">
                                            <span class="material-symbols-outlined">
                                                {{ $service->icon ?: 'settings' }}
                                            </span>
                                        </div>

                                        <span
                                            class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] text-blue-100/65">
                                            {{ $service->category?->name ?? 'Service' }}
                                        </span>
                                    </div>

                                    <div class="mt-auto">
                                        <h3 class="wrap-break-word text-xl font-bold text-white">
                                            {{ $service->card_title }}
                                        </h3>

                                        <p class="mt-3 text-sm leading-6 text-blue-100/65">
                                            {{ Str::limit($service->short_description ?? '', $descriptionLimit) }}
                                        </p>

                                        <span
                                            class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-cyan-200">
                                            Explore
                                            <span
                                                class="material-symbols-outlined text-[17px] transition group-hover:translate-x-1">
                                                arrow_forward
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            @elseif ($isDashboardServiceLayout)
                                <div
                                    class="relative z-10 flex h-full flex-col
                                        {{ $isStatsBoardLayout
                                            ? 'min-h-[250px] md:min-h-0 p-5 lg:p-6'
                                            : 'min-h-[300px] justify-between p-6 sm:p-7' }}">
                                    <div class="flex items-start justify-between gap-4">
                                        <span
                                            class="rounded-full border border-white/10 bg-slate-950/55 px-3 py-1.5 text-[10px] font-medium text-blue-50/80 backdrop-blur-xl">
                                            {{ $service->category?->name ?? 'Service' }}
                                        </span>

                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/8 text-white/80 backdrop-blur-xl transition duration-300 group-hover:border-violet-300/25 group-hover:bg-violet-400/10 group-hover:text-violet-200">
                                            <span class="material-symbols-outlined text-[20px]">
                                                {{ $service->icon ?: 'settings' }}
                                            </span>
                                        </div>
                                    </div>

                                    @if ($isStatsBoardLayout)
                                        <div class="mt-auto {{ $isStatsBoardShortCard ? 'pt-3' : 'pt-8 lg:pt-10' }}">
                                            <h3
                                                class="wrap-break-word font-manrope font-bold leading-tight text-white
                                                    {{ $isStatsBoardShortCard
                                                        ? 'text-lg lg:text-xl'
                                                        : ($isLargeServiceCard ? 'text-2xl lg:text-3xl' : 'text-xl lg:text-2xl') }}">
                                                {{ $service->card_title }}
                                            </h3>

                                            @if (! $isStatsBoardShortCard && filled($service->short_description))
                                                <p class="mt-3 line-clamp-2 text-sm leading-6 text-blue-100/62">
                                                    {{ Str::limit($service->short_description, $descriptionLimit) }}
                                                </p>
                                            @endif

                                            @if (! $isStatsBoardShortCard)
                                                <div
                                                    class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-white/85 transition group-hover:text-violet-200">
                                                    Learn More
                                                    <span
                                                        class="material-symbols-outlined text-[18px] transition-transform duration-300 group-hover:translate-x-1">
                                                        arrow_forward
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="max-w-2xl pt-24 sm:pt-28">
                                            <h3
                                                class="wrap-break-word font-manrope font-bold leading-tight text-white {{ $isLargeServiceCard ? 'text-2xl sm:text-3xl' : 'text-xl sm:text-2xl' }}">
                                                {{ $service->card_title }}
                                            </h3>

                                            @if (filled($service->short_description))
                                                <p
                                                    class="mt-3 text-sm leading-6 text-blue-100/62 {{ $isLargeServiceCard ? 'sm:max-w-xl sm:text-base sm:leading-7' : '' }}">
                                                    {{ Str::limit($service->short_description, $descriptionLimit) }}
                                                </p>
                                            @endif

                                            <div
                                                class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-white/85 transition group-hover:text-violet-200">
                                                Learn More
                                                <span
                                                    class="material-symbols-outlined text-[18px] transition-transform duration-300 group-hover:translate-x-1">
                                                    arrow_forward
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div
                                    class="relative z-10 flex h-full min-h-[310px] flex-col justify-between p-6 {{ $isLargeServiceCard ? 'sm:p-8 lg:p-9' : 'sm:p-7' }}">
                                    <div class="flex items-start justify-between gap-4">
                                        <span
                                            class="inline-flex max-w-[70%] rounded-full border border-cyan-300/15 bg-slate-950/40 px-3 py-1.5 text-[11px] font-medium text-cyan-50 backdrop-blur-xl">
                                            {{ $service->category?->name ?? 'Service' }}
                                        </span>

                                        <div
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-white backdrop-blur-xl transition duration-300 group-hover:rotate-3 group-hover:border-cyan-300/25 group-hover:bg-cyan-300/15">
                                            <span class="material-symbols-outlined text-[22px]">
                                                {{ $service->icon ?: 'settings' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="{{ $isLargeServiceCard ? 'max-w-2xl' : '' }}">
                                        <h3
                                            class="wrap-break-word font-manrope font-bold leading-tight text-white {{ $isLargeServiceCard ? 'text-2xl sm:text-3xl lg:text-4xl' : 'text-xl sm:text-2xl' }}">
                                            {{ $service->card_title }}
                                        </h3>

                                        <p
                                            class="mt-3 text-sm leading-6 text-white/72 {{ $isLargeServiceCard ? 'sm:text-base sm:leading-7' : '' }}">
                                            {{ Str::limit($service->short_description ?? '', $descriptionLimit) }}
                                        </p>

                                        <div
                                            class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-white transition group-hover:text-cyan-200">
                                            Learn More
                                            <span
                                                class="material-symbols-outlined text-[18px] transition-transform duration-300 group-hover:translate-x-1">
                                                arrow_forward
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </a>
                    @empty
                        <div
                            class="col-span-full rounded-[28px] border border-white/10 bg-white/5 p-10 text-center backdrop-blur-xl">
                            <h3 class="text-xl font-bold text-white">
                                No featured services found
                            </h3>

                            <p class="mt-3 text-sm text-blue-100/65">
                                Mark services as active and featured from the admin panel.
                            </p>
                        </div>
                    @endforelse
                </div>

                @if (filled($serviceSettings['button_text']) && filled($serviceSettings['button_url']))
                    <div class="mt-10 flex justify-center">
                        <a href="{{ $serviceSettings['button_url'] }}" class="service-btn">
                            {{ $serviceSettings['button_text'] }}
                            <span class="material-symbols-outlined text-[18px]">
                                north_east
                            </span>
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Company Overview --}}
    @php
        $aboutSettings = $homePageSettings['about'];
    @endphp

    @if ($aboutSettings['enabled'])
        <section class="relative overflow-hidden py-20 md:py-15 lg:py-24">
            <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                    <!-- Left Content -->
                    <div class="relative order-2 lg:order-1">
                        <div class="mb-5 flex justify-center lg:justify-start">
                            <div
                                class="inline-flex items-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                                <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                                {{ $aboutSettings['badge'] }}
                            </div>
                        </div>

                        <h2
                            class="md:mt-6 text-3xl font-bold text-white sm:text-4xl lg:text-5xl text-center lg:text-left">
                            {{ $aboutSettings['title'] }}
                            @if (filled($aboutSettings['highlighted_title']))
                                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                                    {{ $aboutSettings['highlighted_title'] }}
                                </span>
                            @endif
                        </h2>

                        <p
                            class="mt-4 whitespace-pre-line md:mt-6 lg:max-w-2xl text-sm md:text-base text-blue-100/70 text-center lg:text-left">
                            {{ $aboutSettings['description'] }}
                        </p>

                        {{-- <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:bg-white/10">
                            <div
                                class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-500/20 text-cyan-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 7.5l9-4.5 9 4.5m-18 0 9 4.5m-9-4.5V16.5l9 4.5m9-13.5v9l-9 4.5m0-9V21" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-white">Smart Solutions</h3>
                            <p class="mt-2 text-sm leading-6 text-blue-100/65">
                                Tailored digital systems built for real growth, efficiency, and results.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:bg-white/10">
                            <div
                                class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-sky-500/20 text-sky-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.5 6.75v10.5m-4.5-7.5v7.5m-4.5-4.5v4.5M4.5 19.5h15" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-white">Scalable Growth</h3>
                            <p class="mt-2 text-sm leading-6 text-blue-100/65">
                                Flexible and future-ready systems that grow with your business.
                            </p>
                        </div>
                    </div> --}}

                        <!-- Stats -->
                        <div class="mt-8 grid grid-cols-1 gap-4 text-center sm:grid-cols-3 md:text-left">
                            @foreach ($aboutSettings['stats'] as $stat)
                                <div class="rounded-2xl border border-white/10 bg-white/6 px-5 py-4 backdrop-blur-xl">
                                    <p class="text-2xl sm:text-3xl font-bold text-white">{{ $stat['value'] }}</p>
                                    <p class="mt-1 text-xs sm:text-sm text-blue-100/60">{{ $stat['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Right Animated Images -->
                    <div class="relative order-2 lg:order-2">
                        <div
                            class="relative bg-white/10 backdrop-blur-xl rounded-[20px] sm:rounded-[28px] p-2.5 sm:p-5 md:p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)] border border-white/20 max-w155 mx-auto overflow-hidden">

                            <!-- soft glow -->
                            <div
                                class="absolute top-4 left-4 sm:top-10 sm:left-10 w-20 sm:w-32 h-20 sm:h-32 bg-blue-300/20 rounded-full blur-3xl animate-pulse">
                            </div>
                            <div
                                class="absolute bottom-4 right-4 sm:bottom-10 sm:right-10 w-24 sm:w-40 h-24 sm:h-40 bg-sky-300/20 rounded-full blur-3xl animate-pulse">
                            </div>

                            <div
                                class="relative rounded-[18px] sm:rounded-[22px] bg-slate-900/30 backdrop-blur-md p-2.5 sm:p-5 md:p-8 border border-white/10 min-h-65 sm:min-h-105 md:min-h-117.5 flex items-center justify-center overflow-hidden">

                                <div
                                    class="relative w-full max-w-[320px] sm:max-w-115 h-57.5 sm:h-85 md:h-90 scale-[0.84] xs:scale-[0.9] sm:scale-100 origin-center">

                                    <!-- SVG lines -->
                                    <svg class="absolute inset-0 w-full h-full" viewBox="0 0 460 360" fill="none"
                                        aria-hidden="true">
                                        <path d="M230 180 L110 80" stroke="#93C5FD" stroke-width="2"
                                            stroke-dasharray="8 8">
                                            <animate attributeName="stroke-dashoffset" from="16" to="0"
                                                dur="1.5s" repeatCount="indefinite" />
                                        </path>
                                        <path d="M230 180 L350 90" stroke="#93C5FD" stroke-width="2"
                                            stroke-dasharray="8 8">
                                            <animate attributeName="stroke-dashoffset" from="16" to="0"
                                                dur="1.8s" repeatCount="indefinite" />
                                        </path>
                                        <path d="M230 180 L120 280" stroke="#93C5FD" stroke-width="2"
                                            stroke-dasharray="8 8">
                                            <animate attributeName="stroke-dashoffset" from="16" to="0"
                                                dur="1.7s" repeatCount="indefinite" />
                                        </path>
                                        <path d="M230 180 L355 270" stroke="#93C5FD" stroke-width="2"
                                            stroke-dasharray="8 8">
                                            <animate attributeName="stroke-dashoffset" from="16" to="0"
                                                dur="2s" repeatCount="indefinite" />
                                        </path>
                                        <path d="M230 180 L230 50" stroke="#BFDBFE" stroke-width="2"
                                            stroke-dasharray="8 8">
                                            <animate attributeName="stroke-dashoffset" from="16" to="0"
                                                dur="1.6s" repeatCount="indefinite" />
                                        </path>
                                    </svg>

                                    <!-- center -->
                                    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                                        <div class="relative flex items-center justify-center">
                                            <div
                                                class="absolute w-20 sm:w-32 md:w-36 h-20 sm:h-32 md:h-36 rounded-full bg-blue-200/20 blur-2xl animate-pulse">
                                            </div>
                                            <div
                                                class="absolute w-16 sm:w-24 h-16 sm:h-24 rounded-full border border-blue-300/40 animate-spin [animation-duration:10s]">
                                            </div>
                                            <div
                                                class="absolute w-11 sm:w-16 h-11 sm:h-16 rounded-full border border-sky-300/50 animate-spin [animation-duration:7s] [animation-direction:reverse]">
                                            </div>

                                            <div
                                                class="relative z-10 w-11 sm:w-18 md:w-20 h-11 sm:h-18 md:h-20 rounded-2xl bg-linear-to-br from-blue-600 to-sky-400 shadow-xl flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-6 sm:w-5 md:w-10 h-6 sm:h-5 md:h-10 text-white"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                    stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9.75 3v2.25m4.5-2.25v2.25M4.5 9.75h15M6.75 19.5h10.5A2.25 2.25 0 0019.5 17.25V8.25A2.25 2.25 0 0017.25 6H6.75A2.25 2.25 0 004.5 8.25v9A2.25 2.25 0 006.75 19.5z" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- top -->
                                    <div
                                        class="absolute left-1/2 top-0 -translate-x-1/2 bg-white/90 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-md border border-white/70 px-2.5 sm:px-5 py-2 sm:py-4 animate-bounce [animation-duration:3s] max-w-33 sm:max-w-none">
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <div
                                                class="w-7 sm:w-10 h-7 sm:h-10 rounded-lg sm:rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-3.5 sm:w-5 h-3.5 sm:h-5 text-blue-600"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] sm:text-sm font-semibold text-slate-800 leading-tight">
                                                    Cloud Ready</p>
                                                <p class="text-[9px] sm:text-xs text-slate-500 leading-tight">
                                                    Modern
                                                    infrastructure</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- left -->
                                    <div
                                        class="absolute left-0 top-12 sm:top-14 bg-white/90 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-md border border-white/70 px-2.5 sm:px-4 py-2 sm:py-4 animate-pulse max-w-31.5 sm:max-w-none">
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <div
                                                class="w-7 sm:w-10 h-7 sm:h-10 rounded-lg sm:rounded-xl bg-sky-50 flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-3.5 sm:w-5 h-3.5 sm:h-5 text-sky-600" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4" />
                                                    <path d="M14 13.12c0 2.38 0 6.38-1 8.88" />
                                                    <path d="M17.29 21.02c.12-.6.43-2.3.5-3.02" />
                                                    <path d="M2 12a10 10 0 0 1 18-6" />
                                                    <path d="M2 16h.01" />
                                                    <path d="M21.8 16c.2-2 .131-5.354 0-6" />
                                                    <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2" />
                                                    <path d="M8.65 22c.21-.66.45-1.32.57-2" />
                                                    <path d="M9 6.8a6 6 0 0 1 9 5.2v2" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] sm:text-sm font-semibold text-slate-800 leading-tight">
                                                    Secure System</p>
                                                <p class="text-[9px] sm:text-xs text-slate-500 leading-tight">
                                                    Protected
                                                    workflow</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- right -->
                                    <div
                                        class="absolute right-0 top-14 sm:top-16 bg-white/90 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-md border border-white/70 px-2.5 sm:px-4 py-2 sm:py-4 animate-pulse max-w-31.5 sm:max-w-none">
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <div
                                                class="w-7 sm:w-10 h-7 sm:h-10 rounded-lg sm:rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-3.5 sm:w-5 h-3.5 sm:h-5 text-indigo-600" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M7.5 14.25l4.5-4.5 4.5 4.5" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M7.5 9.75l4.5 4.5 4.5-4.5" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] sm:text-sm font-semibold text-slate-800 leading-tight">
                                                    Scalable Stack</p>
                                                <p class="text-[9px] sm:text-xs text-slate-500 leading-tight">Built
                                                    to
                                                    grow</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- bottom left -->
                                    <div
                                        class="absolute left-1 sm:left-4 bottom-1 sm:bottom-4 bg-white/90 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-md border border-white/70 px-2.5 sm:px-4 py-2 sm:py-4 animate-bounce [animation-duration:3.5s] max-w-31.5 sm:max-w-none">
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <div
                                                class="w-7 sm:w-10 h-7 sm:h-10 rounded-lg sm:rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-3.5 sm:w-5 h-3.5 sm:h-5 text-emerald-600" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12l2 2 4-4" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] sm:text-sm font-semibold text-slate-800 leading-tight">
                                                    Reliable Support</p>
                                                <p class="text-[9px] sm:text-xs text-slate-500 leading-tight">
                                                    Always
                                                    connected</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- bottom right -->
                                    <div
                                        class="absolute right-1 sm:right-2 bottom-4 sm:bottom-8 bg-white/90 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-md border border-white/70 px-2.5 sm:px-4 py-2 sm:py-4 animate-bounce [animation-duration:4s] max-w-31.5 sm:max-w-none">
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <div
                                                class="w-7 sm:w-10 h-7 sm:h-10 rounded-lg sm:rounded-xl bg-violet-50 flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-3.5 sm:w-5 h-3.5 sm:h-5 text-violet-600" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] sm:text-sm font-semibold text-slate-800 leading-tight">
                                                    Fast Delivery</p>
                                                <p class="text-[9px] sm:text-xs text-slate-500 leading-tight">Quick
                                                    implementation</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dots -->
                                    <div
                                        class="absolute left-[23%] top-[32%] w-2 sm:w-3 h-2 sm:h-3 bg-blue-500 rounded-full animate-ping">
                                    </div>
                                    <div
                                        class="absolute right-[25%] top-[35%] w-2 sm:w-3 h-2 sm:h-3 bg-sky-400 rounded-full animate-ping [animation-delay:0.4s]">
                                    </div>
                                    <div
                                        class="absolute left-[26%] bottom-[24%] w-2 sm:w-3 h-2 sm:h-3 bg-indigo-400 rounded-full animate-ping [animation-delay:0.7s]">
                                    </div>
                                    <div
                                        class="absolute right-[24%] bottom-[25%] w-2 sm:w-3 h-2 sm:h-3 bg-emerald-400 rounded-full animate-ping [animation-delay:1s]">
                                    </div>
                                </div>
                            </div>

                            <div
                                class="absolute -top-5 -right-5 w-14 sm:w-16 h-14 sm:h-16 bg-blue-200/30 rounded-2xl blur-xl opacity-70 animate-pulse">
                            </div>
                            <div
                                class="absolute -bottom-4 -left-4 w-16 sm:w-20 h-16 sm:h-20 bg-sky-200/30 rounded-full blur-2xl opacity-80 animate-pulse">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <!-- Recent Projects -->
    <section class="relative overflow-hidden py-20 sm:py-24">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <!-- Heading -->
            <div class="mb-8 md:mb-14 text-center lg:mb-18">
                <div
                    class="mx-auto mb-5 inline-flex items-center justify-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                    Recent Projects
                </div>

                <h2 class="text-3xl font-bold text-white sm:text-4xl lg:text-5xl">
                    Built to perform.
                    <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        Designed to impress.
                    </span>
                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-blue-100/70 sm:text-base">
                    A selection of recent digital solutions crafted to help businesses scale faster, operate smarter,
                    and build stronger user experiences.
                </p>
            </div>

            <!-- Bento Grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-6 auto-rows-[240px]">
                @forelse ($this->projects as $project)
                    @php
                        $projectImage = $this->projectImage($project);
                    @endphp

                    @if ($loop->first && $projectImage)
                        <!-- Big Project With Image -->
                        <a href="{{ route('client.projects.details', $project->slug) }}" wire:navigate
                            class="group relative overflow-hidden rounded-[28px] border border-white/10 bg-white/6 p-3 backdrop-blur-2xl shadow-[0_20px_60px_rgba(0,0,0,0.20)] md:col-span-4 md:row-span-2 cursor-pointer">
                            <div class="absolute inset-0">
                                <img src="{{ $projectImage }}" alt="{{ $project->title }}"
                                    class="h-full w-full object-cover transition duration-700 group-hover:scale-105" />

                                <div
                                    class="absolute inset-0 bg-linear-to-t from-slate-950 via-slate-950/50 to-slate-900/10">
                                </div>
                            </div>

                            <div
                                class="relative z-10 flex h-full flex-col justify-between rounded-[22px] border border-white/8 p-4 sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <span
                                        class="inline-flex items-center rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.18em] text-cyan-200">
                                        {{ $this->projectType($project) }}
                                    </span>

                                    @if ($project->live_url || $project->case_study_url)
                                        <a href="{{ $project->case_study_url ?: $project->live_url }}"
                                            target="_blank"
                                            class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-white/90 backdrop-blur-xl">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M17.25 6.75L6.75 17.25M8.25 6.75h9v9" />
                                            </svg>
                                        </a>
                                    @else
                                        <div
                                            class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-white/90 backdrop-blur-xl">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M17.25 6.75L6.75 17.25M8.25 6.75h9v9" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                <div class="max-w-xl">
                                    <h3 class="text-2xl font-bold text-white sm:text-3xl">
                                        {{ $project->title }}
                                    </h3>

                                    @if ($project->short_description)
                                        <p class="mt-3 max-w-lg text-sm leading-7 text-blue-100/72 sm:text-base">
                                            {{ Str::limit($project->short_description, 160) }}
                                        </p>
                                    @endif

                                    @if (!empty($this->projectTechnologies($project)))
                                        <div class="mt-5 flex flex-wrap gap-2">
                                            @foreach ($this->projectTechnologies($project) as $technology)
                                                <span
                                                    class="rounded-full border border-white/10 bg-white/8 px-3 py-1 text-xs text-blue-100/75">
                                                    {{ $technology }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @elseif ($projectImage)
                        <!-- Normal Project With Image -->
                        <a href="{{ route('client.projects.details', $project->slug) }}" wire:navigate
                            class="group relative overflow-hidden rounded-[28px] border border-white/10 bg-white/6 p-3 backdrop-blur-2xl md:col-span-2 cursor-pointer">
                            <div class="absolute inset-0">
                                <img src="{{ $projectImage }}" alt="{{ $project->title }}"
                                    class="h-full w-full object-cover transition duration-700 group-hover:scale-105" />

                                <div
                                    class="absolute inset-0 bg-linear-to-t from-slate-950 via-slate-950/55 to-transparent">
                                </div>
                            </div>

                            <div
                                class="relative z-10 flex h-full flex-col justify-end rounded-[22px] border border-white/8 p-5">
                                <span
                                    class="mb-3 inline-flex w-fit items-center rounded-full border border-sky-300/20 bg-sky-400/10 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.18em] text-sky-200">
                                    {{ $this->projectType($project) }}
                                </span>

                                <h3 class="text-xl font-bold text-white">
                                    {{ $project->title }}
                                </h3>

                                @if ($project->short_description)
                                    <p class="mt-2 text-sm leading-6 text-blue-100/70">
                                        {{ Str::limit($project->short_description, 105) }}
                                    </p>
                                @endif

                                @if (!empty($this->projectTechnologies($project, 2)))
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($this->projectTechnologies($project, 2) as $technology)
                                            <span
                                                class="rounded-full border border-white/10 bg-white/8 px-3 py-1 text-xs text-blue-100/75">
                                                {{ $technology }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </a>
                    @else
                        <!-- Project Without Image -->
                        <a href="{{ route('client.projects.details', $project->slug) }}" wire:navigate
                            class="group relative overflow-hidden rounded-[28px] border border-white/10 bg-white/6 p-5 backdrop-blur-2xl md:col-span-2 cursor-pointer transition-all duration-300 hover:-translate-y-1 hover:border-cyan-300/30 hover:bg-white/8">
                            <span
                                class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-cyan-300/70 to-transparent"></span>

                            <div class="absolute inset-0 pointer-events-none">
                                <div class="absolute left-6 top-6 h-24 w-24 rounded-full bg-cyan-400/10 blur-3xl">
                                </div>
                                <div class="absolute bottom-6 right-6 h-28 w-28 rounded-full bg-blue-500/10 blur-3xl">
                                </div>
                            </div>

                            <div
                                class="relative z-10 flex h-full flex-col justify-between rounded-[22px] border border-white/8 bg-slate-950/25 p-5">

                                <div>
                                    <p class="text-xs uppercase tracking-[0.2em] text-blue-100/45">
                                        {{ $project->is_featured ? 'Featured Build' : $this->projectType($project) }}
                                    </p>

                                    <h3 class="mt-2 text-xl font-bold text-white">
                                        {{ $project->title }}
                                    </h3>

                                    @if ($project->short_description)
                                        <p class="mt-3 text-sm leading-6 text-blue-100/68">
                                            {{ Str::limit($project->short_description, 120) }}
                                        </p>
                                    @endif
                                </div>

                                @if (!empty($this->projectTechnologies($project, 2)))
                                    <div class="mt-5 flex flex-wrap gap-2">
                                        @foreach ($this->projectTechnologies($project, 2) as $technology)
                                            <span
                                                class="rounded-full border border-white/10 bg-white/8 px-3 py-1 text-xs text-blue-100/75">
                                                {{ $technology }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endif
                @empty
                    <div
                        class="md:col-span-6 rounded-[28px] border border-white/10 bg-white/6 p-10 text-center backdrop-blur-2xl">
                        <h3 class="text-2xl font-bold text-white">No projects found</h3>
                        <p class="mt-3 text-sm text-blue-100/70">
                            Please add active projects from your admin panel.
                        </p>
                    </div>
                @endforelse
            </div>

            <!-- bottom button -->
            <div class="mt-10 flex justify-center">
                <a href="{{ route('client.projects') }}" wire:navigate class="service-btn">
                    View All Projects
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.25 6.75L6.75 17.25M8.25 6.75h9v9" />
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section class="relative overflow-hidden py-20 sm:py-24" x-data="{ billing: 'monthly' }">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <!-- Heading -->
            <div class="text-center mb-14 lg:mb-18">
                <div
                    class="mx-auto mb-5 inline-flex items-center justify-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                    Pricing Plans
                </div>

                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
                    Flexible IT plans for
                    <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        every stage of growth
                    </span>
                </h2>

                <p class="mt-4 max-w-2xl mx-auto text-sm sm:text-base leading-7 text-blue-100/70">
                    Choose the right support package for your business. From startup essentials to enterprise-grade
                    security, infrastructure, and continuous IT operations.
                </p>

                <!-- Billing Toggle -->
                <div class="mt-8 flex justify-center">
                    <div class="inline-flex rounded-full border border-white/10 bg-white/5 p-1 backdrop-blur-xl">
                        <button type="button" @click="billing = 'monthly'"
                            :class="billing === 'monthly'
                                ?
                                'bg-linear-to-r from-blue-500 to-sky-400 text-white shadow-lg shadow-blue-500/25' :
                                'text-blue-100/70 hover:text-white'"
                            class="rounded-full px-5 py-2.5 text-sm font-semibold transition cursor-pointer">
                            Monthly
                        </button>

                        <button type="button" @click="billing = 'yearly'"
                            :class="billing === 'yearly'
                                ?
                                'bg-linear-to-r from-blue-500 to-sky-400 text-white shadow-lg shadow-blue-500/25' :
                                'text-blue-100/70 hover:text-white'"
                            class="rounded-full px-5 py-2.5 text-sm font-semibold transition cursor-pointer">
                            Yearly
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cards -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @forelse ($this->pricingPlans() as $plan)
                    @php
                        $isPopular = $plan->plan_type === 'business';

                        $cardClass = $isPopular
                            ? 'border-cyan-300/25 bg-linear-to-b from-blue-500/10 to-white/8 shadow-[0_25px_80px_rgba(0,0,0,0.24)]'
                            : 'border-white/10 bg-white/6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]';

                        $lineClass = $plan->plan_type === 'enterprise' ? 'via-violet-300/70' : 'via-cyan-300/70';

                        $iconBgClass = match ($plan->plan_type) {
                            'business' => 'bg-sky-500/15 text-sky-200',
                            'enterprise' => 'bg-violet-500/15 text-violet-200',
                            default => 'bg-blue-500/15 text-cyan-200',
                        };

                        $typeTextClass = match ($plan->plan_type) {
                            'business' => 'text-sky-200/80',
                            'enterprise' => 'text-violet-200/80',
                            default => 'text-cyan-200/80',
                        };

                        $features = is_array($plan->features) ? $plan->features : [];

                        $monthlyPrice = $plan->monthly_price !== null ? (float) $plan->monthly_price : null;
                        $monthlyDiscountPrice =
                            $plan->monthly_discount_price !== null ? (float) $plan->monthly_discount_price : null;

                        $yearlyPrice = $plan->yearly_price !== null ? (float) $plan->yearly_price : null;
                        $yearlyDiscountPrice =
                            $plan->yearly_discount_price !== null ? (float) $plan->yearly_discount_price : null;

                        $hasMonthlyDiscount =
                            $monthlyPrice && $monthlyDiscountPrice && $monthlyDiscountPrice < $monthlyPrice;
                        $hasYearlyDiscount =
                            $yearlyPrice && $yearlyDiscountPrice && $yearlyDiscountPrice < $yearlyPrice;

                        $monthlySavePercent = $hasMonthlyDiscount
                            ? round((($monthlyPrice - $monthlyDiscountPrice) / $monthlyPrice) * 100)
                            : null;

                        $yearlySavePercent = $hasYearlyDiscount
                            ? round((($yearlyPrice - $yearlyDiscountPrice) / $yearlyPrice) * 100)
                            : null;
                    @endphp

                    <div
                        class="group relative rounded-[30px] border {{ $cardClass }} p-6 sm:p-7 backdrop-blur-2xl transition duration-300 hover:-translate-y-1 hover:border-cyan-300/20">

                        <div
                            class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent {{ $lineClass }} to-transparent">
                        </div>

                        @if ($isPopular)
                            <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                                <span
                                    class="inline-flex rounded-full border border-cyan-300 bg-cyan-400 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-slate-950 backdrop-blur-xl">
                                    Most Popular
                                </span>
                            </div>
                        @endif

                        <div class="flex items-start justify-between gap-4 {{ $isPopular ? 'pt-4' : '' }}">
                            <div>
                                <p class="text-sm font-medium uppercase tracking-[0.22em] {{ $typeTextClass }}">
                                    {{ $plan->plan_type }}
                                </p>

                                <h3 class="mt-2 text-2xl font-bold text-white">
                                    {{ $plan->title }}
                                </h3>
                            </div>

                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 {{ $iconBgClass }}">
                                <span class="material-symbols-outlined">
                                    {{ $plan->icon ?: 'workspace_premium' }}
                                </span>
                            </div>
                        </div>

                        <p class="mt-4 text-sm leading-7 text-blue-100/68">
                            {{ $plan->description ?: 'Flexible IT support package designed for your business growth.' }}
                        </p>

                        <div class="mt-6">
                            <div x-show="billing === 'monthly'" x-transition>
                                @if ($monthlyPrice)
                                    @if ($hasMonthlyDiscount)
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-blue-100/45 line-through">
                                                ৳ {{ number_format($monthlyPrice, 0) }}
                                            </span>

                                            <span class="text-xs font-semibold text-emerald-300">
                                                {{ $monthlySavePercent }}% OFF
                                            </span>
                                        </div>

                                        <div class="flex items-end gap-2">
                                            <span class="text-5xl font-bold text-white">
                                                ৳ {{ number_format($monthlyDiscountPrice, 0) }}
                                            </span>
                                            <span class="pb-1 text-sm text-blue-100/60">/ month</span>
                                        </div>
                                    @else
                                        <div class="flex items-end gap-2">
                                            <span class="text-4xl font-bold text-white">
                                                ৳ {{ number_format($monthlyPrice, 0) }}
                                            </span>
                                            <span class="pb-1 text-sm text-blue-100/60">/ month</span>
                                        </div>
                                    @endif
                                @else
                                    <span class="text-3xl font-bold text-white">Custom</span>
                                @endif
                            </div>

                            <div x-show="billing === 'yearly'" x-transition style="display: none;">
                                @if ($yearlyPrice)
                                    @if ($hasYearlyDiscount)
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-blue-100/45 line-through">
                                                ৳ {{ number_format($yearlyPrice, 0) }}
                                            </span>

                                            <span class="text-xs font-semibold text-emerald-300">
                                                {{ $yearlySavePercent }}% OFF
                                            </span>
                                        </div>

                                        <div class="flex items-end gap-2">
                                            <span class="text-5xl font-bold text-white">
                                                ৳ {{ number_format($yearlyDiscountPrice, 0) }}
                                            </span>
                                            <span class="pb-1 text-sm text-blue-100/60">/ year</span>
                                        </div>
                                    @else
                                        <div class="flex items-end gap-2">
                                            <span class="text-4xl font-bold text-white">
                                                ৳ {{ number_format($yearlyPrice, 0) }}
                                            </span>
                                            <span class="pb-1 text-sm text-blue-100/60">/ year</span>
                                        </div>
                                    @endif
                                @else
                                    <span class="text-3xl font-bold text-white">Custom</span>
                                @endif
                            </div>
                        </div>

                        <a :href="`{{ route('client.checkout.pricing', $plan->id) }}?billing=${billing}`" wire:navigate
                            class="mt-6 inline-flex w-full items-center justify-center rounded-full {{ $isPopular ? 'bg-linear-to-r from-blue-500 to-sky-400 shadow-lg shadow-blue-500/30 hover:-translate-y-0.5' : 'border border-white/15 bg-white/8 hover:bg-white/12' }} px-6 py-3.5 font-semibold text-white backdrop-blur-xl transition">
                            Choose Plan
                        </a>

                        <ul class="mt-7 space-y-3 text-sm text-blue-50/85">
                            @forelse ($features as $feature)
                                <li class="pricing-li">
                                    {{ $feature }}
                                </li>
                            @empty
                                <li class="pricing-li">
                                    Custom features available on request
                                </li>
                            @endforelse
                        </ul>

                        {{-- @if ($plan->purchase_count > 0)
                        <div
                            class="mt-6 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-blue-100/70">
                            {{ $plan->purchase_count }} customers already selected this plan.
                        </div>
                    @endif --}}
                    </div>
                @empty
                    <div
                        class="col-span-1 rounded-[30px] border border-white/10 bg-white/6 p-8 text-center text-blue-100/70 backdrop-blur-2xl lg:col-span-3">
                        No pricing plan found. Please add active pricing plans from admin panel.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="relative overflow-hidden py-20 sm:py-24">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <!-- Heading -->
            <div class="mb-10 md:mb-14 text-center lg:mb-18">
                <div
                    class="mx-auto mb-5 inline-flex items-center justify-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                    Featured Blog
                </div>

                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
                    Insights that help you
                    <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        stay ahead
                    </span>
                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-sm sm:text-base leading-7 text-blue-100/70">
                    Explore practical ideas, expert guidance, and modern technology insights for growing businesses.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @forelse ($this->featuredBlogs as $blog)
                    @php
                        $blogImage = $this->blogImage($blog);
                    @endphp

                    <article
                        class="group overflow-hidden rounded-[28px] border border-white/10 bg-white/6 p-3 backdrop-blur-2xl transition duration-300 hover:-translate-y-1 hover:border-cyan-300/20">

                        @if ($blogImage)
                            <div class="overflow-hidden rounded-[22px]">
                                <img src="{{ $blogImage }}" alt="{{ $blog->title }}"
                                    class="h-60 w-full object-cover transition duration-700 group-hover:scale-105">
                            </div>
                        @else
                            <div
                                class="relative h-60 overflow-hidden rounded-[22px] border border-white/10 bg-slate-950/25">
                                <div
                                    class="absolute inset-0 bg-linear-to-br from-slate-950/85 via-blue-950/40 to-cyan-950/20">
                                </div>
                                <div class="absolute left-6 top-6 h-24 w-24 rounded-full bg-cyan-400/10 blur-3xl">
                                </div>
                                <div class="absolute bottom-6 right-6 h-28 w-28 rounded-full bg-blue-500/10 blur-3xl">
                                </div>

                                <div class="relative z-10 flex h-full items-center justify-center px-6 text-center">
                                    <span class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-100/45">
                                        {{ $blog->category?->name ?? 'Blog Insight' }}
                                    </span>
                                </div>
                            </div>
                        @endif

                        <div class="p-3 sm:p-4">
                            <div class="flex flex-wrap items-center gap-3 text-xs text-blue-100/55">
                                <span class="rounded-full border border-white/10 bg-white/6 px-3 py-1">
                                    {{ $blog->category?->name ?? 'Blog' }}
                                </span>

                                @if ($blog->published_at)
                                    <span>{{ $blog->published_at->format('M Y') }}</span>
                                @endif
                            </div>

                            <h3 class="mt-4 text-xl font-bold text-white leading-snug">
                                {{ $blog->title }}
                            </h3>

                            @if ($blog->excerpt)
                                <p class="mt-3 text-sm leading-7 text-blue-100/68">
                                    {{ Str::limit($blog->excerpt, 145) }}
                                </p>
                            @endif

                            <a href="{{ route('client.blogs.details', $blog->slug) }}" wire:navigate
                                class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-cyan-200 transition hover:text-white">
                                Read More
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17.25 6.75L6.75 17.25M8.25 6.75h9v9" />
                                </svg>
                            </a>
                        </div>
                    </article>
                @empty
                    <div
                        class="col-span-full rounded-[28px] border border-white/10 bg-white/6 p-10 text-center backdrop-blur-2xl">
                        <h3 class="text-2xl font-bold text-white">No featured blogs found</h3>
                        <p class="mt-3 text-sm text-blue-100/70">
                            Please mark some active blogs as featured from your admin panel.
                        </p>
                    </div>
                @endforelse
            </div>

            <div class="mt-10 flex justify-center">
                <a href="{{ route('client.blogs') }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/8 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/12">
                    View All Blogs
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.25 6.75L6.75 17.25M8.25 6.75h9v9" />
                    </svg>
                </a>
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="relative overflow-hidden py-20 sm:py-24" x-data="reviewCarousel()" x-init="init()">
        <div>
            <div class="mb-10 text-center">
                <div
                    class="mx-auto mb-5 inline-flex items-center justify-center gap-2 rounded-full glass-chip px-4 py-2 text-xs sm:text-sm text-blue-100/85">
                    <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                    Client Reviews
                </div>

                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
                    Trusted by businesses that
                    <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        expect more
                    </span>
                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-sm sm:text-base leading-7 text-blue-100/70">
                    Real feedback from clients who value reliability, execution quality, and long-term IT partnership.
                </p>
            </div>

            <div class="relative">
                <div class="overflow-hidden" x-ref="viewport" @mouseenter="pause()" @mouseleave="play()">
                    <div x-ref="track"
                        class="flex items-stretch gap-4 sm:gap-5 lg:gap-6 will-change-transform transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]">

                        <!-- Card 1 -->
                        <article
                            class="review-card group relative w-[86vw] max-w-[86vw] shrink-0 overflow-hidden rounded-[30px] border border-white/10 bg-white/6 p-5 sm:w-140 sm:max-w-140 sm:p-6 lg:w-170 lg:max-w-170 lg:p-7 backdrop-blur-2xl shadow-[0_16px_50px_rgba(0,0,0,0.18)]">
                            <div
                                class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.12),transparent_58%)] opacity-0 transition duration-500 group-[.is-active]:opacity-100">
                            </div>

                            <div class="relative z-10 flex h-full flex-col">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center gap-1 text-cyan-300 text-base sm:text-lg">
                                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                                    </div>

                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-cyan-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M7.5 8.25h9m-9 3h6m-9 8.25h12A2.25 2.25 0 0018.75 17.25V6.75A2.25 2.25 0 0016.5 4.5h-9A2.25 2.25 0 005.25 6.75v10.5A2.25 2.25 0 007.5 19.5z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white/10"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M7.17 6A5.001 5.001 0 002 11v7h7v-7H5.08A3.001 3.001 0 017.17 8.17V6zm9 0A5.001 5.001 0 0011 11v7h7v-7h-3.92a3.001 3.001 0 012.09-2.83V6z" />
                                    </svg>

                                    <p class="mt-4 text-sm sm:text-base leading-7 sm:leading-8 text-blue-50/88">
                                        Their support quality and response time have been excellent. From website work
                                        to
                                        day-to-day IT support, everything feels structured, polished, and highly
                                        professional.
                                    </p>
                                </div>

                                <div class="mt-auto pt-6">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-13 w-13 items-center justify-center rounded-full border border-white/10 bg-blue-500/20 text-white font-bold text-base">
                                            A
                                        </div>

                                        <div class="min-w-0">
                                            <h4 class="text-sm sm:text-base font-semibold text-white">Ahsan Rahman</h4>
                                            <p class="text-xs sm:text-sm text-blue-100/55">Managing Director</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Card 2 -->
                        <article
                            class="review-card group relative w-[86vw] max-w-[86vw] shrink-0 overflow-hidden rounded-[30px] border border-white/10 bg-white/6 p-5 sm:w-140 sm:max-w-140 sm:p-6 lg:w-170 lg:max-w-170 lg:p-7 backdrop-blur-2xl shadow-[0_16px_50px_rgba(0,0,0,0.18)]">
                            <div
                                class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.12),transparent_58%)] opacity-0 transition duration-500 group-[.is-active]:opacity-100">
                            </div>

                            <div class="relative z-10 flex h-full flex-col">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center gap-1 text-cyan-300 text-base sm:text-lg">
                                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                                    </div>

                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-cyan-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M7.5 8.25h9m-9 3h6m-9 8.25h12A2.25 2.25 0 0018.75 17.25V6.75A2.25 2.25 0 0016.5 4.5h-9A2.25 2.25 0 005.25 6.75v10.5A2.25 2.25 0 007.5 19.5z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white/10"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M7.17 6A5.001 5.001 0 002 11v7h7v-7H5.08A3.001 3.001 0 017.17 8.17V6zm9 0A5.001 5.001 0 0011 11v7h7v-7h-3.92a3.001 3.001 0 012.09-2.83V6z" />
                                    </svg>

                                    <p class="mt-4 text-sm sm:text-base leading-7 sm:leading-8 text-blue-50/88">
                                        We needed secure infrastructure, email, networking, and monitoring. Their team
                                        handled
                                        everything smoothly and gave us confidence from the very beginning.
                                    </p>
                                </div>

                                <div class="mt-auto pt-6">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-13 w-13 items-center justify-center rounded-full border border-white/10 bg-sky-500/20 text-white font-bold text-base">
                                            N
                                        </div>

                                        <div class="min-w-0">
                                            <h4 class="text-sm sm:text-base font-semibold text-white">Nafis Ahmed</h4>
                                            <p class="text-xs sm:text-sm text-blue-100/55">Operations Lead</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Card 3 -->
                        <article
                            class="review-card group relative w-[86vw] max-w-[86vw] shrink-0 overflow-hidden rounded-[30px] border border-white/10 bg-white/6 p-5 sm:w-140 sm:max-w-140 sm:p-6 lg:w-170 lg:max-w-170 lg:p-7 backdrop-blur-2xl shadow-[0_16px_50px_rgba(0,0,0,0.18)]">
                            <div
                                class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.12),transparent_58%)] opacity-0 transition duration-500 group-[.is-active]:opacity-100">
                            </div>

                            <div class="relative z-10 flex h-full flex-col">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center gap-1 text-cyan-300 text-base sm:text-lg">
                                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                                    </div>

                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-cyan-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M7.5 8.25h9m-9 3h6m-9 8.25h12A2.25 2.25 0 0018.75 17.25V6.75A2.25 2.25 0 0016.5 4.5h-9A2.25 2.25 0 005.25 6.75v10.5A2.25 2.25 0 007.5 19.5z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white/10"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M7.17 6A5.001 5.001 0 002 11v7h7v-7H5.08A3.001 3.001 0 017.17 8.17V6zm9 0A5.001 5.001 0 0011 11v7h7v-7h-3.92a3.001 3.001 0 012.09-2.83V6z" />
                                    </svg>

                                    <p class="mt-4 text-sm sm:text-base leading-7 sm:leading-8 text-blue-50/88">
                                        Their blend of technical expertise and business understanding made a big
                                        difference.
                                        The final result was practical, modern, and clearly built for growth.
                                    </p>
                                </div>

                                <div class="mt-auto pt-6">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-13 w-13 items-center justify-center rounded-full border border-white/10 bg-violet-500/20 text-white font-bold text-base">
                                            S
                                        </div>

                                        <div class="min-w-0">
                                            <h4 class="text-sm sm:text-base font-semibold text-white">Sarah Khan</h4>
                                            <p class="text-xs sm:text-sm text-blue-100/55">Founder & CEO</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Card 4 -->
                        <article
                            class="review-card group relative w-[86vw] max-w-[86vw] shrink-0 overflow-hidden rounded-[30px] border border-white/10 bg-white/6 p-5 sm:w-140 sm:max-w-140 sm:p-6 lg:w-170 lg:max-w-170 lg:p-7 backdrop-blur-2xl shadow-[0_16px_50px_rgba(0,0,0,0.18)]">
                            <div
                                class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.12),transparent_58%)] opacity-0 transition duration-500 group-[.is-active]:opacity-100">
                            </div>

                            <div class="relative z-10 flex h-full flex-col">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center gap-1 text-cyan-300 text-base sm:text-lg">
                                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                                    </div>

                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-cyan-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M7.5 8.25h9m-9 3h6m-9 8.25h12A2.25 2.25 0 0018.75 17.25V6.75A2.25 2.25 0 0016.5 4.5h-9A2.25 2.25 0 005.25 6.75v10.5A2.25 2.25 0 007.5 19.5z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white/10"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M7.17 6A5.001 5.001 0 002 11v7h7v-7H5.08A3.001 3.001 0 017.17 8.17V6zm9 0A5.001 5.001 0 0011 11v7h7v-7h-3.92a3.001 3.001 0 012.09-2.83V6z" />
                                    </svg>

                                    <p class="mt-4 text-sm sm:text-base leading-7 sm:leading-8 text-blue-50/88">
                                        Their team helped us streamline our systems and improve stability across the
                                        office.
                                        Communication was clear, and the whole process felt dependable.
                                    </p>
                                </div>

                                <div class="mt-auto pt-6">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-13 w-13 items-center justify-center rounded-full border border-white/10 bg-emerald-500/20 text-white font-bold text-base">
                                            R
                                        </div>

                                        <div class="min-w-0">
                                            <h4 class="text-sm sm:text-base font-semibold text-white">Rifat Hasan</h4>
                                            <p class="text-xs sm:text-sm text-blue-100/55">Business Owner</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Card 5 -->
                        <article
                            class="review-card group relative w-[86vw] max-w-[86vw] shrink-0 overflow-hidden rounded-[30px] border border-white/10 bg-white/6 p-5 sm:w-[560px] sm:max-w-[560px] sm:p-6 lg:w-170 lg:max-w-170 lg:p-7 backdrop-blur-2xl shadow-[0_16px_50px_rgba(0,0,0,0.18)]">
                            <div
                                class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.12),transparent_58%)] opacity-0 transition duration-500 group-[.is-active]:opacity-100">
                            </div>

                            <div class="relative z-10 flex h-full flex-col">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center gap-1 text-cyan-300 text-base sm:text-lg">
                                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                                    </div>

                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-cyan-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M7.5 8.25h9m-9 3h6m-9 8.25h12A2.25 2.25 0 0018.75 17.25V6.75A2.25 2.25 0 0016.5 4.5h-9A2.25 2.25 0 005.25 6.75v10.5A2.25 2.25 0 007.5 19.5z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white/10"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M7.17 6A5.001 5.001 0 002 11v7h7v-7H5.08A3.001 3.001 0 017.17 8.17V6zm9 0A5.001 5.001 0 0011 11v7h7v-7h-3.92a3.001 3.001 0 012.09-2.83V6z" />
                                    </svg>

                                    <p class="mt-4 text-sm sm:text-base leading-7 sm:leading-8 text-blue-50/88">
                                        From email migration to network improvements, everything was done with care. We
                                        now
                                        have a much more stable, secure, and efficient setup.
                                    </p>
                                </div>

                                <div class="mt-auto pt-6">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-13 w-13 items-center justify-center rounded-full border border-white/10 bg-pink-500/20 text-white font-bold text-base">
                                            T
                                        </div>

                                        <div class="min-w-0">
                                            <h4 class="text-sm sm:text-base font-semibold text-white">Tanvir Hossain
                                            </h4>
                                            <p class="text-xs sm:text-sm text-blue-100/55">Admin Manager</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-center gap-3">
                    <button type="button" @click="prev()"
                        class="group flex h-12 w-12 items-center justify-center rounded-full border border-white/10 bg-white/8 text-white backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/12 cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 transition group-hover:-translate-x-0.5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-2" x-ref="dots"></div>

                    <button type="button" @click="next()"
                        class="group flex h-12 w-12 items-center justify-center rounded-full border border-white/10 bg-white/8 text-white backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/12 cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition group-hover:translate-x-0.5"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12l-7.5 7.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>


    @push('scripts')
        <script>
            function logoMarquee() {
                return {
                    logos: [{
                            src: 'https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/google/wordmark.svg',
                            alt: 'Google'
                        },
                        {
                            src: 'https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/medium/default.svg',
                            alt: 'Medium'
                        },
                        {
                            src: 'https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/meta/default.svg',
                            alt: 'Meta'
                        },
                        {
                            src: 'https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/microsoft/default.svg',
                            alt: 'Microsoft'
                        },
                        {
                            src: 'https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/stripe/default.svg',
                            alt: 'Stripe'
                        },
                        {
                            src: 'https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/amazon/default.svg',
                            alt: 'Amazon'
                        },
                        {
                            src: 'https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/discord/default.svg',
                            alt: 'Discord'
                        }
                    ],

                    paused: false,
                    offset: 0,
                    speed: 0.6,
                    animationFrame: null,
                    loopWidth: 0,

                    init() {
                        this.$nextTick(() => {
                            this.waitForImages().then(() => {
                                this.build();
                                this.animate();

                                let resizeTimer;
                                window.addEventListener('resize', () => {
                                    clearTimeout(resizeTimer);
                                    resizeTimer = setTimeout(() => {
                                        this.build();
                                    }, 120);
                                });
                            });
                        });
                    },

                    waitForImages() {
                        const images = Array.from(this.$refs.track.querySelectorAll('img'));

                        return Promise.all(
                            images.map(img => {
                                if (img.complete) return Promise.resolve();

                                return new Promise(resolve => {
                                    img.addEventListener('load', resolve, {
                                        once: true
                                    });
                                    img.addEventListener('error', resolve, {
                                        once: true
                                    });
                                });
                            })
                        );
                    },

                    build() {
                        const track = this.$refs.track;
                        const slider = this.$refs.slider;

                        cancelAnimationFrame(this.animationFrame);

                        // remove old clones
                        track.querySelectorAll('[data-clone="true"]').forEach(el => el.remove());

                        const originalItems = Array.from(track.children);
                        const gap = parseFloat(getComputedStyle(track).gap) || 0;

                        this.loopWidth = originalItems.reduce((sum, item) => sum + item.offsetWidth, 0) +
                            gap * (originalItems.length - 1);

                        // clone until enough width exists for seamless loop
                        while (track.scrollWidth < slider.offsetWidth + this.loopWidth * 2) {
                            originalItems.forEach(item => {
                                const clone = item.cloneNode(true);
                                clone.setAttribute('data-clone', 'true');
                                clone.setAttribute('aria-hidden', 'true');
                                track.appendChild(clone);
                            });
                        }

                        this.offset = 0;
                        track.style.transform = `translate3d(0,0,0)`;
                    },

                    animate() {
                        const step = () => {
                            if (!this.paused) {
                                this.offset += this.speed;

                                if (this.offset >= this.loopWidth) {
                                    this.offset = 0;
                                }

                                this.$refs.track.style.transform = `translate3d(-${this.offset}px, 0, 0)`;
                            }

                            this.animationFrame = requestAnimationFrame(step);
                        };

                        step();
                    }
                }
            }


            // Review slider
            function reviewCarousel() {
                return {
                    active: 0,
                    timer: null,
                    cards: [],
                    originals: 0,
                    gap: 0,
                    transitioning: true,

                    init() {
                        this.$nextTick(() => {
                            const track = this.$refs.track;
                            this.cards = Array.from(track.children);
                            this.originals = this.cards.length;

                            if (this.originals < 2) return;

                            const firstClone = this.cards[0].cloneNode(true);
                            const lastClone = this.cards[this.cards.length - 1].cloneNode(true);

                            firstClone.setAttribute('data-clone', 'true');
                            lastClone.setAttribute('data-clone', 'true');

                            track.insertBefore(lastClone, track.firstChild);
                            track.appendChild(firstClone);

                            this.cards = Array.from(track.children);
                            this.active = 1;

                            this.buildDots();
                            this.updateActiveClasses();
                            this.center(false);
                            this.bindResize();
                            this.play();
                        });
                    },

                    bindResize() {
                        let resizeTimer;
                        window.addEventListener('resize', () => {
                            clearTimeout(resizeTimer);
                            resizeTimer = setTimeout(() => {
                                this.center(false);
                            }, 120);
                        });
                    },

                    buildDots() {
                        const dots = this.$refs.dots;
                        dots.innerHTML = '';

                        for (let i = 0; i < this.originals; i++) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'h-2.5 rounded-full transition-all duration-300';
                            btn.addEventListener('click', () => {
                                this.goTo(i + 1);
                            });
                            dots.appendChild(btn);
                        }

                        this.updateDots();
                    },

                    updateDots() {
                        const realIndex = this.getRealIndex();
                        Array.from(this.$refs.dots.children).forEach((dot, i) => {
                            dot.className = 'h-2.5 rounded-full transition-all duration-300 ' +
                                (i === realIndex ?
                                    'w-8 bg-cyan-300 shadow-[0_0_18px_rgba(103,232,249,0.65)]' :
                                    'w-2.5 bg-white/20 hover:bg-white/35');
                        });
                    },

                    getRealIndex() {
                        if (this.active === 0) return this.originals - 1;
                        if (this.active === this.cards.length - 1) return 0;
                        return this.active - 1;
                    },

                    updateActiveClasses() {
                        this.cards.forEach((card, index) => {
                            card.classList.remove('is-active', 'scale-100', 'opacity-100', 'z-20', 'border-cyan-300/20',
                                'shadow-[0_25px_80px_rgba(0,0,0,0.22)]');
                            card.classList.remove('scale-[0.94]', 'opacity-55', 'z-10');

                            if (index === this.active) {
                                card.classList.add('is-active', 'scale-100', 'opacity-100', 'z-20',
                                    'border-cyan-300/20', 'shadow-[0_25px_80px_rgba(0,0,0,0.22)]');
                            } else {
                                card.classList.add('scale-[0.94]', 'opacity-55', 'z-10');
                            }
                        });

                        this.updateDots();
                    },

                    center(withAnimation = true) {
                        const viewport = this.$refs.viewport;
                        const track = this.$refs.track;
                        const card = this.cards[this.active];
                        if (!card) return;

                        this.transitioning = withAnimation;
                        track.style.transitionDuration = withAnimation ? '700ms' : '0ms';

                        const viewportWidth = viewport.offsetWidth;
                        const cardLeft = card.offsetLeft;
                        const cardWidth = card.offsetWidth;
                        const target = cardLeft - ((viewportWidth - cardWidth) / 2);

                        track.style.transform = `translate3d(${-target}px,0,0)`;
                        this.updateActiveClasses();
                    },

                    next() {
                        this.pause();
                        this.active++;
                        this.center(true);
                        this.afterMove();
                    },

                    prev() {
                        this.pause();
                        this.active--;
                        this.center(true);
                        this.afterMove();
                    },

                    goTo(index) {
                        this.pause();
                        this.active = index;
                        this.center(true);
                        this.afterMove();
                    },

                    afterMove() {
                        const track = this.$refs.track;

                        const handler = () => {
                            track.removeEventListener('transitionend', handler);

                            if (this.active === this.cards.length - 1) {
                                this.active = 1;
                                this.center(false);
                            } else if (this.active === 0) {
                                this.active = this.cards.length - 2;
                                this.center(false);
                            }

                            this.play();
                        };

                        track.addEventListener('transitionend', handler, {
                            once: true
                        });
                    },

                    play() {
                        this.pause();
                        this.timer = setInterval(() => {
                            this.next();
                        }, 4200);
                    },

                    pause() {
                        if (this.timer) {
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                    }
                }
            }
        </script>
    @endpush
</div>