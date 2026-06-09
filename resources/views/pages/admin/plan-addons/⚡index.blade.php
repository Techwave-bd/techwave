<?php

use App\Models\PlanAddon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Plan Addons')] class extends Component {
    use WithPagination;

    public ?int $editingId = null;

    public string $name = '';
    public string $price = '';
    public string $monthly_price = '';
    public string $yearly_price = '';
    public bool $is_active = true;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 10;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
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

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function addons()
    {
        return PlanAddon::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function save(): void
    {
        $validated = $this->validate();

        PlanAddon::create([
            'name' => $validated['name'],
            'price' => filled($validated['price'] ?? null) ? $validated['price'] : null,
            'monthly_price' => filled($validated['monthly_price'] ?? null) ? $validated['monthly_price'] : null,
            'yearly_price' => filled($validated['yearly_price'] ?? null) ? $validated['yearly_price'] : null,
            'is_active' => $validated['is_active'],
        ]);

        $this->resetForm();

        $this->dispatch('toast', message: 'Addon created successfully.', type: 'success');
    }

    public function edit(int $addonId): void
    {
        $addon = PlanAddon::findOrFail($addonId);

        $this->editingId = $addon->id;

        $this->name = $addon->name;
        $this->price = $addon->price !== null ? (string) $addon->price : '';
        $this->monthly_price = $addon->monthly_price !== null ? (string) $addon->monthly_price : '';
        $this->yearly_price = $addon->yearly_price !== null ? (string) $addon->yearly_price : '';
        $this->is_active = (bool) $addon->is_active;

        $this->resetValidation();
    }

    public function update(): void
    {
        if (!$this->editingId) {
            return;
        }

        $validated = $this->validate();

        $addon = PlanAddon::findOrFail($this->editingId);

        $addon->update([
            'name' => $validated['name'],
            'price' => filled($validated['price'] ?? null) ? $validated['price'] : null,
            'monthly_price' => filled($validated['monthly_price'] ?? null) ? $validated['monthly_price'] : null,
            'yearly_price' => filled($validated['yearly_price'] ?? null) ? $validated['yearly_price'] : null,
            'is_active' => $validated['is_active'],
        ]);

        $this->resetForm();

        $this->dispatch('toast', message: 'Addon updated successfully.', type: 'success');
    }

    public function toggleStatus(int $addonId): void
    {
        $addon = PlanAddon::findOrFail($addonId);

        $addon->update([
            'is_active' => !$addon->is_active,
        ]);

        $this->dispatch('toast', message: 'Addon status updated successfully.', type: 'success');
    }

    public function delete(int $addonId): void
    {
        PlanAddon::findOrFail($addonId)->delete();

        if ($this->editingId === $addonId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: 'Addon deleted successfully.', type: 'success');
    }

    public function discard(): void
    {
        $this->resetForm();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }

    private function resetForm(): void
    {
        $this->editingId = null;

        $this->name = '';
        $this->price = '';
        $this->monthly_price = '';
        $this->yearly_price = '';
        $this->is_active = true;

        $this->resetValidation();
    }
};
?>

<div class="mx-auto space-y-stack-lg">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <h2 class="font-h1 text-h1 text-on-surface">Plan Addons</h2>
            <p class="mt-1 text-body-md text-on-surface-variant">
                Create optional addons that can be attached to service plans.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-gutter">
        <section class="col-span-12 rounded-xl border border-slate-200 bg-white p-stack-lg shadow-sm lg:col-span-5">
            <div class="mb-stack-lg flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">
                    {{ $editingId ? 'edit' : 'extension' }}
                </span>
                <h3 class="font-h3 text-h3">
                    {{ $editingId ? 'Edit Addon' : 'Create New Addon' }}
                </h3>
            </div>

            <form wire:submit.prevent="{{ $editingId ? 'update' : 'save' }}" class="space-y-stack-md">
                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Addon Name</label>
                    <input wire:model="name"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                        placeholder="e.g. Extra SSD Storage" type="text" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">One-time Price</label>
                    <input wire:model="price"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                        placeholder="e.g. 500" type="number" step="0.01" min="0" />
                    @error('price')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Monthly Price</label>
                    <input wire:model="monthly_price"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                        placeholder="e.g. 50" type="number" step="0.01" min="0" />
                    @error('monthly_price')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Yearly Price</label>
                    <input wire:model="yearly_price"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                        placeholder="e.g. 500" type="number" step="0.01" min="0" />
                    @error('yearly_price')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <input type="checkbox" wire:model.live="is_active" class="h-4 w-4 accent-primary" />
                        <span class="text-label-md">{{ $is_active ? 'Active' : 'Inactive' }}</span>
                    </label>
                    @error('is_active')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3 pt-stack-md">
                    <button type="button" wire:click="discard"
                        class="w-1/3 rounded-lg border border-outline-variant bg-white py-3 font-label-md text-on-surface transition-all hover:bg-slate-50 cursor-pointer">
                        Discard
                    </button>
                    <button
                        class="w-2/3 rounded-lg bg-primary py-3 font-label-md text-white shadow-sm transition-all hover:opacity-90 active:scale-[0.98] cursor-pointer"
                        type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="{{ $editingId ? 'update' : 'save' }}">
                            {{ $editingId ? 'Update Addon' : 'Save Addon' }}
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
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search addons..."
                            class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    </div>
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
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">ADDON</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">ONE-TIME</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">MONTHLY</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant">YEARLY</th>
                                <th class="px-6 py-4 text-center text-label-sm text-on-surface-variant">STATUS</th>
                                <th class="px-6 py-4 text-label-sm text-on-surface-variant"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->addons() as $addon)
                                <tr wire:key="addon-{{ $addon->id }}"
                                    class="transition-colors hover:bg-slate-50/80">
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-label-md font-semibold text-on-surface">{{ $addon->name }}</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-body-md">
                                        {{ $addon->price !== null ? '৳' . number_format((float) $addon->price, 0) : '—' }}
                                    </td>
                                    <td class="px-6 py-4 font-mono text-body-md">
                                        {{ $addon->monthly_price !== null ? '৳' . number_format((float) $addon->monthly_price, 0) : '—' }}
                                    </td>
                                    <td class="px-6 py-4 font-mono text-body-md">
                                        {{ $addon->yearly_price !== null ? '৳' . number_format((float) $addon->yearly_price, 0) : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button type="button" wire:click="toggleStatus({{ $addon->id }})"
                                            class="{{ $addon->is_active ? 'bg-green-50 text-green-700 border-green-100' : 'bg-amber-50 text-amber-700 border-amber-100' }} inline-flex rounded border px-2 py-1 text-[11px] font-bold uppercase tracking-wider">
                                            {{ $addon->is_active ? 'Active' : 'Inactive' }}
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
                                                <button type="button" wire:click="edit({{ $addon->id }})"
                                                    @click="open = false"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                                    Edit
                                                </button>
                                                <button type="button" wire:click="delete({{ $addon->id }})"
                                                    wire:confirm="Are you sure you want to delete this addon?"
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
                                    <td colspan="6" class="px-6 py-14 text-center">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <div
                                                class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                                <span class="material-symbols-outlined">extension</span>
                                            </div>
                                            <h3 class="text-base font-semibold text-on-surface">No addons found</h3>
                                            <p class="mt-1 text-sm text-secondary">Create your first addon from the
                                                form.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-4 bg-slate-50/50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-body-sm text-on-surface-variant">Showing plan addons</span>
                    <div>
                        {{ $this->addons()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
