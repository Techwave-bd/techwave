<?php

use App\Jobs\CompressPdfJob;
use App\Models\CompressedPdf;
use App\Models\ToolCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('PDF Compressor')] class extends Component {
    use WithFileUploads;

    public array $files = [];

    public array $fileSizes = [];

    public string $compressionLevel = 'recommended';

    public bool $processing = false;

    public ?string $startedAt = null;

    public int $progressPercent = 0;

    /** @var array<int, CompressedPdf> */
    public array $records = [];

    private ?ToolCategory $category = null;

    public function boot(): void
    {
        $this->category = ToolCategory::query()->where('slug', 'pdf-tools')->first();
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:' . $this->maxFiles],

            'files.*' => ['required', 'file', 'mimes:pdf', 'max:' . (int) (config('pdf-compressor.max_upload_size') / 1024)],

            'compressionLevel' => ['required', 'in:low,recommended,extreme'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'files' => 'PDF files',
            'files.*' => 'PDF file',
            'compressionLevel' => 'compression level',
        ];
    }

    public function getMaxFilesProperty(): int
    {
        if (!$this->category) {
            return 1;
        }

        return auth()->user()?->maxFileUploadFor($this->category) ?? ($this->category->free_max_file_upload ?? 1);
    }

    public function getIsPremiumUserProperty(): bool
    {
        return $this->category !== null && auth()->check() && auth()->user()->hasActiveToolSubscription($this->category);
    }

    public function getLevelsProperty(): array
    {
        return config('pdf-compressor.levels', []);
    }

    public function getMaxUploadSizeMbProperty(): int
    {
        return (int) (config('pdf-compressor.max_upload_size') / 1024 / 1024);
    }

    private function retentionSettings(): array
    {
        if ($this->isPremiumUser) {
            $expiresAt = now()->addDays(30);

            return [
                'is_backup_enabled' => true,
                'expires_at' => $expiresAt,
                'backup_expires_at' => $expiresAt,
            ];
        }

        return [
            'is_backup_enabled' => false,
            'expires_at' => now()->addHour(),
            'backup_expires_at' => null,
        ];
    }

    public function updatedFiles(): void
    {
        if (empty($this->files)) {
            return;
        }

        if (count($this->files) > $this->maxFiles) {
            $this->files = array_slice($this->files, 0, $this->maxFiles);

            $this->dispatch('toast', message: 'You can upload a maximum of ' . $this->maxFiles . ' PDF(s) at a time. Extra files were removed.', type: 'warning');
        }

        $this->fileSizes = [];

        foreach ($this->files as $index => $file) {
            if (is_object($file) && method_exists($file, 'getSize')) {
                try {
                    $this->fileSizes[$index] = (int) $file->getSize();
                } catch (\Throwable) {
                    $this->fileSizes[$index] = 0;
                }
            }
        }

        $this->resetValidation('files');
        $this->records = [];
        $this->processing = false;
        $this->progressPercent = 0;
    }

    public function removeFile(int $index): void
    {
        if (!isset($this->files[$index])) {
            return;
        }

        unset($this->files[$index], $this->fileSizes[$index]);

        $this->files = array_values($this->files);
        $this->fileSizes = array_values($this->fileSizes);

        $this->records = [];
        $this->processing = false;
        $this->progressPercent = 0;

        $this->resetErrorBag();
    }

    public function compress(): void
    {
        if (empty($this->files)) {
            $this->dispatch('toast', message: 'Please select at least one PDF file.', type: 'error');

            return;
        }

        $this->validate();

        $validFiles = collect($this->files)
            ->filter(function ($file) {
                if (!is_object($file) || !method_exists($file, 'getSize')) {
                    return false;
                }

                $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : null;

                return $path && is_file($path);
            })
            ->values()
            ->all();

        if (empty($validFiles)) {
            $this->dispatch('toast', message: 'No valid PDF files found. Please upload again.', type: 'error');

            return;
        }

        $this->processing = true;
        $this->startedAt = now()->toIso8601String();
        $this->progressPercent = 5;
        $this->records = [];

        $disk = config('pdf-compressor.storage_disk');

        $userId = Auth::id();

        $sessionId = $userId === null ? session()->getId() : null;

        $ownerFolder = $userId !== null ? 'users/' . $userId : 'guests/' . $sessionId;

        $directory = 'uploaded-pdfs/' . $ownerFolder;

        $retention = $this->retentionSettings();

        try {
            Storage::disk($disk)->makeDirectory($directory);

            foreach ($validFiles as $file) {
                $originalName = $file->getClientOriginalName();

                $originalSize = (int) $file->getSize();

                $uniqueId = Str::random(30);

                $originalPath = $directory . '/' . $uniqueId . '.pdf';

                $storedPath = $file->storeAs($directory, $uniqueId . '.pdf', $disk);

                if (!$storedPath) {
                    throw new RuntimeException('Unable to store uploaded PDF.');
                }

                $record = CompressedPdf::create([
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'original_name' => $originalName,
                    'original_path' => $originalPath,
                    'original_size' => $originalSize,
                    'compression_level' => $this->compressionLevel,
                    'status' => 'pending',

                    'is_backup_enabled' => $retention['is_backup_enabled'],

                    'expires_at' => $retention['expires_at'],

                    'backup_expires_at' => $retention['backup_expires_at'],
                ]);

                CompressPdfJob::dispatch($record->id)->onQueue('pdf-compression');

                $this->records[] = $record;
            }

            $this->files = [];
            $this->fileSizes = [];

            $count = count($this->records);

            $this->dispatch('toast', message: $count . ' PDF(s) uploaded. Compression started.', type: 'success');
        } catch (\Throwable $e) {
            report($e);

            $this->processing = false;

            $this->dispatch('toast', message: 'Upload failed: ' . $e->getMessage(), type: 'error');
        }
    }

    public function pollStatus(): void
    {
        if (empty($this->records)) {
            return;
        }

        $allDone = true;
        $completedCount = 0;
        $totalCount = count($this->records);

        foreach ($this->records as $index => $record) {
            $refreshed = CompressedPdf::find($record->id);

            if (!$refreshed) {
                continue;
            }

            $this->records[$index] = $refreshed;

            if ($refreshed->isCompleted() || $refreshed->isFailed()) {
                $completedCount++;
            } else {
                $allDone = false;
            }
        }

        /*
         * This is reliable batch progress. Ghostscript itself does not
         * provide a simple percentage for one PDF through this job.
         *
         * While a single file is processing, move smoothly up to 90%.
         * When files complete, calculate progress from completed files.
         */
        $batchProgress = $totalCount > 0 ? (int) floor(($completedCount / $totalCount) * 100) : 0;

        if ($allDone) {
            $this->progressPercent = 100;
            $this->processing = false;

            return;
        }

        $elapsedSeconds = $this->startedAt ? (int) \Carbon\Carbon::parse($this->startedAt)->diffInSeconds(now()) : 0;

        $estimatedProgress = min(90, 8 + (int) floor($elapsedSeconds / 2));

        $this->progressPercent = max($this->progressPercent, $batchProgress, $estimatedProgress);
    }

    public function downloadResult(int $index): mixed
    {
        if (!isset($this->records[$index])) {
            return null;
        }

        $record = CompressedPdf::find($this->records[$index]->id);

        if (!$record || !$record->isCompleted()) {
            return null;
        }

        if ($record->isExpired()) {
            $this->dispatch('toast', message: 'This PDF has expired and was removed.', type: 'error');

            return null;
        }

        if (!$record->belongsToCurrentVisitor()) {
            abort(403);
        }

        if (!$record->downloadableFileExists()) {
            $this->dispatch('toast', message: 'The downloadable PDF file was not found.', type: 'error');

            return null;
        }

        $path = $record->downloadablePath();

        if (!$path) {
            return null;
        }

        return Storage::disk(config('pdf-compressor.storage_disk'))->download($path, $record->downloadName());
    }

    public function getElapsedTimeProperty(): string
    {
        if (!$this->startedAt) {
            return '0s';
        }

        $seconds = (int) now()->diffInSeconds($this->startedAt);

        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return $minutes . 'm ' . $remaining . 's';
    }

    public function startOver(): void
    {
        foreach ($this->records as $record) {
            /*
             * Do not delete premium backups when the user
             * presses Start over.
             */
            if ($record->is_backup_enabled) {
                continue;
            }

            $record->deleteAllFiles();
            $record->delete();
        }

        $this->reset(['files', 'fileSizes', 'records', 'processing', 'compressionLevel', 'startedAt', 'progressPercent']);

        $this->compressionLevel = 'recommended';
        $this->startedAt = null;
    }

    public function formatBytes(?int $bytes): string
    {
        $bytes = $bytes ?? 0;

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

<div class="min-h-screen text-white">
    <main class="mx-auto flex w-full max-w-7xl flex-col items-center px-4 pb-24 pt-8 md:pt-10 sm:px-6 lg:px-8">

        <div class="mb-10 text-center">
            <h1 class="text-5xl font-extrabold tracking-tight sm:text-6xl md:text-7xl">
                Compress your
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text italic text-transparent">PDFs</span>
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-blue-100/60 md:text-lg">
                Reduce PDF file size with Ghostscript-powered compression. Choose a compression level and download your
                optimized PDF.
            </p>
            {{-- @if (!$this->isPremiumUser)
                <p class="mt-2 text-xs text-blue-100/40">
                    Free plan: {{ $this->maxFiles }} PDF at a time.
                    <a href="{{ route('client.tools.index') }}" wire:navigate
                        class="underline text-cyan-300/70 hover:text-cyan-300">Upgrade to Premium</a> for batch
                    processing.
                </p>
            @else
                <p class="mt-2 text-xs text-emerald-300/60">
                    <span class="material-symbols-outlined align-middle text-sm">verified</span>
                    Premium: up to {{ $this->maxFiles }} PDFs at a time.
                </p>
            @endif --}}
        </div>

        <div class="mb-12 hidden lg:flex flex-wrap items-center justify-center gap-3 sm:gap-4">
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
                <span class="text-xs font-bold tracking-[0.22em] text-blue-100/50">COMPRESS</span>
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

        @php
            $hasRecords = !empty($this->records);
            $allCompleted =
                $hasRecords && collect($this->records)->every(fn($r) => $r->isCompleted() || $r->isFailed());
            $anyProcessing =
                $hasRecords &&
                collect($this->records)->contains(fn($r) => $r->isProcessing() || $r->status === 'pending');
            $anyFailed = $hasRecords && collect($this->records)->contains(fn($r) => $r->isFailed());
        @endphp

        @if ($hasRecords && $anyProcessing)
            <div wire:poll.2s.keep-alive="pollStatus" class="w-full">
            @elseif ($hasRecords && $allCompleted)
                <div class="w-full">
                @else
                    <div class="w-full">
        @endif

        {{-- Results View --}}
        @if ($hasRecords && $allCompleted)
            <section
                class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/6 p-6 shadow-[0_30px_100px_rgba(0,0,0,0.35)] backdrop-blur-2xl sm:p-8">
                <div
                    class="pointer-events-none absolute inset-0 bg-linear-to-br from-cyan-400/5 via-transparent to-blue-500/5">
                </div>

                <div class="relative mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-extrabold text-white">Compression complete</h2>
                        <p class="mt-1 text-sm text-blue-100/55">
                            {{ count($this->records) }} PDF(s) processed.
                        </p>
                    </div>
                    <button type="button" wire:click="startOver" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/8 px-4 py-2 text-sm font-semibold text-white transition hover:border-cyan-400/30 hover:bg-white/12 cursor-pointer">
                        <span class="material-symbols-outlined text-base">refresh</span>
                        Start over
                    </button>
                </div>

                <div class="space-y-4">
                    @foreach ($this->records as $index => $record)
                        @php
                            $hasReduction = !$record->no_reduction && $record->compressed_size;
                        @endphp
                        <div
                            class="relative flex items-center gap-4 rounded-xl border border-white/10 bg-slate-950/25 p-4">
                            <div
                                class="h-2.5 w-2.5 shrink-0 rounded-full {{ $record->isFailed() ? 'bg-red-400 shadow-lg shadow-red-400/25' : ($hasReduction ? 'bg-emerald-400 shadow-lg shadow-emerald-400/25' : 'bg-amber-400 shadow-lg shadow-amber-400/25') }}">
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-white">{{ $record->original_name }}
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-blue-100/50">
                                    <span>{{ $this->formatBytes($record->original_size) }}</span>
                                    @if ($record->isFailed())
                                        <span class="font-semibold text-red-400">Failed:
                                            {{ $record->error_message }}</span>
                                    @elseif ($hasReduction)
                                        <span class="text-blue-100/30">&rarr;</span>
                                        <span
                                            class="font-semibold text-cyan-300">{{ $this->formatBytes($record->compressed_size) }}</span>
                                        <span
                                            class="font-semibold text-emerald-400">-{{ $record->savingsPercent() }}%</span>
                                    @else
                                        <span class="text-amber-300/70">Already optimized - original kept</span>
                                    @endif
                                </div>
                            </div>
                            @if ($record->isCompleted())
                                <button type="button" wire:click="downloadResult({{ $index }})"
                                    wire:loading.attr="disabled"
                                    class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-linear-to-r from-cyan-500 to-blue-500 px-3 py-1.5 text-xs font-bold text-white transition hover:-translate-y-0.5 cursor-pointer">
                                    <span class="material-symbols-outlined text-sm">download</span>
                                    Download
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                @php
                    $successfulCount = collect($this->records)
                        ->filter(fn($r) => $r->isCompleted() && !$r->no_reduction)
                        ->count();
                    $totalOriginal = collect($this->records)->sum('original_size');
                    $totalCompressed = collect($this->records)
                        ->filter(fn($r) => $r->compressed_size)
                        ->sum('compressed_size');
                    $totalSaved = $totalOriginal - $totalCompressed;
                @endphp

                @if ($successfulCount > 0)
                    <div class="relative mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-white/10 bg-slate-950/30 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-100/45">Original
                            </p>
                            <p class="mt-1 text-lg font-extrabold text-white">
                                {{ $this->formatBytes($totalOriginal) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/30 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-100/45">Compressed
                            </p>
                            <p class="mt-1 text-lg font-extrabold text-cyan-300">
                                {{ $this->formatBytes($totalCompressed) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/30 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-100/45">Saved</p>
                            <p class="mt-1 text-lg font-extrabold text-emerald-300">
                                {{ $this->formatBytes($totalSaved) }}</p>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Processing View --}}
        @elseif ($hasRecords && $anyProcessing)
            <section wire:key="pdf-processing-panel"
                class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/6 p-6 shadow-[0_30px_100px_rgba(0,0,0,0.35)] backdrop-blur-2xl sm:p-8">
                <div
                    class="pointer-events-none absolute inset-0 bg-linear-to-br from-cyan-400/5 via-transparent to-blue-500/5">
                </div>

                <div class="relative flex flex-col items-center text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-cyan-400/10 text-cyan-300">
                        <span
                            class="h-8 w-8 animate-spin rounded-full border-2 border-cyan-100/30 border-t-cyan-100"></span>
                    </div>
                    <h2 class="mt-5 text-2xl font-extrabold text-white">Compressing your PDFs...</h2>
                    <p class="mt-2 text-sm text-blue-100/55">
                        Processing {{ count($this->records) }} file(s) with
                        {{ $this->levels[$this->compressionLevel]['label'] ?? 'Recommended' }} settings.
                    </p>
                    <p class="mt-1 text-xs text-blue-100/40">
                        Elapsed: {{ $this->elapsedTime }} &middot; Large files may take a few minutes.
                    </p>

                    <div class="mt-6 w-full max-w-sm">
                        <div class="mb-2 flex items-center justify-between text-xs">
                            <span class="text-blue-100/50">Progress</span>
                            <span class="font-semibold text-cyan-300">
                                {{ $progressPercent }}%
                            </span>
                        </div>

                        <div class="h-3 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-linear-to-r from-cyan-400 to-blue-500 transition-[width] duration-700 ease-out"
                                style="width: {{ $progressPercent }}%">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 w-full max-w-md space-y-2">
                        @foreach ($this->records as $record)
                            <div wire:key="processing-record-{{ $record->id }}"
                                class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-xs">
                                @if ($record->isCompleted())
                                    <span class="material-symbols-outlined text-sm text-emerald-400">check_circle</span>
                                @elseif ($record->isFailed())
                                    <span class="material-symbols-outlined text-sm text-red-400">error</span>
                                @else
                                    <span
                                        class="h-3 w-3 animate-spin rounded-full border-2 border-cyan-100/30 border-t-cyan-100"></span>
                                @endif
                                <span class="truncate text-blue-100/70">{{ $record->original_name }}</span>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 text-xs leading-5 text-blue-100/45">
                        Please keep this page open while your PDFs are being optimized.
                    </p>
                </div>
            </section>

            {{-- Upload + Settings View --}}
        @else
            <div class="grid w-full grid-cols-1 gap-8 lg:grid-cols-12">
                <section
                    class="relative flex min-h-140 flex-col overflow-hidden rounded-2xl border border-white/10 bg-white/6 p-6 shadow-[0_24px_80px_rgba(0,0,0,0.25)] backdrop-blur-2xl lg:col-span-8 sm:p-8">
                    <div
                        class="pointer-events-none absolute inset-0 rounded-2xl bg-linear-to-br from-cyan-400/5 via-transparent to-blue-500/5">
                    </div>

                    <div wire:loading.flex wire:target="files"
                        class="absolute inset-0 z-30 items-center justify-center rounded-2xl bg-slate-950/90 backdrop-blur-md">
                        <div class="flex flex-col items-center gap-4 text-center">
                            <span
                                class="h-10 w-10 animate-spin rounded-full border-2 border-cyan-100/30 border-t-cyan-300"></span>
                            <div>
                                <p class="font-semibold text-white">Uploading PDF...</p>
                                <p class="mt-1 text-sm text-blue-100/50">Please wait a moment.</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative mb-6 flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-extrabold text-white">PDFs to Process</h2>
                            <p class="mt-1 text-sm text-blue-100/45">
                                @if (!empty($this->files))
                                    {{ count($this->files) }} file(s) selected. Max {{ $this->maxFiles }}.
                                @else
                                    No PDF selected yet.
                                @endif
                            </p>
                        </div>
                    </div>

                    @if (!empty($this->files))
                        <div
                            class="relative flex flex-1 flex-col gap-3 rounded-xl border border-white/10 bg-slate-950/25 p-4">
                            @foreach ($this->files as $index => $file)
                                @if (is_object($file) && method_exists($file, 'getClientOriginalName'))
                                    <div
                                        class="flex items-center gap-3 rounded-lg border border-white/10 bg-white/4 px-4 py-3">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-cyan-300/15 bg-cyan-400/10 text-cyan-300">
                                            <span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-white">
                                                {{ $file->getClientOriginalName() }}</p>
                                            <p class="text-xs text-blue-100/50">
                                                {{ $this->formatBytes($this->fileSizes[$index] ?? null) }}
                                            </p>
                                        </div>
                                        <button type="button" wire:click="removeFile({{ $index }})"
                                            class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-red-400/20 bg-red-400/10 px-2.5 py-1.5 text-xs font-semibold text-red-300 transition hover:border-red-400/30 hover:bg-red-400/15 cursor-pointer">
                                            <span class="material-symbols-outlined text-sm">close</span>
                                        </button>
                                    </div>
                                @endif
                            @endforeach

                            @if (count($this->files) < $this->maxFiles)
                                <label for="pdf-upload"
                                    class="group flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-cyan-300/20 bg-slate-950/15 p-4 text-center transition hover:border-cyan-300/40 hover:bg-cyan-400/5">
                                    <span class="material-symbols-outlined text-lg text-cyan-300">add</span>
                                    <span class="text-sm font-semibold text-cyan-200/80">Add more PDFs</span>
                                </label>
                            @endif
                        </div>
                    @else
                        <label for="pdf-upload"
                            class="group relative flex flex-1 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-cyan-300/25 bg-slate-950/25 p-8 text-center transition hover:border-cyan-300/50 hover:bg-cyan-400/5 sm:p-12"
                            x-data="{ dragover: false }" x-on:dragover.prevent="dragover = true"
                            x-on:dragleave.prevent="dragover = false" x-on:drop.prevent="dragover = false"
                            :class="{ 'border-cyan-300/50 bg-cyan-400/5': dragover }">
                            <div
                                class="flex h-16 w-16 items-center justify-center rounded-full border border-cyan-300/15 bg-cyan-400/10 text-cyan-300 transition group-hover:scale-110">
                                <span class="material-symbols-outlined text-4xl">cloud_upload</span>
                            </div>
                            <h3 class="mt-6 text-2xl font-extrabold text-white">
                                {{ $this->maxFiles > 1 ? 'Drop your PDFs here' : 'Drop your PDF here' }}
                            </h3>
                            <p class="mt-2 text-sm text-blue-100/55">
                                Or click to browse. PDF only, up to {{ $this->maxFiles }} file(s),
                                {{ $this->max_upload_size_mb }}MB each.
                            </p>
                            <div
                                class="mt-8 inline-flex items-center gap-2 rounded-xl border border-cyan-300/30 bg-cyan-400/10 px-8 py-3 font-bold text-cyan-200 transition hover:bg-cyan-400/15">
                                <span class="material-symbols-outlined text-xl">upload_file</span>
                                Browse Files
                            </div>
                            <div class="mt-6 flex flex-wrap justify-center gap-2">
                                <span
                                    class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] font-medium text-blue-100/60">PDF</span>
                                <span
                                    class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] text-blue-100/60">Max
                                    {{ $this->maxFiles }} file(s)</span>
                                <span
                                    class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] text-blue-100/60">{{ $this->max_upload_size_mb }}MB
                                    each</span>
                            </div>
                        </label>
                    @endif

                    <input id="pdf-upload" type="file" wire:model="files" accept="application/pdf"
                        {{ $this->maxFiles > 1 ? 'multiple' : '' }} class="hidden" />
                </section>

                <aside class="flex flex-col gap-6 lg:col-span-4">
                    <div
                        class="flex h-full flex-col rounded-2xl border border-white/10 bg-white/6 p-6 shadow-[0_24px_80px_rgba(0,0,0,0.25)] backdrop-blur-2xl sm:p-8 lg:sticky lg:top-24">
                        <div class="mb-8 flex items-center gap-4">
                            <div class="rounded-xl border border-cyan-300/15 bg-cyan-400/10 p-3 text-cyan-300">
                                <span class="material-symbols-outlined">tune</span>
                            </div>
                            <div>
                                <h2 class="text-lg font-extrabold leading-none text-white">Compression Level
                                </h2>
                                <p class="mt-1 text-xs text-blue-100/45">Choose how aggressively to compress.
                                </p>
                            </div>
                        </div>

                        <div class="mb-6 space-y-3">
                            @foreach ($this->levels as $key => $level)
                                <label wire:key="level-{{ $key }}"
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ $compressionLevel === $key ? 'border-cyan-400/30 bg-cyan-400/5' : 'border-white/10 bg-slate-950/25 hover:border-cyan-400/20 hover:bg-cyan-400/3' }}">
                                    <span class="relative mt-0.5 shrink-0">
                                        <input type="radio" wire:model.live="compressionLevel"
                                            value="{{ $key }}" class="peer sr-only" />
                                        <span
                                            class="block h-5 w-5 rounded-full border-2 border-white/20 transition peer-checked:border-cyan-400 peer-checked:bg-cyan-400/20"></span>
                                        <span
                                            class="absolute inset-0 m-auto hidden h-2 w-2 rounded-full bg-cyan-300 peer-checked:block"></span>
                                    </span>
                                    <span class="flex-1">
                                        <span class="block text-sm font-bold text-white">{{ $level['label'] }}</span>
                                        <span
                                            class="mt-0.5 block text-[11px] leading-5 text-blue-100/45">{{ $level['description'] }}</span>
                                        <span class="mt-1 block text-[10px] text-blue-100/35">
                                            {{ $level['image_resolution'] }} DPI &middot; Quality
                                            {{ $level['jpeg_quality'] ?? ($level['image_quality'] ?? 70) }}%
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @if (empty($this->files))
                            <button type="button" disabled
                                class="mt-8 flex w-full cursor-not-allowed items-center justify-center gap-3 rounded-xl bg-white/8 px-6 py-4 font-extrabold text-white/30">
                                <span class="material-symbols-outlined">compress</span>
                                Select PDF(s) to compress
                            </button>
                        @else
                            <button type="button" wire:click="compress" wire:loading.attr="disabled"
                                class="group relative mt-8 flex w-full items-center justify-center gap-3 overflow-hidden rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 px-6 py-4 font-extrabold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 hover:shadow-[0_0_25px_rgba(34,211,238,0.35)] active:translate-y-0 disabled:opacity-60 cursor-pointer">
                                <span
                                    class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover:left-full"></span>
                                <span wire:loading.remove wire:target="compress"
                                    class="relative flex items-center gap-2">
                                    <span class="material-symbols-outlined">compress</span>
                                    Compress {{ count($this->files) > 1 ? count($this->files) . ' PDFs' : 'PDF' }}
                                </span>
                                <span wire:loading wire:target="compress" class="relative flex items-center gap-2">
                                    <span
                                        class="h-5 w-5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                    Compressing...
                                </span>
                            </button>
                        @endif
                    </div>
                </aside>
            </div>

            <div
                class="fixed inset-x-0 bottom-0 z-50 border-t border-white/10 bg-slate-950/90 backdrop-blur-xl lg:hidden">
                <div class="mx-auto max-w-3xl px-4 py-3">
                    @if (!empty($this->files))
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-white truncate">{{ count($this->files) }}
                                    PDF(s) selected</p>
                                <p class="text-xs text-blue-100/45">
                                    {{ $this->levels[$compressionLevel]['label'] }}
                                </p>
                            </div>
                            <button type="button" wire:click="compress" wire:loading.attr="disabled"
                                class="group relative flex shrink-0 items-center gap-2 overflow-hidden rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/25 transition active:scale-95 disabled:opacity-60 cursor-pointer">
                                <span wire:loading.remove wire:target="compress"
                                    class="relative flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base">compress</span>
                                    Compress
                                </span>
                                <span wire:loading wire:target="compress" class="relative flex items-center gap-1.5">
                                    <span
                                        class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                    Working...
                                </span>
                            </button>
                        </div>
                    @else
                        <label for="pdf-upload"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-500/25">
                            <span class="material-symbols-outlined text-base">upload_file</span>
                            Choose PDF to Compress
                        </label>
                    @endif
                </div>
            </div>

            <div class="h-20 lg:hidden"></div>
        @endif
