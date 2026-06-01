<?php

use App\Models\UserBgRemovedImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My BG Removed Images')] class extends Component {
    use WithPagination;

    public function delete(int $id): void
    {
        $image = UserBgRemovedImage::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $image->deleteFile();
        $image->delete();

        $this->dispatch('toast', message: 'Image deleted from backup.', type: 'success');
    }

    public function download(int $id): mixed
    {
        $image = UserBgRemovedImage::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if (!$image->fileExists()) {
            $this->dispatch('toast', message: 'File no longer available. It may have expired.', type: 'error');
            return null;
        }

        return Storage::disk('public')->download($image->result_path, $image->downloadName());
    }

    public function images()
    {
        return UserBgRemovedImage::query()
            ->with('toolCategory')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);
    }
};
?>

<div class="min-h-screen text-white">
    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                BG Removed
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">Images</span>
            </h1>
            <p class="mt-2 text-blue-100/60">Your backed-up background-removed images. Backups are kept for 30 days.</p>
        </div>

        <div x-data="{ preview: null }" class="space-y-4">
            @forelse ($this->images() as $image)
                <div class="rounded-2xl border border-white/15 bg-white/[0.07] p-5 shadow-[0_10px_30px_rgba(0,0,0,0.12)] backdrop-blur-2xl">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            @if ($image->fileExists() && $image->previewUrl())
                                <button type="button" @click="preview = '{{ $image->previewUrl() }}'"
                                    class="group relative h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-cyan-400/10 transition hover:scale-105">
                                    <img src="{{ $image->previewUrl() }}"
                                        alt="{{ $image->original_name }}"
                                        class="h-full w-full object-contain"
                                        loading="lazy">
                                    <span class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition group-hover:opacity-100">
                                        <span class="material-symbols-outlined text-lg text-white">zoom_in</span>
                                    </span>
                                </button>
                            @else
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-cyan-400/10 text-cyan-300">
                                    <span class="material-symbols-outlined">image</span>
                                </div>
                            @endif
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-bold text-white" title="{{ $image->original_name }}">
                                    {{ $image->original_name }}
                                </h3>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-blue-100/50">
                                    <span class="rounded bg-white/8 px-1.5 py-0.5 uppercase">{{ $image->result_ext }}</span>
                                    <span>{{ number_format($image->original_size / 1024, 1) }} KB → {{ number_format($image->result_size / 1024, 1) }} KB</span>
                                    @if ($image->toolCategory)
                                        <span>· {{ $image->toolCategory->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <div class="text-right">
                                @if ($image->isExpired())
                                    <p class="text-xs font-semibold text-red-300">Expired</p>
                                @else
                                    <p class="text-xs text-blue-100/45">
                                        Expires {{ $image->expires_at->format('M d, Y') }}
                                    </p>
                                @endif
                            </div>

                            @if (!$image->isExpired() && $image->fileExists())
                                <button type="button" wire:click="download({{ $image->id }})"
                                    class="flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/15">
                                    <span class="material-symbols-outlined text-sm leading-none">download</span>
                                    Download
                                </button>
                            @endif

                            <button type="button" wire:click="delete({{ $image->id }})"
                                wire:confirm="Delete this backup? This cannot be undone."
                                class="flex items-center gap-1.5 rounded-lg bg-red-500/10 px-3 py-1.5 text-xs font-semibold text-red-300 transition hover:bg-red-500/20">
                                <span class="material-symbols-outlined text-sm leading-none">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-white/15 bg-white/[0.07] p-12 text-center backdrop-blur-2xl">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/8">
                        <span class="material-symbols-outlined text-3xl text-blue-100/40">backup</span>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-white">No backed-up images yet</h3>
                    <p class="mt-2 text-sm text-blue-100/50">Remove backgrounds as a premium user to get 30-day backup.</p>
                    <a href="{{ route('client.tools.bg-remover') }}" wire:navigate
                        class="mt-6 inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 px-6 py-3 font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-base">magic_exchange</span>
                        Remove Background
                    </a>
                </div>
            @endforelse

            <div class="mt-4">
                {{ $this->images()->links() }}
            </div>

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
    </div>
</div>
