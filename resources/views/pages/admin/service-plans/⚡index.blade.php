<?php

use App\Models\Service;
use App\Models\ServicePlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Service Plans')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public string $serviceFilter = 'all';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedServiceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function services()
    {
        return Service::query()->where('is_active', true)->orderBy('card_title')->get();
    }

    public function plans()
    {
        $search = trim($this->search);

        return ServicePlan::query()
            ->with('service', 'serviceOption')
            ->withCount('addons')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('badge', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhereHas('service', function ($serviceQuery) use ($search) {
                            $serviceQuery->where('card_title', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('serviceOption', function ($optionQuery) use ($search) {
                            $optionQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($this->status !== 'all', function ($query) {
                $query->where('is_active', $this->status === 'active');
            })
            ->when($this->serviceFilter !== 'all', function ($query) {
                $query->where('service_id', $this->serviceFilter);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function toggleStatus(int $planId): void
    {
        $plan = ServicePlan::findOrFail($planId);

        $plan->update([
            'is_active' => !$plan->is_active,
        ]);

        $this->dispatch('toast', message: 'Service plan status updated successfully.', type: 'success');
    }

    public function delete(int $planId): void
    {
        ServicePlan::findOrFail($planId)->delete();

        $this->dispatch('toast', message: 'Service plan deleted successfully.', type: 'success');
    }

    public function hasPrice($price): bool
    {
        return $price !== null && $price !== '' && (float) $price > 0;
    }

    public function hasDiscount($regularPrice, $discountPrice): bool
    {
        return $this->hasPrice($regularPrice) && $this->hasPrice($discountPrice) && (float) $discountPrice < (float) $regularPrice;
    }

    public function finalPrice($regularPrice, $discountPrice): ?float
    {
        if (!$this->hasPrice($regularPrice)) {
            return null;
        }

        return $this->hasDiscount($regularPrice, $discountPrice) ? (float) $discountPrice : (float) $regularPrice;
    }
};
?>

<div>
    <div class="mx-auto w-full max-w-7xl space-y-stack-lg">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Service Plans
                </h2>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    Manage service-based plans and connect each plan to your external cart or checkout page.
                </p>
            </div>

            <div class="flex w-full flex-col gap-4 lg:w-auto lg:flex-row lg:items-center">
                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:max-w-4xl">
                    <div class="relative sm:col-span-2 xl:col-span-1">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            search
                        </span>

                        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search plan..."
                            class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-label-md font-label-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    </div>

                    <div class="relative">
                        <select wire:model.live="serviceFilter"
                            class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="all">All Services</option>

                            @foreach ($this->services() as $service)
                                <option value="{{ $service->id }}">
                                    {{ $service->card_title }}
                                </option>
                            @endforeach
                        </select>

                        <span
                            class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            expand_more
                        </span>
                    </div>

                    <div class="relative">
                        <select wire:model.live="status"
                            class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>

                        <span
                            class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            expand_more
                        </span>
                    </div>
                </div>

                <a href="{{ route('admin.service-plans.create') }}" wire:navigate
                    class="flex w-full shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-label-md font-label-md text-on-primary transition-all hover:shadow-lg hover:shadow-primary/20 active:scale-[0.98] sm:w-auto">
                    <span class="material-symbols-outlined text-lg">add</span>
                    Create New
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/50">
                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Plan
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Service
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Option
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Price
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Addons
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Sort
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Status
                            </th>

                            <th
                                class="px-6 py-4 text-right text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->plans() as $plan)
                            @php
                                $hasOneTimePrice = (bool) $plan->has_one_time_price && $this->hasPrice($plan->price);

                                $hasMonthlyPrice =
                                    (bool) $plan->has_monthly_price && $this->hasPrice($plan->monthly_price);

                                $hasYearlyPrice =
                                    (bool) $plan->has_yearly_price && $this->hasPrice($plan->yearly_price);

                                $monthlyFinal = $this->finalPrice($plan->monthly_price, $plan->monthly_discount_price);
                                $yearlyFinal = $this->finalPrice($plan->yearly_price, $plan->yearly_discount_price);
                                $oneTimeFinal = $this->finalPrice($plan->price, $plan->discount_price);

                                $hasMonthlyDiscount = $this->hasDiscount(
                                    $plan->monthly_price,
                                    $plan->monthly_discount_price,
                                );
                                $hasYearlyDiscount = $this->hasDiscount(
                                    $plan->yearly_price,
                                    $plan->yearly_discount_price,
                                );
                                $hasOneTimeDiscount = $this->hasDiscount($plan->price, $plan->discount_price);
                            @endphp

                            <tr wire:key="service-plan-{{ $plan->id }}"
                                class="transition-colors hover:bg-slate-50/80">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-label-md font-label-md text-on-surface">
                                                {{ $plan->name }}
                                            </span>

                                            @if ($plan->badge)
                                                <span
                                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                                                    {{ $plan->badge }}
                                                </span>
                                            @endif
                                        </div>

                                        <span class="block font-mono text-[11px] text-slate-400">
                                            {{ $plan->slug }}
                                        </span>

                                        @if ($plan->description)
                                            <span class="mt-1 block max-w-md truncate text-body-sm text-secondary">
                                                {{ $plan->description }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="text-body-sm text-on-surface">
                                        {{ $plan->service?->card_title ?? 'No Service' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="text-body-sm text-on-surface">
                                        {{ $plan->serviceOption?->card_title ?? '—' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        @if ($hasMonthlyPrice)
                                            <div>
                                                <span class="text-xs font-semibold text-secondary">Monthly:</span>

                                                <span class="font-mono text-body-sm text-on-surface">
                                                    ৳{{ number_format((float) $monthlyFinal, 2) }}
                                                </span>

                                                @if ($hasMonthlyDiscount)
                                                    <span class="ml-1 font-mono text-xs text-slate-400 line-through">
                                                        ৳{{ number_format((float) $plan->monthly_price, 2) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($hasYearlyPrice)
                                            <div>
                                                <span class="text-xs font-semibold text-secondary">Yearly:</span>

                                                <span class="font-mono text-body-sm text-on-surface">
                                                    ৳{{ number_format((float) $yearlyFinal, 2) }}
                                                </span>

                                                @if ($hasYearlyDiscount)
                                                    <span class="ml-1 font-mono text-xs text-slate-400 line-through">
                                                        ৳{{ number_format((float) $plan->yearly_price, 2) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                        @if (!$hasMonthlyPrice && !$hasYearlyPrice && $hasOneTimePrice)
                                            <div>
                                                <span class="font-mono text-body-sm text-on-surface">
                                                    ৳{{ number_format((float) $oneTimeFinal, 2) }}
                                                </span>

                                                @if ($hasOneTimeDiscount)
                                                    <span class="ml-1 font-mono text-xs text-slate-400 line-through">
                                                        ৳{{ number_format((float) $plan->price, 2) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                        @if (!$hasMonthlyPrice && !$hasYearlyPrice && !$hasOneTimePrice)
                                            <span class="text-body-sm text-slate-400">
                                                Custom
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    @php
                                        $addonCount = $plan->addons_count;
                                    @endphp

                                    @if ($addonCount > 0)
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
                                            <span class="material-symbols-outlined text-[14px]">extension</span>
                                            {{ $addonCount }}
                                        </span>
                                    @else
                                        <span class="text-body-sm text-secondary">—</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <span class="font-mono text-body-sm text-secondary">
                                        {{ $plan->sort_order }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <button type="button" wire:click="toggleStatus({{ $plan->id }})"
                                        class="flex items-center gap-2">
                                        @if ($plan->is_active)
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-green-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Active</span>
                                        @else
                                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Inactive</span>
                                        @endif
                                    </button>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button type="button" @click="open = !open"
                                            class="text-slate-400 transition-colors hover:text-primary">
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>

                                        <div x-cloak x-show="open" @click.outside="open = false" x-transition
                                            class="absolute right-0 z-20 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                            <a href="{{ route('admin.service-plans.edit', $plan) }}" wire:navigate
                                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                Edit
                                            </a>

                                            @if ($plan->buy_url)
                                                <a href="{{ $plan->buy_url }}" target="_blank"
                                                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span
                                                        class="material-symbols-outlined text-[18px]">shopping_cart</span>
                                                    Open Buy URL
                                                </a>
                                            @endif

                                            <button type="button" wire:click="toggleStatus({{ $plan->id }})"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">
                                                    {{ $plan->is_active ? 'visibility_off' : 'visibility' }}
                                                </span>

                                                {{ $plan->is_active ? 'Make Inactive' : 'Make Active' }}
                                            </button>

                                            <button type="button" wire:click="delete({{ $plan->id }})"
                                                wire:confirm="Are you sure you want to delete this service plan?"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div
                                            class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <span class="material-symbols-outlined">inventory_2</span>
                                        </div>

                                        <h3 class="text-base font-semibold text-on-surface">
                                            No service plans found
                                        </h3>

                                        <p class="mt-1 text-sm text-secondary">
                                            Create your first service plan for your service detail page.
                                        </p>

                                        <a href="{{ route('admin.service-plans.create') }}" wire:navigate
                                            class="mt-5 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                                            Create Service Plan
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/30 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-body-sm font-body-sm text-secondary">Per page</span>

                    <select wire:model.live="perPage"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 focus:border-primary focus:ring-primary/10">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div>
                    {{ $this->plans()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
