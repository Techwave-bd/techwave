<?php

use App\Models\ToolCategory;
use Livewire\Component;

new class extends Component {
    public function categories()
    {
        return ToolCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['tools', 'activePlans'])
            ->get();
    }

    public function hasActiveSubscription(?ToolCategory $category): bool
    {
        if (!auth()->check() || !$category) {
            return false;
        }

        return auth()->user()->hasActiveToolSubscription($category);
    }
};
?>

<div class="min-h-screen text-white">
    <div class="mx-auto max-w-350 px-4 py-10 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-10 text-center">
            <div
                class="mx-auto mb-5 inline-flex rounded-full border border-white/10 bg-white/6 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-cyan-200/80 backdrop-blur-xl">
                Tools Library
            </div>

            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">
                All
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">Tools</span>
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-sm leading-6 text-blue-100/60 sm:text-base">
                Browse tools by category. One premium plan covers every tool in a category.
            </p>
        </div>

        {{-- Categories --}}
        <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->categories() as $category)
                @php $isPremium = $this->hasActiveSubscription($category); @endphp

                <div
                    class="group relative overflow-hidden rounded-4xl border border-white/12 bg-white/5.5 p-6 shadow-[0_24px_70px_rgba(0,0,0,0.22)] backdrop-blur-2xl transition duration-300 hover:-translate-y-1 hover:border-cyan-300/30 hover:bg-white/7.5">

                    {{-- Soft hover glow --}}
                    <div
                        class="pointer-events-none absolute -right-16 -top-16 h-44 w-44 rounded-full bg-cyan-400/10 blur-3xl transition duration-300 group-hover:bg-cyan-400/16">
                    </div>

                    {{-- Premium top-right --}}
                    <div class="absolute right-4 top-4 z-10">
                        @auth
                            @if ($isPremium)
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300 shadow-lg shadow-emerald-500/5">
                                    <span class="material-symbols-outlined text-[14px]">verified</span>
                                    Active
                                </span>
                            @elseif ($firstPlan = $category->activePlans->first())
                                <a href="{{ route('client.tool-subscriptions.checkout', $firstPlan) }}" wire:navigate
                                    class="inline-flex items-center gap-1 rounded-full bg-linear-to-r from-cyan-500 to-blue-500 px-3 py-1 text-xs font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 hover:shadow-cyan-500/35">
                                    <span class="material-symbols-outlined text-[14px]">workspace_premium</span>
                                    Premium
                                </a>
                            @endif
                        @endauth

                        @guest
                            <button type="button"
                                @click="window.dispatchEvent(new CustomEvent('open-auth', { detail: { mode: 'login' } }))"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-full bg-linear-to-r from-cyan-500 to-blue-500 px-3 py-1 text-xs font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 hover:shadow-cyan-500/35">
                                <span class="material-symbols-outlined text-[14px]">workspace_premium</span>
                                Premium
                            </button>
                        @endguest
                    </div>

                    <div class="relative">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-300/15 bg-cyan-400/10 text-cyan-300 shadow-lg shadow-cyan-500/5">
                            <span class="material-symbols-outlined">{{ $category->icon ?? 'build' }}</span>
                        </div>

                        <h3 class="mt-5 pr-24 text-xl font-bold text-white">{{ $category->name }}</h3>

                        @if ($category->description)
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-blue-100/58">
                                {{ $category->description }}
                            </p>
                        @endif

                        <ul class="mt-5 space-y-2.5">
                            @forelse ($category->tools as $tool)
                                <li
                                    class="flex items-center gap-3 rounded-2xl border border-white/8 bg-black/10 px-3 py-2.5 text-sm text-blue-50/82 transition hover:border-cyan-300/18 hover:bg-white/5.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-cyan-300/70"></span>

                                    <div class="min-w-0 flex-1">
                                        @if ($tool->route)
                                            <a href="{{ route($tool->route) }}" wire:navigate
                                                class="block truncate transition-colors hover:text-cyan-300">
                                                {{ $tool->name }}
                                            </a>
                                        @else
                                            <span class="block truncate">{{ $tool->name }}</span>
                                        @endif
                                    </div>
                                </li>
                            @empty
                                <li
                                    class="rounded-2xl border border-white/8 bg-black/10 px-4 py-4 text-center text-sm text-blue-100/40">
                                    No tools yet.
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
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
        </div>
    </div>
</div>
