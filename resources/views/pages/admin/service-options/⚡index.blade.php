<?php

use App\Models\Service;
use App\Models\ServiceOption;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Service Options')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public ?int $serviceFilter = null;
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
        return Service::query()
            ->where('is_active', true)
            ->orderBy('card_title')
            ->get();
    }

    public function serviceOptions()
    {
        return ServiceOption::query()
            ->with('service')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('card_title', 'like', '%' . $this->search . '%')
                        ->orWhere('detail_title', 'like', '%' . $this->search . '%')
                        ->orWhere('short_description', 'like', '%' . $this->search . '%')
                        ->orWhere('slug', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->status !== 'all', function ($query) {
                $query->where('is_active', $this->status === 'active');
            })
            ->when($this->serviceFilter, function ($query) {
                $query->where('service_id', $this->serviceFilter);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function toggleStatus(int $optionId): void
    {
        $option = ServiceOption::findOrFail($optionId);

        $option->update([
            'is_active' => !$option->is_active,
        ]);

        $this->dispatch('toast', message: 'Service option status updated successfully.', type: 'success');
    }

    public function delete(int $optionId): void
    {
        $option = ServiceOption::findOrFail($optionId);

        if ($option->image && Storage::disk('public')->exists($option->image)) {
            Storage::disk('public')->delete($option->image);
        }

        $option->delete();

        $this->dispatch('toast', message: 'Service option deleted successfully.', type: 'success');
    }
};
?>

<div>
    <div class="mx-auto w-full space-y-stack-lg">
        <!-- Header Section -->
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Service Options
                </h2>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    Manage options within services. Each option can have its own service plans.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        search
                    </span>

                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search options..."
                        class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-label-md font-label-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-64" />
                </div>

                <div class="relative">
                    <select wire:model.live="serviceFilter"
                        class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface transition-colors hover:bg-surface-container-low focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-45">
                        <option value="">All Services</option>

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
                        class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface transition-colors hover:bg-surface-container-low focus:border-primary focus:ring-2 focus:ring-primary/10 sm:w-35">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <span
                        class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                        expand_more
                    </span>
                </div>

                <a href="{{ route('admin.service-options.create') }}" wire:navigate
                    class="flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-label-md font-label-md text-on-primary transition-all hover:shadow-lg hover:shadow-primary/20 active:scale-[0.98]">
                    <span class="material-symbols-outlined text-lg">add</span>
                    Create
                </a>
            </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/50">
                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Option
                            </th>
                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Service
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
                        @forelse ($this->serviceOptions() as $option)
                            <tr wire:key="option-{{ $option->id }}" class="transition-colors hover:bg-slate-50/80">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 overflow-hidden rounded-xl bg-slate-100">
                                            @if ($option->image)
                                                <img src="{{ Storage::url($option->image) }}"
                                                    alt="{{ $option->card_title }}"
                                                    class="h-full w-full object-cover" />
                                            @else
                                                <div
                                                    class="flex h-full w-full items-center justify-center bg-primary/10 text-primary">
                                                    <span class="material-symbols-outlined text-[24px]">
                                                        {{ $option->icon ?: 'tune' }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-label-md font-label-md text-on-surface">
                                                    {{ $option->card_title }}
                                                </span>
                                            </div>

                                            <span
                                                class="block max-w-md truncate text-body-sm font-body-sm text-secondary">
                                                {{ $option->short_description }}
                                            </span>

                                            <span class="mt-1 block font-mono text-[11px] text-slate-400">
                                                {{ $option->slug }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="text-body-md font-body-md text-on-surface">{{ $option->service->card_title }}</span>
                                </td>

                                <td class="px-6 py-4">
                                    <button type="button" wire:click="toggleStatus({{ $option->id }})"
                                        class="flex items-center gap-2">
                                        @if ($option->is_active)
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-green-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Active</span>
                                        @else
                                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                            <span class="text-body-md font-body-md text-on-surface">Inactive</span>
                                        @endif
                                    </button>
                                </td>

                                <td class="px-6 py-4 font-mono text-body-sm text-secondary">
                                    {{ $option->created_at?->format('M d, Y') }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button type="button" @click="open = !open"
                                            class="text-slate-400 transition-colors hover:text-primary">
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>

                                        <div x-cloak x-show="open" @click.outside="open = false" x-transition
                                            class="absolute right-0 z-20 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                            <a href="{{ route('admin.service-options.edit', $option) }}" wire:navigate
                                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                Edit
                                            </a>

                                            <button type="button" wire:click="toggleStatus({{ $option->id }})"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">
                                                    {{ $option->is_active ? 'visibility_off' : 'visibility' }}
                                                </span>

                                                {{ $option->is_active ? 'Make Inactive' : 'Make Active' }}
                                            </button>

                                            <button type="button" wire:click="delete({{ $option->id }})"
                                                wire:confirm="Are you sure you want to delete this service option?"
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
                                <td colspan="5" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div
                                            class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <span class="material-symbols-outlined">tune</span>
                                        </div>

                                        <h3 class="text-base font-semibold text-on-surface">
                                            No service options found
                                        </h3>

                                        <p class="mt-1 text-sm text-secondary">
                                            Create your first service option to organize service plans.
                                        </p>

                                        <a href="{{ route('admin.service-options.create') }}" wire:navigate
                                            class="mt-5 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                                            Create Service Option
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
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
                    {{ $this->serviceOptions()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
