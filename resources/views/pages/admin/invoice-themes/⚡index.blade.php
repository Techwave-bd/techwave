<?php

use App\Models\InvoiceTheme;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Invoice Theme Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public string $access = 'all';
    public int $perPage = 12;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedAccess(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function themes()
    {
        return InvoiceTheme::query()
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            }))
            ->when($this->access === 'free', fn ($query) => $query->where('is_paid', false))
            ->when($this->access === 'pro', fn ($query) => $query->where('is_paid', true))
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function toggleStatus(int $themeId): void
    {
        $theme = InvoiceTheme::findOrFail($themeId);

        $theme->update(['is_active' => ! $theme->is_active]);

        $this->dispatch(
            'toast',
            message: 'Invoice theme status updated successfully.',
            type: 'success'
        );
    }

    public function delete(int $themeId): void
    {
        $theme = InvoiceTheme::findOrFail($themeId);

        if ($theme->preview_image) {
            Storage::disk('public')->delete($theme->preview_image);
        }

        $theme->delete();

        $this->dispatch(
            'toast',
            message: 'Invoice theme deleted successfully.',
            type: 'success'
        );
    }
};
?>

<div>
    <div class="mx-auto w-full space-y-stack-lg">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Invoice Theme Management
                </h2>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    Manage invoice themes users can choose for their invoices.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        search
                    </span>

                    <input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Search theme..."
                        class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-label-md font-label-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-64"
                    />
                </div>

                <div class="relative">
                    <select
                        wire:model.live="access"
                        class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface transition-colors hover:bg-surface-container-low focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-44"
                    >
                        <option value="all">All Access</option>
                        <option value="free">Free Themes</option>
                        <option value="pro">Pro Themes</option>
                    </select>

                    <span class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        expand_more
                    </span>
                </div>

                <div class="relative">
                    <select
                        wire:model.live="status"
                        class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface transition-colors hover:bg-surface-container-low focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-44"
                    >
                        <option value="all">All Status</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>

                    <span class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        expand_more
                    </span>
                </div>

                <a
                    href="{{ route('admin.invoice-themes.create') }}"
                    wire:navigate
                    class="flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-label-md font-label-md text-on-primary transition-all hover:shadow-lg hover:shadow-primary/20 active:scale-[0.98]"
                >
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
                                Theme
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Slug
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Access
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Status
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Sort Order
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Created At
                            </th>

                            <th class="px-6 py-4 text-right text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->themes() as $theme)
                            <tr
                                wire:key="theme-{{ $theme->id }}"
                                class="transition-colors hover:bg-slate-50/80"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                                            @if ($theme->preview_image)
                                                <img
                                                    src="{{ Storage::url($theme->preview_image) }}"
                                                    alt="{{ $theme->name }}"
                                                    class="h-full w-full object-cover"
                                                />
                                            @else
                                                <div class="flex h-full w-full items-center justify-center bg-primary/10 text-primary">
                                                    <span class="material-symbols-outlined text-[24px]">
                                                        photo
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <span class="block text-label-md font-label-md text-on-surface">
                                                {{ $theme->name }}
                                            </span>

                                            <span class="block max-w-md truncate text-body-sm font-body-sm text-secondary">
                                                {{ $theme->description ?: 'No description added.' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $theme->slug }}
                                </td>

                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $theme->is_paid ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $theme->is_paid ? 'Pro' : 'Free' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        wire:click="toggleStatus({{ $theme->id }})"
                                        class="flex items-center gap-2"
                                    >
                                        @if ($theme->is_active)
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-green-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Active</span>
                                        @else
                                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Inactive</span>
                                        @endif
                                    </button>
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $theme->sort_order }}
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $theme->created_at?->format('M d, Y') }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div
                                        x-data="{ open: false }"
                                        class="relative inline-block text-left"
                                    >
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="text-slate-400 transition-colors hover:text-primary"
                                        >
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>

                                        <div
                                            x-cloak
                                            x-show="open"
                                            @click.outside="open = false"
                                            x-transition
                                            class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                                        >
                                            <a
                                                href="{{ route('admin.invoice-themes.edit', $theme) }}"
                                                wire:navigate
                                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                Edit
                                            </a>

                                            <button
                                                type="button"
                                                wire:click="toggleStatus({{ $theme->id }})"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">
                                                    {{ $theme->is_active ? 'visibility_off' : 'visibility' }}
                                                </span>

                                                {{ $theme->is_active ? 'Make Inactive' : 'Make Active' }}
                                            </button>

                                            <button
                                                type="button"
                                                wire:click="delete({{ $theme->id }})"
                                                wire:confirm="Are you sure you want to delete this invoice theme?"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50"
                                            >
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
                                            <span class="material-symbols-outlined">photo</span>
                                        </div>

                                        <h3 class="text-base font-semibold text-on-surface">
                                            No invoice themes found
                                        </h3>

                                        <p class="mt-1 text-sm text-secondary">
                                            Create your first invoice theme to get started.
                                        </p>

                                        <a
                                            href="{{ route('admin.invoice-themes.create') }}"
                                            wire:navigate
                                            class="mt-5 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
                                        >
                                            Create Theme
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/30 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-body-sm font-body-sm text-secondary">
                        Per page
                    </span>

                    <select
                        wire:model.live="perPage"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 focus:border-primary focus:ring-primary/10"
                    >
                        <option value="12">12</option>
                        <option value="24">24</option>
                        <option value="36">36</option>
                        <option value="48">48</option>
                    </select>
                </div>

                <div>
                    {{ $this->themes()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
