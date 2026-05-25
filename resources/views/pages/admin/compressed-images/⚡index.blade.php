<?php

use App\Models\User;
use App\Models\UserCompressedImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Compressed Images')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $expiredFilter = 'all';

    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedExpiredFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $image = UserCompressedImage::findOrFail($id);

        $image->deleteFile();
        $image->delete();

        $this->dispatch('toast', message: 'Compressed image deleted.', type: 'success');
    }

    public function download(int $id): mixed
    {
        $image = UserCompressedImage::findOrFail($id);

        if (!$image->fileExists()) {
            $this->dispatch('toast', message: 'File no longer exists on disk.', type: 'error');

            return null;
        }

        return Storage::disk('public')->download($image->compressed_path, $image->downloadName());
    }

    public function images()
    {
        return UserCompressedImage::query()
            ->with(['user', 'category'])
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                })->orWhere('original_name', 'like', '%' . $this->search . '%');
            })
            ->when($this->expiredFilter === 'active', function ($query) {
                $query->where('expires_at', '>', now());
            })
            ->when($this->expiredFilter === 'expired', function ($query) {
                $query->where('expires_at', '<=', now());
            })
            ->latest()
            ->paginate($this->perPage);
    }
};
?>

<div class="mx-auto space-y-stack-lg">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h2 class="font-h1 text-h1 text-on-surface">Compressed Images</h2>
            <p class="mt-1 text-body-md text-on-surface-variant">
                All backed-up compressed images from premium users.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="expiredFilter"
                class="input-select min-w-[140px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                <option value="all">All backups</option>
                <option value="active">Active only</option>
                <option value="expired">Expired only</option>
            </select>

            <select wire:model.live="perPage"
                class="input-select rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                <option value="15">15 per page</option>
                <option value="30">30 per page</option>
                <option value="50">50 per page</option>
            </select>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative max-w-md">
        <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by user name, email or file name..."
            class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3.5">Preview</th>
                        <th class="px-4 py-3.5">User</th>
                        <th class="px-4 py-3.5">File</th>
                        <th class="px-4 py-3.5">Category</th>
                        <th class="px-4 py-3.5">Size</th>
                        <th class="px-4 py-3.5">Expires</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->images() as $image)
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="px-4 py-3">
                                @if ($image->fileExists() && $image->previewUrl())
                                    <div x-data="{ preview: null }">
                                        <button type="button" @click="preview = '{{ $image->previewUrl() }}'"
                                            class="group relative block h-10 w-10 overflow-hidden rounded-lg bg-slate-100 transition hover:scale-110">
                                            <img src="{{ $image->previewUrl() }}"
                                                alt="{{ $image->original_name }}"
                                                class="h-full w-full object-cover"
                                                loading="lazy">
                                            <span class="absolute inset-0 flex items-center justify-center bg-black/30 text-white opacity-0 transition group-hover:opacity-100">
                                                <span class="material-symbols-outlined text-sm">zoom_in</span>
                                            </span>
                                        </button>

                                        {{-- Lightbox --}}
                                        <template x-teleport="body">
                                            <div x-show="preview" x-transition.opacity.duration.200ms
                                                class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-sm"
                                                @click="preview = null" @keydown.escape.window="preview = null">
                                                <button type="button" @click="preview = null"
                                                    class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                                                    <span class="material-symbols-outlined">close</span>
                                                </button>
                                                <img :src="preview" @click.stop
                                                    class="max-h-[90vh] max-w-[90vw] rounded-2xl object-contain shadow-2xl">
                                            </div>
                                        </template>
                                    </div>
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                        <span class="material-symbols-outlined text-lg">image</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                                        {{ strtoupper(substr($image->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800">{{ $image->user?->name ?? 'Deleted User' }}</p>
                                        <p class="text-xs text-slate-400">{{ $image->user?->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="max-w-[200px] truncate px-4 py-3">
                                <p class="truncate font-medium text-slate-700" title="{{ $image->original_name }}">
                                    {{ $image->original_name }}
                                </p>
                                <span class="mt-0.5 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium uppercase text-slate-500">
                                    {{ $image->compressed_ext }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $image->category?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-slate-700">{{ number_format($image->original_size / 1024, 1) }} KB</p>
                                <p class="text-xs text-slate-400">→ {{ number_format($image->compressed_size / 1024, 1) }} KB
                                    @if ($image->original_size > 0)
                                        <span class="text-emerald-600">({{ (int) round((1 - $image->compressed_size / $image->original_size) * 100) }}%)</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ $image->expires_at->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($image->isExpired())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                        Expired
                                    </span>
                                @elseif ($image->fileExists())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        Missing
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    @if (!$image->isExpired() && $image->fileExists())
                                        <button type="button" wire:click="download({{ $image->id }})"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-blue-600"
                                            title="Download">
                                            <span class="material-symbols-outlined text-lg">download</span>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="delete({{ $image->id }})"
                                        wire:confirm="Delete this backup permanently?"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                                        title="Delete">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                                <span class="material-symbols-outlined mb-2 block text-3xl">backup</span>
                                <p class="font-medium">No compressed images found</p>
                                <p class="mt-1 text-xs">Premium users' backed-up images will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $this->images()->links() }}
    </div>
</div>
