<?php

use App\Models\ToolCategory;
use Livewire\Component;

new class extends Component {
    public $categories;

    public array $activeSubscriptions = [];

    public mixed $checkoutPlan = null;

    public function mount(): void
    {
        $this->loadCategories();
        $this->loadActiveSubscriptions();

        $this->checkoutPlan = $this->categories
            ->flatMap(fn ($category) => $category->activePlans)
            ->first();
    }

    private function loadCategories(): void
    {
        $this->categories = ToolCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'tools' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->getQuery();
                },
                'activePlans' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->getQuery();
                },
            ])
            ->get();
    }

    private function loadActiveSubscriptions(): void
    {
        if (! auth()->check()) {
            $this->activeSubscriptions = [];

            return;
        }

        $this->activeSubscriptions = auth()->user()
            ->toolSubscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get([
                'id',
                'tool_category_id',
                'status',
                'expires_at',
            ])
            ->keyBy('tool_category_id')
            ->toArray();
    }

    public function hasActiveSubscription(int $categoryId): bool
    {
        return isset($this->activeSubscriptions[$categoryId]);
    }
};
?>

<div class="min-h-screen text-white">
    <main class="relative mx-auto max-w-350 px-4 py-8 md:py-10 sm:px-6 lg:px-8">

        {{-- Hero Section --}}
        <section class="relative mb-12 overflow-hidden text-center">
            <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 mx-auto h-72 max-w-4xl"></div>

            <h1 class="text-5xl font-extrabold leading-tight tracking-tight sm:text-6xl lg:text-7xl">
                All
                <span class="bg-linear-to-r from-cyan-300 to-blue-500 bg-clip-text italic text-transparent">
                    Tools
                </span>
            </h1>

            <p class="mx-auto mt-3 lg:mt-5 max-w-2xl text-sm leading-7 text-blue-100/60 sm:text-base lg:text-lg">
                Explore our complete ecosystem of specialized tools. One premium plan unlocks every tool in a category.
            </p>
        </section>

        {{-- Category Grid --}}
        <section id="tools-library" class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($categories as $category)
                @php
                    $isPremium = $this->hasActiveSubscription($category->id);
                    $firstPlan = $category->activePlans->first();
                @endphp

                <article
                    class="group relative flex min-h-80 cursor-default flex-col overflow-hidden rounded-2xl lg:rounded-[1.75rem] border border-white/10 bg-white/5.5 p-6 shadow-[0_24px_70px_rgba(0,0,0,0.22)] backdrop-blur-2xl transition duration-300 hover:-translate-y-1 hover:border-cyan-300/35 hover:bg-white/7.5 hover:shadow-[0_26px_80px_rgba(6,182,212,0.16)]">

                    {{-- Circuit style background --}}
                    <div
                        class="pointer-events-none absolute inset-0 opacity-[0.08] bg-[linear-gradient(rgba(255,255,255,.18)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.18)_1px,transparent_1px)] bg-size-[32px_32px]">
                    </div>

                    {{-- Soft glow --}}
                    <div
                        class="pointer-events-none absolute -right-20 -top-20 h-52 w-52 rounded-full bg-cyan-400/10 blur-3xl transition duration-500 group-hover:scale-125 group-hover:bg-cyan-400/18">
                    </div>

                    <div class="relative z-10 flex items-start justify-between gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-white/8 bg-white/7.5 text-cyan-300 shadow-lg shadow-cyan-500/5">
                            <span class="material-symbols-outlined text-[30px]">
                                {{ $category->icon ?? 'build' }}
                            </span>
                        </div>

                        <div class="shrink-0">
                            @auth
                                @if ($isPremium)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300 shadow-lg shadow-emerald-500/5">
                                        <span class="material-symbols-outlined text-[14px]">verified</span>
                                        Active
                                    </span>
                                @elseif ($firstPlan)
                                    <a href="{{ route('client.tool-subscriptions.checkout', $firstPlan) }}" wire:navigate
                                        class="inline-flex items-center gap-1 rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-cyan-300 shadow-lg shadow-cyan-500/10 transition hover:-translate-y-0.5 hover:bg-cyan-400/15 hover:shadow-cyan-500/20">
                                        <span class="material-symbols-outlined text-[14px]">workspace_premium</span>
                                        Premium
                                    </a>
                                @endif
                            @endauth

                            @guest
                                <button type="button"
                                    @click="window.dispatchEvent(new CustomEvent('open-auth', { detail: { mode: 'login' } }))"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-cyan-300 shadow-lg shadow-cyan-500/10 transition hover:-translate-y-0.5 hover:bg-cyan-400/15 hover:shadow-cyan-500/20">
                                    <span class="material-symbols-outlined text-[14px]">workspace_premium</span>
                                    Premium
                                </button>
                            @endguest
                        </div>
                    </div>

                    <div class="relative z-10 mt-5">
                        <h3 class="text-2xl font-bold tracking-tight text-white">
                            {{ $category->name }}
                        </h3>

                        @if ($category->description)
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-blue-100/58">
                                {{ $category->description }}
                            </p>
                        @endif
                    </div>

                    <div class="relative z-10 mt-5 flex flex-1 flex-col gap-2">
                        @forelse ($category->tools as $tool)
                            @if ($tool->route)
                                <a href="{{ route($tool->route) }}" wire:navigate
                                    class="group/link flex items-center justify-between gap-3 rounded-xl border border-transparent bg-white/5.5 px-4 py-3 text-sm font-semibold text-blue-50/85 transition hover:border-cyan-300/20 hover:bg-cyan-400/10 hover:text-cyan-100">
                                    <span class="min-w-0 truncate">{{ $tool->name }}</span>
                                    <span
                                        class="material-symbols-outlined text-base text-cyan-300 opacity-0 transition duration-200 group-hover/link:translate-x-1 group-hover/link:opacity-100">
                                        arrow_forward
                                    </span>
                                </a>
                            @else
                                <div
                                    class="flex items-center justify-between gap-3 rounded-xl border border-white/8 bg-white/[0.035] px-4 py-3 text-sm font-semibold text-blue-50/55">
                                    <span class="min-w-0 truncate">{{ $tool->name }}</span>
                                    <span class="material-symbols-outlined text-base text-blue-100/25">lock</span>
                                </div>
                            @endif
                        @empty
                            <div
                                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-white/10 bg-black/10 px-4 py-10 text-center">
                                <span class="material-symbols-outlined text-4xl text-blue-100/20">terminal</span>
                                <span class="mt-2 text-[10px] font-bold uppercase tracking-[0.22em] text-blue-100/35">
                                    Coming Soon
                                </span>
                            </div>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="col-span-full py-20 text-center">
                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-white/10 bg-white/8 text-blue-100/40">
                        <span class="material-symbols-outlined text-3xl">construction</span>
                    </div>

                    <h3 class="mt-4 text-lg font-semibold text-white">No tools available yet</h3>
                    <p class="mt-1 text-sm text-blue-100/50">Check back soon for new tools.</p>
                </div>
            @endforelse
        </section>

        {{-- Premium CTA --}}
        <section
            class="group relative mt-12 flex min-h-90 flex-col items-center justify-center overflow-hidden rounded-2xl lg:rounded-4xl border border-white/10 bg-white/5.5 px-5 py-12 text-center shadow-[0_30px_100px_rgba(0,0,0,0.28)] backdrop-blur-2xl sm:px-10">

            <div
                class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.18),transparent_36%),radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.16),transparent_34%)] opacity-80 transition duration-700 group-hover:opacity-100">
            </div>

            <div
                class="pointer-events-none absolute inset-0 -z-10 opacity-[0.06] bg-[linear-gradient(rgba(255,255,255,.2)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.2)_1px,transparent_1px)] bg-size-[38px_38px]">
            </div>

            <div
                class="inline-flex items-center gap-2 rounded-full border border-cyan-300/25 bg-cyan-400/10 px-4 py-1.5 text-[10px] font-bold uppercase tracking-[0.22em] text-cyan-300">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">stars</span>
                Ultimate Access
            </div>

            <h2 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
                Upgrade to
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                    Premium
                </span>
            </h2>

            <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-blue-100/60 sm:text-base">
                Unlock every high-end tool in your selected category with a clean workflow and professional-grade
                access.
            </p>

            <div class="mt-8 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row">
                @guest
                    <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-auth', { detail: { mode: 'login' } }))"
                        class="group/btn relative inline-flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 px-8 py-4 text-sm font-black uppercase tracking-wider text-white shadow-2xl shadow-cyan-500/25 transition hover:-translate-y-0.5 hover:shadow-cyan-500/35 sm:w-auto">
                        <span
                            class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover/btn:left-full"></span>
                        <span class="relative">Login to Unlock</span>
                    </button>
                @else
                    @if ($checkoutPlan)
                        <a href="{{ route('client.tool-subscriptions.checkout', $checkoutPlan) }}" wire:navigate
                            class="group/btn relative inline-flex w-full items-center justify-center overflow-hidden rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 px-8 py-4 text-sm font-black uppercase tracking-wider text-white shadow-2xl shadow-cyan-500/25 transition hover:-translate-y-0.5 hover:shadow-cyan-500/35 sm:w-auto">
                            <span
                                class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover/btn:left-full"></span>
                            <span class="relative">Unlock Everything</span>
                        </a>
                    @endif
                @endguest

                <a href="#tools-library"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-white/15 bg-white/6 px-8 py-4 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-white/10 sm:w-auto">
                    View Tools
                </a>
            </div>
        </section>
    </main>
</div>