<?php

use App\Models\Tool;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Tools')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function tools()
    {
        $search = trim($this->search);

        return Tool::query()
            ->with('toolCategory')
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
            ->orderBy('sort_order')
            ->latest()
            ->paginate($this->perPage);
    }

    public function toggleStatus(int $id): void
    {
        $tool = Tool::findOrFail($id);

        $tool->update([
            'is_active' => ! $tool->is_active,
        ]);

        $this->dispatch('toast', message: 'Tool status updated successfully.', type: 'success');
    }

    public function delete(int $id): void
    {
        $tool = Tool::findOrFail($id);

        $tool->delete();

        $this->dispatch('toast', message: 'Tool deleted successfully.', type: 'success');
    }
};
?>

<div>
    <div class="mx-auto w-full space-y-stack-lg">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Tools
                </h2>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    Manage your tools section.
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
                        placeholder="Search tools..."
                        class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-label-md font-label-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-64"
                    />
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
                    href="{{ route('admin.tools.create') }}"
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
                                Tool
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Category
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Route
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Sort
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Status
                            </th>

                            <th class="px-6 py-4 text-right text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->tools() as $tool)
                            <tr
                                wire:key="tool-{{ $tool->id }}"
                                class="transition-colors hover:bg-slate-50/80"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined text-[22px]">
                                                {{ $tool->icon ?: 'build' }}
                                            </span>
                                        </div>

                                        <div class="min-w-0">
                                            <span class="block text-label-md font-label-md text-on-surface">
                                                {{ $tool->name }}
                                            </span>

                                            <span class="block max-w-md truncate text-body-sm font-body-sm text-secondary">
                                                {{ $tool->description ?: 'No description added.' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                        {{ $tool->toolCategory?->name ?? 'Uncategorized' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $tool->route ?: '—' }}
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $tool->sort_order }}
                                </td>

                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        wire:click="toggleStatus({{ $tool->id }})"
                                        class="flex items-center gap-2"
                                    >
                                        @if ($tool->is_active)
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-green-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Active</span>
                                        @else
                                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Inactive</span>
                                        @endif
                                    </button>
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
                                                href="{{ route('admin.tools.edit', $tool) }}"
                                                wire:navigate
                                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                Edit
                                            </a>

                                            <button
                                                type="button"
                                                wire:click="toggleStatus({{ $tool->id }})"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">
                                                    {{ $tool->is_active ? 'visibility_off' : 'visibility' }}
                                                </span>

                                                {{ $tool->is_active ? 'Make Inactive' : 'Make Active' }}
                                            </button>

                                            <button
                                                type="button"
                                                wire:click="delete({{ $tool->id }})"
                                                wire:confirm="Are you sure you want to delete this tool?"
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
                                <td colspan="6" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <span class="material-symbols-outlined">build</span>
                                        </div>

                                        <h3 class="text-base font-semibold text-on-surface">
                                            No tools found
                                        </h3>

                                        <p class="mt-1 text-sm text-secondary">
                                            Create your first tool to get started.
                                        </p>

                                        <a
                                            href="{{ route('admin.tools.create') }}"
                                            wire:navigate
                                            class="mt-5 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
                                        >
                                            Create Tool
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
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div>
                    {{ $this->tools()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
