<?php

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Subcategory Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public ?int $categoryFilter = null;
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
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
        return Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function subcategories()
    {
        $search = trim($this->search);

        return Subcategory::query()
            ->with('category')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->when($this->status !== 'all', function ($query) {
                $query->where('is_active', $this->status === 'active');
            })
            ->when($this->categoryFilter, function ($query) {
                $query->where('category_id', $this->categoryFilter);
            })
            ->orderBy('sort_order')
            ->latest()
            ->paginate($this->perPage);
    }

    public function toggleStatus(int $subcategoryId): void
    {
        $subcategory = Subcategory::findOrFail($subcategoryId);

        $subcategory->update([
            'is_active' => !$subcategory->is_active,
        ]);

        $this->dispatch('toast', message: 'Subcategory status updated successfully.', type: 'success');
    }

    public function delete(int $subcategoryId): void
    {
        $subcategory = Subcategory::findOrFail($subcategoryId);

        if ($subcategory->image && Storage::disk('public')->exists($subcategory->image)) {
            Storage::disk('public')->delete($subcategory->image);
        }

        $subcategory->delete();

        $this->dispatch('toast', message: 'Subcategory deleted successfully.', type: 'success');
    }
};
?>

<div>
    <div class="mx-auto w-full space-y-stack-lg">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Subcategory Management
                </h2>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    Manage subcategories under each category for organizing services.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        search
                    </span>

                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search subcategories..."
                        class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-label-md font-label-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-58" />
                </div>

                <div class="relative">
                    <select wire:model.live="categoryFilter"
                        class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface transition-colors hover:bg-surface-container-low focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-44">
                        <option value="">All Categories</option>

                        @foreach ($this->categories() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <span
                        class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        expand_more
                    </span>
                </div>

                <div class="relative">
                    <select wire:model.live="status"
                        class="appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface transition-colors hover:bg-surface-container-low focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-37">
                        <option value="all">All Status</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>

                    <span
                        class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        expand_more
                    </span>
                </div>

                <a href="{{ route('admin.subcategories.create') }}" wire:navigate
                    class="flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-label-md font-label-md text-on-primary transition-all hover:shadow-lg hover:shadow-primary/20 active:scale-[0.98]">
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
                                Subcategory
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Parent Category
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Slug
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Sort
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Status
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Created At
                            </th>

                            <th
                                class="px-6 py-4 text-right text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->subcategories() as $subcategory)
                            <tr wire:key="subcategory-{{ $subcategory->id }}"
                                class="transition-colors hover:bg-slate-50/80">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                                            @if ($subcategory->image)
                                                <img src="{{ Storage::url($subcategory->image) }}"
                                                    alt="{{ $subcategory->name }}" class="h-full w-full object-cover" />
                                            @else
                                                <div
                                                    class="flex h-full w-full items-center justify-center bg-primary/10 text-primary">
                                                    <span class="material-symbols-outlined text-[24px]">
                                                        {{ $subcategory->icon ?: 'account_tree' }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <span class="block text-label-md font-label-md text-on-surface">
                                                {{ $subcategory->name }}
                                            </span>

                                            <span
                                                class="block max-w-md truncate text-body-sm font-body-sm text-secondary">
                                                {{ $subcategory->description ?: 'No description added.' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                        {{ $subcategory->category->name ?? 'N/A' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $subcategory->slug }}
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $subcategory->sort_order }}
                                </td>

                                <td class="px-6 py-4">
                                    <button type="button" wire:click="toggleStatus({{ $subcategory->id }})"
                                        class="flex items-center gap-2">
                                        @if ($subcategory->is_active)
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-green-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Active</span>
                                        @else
                                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Inactive</span>
                                        @endif
                                    </button>
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $subcategory->created_at?->format('M d, Y') }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button type="button" @click="open = !open"
                                            class="text-slate-400 transition-colors hover:text-primary">
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>

                                        <div x-cloak x-show="open" @click.outside="open = false" x-transition
                                            class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                            <a href="{{ route('admin.subcategories.edit', $subcategory) }}"
                                                wire:navigate
                                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                Edit
                                            </a>

                                            <button type="button" wire:click="toggleStatus({{ $subcategory->id }})"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">
                                                    {{ $subcategory->is_active ? 'visibility_off' : 'visibility' }}
                                                </span>

                                                {{ $subcategory->is_active ? 'Make Inactive' : 'Make Active' }}
                                            </button>

                                            <button type="button" wire:click="delete({{ $subcategory->id }})"
                                                wire:confirm="Are you sure you want to delete this subcategory?"
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
                                            <span class="material-symbols-outlined">account_tree</span>
                                        </div>

                                        <h3 class="text-base font-semibold text-on-surface">
                                            No subcategories found
                                        </h3>

                                        <p class="mt-1 text-sm text-secondary">
                                            Create your first subcategory to organize plans under a category.
                                        </p>

                                        <a href="{{ route('admin.subcategories.create') }}" wire:navigate
                                            class="mt-5 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                                            Create Subcategory
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
                    <span class="text-body-sm font-body-sm text-secondary">
                        Per page
                    </span>

                    <select wire:model.live="perPage"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 focus:border-primary focus:ring-primary/10">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div>
                    {{ $this->subcategories()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
