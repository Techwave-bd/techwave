<?php

use App\Models\ToolCategory;
use App\Models\ToolPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Tool Plans')] class extends Component {
    use WithPagination;

    public ?int $editingId = null;

    public string $tool_category_id = '';
    public string $name = '';
    public string $badge = '';
    public string $description = '';
    public ?float $monthly_price = null;
    public ?float $yearly_price = null;
    public int $max_file_upload = 100;
    public array $features = [];
    public string $feature = '';
    public int $sort_order = 0;
    public bool $is_active = true;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $categoryFilter = '';
    public int $perPage = 10;

    protected function rules(): array
    {
        return [
            'tool_category_id' => ['required', 'exists:tool_categories,id'],
            'name' => ['required', 'string', 'max:160', 'unique:tool_plans,name,' . $this->editingId],
            'badge' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'max_file_upload' => ['required', 'integer', 'min:1', 'max:100000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function categories()
    {
        return ToolCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function plans()
    {
        return ToolPlan::query()
            ->with('toolCategory')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->when($this->categoryFilter !== '', function ($query) {
                $query->where('tool_category_id', $this->categoryFilter);
            })
            ->latest()
            ->paginate($this->perPage);
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

        ToolPlan::create([
            'tool_category_id' => $validated['tool_category_id'],
            'name' => $validated['name'],
            'badge' => $validated['badge'] ?: null,
            'description' => $validated['description'] ?: null,
            'monthly_price' => $validated['monthly_price'] ?? null,
            'yearly_price' => $validated['yearly_price'] ?? null,
            'max_file_upload' => $validated['max_file_upload'],
            'features' => array_values(array_filter($validated['features'] ?? [])),
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        $this->resetForm();

        $this->dispatch('toast', message: 'Tool plan created successfully.', type: 'success');
    }

    public function edit(int $planId): void
    {
        $plan = ToolPlan::findOrFail($planId);

        $this->editingId = $plan->id;

        $this->tool_category_id = (string) $plan->tool_category_id;
        $this->name = $plan->name;
        $this->badge = $plan->badge ?? '';
        $this->description = $plan->description ?? '';
        $this->monthly_price = $plan->monthly_price !== null ? (float) $plan->monthly_price : null;
        $this->yearly_price = $plan->yearly_price !== null ? (float) $plan->yearly_price : null;
        $this->max_file_upload = $plan->max_file_upload;
        $this->features = $plan->features ?: [];
        $this->sort_order = $plan->sort_order;
        $this->is_active = (bool) $plan->is_active;

        $this->feature = '';

        $this->resetValidation();
    }

    public function update(): void
    {
        if (!$this->editingId) {
            return;
        }

        $validated = $this->validate();

        $plan = ToolPlan::findOrFail($this->editingId);

        $plan->update([
            'tool_category_id' => $validated['tool_category_id'],
            'name' => $validated['name'],
            'badge' => $validated['badge'] ?: null,
            'description' => $validated['description'] ?: null,
            'monthly_price' => $validated['monthly_price'] ?? null,
            'yearly_price' => $validated['yearly_price'] ?? null,
            'max_file_upload' => $validated['max_file_upload'],
            'features' => array_values(array_filter($validated['features'] ?? [])),
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        $this->resetForm();

        $this->dispatch('toast', message: 'Tool plan updated successfully.', type: 'success');
    }

    public function toggleStatus(int $planId): void
    {
        $plan = ToolPlan::findOrFail($planId);

        $plan->update([
            'is_active' => !$plan->is_active,
        ]);

        $this->dispatch('toast', message: 'Plan status updated successfully.', type: 'success');
    }

    public function delete(int $planId): void
    {
        ToolPlan::findOrFail($planId)->delete();

        if ($this->editingId === $planId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: 'Tool plan deleted successfully.', type: 'success');
    }

    public function discard(): void
    {
        $this->resetForm();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }

    private function resetForm(): void
    {
        $this->editingId = null;

        $this->tool_category_id = '';
        $this->name = '';
        $this->badge = '';
        $this->description = '';
        $this->monthly_price = null;
        $this->yearly_price = null;
        $this->max_file_upload = 100;
        $this->features = [];
        $this->feature = '';
        $this->sort_order = 0;
        $this->is_active = true;

        $this->resetValidation();
    }
};
?>

<div class="mx-auto space-y-stack-lg">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <h2 class="font-h1 text-h1 text-on-surface">Tool Plans</h2>
            <p class="mt-1 text-body-md text-on-surface-variant">
                Configure premium pricing and upload limits per tool category.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-gutter">
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
                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Tool Category</label>
                        <select wire:model="tool_category_id"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="">Select Category</option>
                            @foreach ($this->categories() as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('tool_category_id')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Plan Name</label>
                        <input wire:model="name"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="e.g. Premium" type="text" />
                        @error('name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Badge</label>
                        <input wire:model="badge"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="e.g. Most Popular, Best Value" type="text" />
                        @error('badge')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Description</label>
                        <textarea wire:model="description"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="Briefly describe this plan..." rows="2"></textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Monthly Price</label>
                        <input wire:model="monthly_price"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="5.00" type="number" step="0.01" min="0" />
                        @error('monthly_price')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Yearly Price</label>
                        <input wire:model="yearly_price"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="36.00" type="number" step="0.01" min="0" />
                        @error('yearly_price')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Max File Upload</label>
                        <input wire:model="max_file_upload"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="100" type="number" min="1" max="100000" />
                        @error('max_file_upload')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Sort Order</label>
                        <input wire:model="sort_order"
                            class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="0" type="number" min="0" />
                        @error('sort_order')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Status</label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <input type="checkbox" wire:model.live="is_active" class="h-4 w-4 accent-primary" />
                            <span class="text-label-md">{{ $is_active ? 'Active' : 'Inactive' }}</span>
                        </label>
                        @error('is_active')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="mb-stack-xs block text-label-md text-on-surface-variant">Features</label>
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
                                    <span class="text-sm text-slate-400">No features added yet.</span>
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

        <div class="col-span-12 space-y-gutter lg:col-span-7">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search plans..."
                            class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    </div>
                    <select wire:model.live="categoryFilter"
                        class="rounded-lg border border-outline-variant bg-white px-3 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">All Categories</option>
                        @foreach ($this->categories() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="statusFilter"
                        class="rounded-lg border border-outline-variant bg-white px-3 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">PLAN</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">CATEGORY</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">MONTHLY</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">YEARLY</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">MAX FILES</th>
                                <th class="px-6 py-4 text-center text-label-sm text-on-surface-variant">STATUS</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->plans() as $plan)
                                <tr wire:key="plan-{{ $plan->id }}" class="transition-colors hover:bg-slate-50/80">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-label-md font-semibold text-on-surface">{{ $plan->name }}</span>
                                                    @if ($plan->badge)
                                                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-700">{{ $plan->badge }}</span>
                                                    @endif
                                                </div>
                                                @if ($plan->description)
                                                    <span class="max-w-55 truncate text-body-sm text-on-surface-variant">{{ $plan->description }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="rounded bg-primary/5 px-2 py-1 text-xs font-medium text-primary">
                                            {{ $plan->toolCategory?->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-body-md">
                                        {{ $plan->monthly_price !== null ? '$' . number_format((float) $plan->monthly_price, 2) : '—' }}
                                    </td>
                                    <td class="px-6 py-4 font-mono text-body-md">
                                        {{ $plan->yearly_price !== null ? '$' . number_format((float) $plan->yearly_price, 2) : '—' }}
                                    </td>
                                    <td class="px-6 py-4 font-mono text-body-md">{{ number_format($plan->max_file_upload) }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <button type="button" wire:click="toggleStatus({{ $plan->id }})"
                                            class="{{ $plan->is_active ? 'bg-green-50 text-green-700 border-green-100' : 'bg-amber-50 text-amber-700 border-amber-100' }} inline-flex rounded border px-2 py-1 text-[11px] font-bold uppercase tracking-wider">
                                            {{ $plan->is_active ? 'Active' : 'Inactive' }}
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
                                                    wire:confirm="Are you sure you want to delete this tool plan?"
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
                                            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                                <span class="material-symbols-outlined">payments</span>
                                            </div>
                                            <h3 class="text-base font-semibold text-on-surface">No tool plans found</h3>
                                            <p class="mt-1 text-sm text-secondary">Create your first tool plan from the form.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-4 bg-slate-50/50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-body-sm text-on-surface-variant">Showing tool plans</span>
                    <div>
                        {{ $this->plans()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
