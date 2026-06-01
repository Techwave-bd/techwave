<?php

use App\Models\ToolCategory;
use App\Models\ToolUsage;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Background Remover')] class extends Component {
    use WithFileUploads;

    private const MAX_IMAGE_SIZE_KB = 10240;

    private ?ToolCategory $category = null;

    public $image = null;

    public int $dailyUsage = 0;

    public function boot(): void
    {
        $this->category = ToolCategory::query()->where('slug', 'image-tools')->first();

        if (auth()->check()) {
            $this->dailyUsage = ToolUsage::query()
                ->where('user_id', auth()->id())
                ->where('tool_type', 'image_bg_remover')
                ->where('period', now()->format('Y-m-d'))
                ->sum('usage_count');
        }
    }

    public function getIsPremiumUserProperty(): bool
    {
        return $this->category && auth()->check() && auth()->user()->hasActiveToolSubscription($this->category);
    }

    public function updatedImage(): void
    {
        try {
            $this->validate(
                [
                    'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_IMAGE_SIZE_KB],
                ],
                $this->messages(),
            );

            $this->resetErrorBag();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', message: $this->uploadErrorMessage($e), type: 'error');

            $this->image = null;

            $this->resetErrorBag();
        }
    }

    protected function messages(): array
    {
        return [
            'image.required' => 'Please upload one image.',
            'image.uploaded' => 'Image failed to upload. Please check file size or type.',
            'image.image' => 'Selected file is not a valid image.',
            'image.mimes' => 'Only JPG, JPEG, PNG, and WebP images are allowed.',
            'image.max' => 'Image must be 10MB or smaller.',
        ];
    }

    private function uploadErrorMessage(\Illuminate\Validation\ValidationException $e): string
    {
        $errors = $e->validator->errors()->messages();

        if (isset($errors['image'])) {
            return $errors['image'][0] ?? 'Image upload failed. Please try again.';
        }

        return 'Image upload failed. Please check file size or type.';
    }

    public function removeImage(): void
    {
        $this->image = null;

        $this->resetErrorBag();

        $this->dispatch('toast', message: 'Image removed.', type: 'success');
    }

    public function resetUpload(): void
    {
        $this->image = null;

        $this->resetErrorBag();
    }

    #[On('usage-track')]
    public function trackUsage(int $count = 1): void
    {
        if (!auth()->check() || $count <= 0) {
            return;
        }

        $usage = ToolUsage::query()->firstOrCreate(
            [
                'user_id' => auth()->id(),
                'tool_type' => 'image_bg_remover',
                'period' => now()->format('Y-m-d'),
            ],
            ['usage_count' => 0],
        );

        $usage->increment('usage_count', $count);

        $this->dailyUsage += $count;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
};
?>

@push('scripts')
    @vite('resources/js/bg-remover.js')
@endpush

