<?php

use App\Models\PricingPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Pricing Management')] class extends Component {
    use WithPagination;

    public ?int $editingId = null;

    public string $plan_type = 'startup';

    public string $title = '';
    public string $icon = '';
    public string $description = '';

    public ?float $monthly_price = null;
    public ?float $yearly_price = null;
    public ?float $monthly_discount_price = null;
    public ?float $yearly_discount_price = null;

    public array $features = [];
    public string $feature = '';

    public string $status = 'active';

    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';
    public int $perPage = 10;

    public array $planTypes = [
        'startup' => 'Startup',
        'business' => 'Business',
        'enterprise' => 'Enterprise',
    ];

    public array $statuses = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    protected function rules(): array
    {
        return [
            'plan_type' => ['required', 'string', 'in:startup,business,enterprise'],

            'title' => ['required', 'string', 'max:160'],
            'icon' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:500'],

            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'monthly_discount_price' => ['nullable', 'numeric', 'min:0', 'lt:monthly_price'],
            'yearly_discount_price' => ['nullable', 'numeric', 'min:0', 'lt:yearly_price'],

            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['required', 'string', 'max:160'],

            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    protected function messages(): array
    {
        return [
            'features.required' => 'Please add at least one included feature.',
            'features.min' => 'Please add at least one included feature.',
            'monthly_discount_price.lt' => 'Monthly discount price must be lower than monthly price.',
            'yearly_discount_price.lt' => 'Yearly discount price must be lower than yearly price.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (blank($this->monthly_price) && blank($this->yearly_price)) {
                $validator->errors()->add('monthly_price', 'Please enter at least monthly or yearly price.');
                $validator->errors()->add('yearly_price', 'Please enter at least monthly or yearly price.');
            }
        });
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function plans()
    {
        return PricingPlan::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%')
                        ->orWhere('plan_type', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter !== 'all', function ($query) {
                $query->where('plan_type', $this->typeFilter);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function activeCount(): int
    {
        return PricingPlan::query()->where('status', 'active')->count();
    }

    public function mostPopularPlanId(): ?int
    {
        return PricingPlan::query()->where('status', 'active')->where('purchase_count', '>', 0)->orderByDesc('purchase_count')->value('id');
    }

    public function mostPopularPlanTitle(): string
    {
        return PricingPlan::query()->where('status', 'active')->where('purchase_count', '>', 0)->orderByDesc('purchase_count')->value('title') ?? 'None';
    }

    public function totalPurchases(): int
    {
        return PricingPlan::query()->sum('purchase_count');
    }

    public function averageMonthlyPrice(): string
    {
        $avg = PricingPlan::query()->whereNotNull('monthly_price')->avg('monthly_price');

        return $avg ? number_format($avg, 2) : '0.00';
    }

    public function addFeature(): void
    {
        $feature = trim($this->feature);

        if ($feature === '') {
            $this->dispatch('toast', message: 'Please type a feature first.', type: 'warning');

            return;
        }

        if (!in_array($feature, $this->features, true)) {
            $this->features[] = $feature;
        }

        $this->feature = '';
        $this->resetValidation('features');
    }

    public function removeFeature(int $index): void
    {
        unset($this->features[$index]);

        $this->features = array_values($this->features);
    }

    public function save(): void
    {
        $validated = $this->validate();

        PricingPlan::create([
            'plan_type' => $validated['plan_type'],
            'title' => $validated['title'],
            'icon' => $validated['icon'] ?: null,
            'description' => $validated['description'],
            'monthly_price' => $validated['monthly_price'] ?? null,
            'monthly_discount_price' => $validated['monthly_discount_price'] ?? null,
            'yearly_price' => $validated['yearly_price'] ?? null,
            'yearly_discount_price' => $validated['yearly_discount_price'] ?? null,
            'features' => array_values(array_filter($validated['features'])),
            'status' => $validated['status'],
        ]);

        $this->resetForm();

        $this->dispatch('toast', message: 'Pricing plan created successfully.', type: 'success');
    }

    public function edit(int $planId): void
    {
        $plan = PricingPlan::findOrFail($planId);

        $this->editingId = $plan->id;

        $this->plan_type = $plan->plan_type;
        $this->title = $plan->title;
        $this->icon = $plan->icon ?? '';
        $this->description = $plan->description ?? '';

        $this->monthly_price = $plan->monthly_price !== null ? (float) $plan->monthly_price : null;
        $this->monthly_discount_price = $plan->monthly_discount_price !== null ? (float) $plan->monthly_discount_price : null;

        $this->yearly_price = $plan->yearly_price !== null ? (float) $plan->yearly_price : null;
        $this->yearly_discount_price = $plan->yearly_discount_price !== null ? (float) $plan->yearly_discount_price : null;

        $this->features = $plan->features ?: [];
        $this->status = $plan->status;

        $this->feature = '';

        $this->resetValidation();
    }

    public function update(): void
    {
        if (!$this->editingId) {
            return;
        }

        $validated = $this->validate();

        $plan = PricingPlan::findOrFail($this->editingId);

        $plan->update([
            'plan_type' => $validated['plan_type'],
            'title' => $validated['title'],
            'icon' => $validated['icon'] ?: null,
            'description' => $validated['description'],
            'monthly_price' => $validated['monthly_price'] ?? null,
            'monthly_discount_price' => $validated['monthly_discount_price'] ?? null,
            'yearly_price' => $validated['yearly_price'] ?? null,
            'yearly_discount_price' => $validated['yearly_discount_price'] ?? null,
            'features' => array_values(array_filter($validated['features'])),
            'status' => $validated['status'],
        ]);

        $this->resetForm();

        $this->dispatch('toast', message: 'Pricing plan updated successfully.', type: 'success');
    }

    public function toggleStatus(int $planId): void
    {
        $plan = PricingPlan::findOrFail($planId);

        $nextStatus = $plan->status === 'active' ? 'inactive' : 'active';

        $plan->update([
            'status' => $nextStatus,
        ]);

        $this->dispatch('toast', message: 'Plan status updated successfully.', type: 'success');
    }

    public function delete(int $planId): void
    {
        PricingPlan::findOrFail($planId)->delete();

        if ($this->editingId === $planId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: 'Pricing plan deleted successfully.', type: 'success');
    }

    public function discard(): void
    {
        $this->resetForm();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }

    private function resetForm(): void
    {
        $this->editingId = null;

        $this->plan_type = 'startup';

        $this->title = '';
        $this->icon = '';
        $this->description = '';

        $this->monthly_price = null;
        $this->yearly_price = null;
        $this->monthly_discount_price = null;
        $this->yearly_discount_price = null;

        $this->features = [];
        $this->feature = '';

        $this->status = 'active';

        $this->resetValidation();
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-green-50 text-green-700 border-green-100',
            'inactive' => 'bg-amber-50 text-amber-700 border-amber-100',
            default => 'bg-slate-100 text-slate-500 border-slate-200',
        };
    }

    public function planTypeBadgeClass(string $type): string
    {
        return match ($type) {
            'startup' => 'bg-blue-50 text-blue-700 border-blue-100',
            'business' => 'bg-primary/10 text-primary border-primary/10',
            'enterprise' => 'bg-purple-50 text-purple-700 border-purple-100',
            default => 'bg-slate-100 text-slate-600 border-slate-200',
        };
    }
};
?>

<div class="mx-auto space-y-stack-lg">
    <!-- Page Heading -->
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <h2 class="font-h1 text-h1 text-on-surface">Pricing Management</h2>

            <p class="mt-1 text-body-md text-on-surface-variant">
                Configure and manage subscription tiers for your infrastructure services.
            </p>
        </div>

        {{-- <div class="flex gap-stack-sm">
            <button type="button" onclick="window.print()"
                class="flex items-center gap-2 rounded border border-outline-variant bg-white px-4 py-2 text-label-md transition-colors hover:bg-surface-container">
                <span class="material-symbols-outlined text-[18px]">file_download</span>
                Export List
            </button>
        </div> --}}
    </div>

    <!-- Bento Grid Content -->
    <div class="grid grid-cols-12 gap-gutter">
        <!-- Create / Edit Plan Form Card -->
        <section class="col-span-12 rounded-xl border border-slate-200 bg-white p-stack-lg shadow-sm lg:col-span-5">
            <div class="mb-stack-lg flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">
                    {{ $editingId ? 'edit' : 'add_circle' }}
                </span>

                <h3 class="font-h3 text-h3">
                    {{ $editingId ? 'Edit Plan' : 'Create New Plan' }}
                </h3>
            </div>

            <form wire:submit.prevent="{{ $editingId ? 'update' : 'save' }}" class="space-y-stack-md">
                <div class="grid grid-cols-2 gap-stack-md">
                    <!-- Plan Type -->
                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Pricing Type
                        </label>

                        <div class="grid grid-cols-3 gap-2 rounded-lg bg-slate-100 p-1">
                            @foreach ($planTypes as $value => $label)
                                <button type="button" wire:click="$set('plan_type', '{{ $value }}')"
                                    @class([
                                        'rounded-md py-2 text-label-md transition-colors',
                                        'bg-white text-primary shadow-sm' => $plan_type === $value,
                                        'text-slate-500 hover:text-slate-700' => $plan_type !== $value,
                                    ])>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        @error('plan_type')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Icon -->
                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Plan Icon
                        </label>

                        <div class="flex gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <span class="material-symbols-outlined">
                                    {{ $icon ?: 'workspace_premium' }}
                                </span>
                            </div>

                            <input wire:model.live="icon"
                                class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                                placeholder="e.g. rocket_launch, business_center, shield" type="text" />
                        </div>

                        <p class="mt-1 text-[10px] font-bold uppercase tracking-tight text-on-surface-variant">
                            Use Material Symbol icon name.
                        </p>

                        @error('icon')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Title -->
                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Plan Title
                        </label>

                        <input wire:model="title"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="e.g. Enterprise Pro" type="text" />

                        @error('title')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Description
                        </label>

                        <textarea wire:model="description"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="Briefly describe the target audience..." rows="2"></textarea>

                        @error('description')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Monthly Price -->
                    <div>
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Monthly Price
                        </label>

                        <input wire:model="monthly_price"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="49.00" type="number" step="0.01" min="0" />

                        @error('monthly_price')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Monthly Discount Price -->
                    <div>
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Monthly Discount Price
                        </label>

                        <input wire:model="monthly_discount_price"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="39.00" type="number" step="0.01" min="0" />

                        @error('monthly_discount_price')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Yearly Price -->
                    <div>
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Yearly Price
                        </label>

                        <input wire:model="yearly_price"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="499.00" type="number" step="0.01" min="0" />

                        @error('yearly_price')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Yearly Discount Price -->
                    <div>
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Yearly Discount Price
                        </label>

                        <input wire:model="yearly_discount_price"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="399.00" type="number" step="0.01" min="0" />

                        @error('yearly_discount_price')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            Status
                        </label>

                        <select wire:model="status"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>

                        @error('status')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Features -->
                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">
                            What it includes
                        </label>

                        <div class="space-y-stack-sm">
                            <div class="flex items-center gap-2">
                                <input wire:model="feature" wire:keydown.enter.prevent="addFeature"
                                    class="flex-1 rounded border border-outline bg-white px-3 py-2 text-body-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                                    placeholder="Add a feature..." type="text" />

                                <button wire:click="addFeature"
                                    class="rounded p-2 text-primary bg-primary/5 hover:bg-primary/10 transition cursor-pointer"
                                    type="button">
                                    <span class="material-symbols-outlined">add</span>
                                </button>
                            </div>

                            <div class="flex min-h-10.5 flex-wrap gap-2 pt-2">
                                @forelse ($features as $index => $item)
                                    <span wire:key="feature-{{ $index }}"
                                        class="inline-flex items-center gap-1 rounded bg-secondary-container px-2 py-1 text-body-sm text-on-secondary-container">
                                        {{ $item }}

                                        <button type="button" wire:click="removeFeature({{ $index }})">
                                            <span class="material-symbols-outlined text-[14px]">close</span>
                                        </button>
                                    </span>
                                @empty
                                    <span class="text-sm text-slate-400">
                                        No features added yet.
                                    </span>
                                @endforelse
                            </div>

                            @error('features')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-stack-md">
                    <button type="button" wire:click="discard"
                        class="w-1/3 rounded-lg border border-outline-variant bg-white py-3 font-label-md text-on-surface transition-all hover:bg-slate-50 cursor-pointer">
                        Discard
                    </button>

                    <button
                        class="w-2/3 rounded-lg bg-primary-container py-3 font-label-md text-white shadow-sm transition-all hover:opacity-90 active:scale-[0.98] cursor-pointer"
                        type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="{{ $editingId ? 'update' : 'save' }}">
                            {{ $editingId ? 'Update Plan' : 'Save Plan' }}
                        </span>

                        <span wire:loading wire:target="{{ $editingId ? 'update' : 'save' }}">
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
        </section>

        <!-- Existing Plans List -->
        <div class="col-span-12 space-y-gutter lg:col-span-7">
            <!-- Stats / Quick Glance -->
            <div class="grid grid-cols-1 gap-stack-md md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-stack-md">
                    <p class="text-label-sm uppercase text-slate-500">Active Plans</p>
                    <h4 class="mt-1 text-h2 font-h2">{{ $this->activeCount() }}</h4>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-stack-md">
                    <p class="text-label-sm uppercase text-slate-500">Most Popular</p>
                    <h4 class="mt-1 truncate text-h2 font-h2 text-primary">
                        {{ $this->mostPopularPlanTitle() }}
                    </h4>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-stack-md">
                    <p class="text-label-sm uppercase text-slate-500">Total Purchases</p>
                    <h4 class="mt-1 text-h2 font-h2">{{ number_format($this->totalPurchases()) }}</h4>
                </div>
            </div>

            <!-- Filters -->
            <div
                class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">
                        search
                    </span>

                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search plans..."
                        class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10" />
                </div>

                <select wire:model.live="typeFilter"
                    class="rounded-lg border border-outline-variant bg-white px-3 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                    <option value="all">All Types</option>

                    @foreach ($planTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="statusFilter"
                    class="rounded-lg border border-outline-variant bg-white px-3 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                    <option value="all">All Status</option>

                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Plans Table Container -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-stack-lg py-stack-md">
                    <h3 class="font-h3 text-h3">Current Tiers</h3>

                    <select wire:model="perPage" class="rounded border border-slate-200 bg-white px-2 py-1 text-sm">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">PLAN NAME</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">TYPE</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">MONTHLY</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">YEARLY</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">PURCHASED</th>
                                <th class="px-6 py-4 text-center text-label-sm text-on-surface-variant">STATUS</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @php
                                $mostPopularPlanId = $this->mostPopularPlanId();
                            @endphp

                            @forelse ($this->plans() as $plan)
                                <tr wire:key="plan-{{ $plan->id }}"
                                    class="transition-colors hover:bg-slate-50/80">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <span class="material-symbols-outlined text-[22px]">
                                                    {{ $plan->icon ?: 'workspace_premium' }}
                                                </span>
                                            </div>

                                            <div class="flex min-w-0 flex-col">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-label-md font-semibold text-on-surface">
                                                        {{ $plan->title }}
                                                    </span>

                                                    @if ($plan->id === $mostPopularPlanId && $plan->purchase_count > 0)
                                                        <span
                                                            class="inline-flex items-center rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-700">
                                                            Most Popular
                                                        </span>
                                                    @endif
                                                </div>

                                                <span class="max-w-55 truncate text-body-sm text-on-surface-variant">
                                                    {{ $plan->description }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span
                                            class="{{ $this->planTypeBadgeClass($plan->plan_type) }} inline-flex rounded border px-2 py-1 text-[11px] font-bold uppercase tracking-wider">
                                            {{ $planTypes[$plan->plan_type] ?? ucfirst($plan->plan_type) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 font-mono text-body-md">
                                        @if ($plan->monthly_discount_price !== null)
                                            <div>
                                                <span class="text-primary font-semibold">
                                                    ${{ number_format((float) $plan->monthly_discount_price, 2) }}
                                                </span>

                                                <span class="block text-xs text-slate-400 line-through">
                                                    ${{ number_format((float) $plan->monthly_price, 2) }}
                                                </span>
                                            </div>
                                        @else
                                            {{ $plan->monthly_price !== null ? '$' . number_format((float) $plan->monthly_price, 2) : '—' }}
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 font-mono text-body-md">
                                        @if ($plan->yearly_discount_price !== null)
                                            <div>
                                                <span class="text-primary font-semibold">
                                                    ${{ number_format((float) $plan->yearly_discount_price, 2) }}
                                                </span>

                                                <span class="block text-xs text-slate-400 line-through">
                                                    ${{ number_format((float) $plan->yearly_price, 2) }}
                                                </span>
                                            </div>
                                        @else
                                            {{ $plan->yearly_price !== null ? '$' . number_format((float) $plan->yearly_price, 2) : '—' }}
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="font-mono text-body-md text-on-surface">
                                            {{ number_format($plan->purchase_count) }}
                                        </span>

                                        <span class="block text-[11px] uppercase tracking-wider text-slate-400">
                                            Purchases
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <button type="button" wire:click="toggleStatus({{ $plan->id }})"
                                            class="{{ $this->statusBadgeClass($plan->status) }} inline-flex rounded border px-2 py-1 text-[11px] font-bold uppercase tracking-wider">
                                            {{ $statuses[$plan->status] ?? ucfirst($plan->status) }}
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
                                                <button type="button" wire:click="edit({{ $plan->id }})"
                                                    @click="open = false"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                                    Edit
                                                </button>

                                                <button type="button" wire:click="delete({{ $plan->id }})"
                                                    wire:confirm="Are you sure you want to delete this pricing plan?"
                                                    @click="open = false"
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
                                    <td colspan="7" class="px-6 py-14 text-center">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <div
                                                class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                                <span class="material-symbols-outlined">payments</span>
                                            </div>

                                            <h3 class="text-base font-semibold text-on-surface">
                                                No pricing plans found
                                            </h3>

                                            <p class="mt-1 text-sm text-secondary">
                                                Create your first pricing plan from the form.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-4 bg-slate-50/50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-body-sm text-on-surface-variant">
                        Showing pricing plans
                    </span>

                    <div>
                        {{ $this->plans()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Usage Analytics -->
    {{-- <div class="grid grid-cols-1 gap-gutter lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-stack-lg shadow-sm lg:col-span-2">
            <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
                <div class="space-y-stack-sm">
                    <h4 class="font-h3 text-h3">Revenue Forecast</h4>

                    <p class="text-body-md text-on-surface-variant">
                        Based on active monthly pricing plan distribution.
                    </p>

                    <div class="flex items-baseline gap-2 pt-4">
                        <span class="font-h1 text-[32px] font-bold">
                            ${{ number_format((float) PricingPlan::query()->where('status', 'live')->sum('monthly_price'), 2) }}
                        </span>

                        <span class="flex items-center text-label-md text-green-600">
                            <span class="material-symbols-outlined text-[16px]">trending_up</span>
                            Monthly
                        </span>
                    </div>
                </div>

                <div class="relative flex h-24 w-full items-end gap-1 overflow-hidden rounded-lg bg-slate-50 p-2 sm:w-1/3">
                    <div class="h-[40%] flex-1 rounded-t bg-primary/20"></div>
                    <div class="h-[60%] flex-1 rounded-t bg-primary/30"></div>
                    <div class="h-[50%] flex-1 rounded-t bg-primary/40"></div>
                    <div class="h-[80%] flex-1 rounded-t bg-primary/60"></div>
                    <div class="h-[70%] flex-1 rounded-t bg-primary/80"></div>
                    <div class="h-[95%] flex-1 rounded-t bg-primary"></div>
                    <div class="absolute inset-0 bg-linear-to-t from-white/10 to-transparent"></div>
                </div>
            </div>
        </div>
    </div> --}}
</div>