</div>

<div class="mt-12 grid w-full gap-6 sm:grid-cols-3">
    <div
        class="rounded-2xl border border-white/10 bg-white/6 p-6 text-center shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cyan-500/15 text-cyan-300">
            <span class="material-symbols-outlined">upload</span>
        </div>
        <h3 class="mt-4 font-semibold text-white">1. Upload</h3>
        <p class="mt-2 text-sm text-blue-100/62">
            Select PDF file(s) up to {{ $this->max_upload_size_mb }}MB from your device.
        </p>
    </div>
    <div
        class="rounded-2xl border border-white/10 bg-white/6 p-6 text-center shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-500/15 text-blue-300">
            <span class="material-symbols-outlined">tune</span>
        </div>
        <h3 class="mt-4 font-semibold text-white">2. Choose Level</h3>
        <p class="mt-2 text-sm text-blue-100/62">
            Select low, recommended, or extreme compression based on your needs.
        </p>
    </div>
    <div
        class="rounded-2xl border border-white/10 bg-white/6 p-6 text-center shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl">
        <div
            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
            <span class="material-symbols-outlined">download</span>
        </div>
        <h3 class="mt-4 font-semibold text-white">3. Download</h3>
        <p class="mt-2 text-sm text-blue-100/62">
            Download your optimized PDFs with full details on size savings.
        </p>
    </div>
</div>
</main>
</div>