<div class="min-h-screen text-white" x-data="bgRemover" data-premium="@json($this->is_premium_user)">
    <main class="mx-auto flex w-full max-w-7xl flex-col items-center px-4 pb-24 pt-10 sm:px-6 lg:px-8">

        {{-- Hero Header --}}
        <div class="mb-10 text-center">
            <h1 class="text-5xl font-extrabold tracking-tight sm:text-6xl md:text-7xl">
                Remove
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text italic text-transparent">
                    Background
                </span>
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-blue-100/60 sm:text-lg">
                Upload one image and remove its background directly in your browser.
            </p>
        </div>

        {{-- Progress Stepper --}}
        <div class="mb-12 lg:flex flex-wrap items-center justify-center gap-3 sm:gap-4 hidden">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-cyan-300/40 bg-cyan-400/15 text-sm font-bold text-cyan-200 shadow-lg shadow-cyan-500/20">
                    1
                </div>
                <span class="text-xs font-bold tracking-[0.22em] text-white">UPLOAD</span>
            </div>

            <div class="hidden h-px w-10 bg-white/15 sm:block"></div>

            <div class="flex items-center gap-3 opacity-70">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-white/15 bg-white/5 text-sm font-bold text-blue-100/60">
                    2
                </div>
                <span class="text-xs font-bold tracking-[0.22em] text-blue-100/50">PROCESS</span>
            </div>

            <div class="hidden h-px w-10 bg-white/15 sm:block"></div>

            <div class="flex items-center gap-3 opacity-70">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-white/15 bg-white/5 text-sm font-bold text-blue-100/60">
                    3
                </div>
                <span class="text-xs font-bold tracking-[0.22em] text-blue-100/50">DOWNLOAD</span>
            </div>
        </div>

        {{-- Processing Overlay --}}
        <div x-show="processing" x-cloak
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/75 px-4 backdrop-blur-md">
            <div
                class="w-full max-w-md rounded-3xl border border-cyan-400/20 bg-slate-900/90 p-6 text-center shadow-2xl shadow-cyan-500/20">
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-cyan-400/10 text-cyan-300">
                    <span
                        class="h-8 w-8 animate-spin rounded-full border-2 border-cyan-100/30 border-t-cyan-100"></span>
                </div>

                <h3 class="mt-5 text-xl font-bold text-white">Removing background...</h3>

                <p class="mt-2 text-sm text-blue-100/60" x-text="progressText">
                    Preparing image...
                </p>

                <div class="mt-6">
                    <div class="mb-2 flex items-center justify-between text-xs text-blue-100/60">
                        <span>Progress</span>
                        <span><span x-text="progress"></span>%</span>
                    </div>

                    <div class="h-3 overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-blue-500 transition-all duration-300"
                            :style="'width: ' + progress + '%'"></div>
                    </div>
                </div>

                <p class="mt-4 text-xs leading-5 text-blue-100/45">
                    First-time processing can be slower because the AI model needs to load.
                </p>
            </div>
        </div>

        <div class="w-full">
            {{-- Results View --}}
            <section x-show="hasResult" x-cloak
                class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.06] p-6 shadow-[0_30px_100px_rgba(0,0,0,0.35)] backdrop-blur-2xl sm:p-8">
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-br from-cyan-400/5 via-transparent to-blue-500/5">
                </div>

                <div class="relative mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-extrabold text-white">Background removed</h2>

                        <p class="mt-1 text-sm text-blue-100/55" x-show="result && result.status === 'success'">
                            <span x-text="formatBytes(result.originalSize)"></span>
                            <span class="mx-1 text-blue-100/30">→</span>
                            <span class="font-semibold text-cyan-300" x-text="formatBytes(result.resultSize)"></span>
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button type="button" x-on:click="resetResult(); $wire.resetUpload()"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/8 px-4 py-2 text-sm font-semibold text-white transition hover:border-cyan-400/30 hover:bg-white/12">
                            <span class="material-symbols-outlined text-base">refresh</span>
                            New image
                        </button>

                        <button type="button" x-show="result && result.status === 'success'" x-on:click="download"
                            class="group relative inline-flex items-center gap-1.5 overflow-hidden rounded-xl bg-gradient-to-r cursor-pointer from-cyan-500 to-blue-500 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 disabled:opacity-60">
                            <span
                                class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover:left-full"></span>
                            <span class="material-symbols-outlined relative text-base">download</span>
                            <span class="relative">Download</span>
                        </button>
                    </div>
                </div>

                {{-- Success Result --}}
                <div x-show="result && result.status === 'success'" x-cloak
                    class="relative overflow-hidden rounded-xl border border-white/10 bg-slate-950/25">

                    <div class="relative grid grid-cols-1 md:grid-cols-2">
                        <div class="relative">
                            <div
                                class="absolute left-3 top-3 z-10 rounded-full bg-black/55 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white/85 backdrop-blur-sm">
                                Before
                            </div>

                            <div class="flex h-[320px] items-center justify-center overflow-hidden bg-white/[0.03]">
                                <img :src="result.originalUrl" alt="Original" class="h-full w-full object-contain p-3">
                            </div>
                        </div>

                        <div class="relative">
                            <div
                                class="absolute left-3 top-3 z-10 rounded-full bg-cyan-500/70 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white backdrop-blur-sm">
                                After
                            </div>

                            <div class="flex h-[320px] items-center justify-center overflow-hidden"
                                style="background: repeating-conic-gradient(#e5e7eb 0% 25%, transparent 0% 50%) 50% / 10px 10px;">
                                <img :src="result.processedUrl" alt="Result" class="h-full w-full object-contain p-3">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-white/10 px-4 py-3">
                        <p class="min-w-0 flex-1 truncate text-sm font-semibold text-white"
                            x-text="result.originalName"></p>

                        <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs text-blue-100/50">
                            <span x-text="formatBytes(result.originalSize)"></span>
                            <span class="text-blue-100/30">→</span>
                            <span class="font-semibold text-cyan-300" x-text="formatBytes(result.resultSize)"></span>
                            <span
                                class="rounded-md border border-white/10 bg-white/8 px-1.5 py-0.5 uppercase">PNG</span>
                        </div>
                    </div>
                </div>

                {{-- Error Result --}}
                <div x-show="result && result.status === 'error'" x-cloak
                    class="flex items-center gap-3 rounded-xl border border-red-400/15 bg-red-500/10 px-5 py-4 text-sm">
                    <span class="material-symbols-outlined text-base text-red-300">error</span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-red-200" x-text="result.originalName"></p>
                        <p class="text-red-300/70" x-text="result.note || 'Processing failed'"></p>
                    </div>
                </div>
            </section>

            {{-- Upload View --}}
            <div x-show="!hasResult">
                <div class="grid w-full grid-cols-1 gap-8 lg:grid-cols-12">
                    {{-- Upload Section --}}
                    <section
                        class="relative flex min-h-[560px] flex-col overflow-hidden rounded-2xl border border-white/10 bg-white/[0.06] p-6 shadow-[0_24px_80px_rgba(0,0,0,0.25)] backdrop-blur-2xl sm:p-8 lg:col-span-8">
                        <div
                            class="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br from-cyan-400/5 via-transparent to-blue-500/5">
                        </div>

                        {{-- Upload loading overlay --}}
                        <div wire:loading.flex wire:target="image"
                            class="absolute inset-0 z-30 items-center justify-center rounded-2xl bg-slate-950/90 backdrop-blur-md">
                            <div class="flex flex-col items-center gap-4 text-center">
                                <span
                                    class="h-10 w-10 animate-spin rounded-full border-2 border-cyan-100/30 border-t-cyan-300"></span>

                                <div>
                                    <p class="font-semibold text-white">Uploading image...</p>
                                    <p class="mt-1 text-sm text-blue-100/50">Please wait a moment.</p>
                                </div>
                            </div>
                        </div>

                        <div class="relative mb-6 flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-extrabold text-white">Image to Process</h2>
                                <p class="mt-1 text-sm text-blue-100/45">
                                    {{ $image ? '1 image ready to process.' : 'No image selected yet.' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full border border-white/10 bg-slate-950/30 px-3 py-1 text-xs font-semibold text-blue-100/55">
                                    Single image only
                                </span>

                                @if ($image)
                                    <button type="button" wire:click="removeImage"
                                        class="hidden items-center gap-1.5 rounded-xl border border-red-400/15 bg-red-500/10 px-3 py-1.5 text-xs font-semibold text-red-200 transition hover:bg-red-500/15 sm:inline-flex">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                        Remove
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if (!$image)
                            <label for="bg-image-upload"
                                class="group relative flex flex-1 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-cyan-300/25 bg-slate-950/25 p-8 text-center transition hover:border-cyan-300/50 hover:bg-cyan-400/5 sm:p-12">
                                <div
                                    class="flex h-16 w-16 items-center justify-center rounded-full border border-cyan-300/15 bg-cyan-400/10 text-cyan-300 transition group-hover:scale-110">
                                    <span class="material-symbols-outlined text-4xl">cloud_upload</span>
                                </div>

                                <h3 class="mt-6 text-2xl font-extrabold text-white">Drop one image here</h3>

                                <p class="mt-2 text-sm text-blue-100/55">
                                    Supports JPG, PNG, and WebP up to 10MB.
                                </p>

                                <div
                                    class="mt-8 inline-flex items-center gap-2 rounded-xl border border-cyan-300/30 bg-cyan-400/10 px-8 py-3 font-bold text-cyan-200 transition hover:bg-cyan-400/15">
                                    <span class="material-symbols-outlined text-xl">add_photo_alternate</span>
                                    Browse Image
                                </div>

                                <div class="mt-6 flex flex-wrap justify-center gap-2">
                                    @foreach (['JPG', 'PNG', 'WebP'] as $fmt)
                                        <span
                                            class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] font-medium text-blue-100/60">
                                            {{ $fmt }}
                                        </span>
                                    @endforeach

                                    <span
                                        class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] text-blue-100/60">
                                        Max 10MB
                                    </span>

                                    <span
                                        class="rounded-full border border-cyan-300/15 bg-cyan-400/10 px-2.5 py-1 text-[11px] text-cyan-200">
                                        1 file only
                                    </span>
                                </div>
                            </label>
                        @else
                            @php
                                $imageInfo = [
                                    'url' => $image->temporaryUrl(),
                                    'name' => $image->getClientOriginalName(),
                                    'size' => $image->getSize(),
                                ];
                            @endphp

                            <div id="bg-image-card" data-image-info='@json($imageInfo)'
                                class="group relative flex flex-1 min-h-0 flex-col overflow-hidden rounded-xl border border-white/10 bg-slate-950/25 transition hover:border-cyan-400/30 hover:bg-white/[0.04]">

                                <button type="button" wire:click="removeImage"
                                    class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/60 text-white/70 backdrop-blur-sm transition hover:bg-red-500/70 hover:text-white"
                                    title="Remove">
                                    <span class="material-symbols-outlined text-base">close</span>
                                </button>

                                <div
                                    class="flex min-h-[390px] flex-1 items-center justify-center overflow-hidden bg-white/[0.03]">
                                    <img src="{{ $image->temporaryUrl() }}" alt="Preview"
                                        class="max-h-[440px] w-full object-contain p-4" />
                                </div>

                                <div class="shrink-0 border-t border-white/10 px-4 py-4">
                                    <p class="truncate text-sm font-semibold text-white"
                                        title="{{ $image->getClientOriginalName() }}">
                                        {{ $image->getClientOriginalName() }}
                                    </p>

                                    <div class="mt-2 flex items-center gap-2">
                                        <span
                                            class="rounded bg-white/8 px-1.5 py-0.5 text-[10px] font-medium uppercase text-blue-100/55">
                                            {{ $image->extension() }}
                                        </span>

                                        <span class="text-xs text-blue-100/45">
                                            {{ $this->formatBytes((int) $image->getSize()) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <input id="bg-image-upload" type="file" wire:model="image"
                            accept="image/png,image/jpeg,image/jpg,image/webp" class="hidden" />
                    </section>

                    {{-- Settings Sidebar --}}
                    <aside class="flex flex-col gap-6 lg:col-span-4">
                        <div
                            class="flex h-full flex-col rounded-2xl border border-white/10 bg-white/[0.06] p-6 shadow-[0_24px_80px_rgba(0,0,0,0.25)] backdrop-blur-2xl sm:p-8 lg:sticky lg:top-24">
                            <div class="mb-8 flex items-center gap-4">
                                <div class="rounded-xl border border-cyan-300/15 bg-cyan-400/10 p-3 text-cyan-300">
                                    <span class="material-symbols-outlined">magic_exchange</span>
                                </div>

                                <div>
                                    <h2 class="text-lg font-extrabold leading-none text-white">Background Remover</h2>
                                    <p class="mt-1 text-xs text-blue-100/45">
                                        Single-image AI background removal.
                                    </p>
                                </div>
                            </div>

                            <label for="bg-image-upload"
                                class="mb-6 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-cyan-300/25 bg-cyan-400/10 px-5 py-3 text-sm font-bold text-cyan-200 transition hover:border-cyan-300/40 hover:bg-cyan-400/15">
                                <span class="material-symbols-outlined text-lg">add_photo_alternate</span>
                                {{ $image ? 'Change Image' : 'Choose Image' }}
                            </label>

                            {{-- Stats --}}
                            <div class="mb-6 grid grid-cols-2 gap-2">
                                <div class="rounded-xl border border-white/10 bg-slate-950/30 px-4 py-3">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-100/45">
                                        Selected
                                    </p>
                                    <p class="mt-1 text-2xl font-extrabold text-white">
                                        {{ $image ? '1' : '0' }}
                                    </p>
                                </div>

                                <div class="rounded-xl border border-white/10 bg-slate-950/30 px-4 py-3">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-100/45">
                                        Limit
                                    </p>

                                    <p class="mt-2">
                                        <span
                                            class="inline-flex rounded-full border border-cyan-300/15 bg-cyan-400/10 px-2.5 py-1 text-xs font-semibold text-cyan-300">
                                            1 file only
                                        </span>
                                    </p>
                                </div>
                            </div>

                            {{-- Speed selector --}}
                            <div class="mb-6 rounded-xl border border-white/10 bg-slate-950/30 p-4">
                                <label class="flex items-center justify-between gap-4">
                                    <div
                                        class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-blue-100/45">
                                        <span class="material-symbols-outlined text-base text-cyan-300">speed</span>
                                        <span>Mode</span>
                                    </div>

                                    <select x-model="quality"
                                        class="rounded-lg border border-white/10 bg-slate-950/70 px-2.5 py-1.5 text-xs font-medium text-white outline-none transition focus:border-cyan-400/40">
                                        <option value="auto">Auto</option>
                                        <option value="fast">Fast</option>
                                        <option value="balanced">Balanced</option>
                                        <option value="best">Best</option>
                                    </select>
                                </label>

                                <p class="mt-3 text-[11px] leading-5 text-blue-100/45" x-show="quality === 'auto'">
                                    Recommended. Uses GPU when available, otherwise uses the faster CPU model.
                                </p>

                                <p class="mt-3 text-[11px] leading-5 text-blue-100/45" x-show="quality === 'fast'">
                                    Fastest mode. Better for large images and low-end devices.
                                </p>

                                <p class="mt-3 text-[11px] leading-5 text-blue-100/45"
                                    x-show="quality === 'balanced'">
                                    Better edge quality, but slower on CPU.
                                </p>

                                <p class="mt-3 text-[11px] leading-5 text-blue-100/45" x-show="quality === 'best'">
                                    Highest quality. Can be slow without GPU support.
                                </p>
                            </div>

                            <div class="flex-grow">
                                {{-- <div
                                    class="rounded-xl border border-cyan-300/15 bg-cyan-400/8 px-4 py-3 text-xs leading-5 text-blue-100/60">
                                    <div class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-base text-cyan-300">info</span>
                                        <span>
                                            This tool processes one image only. Multiple background removal is disabled
                                            for better stability.
                                        </span>
                                    </div>
                                </div> --}}

                                <div
                                    class="mt-3 rounded-xl border border-amber-300/15 bg-amber-400/8 px-4 py-3 text-xs leading-5 text-amber-100/70">
                                    <div class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-base text-amber-200">bolt</span>
                                        <span>
                                            First run can take longer because the browser loads the AI model.
                                        </span>
                                    </div>
                                </div>
                            </div>

                            @if ($image)
                                <button type="button" x-on:click="removeBg" x-bind:disabled="processing"
                                    class="group relative mt-8 flex w-full items-center justify-center gap-3 overflow-hidden rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 px-6 py-4 font-extrabold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 hover:shadow-[0_0_25px_rgba(34,211,238,0.35)] active:translate-y-0 disabled:opacity-60">
                                    <span
                                        class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover:left-full"></span>

                                    <span class="relative flex items-center gap-2">
                                        <span class="material-symbols-outlined">magic_exchange</span>
                                        Remove Background
                                    </span>
                                </button>
                            @else
                                <button type="button" disabled
                                    class="mt-8 flex w-full cursor-not-allowed items-center justify-center gap-3 rounded-xl bg-white/8 px-6 py-4 font-extrabold text-white/30">
                                    <span class="material-symbols-outlined">magic_exchange</span>
                                    Remove Background
                                </button>
                            @endif
                        </div>
                    </aside>
                </div>

                {{-- Mobile Fixed Bottom Bar --}}
                <div
                    class="fixed inset-x-0 bottom-0 z-50 border-t border-white/10 bg-slate-950/90 backdrop-blur-xl lg:hidden">
                    <div class="mx-auto max-w-3xl px-4 py-3">
                        @if ($image)
                            <div class="flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white">1 image selected</p>
                                    <p class="text-xs text-blue-100/45">Browser AI background removal</p>
                                </div>

                                <label for="bg-image-upload"
                                    class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-white/15 bg-white/8 text-white transition hover:bg-white/12">
                                    <span class="material-symbols-outlined text-lg">edit</span>
                                </label>

                                <button type="button" x-on:click="removeBg" x-bind:disabled="processing"
                                    class="group relative flex shrink-0 items-center gap-2 overflow-hidden rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/25 transition active:scale-95 disabled:opacity-60">
                                    <span
                                        class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover:left-full"></span>

                                    <span class="relative flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-base">magic_exchange</span>
                                        Remove BG
                                    </span>
                                </button>
                            </div>
                        @else
                            <label for="bg-image-upload"
                                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-500/25">
                                <span class="material-symbols-outlined text-base">add_photo_alternate</span>
                                Choose Image
                            </label>
                        @endif
                    </div>
                </div>

                <div class="h-20 lg:hidden"></div>
            </div>
        </div>

        {{-- How It Works --}}
        <div class="mt-12 grid w-full gap-6 sm:grid-cols-3">
            <div
                class="rounded-2xl border border-white/10 bg-white/[0.06] p-6 text-center shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cyan-500/15 text-cyan-300">
                    <span class="material-symbols-outlined">upload</span>
                </div>

                <h3 class="mt-4 font-semibold text-white">1. Upload</h3>

                <p class="mt-2 text-sm text-blue-100/62">
                    Select one JPG, PNG, or WebP image.
                </p>
            </div>

            <div
                class="rounded-2xl border border-white/10 bg-white/[0.06] p-6 text-center shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-500/15 text-blue-300">
                    <span class="material-symbols-outlined">magic_exchange</span>
                </div>

                <h3 class="mt-4 font-semibold text-white">2. Process</h3>

                <p class="mt-2 text-sm text-blue-100/62">
                    AI removes the background in your browser.
                </p>
            </div>

            <div
                class="rounded-2xl border border-white/10 bg-white/[0.06] p-6 text-center shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
                    <span class="material-symbols-outlined">download</span>
                </div>

                <h3 class="mt-4 font-semibold text-white">3. Download</h3>

                <p class="mt-2 text-sm text-blue-100/62">
                    Download the transparent PNG result.
                </p>
            </div>
        </div>
    </main>
</div>
