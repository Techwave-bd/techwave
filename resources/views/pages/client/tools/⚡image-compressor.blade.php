<?php

use App\Models\ToolCategory;
use App\Models\ToolPlan;
use App\Models\UserCompressedImage;
use App\Models\ToolUsage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Image\Image;

new #[Title('Image Compressor')] class extends Component {
    use WithFileUploads;

    private const MAX_IMAGE_SIZE_KB = 10240; // 10MB

    private const DEFAULT_QUALITY = 32;

    private ?ToolCategory $category = null;

    public array $images = [];
    public array $results = [];

    public bool $convertPngToWebp = false;

    public bool $processing = false;
    public int $dailyUsage = 0;

    public int $compressionProgress = 0;
    public int $totalImages = 0;
    public int $processedImages = 0;

    public function boot(): void
    {
        $this->category = ToolCategory::query()->where('slug', 'image')->first();

        if (auth()->check()) {
            $this->dailyUsage = ToolUsage::query()
                ->where('user_id', auth()->id())
                ->where('tool_type', 'image_compressor')
                ->where('period', now()->format('Y-m-d'))
                ->sum('usage_count');
        }
    }

    public function getPremiumPlansProperty()
    {
        $category = ToolCategory::query()->where('slug', 'image-tools')->first();

        if (!$category) {
            return collect();
        }

        return ToolPlan::query()
            ->where('tool_category_id', $category->id)
            ->where(function ($query) {
                $query->where('is_active', true)->orWhere('is_active', 1);
            })
            ->orderBy('monthly_price')
            ->get();
    }

    public function getMaxImagesProperty(): int
    {
        if (!$this->category) {
            return 30;
        }

        return auth()->user()?->maxFileUploadFor($this->category) ?? ($this->category->free_max_file_upload ?? 30);
    }

    public function getIsPremiumUserProperty(): bool
    {
        return $this->category && auth()->check() && auth()->user()->hasActiveToolSubscription($this->category);
    }

    public function updatedImages(): void
    {
        if (count($this->images) > $this->max_images) {
            $this->images = array_slice($this->images, 0, $this->max_images);

            $this->dispatch('toast', message: 'You selected more than ' . $this->max_images . ' images. Only the first ' . $this->max_images . ' images were added.', type: 'warning');
        }

        try {
            $this->validate(
                [
                    'images' => ['required', 'array', 'max:' . $this->max_images],
                    'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_IMAGE_SIZE_KB],
                ],
                $this->messages(),
            );

            $this->resetErrorBag();
            $this->results = [];
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: $this->uploadErrorMessage($e), type: 'error');

            $this->resetErrorBag();
            $this->results = [];
        }
    }

    protected function messages(): array
    {
        return [
            'images.required' => 'Please upload at least one image.',
            'images.array' => 'Please upload valid image files.',
            'images.max' => 'You selected more than ' . $this->max_images . ' images. Only the first ' . $this->max_images . ' images will be processed.',

            'images.*.uploaded' => 'One image failed to upload. Please check file size, type, or upload fewer images.',
            'images.*.image' => 'One selected file is not a valid image.',
            'images.*.mimes' => 'Only JPG, JPEG, PNG, and WebP images are allowed.',
            'images.*.max' => 'Each image must be 10MB or smaller.',
        ];
    }

    private function uploadErrorMessage(ValidationException $e): string
    {
        $errors = $e->validator->errors()->messages();

        foreach ($errors as $field => $messages) {
            if (preg_match('/^images\.(\d+)/', $field, $matches)) {
                $imageNumber = ((int) $matches[1]) + 1;

                return "Image #{$imageNumber} failed to upload. Please check file size, type, or upload fewer images.";
            }
        }

        if (isset($errors['images'])) {
            return $errors['images'][0] ?? 'Image upload failed. Please try again.';
        }

        return 'Image upload failed. Please check file size, type, or upload fewer images.';
    }

    public function removeImage(int $index): void
    {
        if (!isset($this->images[$index])) {
            return;
        }

        unset($this->images[$index]);

        $this->images = array_values($this->images);
        $this->results = [];

        $this->resetErrorBag();
    }

    public function compress(): void
    {
        /*
         * Do not validate images.* again here.
         * For large batches, one Livewire temp file can fail/expire
         * and block the whole compression.
         */
        $validImages = $this->processableImages();

        if (empty($validImages)) {
            $this->dispatch('toast', message: 'No valid images found. Please upload again.', type: 'error');
            return;
        }

        if (count($this->images) > count($validImages)) {
            $this->dispatch('toast', message: count($this->images) - count($validImages) . ' image(s) were skipped because upload failed or temporary file expired.', type: 'warning');
        }

        $count = count($validImages);

        $this->processing = true;
        $this->results = [];
        $this->compressionProgress = 0;
        $this->processedImages = 0;
        $this->totalImages = $count;

        $this->streamCompressionProgress(0, "Preparing {$count} image(s)...");

        try {
            foreach ($validImages as $image) {
                $this->results[] = $this->compressSingle($image);

                $this->processedImages++;
                $this->compressionProgress = (int) round(($this->processedImages / $this->totalImages) * 100);

                $this->streamCompressionProgress($this->compressionProgress, "Compressed {$this->processedImages} of {$this->totalImages} image(s)");
            }

            $successfulCount = collect($this->results)->filter(fn($result) => ($result['status'] ?? null) === 'success')->count();

            if ($successfulCount > 0 && auth()->check()) {
                $usage = ToolUsage::query()->firstOrCreate(
                    [
                        'user_id' => auth()->id(),
                        'tool_type' => 'image_compressor',
                        'period' => now()->format('Y-m-d'),
                    ],
                    ['usage_count' => 0],
                );

                $usage->increment('usage_count', $successfulCount);

                $this->dailyUsage += $successfulCount;
            }

            $this->streamCompressionProgress(100, 'Compression completed.');

            if ($successfulCount > 0) {
                $this->dispatch('toast', message: $successfulCount . ' image(s) compressed successfully!', type: 'success');
            } else {
                $this->dispatch('toast', message: 'Compression failed. Please upload again and try fewer images.', type: 'error');
            }
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('toast', message: 'Compression failed: ' . $e->getMessage(), type: 'error');
        } finally {
            $this->processing = false;
        }
    }

    private function processableImages(): array
    {
        return collect($this->images)
            ->filter(function ($image) {
                if (!is_object($image)) {
                    return false;
                }

                if (!method_exists($image, 'getClientOriginalName') || !method_exists($image, 'getSize') || !method_exists($image, 'getRealPath')) {
                    return false;
                }

                $path = $image->getRealPath();

                return $path && is_file($path);
            })
            ->take($this->max_images)
            ->values()
            ->all();
    }

    private function streamCompressionProgress(int $progress, string $status): void
    {
        $progress = max(0, min(100, $progress));

        $bar = '<div class="h-full rounded-full bg-linear-to-r from-cyan-400 to-blue-500 transition-all duration-300" style="width: ' . $progress . '%"></div>';

        $this->stream(to: 'compression-progress', content: (string) $progress, replace: true);
        $this->stream(to: 'compression-status', content: e($status), replace: true);
        $this->stream(to: 'compression-bar', content: $bar, replace: true);
    }

    private function compressSingle($image): array
    {
        try {
            $originalName = $image->getClientOriginalName();
            $originalSize = (int) $image->getSize();

            /*
             * Keep original extension by default.
             * Only PNG will become WebP when the user enables the option.
             */
            $ext = strtolower($image->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw new \RuntimeException('Unsupported image format: ' . $ext);
            }

            $quality = $this->outputQuality();
            $shouldConvertPngToWebp = $ext === 'png' && $this->convertPngToWebp;
            $outputExt = $shouldConvertPngToWebp ? 'webp' : $ext;

            $uniqueId = Str::random(20);
            $storagePath = 'temp/compressor/' . $uniqueId . '.' . $outputExt;
            $outputFullPath = Storage::disk('public')->path($storagePath);

            Storage::disk('public')->makeDirectory('temp/compressor');

            $sourcePath = $image->getRealPath();

            if (!$sourcePath || !is_file($sourcePath)) {
                throw new \RuntimeException('Uploaded temporary image file not found.');
            }

            if ($shouldConvertPngToWebp) {
                $this->compressPngToWebp($sourcePath, $outputFullPath, $quality);
            } elseif ($ext === 'png') {
                $this->compressPngKeepingExtension($sourcePath, $outputFullPath, $quality);
            } else {
                $this->compressRasterKeepingExtension($sourcePath, $outputFullPath, $ext, $quality);
            }

            clearstatcache(true, $outputFullPath);

            if (!is_file($outputFullPath)) {
                throw new \RuntimeException('Compressed image was not saved.');
            }

            $compressedSize = (int) filesize($outputFullPath);

            /*
             * If same-extension output becomes bigger, keep original.
             * Do NOT copy PNG bytes into a .webp file when converting.
             */
            if (!$shouldConvertPngToWebp && ($compressedSize <= 0 || $compressedSize > $originalSize)) {
                copy($sourcePath, $outputFullPath);
                clearstatcache(true, $outputFullPath);
                $compressedSize = (int) filesize($outputFullPath);
            }

            if ($shouldConvertPngToWebp && $compressedSize <= 0) {
                throw new \RuntimeException('PNG to WebP conversion failed.');
            }

            $actualReduction = $originalSize > 0 ? max(0, (int) round((1 - $compressedSize / $originalSize) * 100)) : 0;

            $note = null;

            if ($shouldConvertPngToWebp) {
                $note = 'PNG converted to WebP for stronger compression.';

                if ($compressedSize > $originalSize) {
                    $note = 'PNG converted to WebP, but this image could not be reduced much.';
                }
            } elseif ($actualReduction <= 0) {
                $note = 'Image was already optimized. Size could not be reduced much.';
            } elseif ($ext === 'png' && $actualReduction < 30) {
                $note = 'PNG kept as PNG. Enable WebP option for stronger compression.';
            }

            if ($this->is_premium_user) {
                $persistentPath = 'compressed/users/' . auth()->id() . '/' . $uniqueId . '.' . $outputExt;

                Storage::disk('public')->writeStream($persistentPath, Storage::disk('public')->readStream($storagePath));

                UserCompressedImage::query()->create([
                    'user_id' => auth()->id(),
                    'tool_category_id' => $this->category->id,
                    'original_name' => $originalName,
                    'compressed_path' => $persistentPath,
                    'compressed_ext' => $outputExt,
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                    'expires_at' => now()->addMonth(),
                ]);
            }

            return [
                'original_name' => $originalName,
                'original_size' => $originalSize,
                'original_path' => null,

                'compressed_size' => $compressedSize,
                'compressed_path' => $storagePath,
                'compressed_ext' => $outputExt,
                'compressed_note' => $note,
                'backed_up' => $this->is_premium_user,

                'target_percent' => $quality,
                'savings_percent' => $actualReduction,
                'status' => 'success',
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'original_name' => $image->getClientOriginalName(),
                'original_size' => (int) $image->getSize(),
                'original_path' => null,
                'compressed_size' => 0,
                'compressed_path' => '',
                'compressed_ext' => strtolower($image->getClientOriginalExtension() ?: ''),
                'compressed_note' => 'Compression failed: ' . $e->getMessage(),
                'target_percent' => self::DEFAULT_QUALITY,
                'savings_percent' => 0,
                'status' => 'error',
            ];
        }
    }

    private function compressRasterKeepingExtension(string $sourcePath, string $outputFullPath, string $ext, int $quality): void
    {
        $image = Image::load($sourcePath);

        if (in_array($ext, ['jpg', 'jpeg', 'webp'], true)) {
            $image->quality($quality);
        }

        $image->optimize()->save($outputFullPath);
    }

    private function compressPngKeepingExtension(string $sourcePath, string $outputFullPath, int $quality): void
    {
        /*
         * Strong PNG compression while keeping .png extension.
         * Works locally on Windows using:
         * C:\tools\pngquant\pngquant.exe
         */

        $pngquantPaths = ['C:\\tools\\pngquant\\pngquant.exe', 'C:\\ProgramData\\chocolatey\\bin\\pngquant.exe', '/usr/bin/pngquant', '/usr/local/bin/pngquant'];

        $pngquant = null;

        foreach ($pngquantPaths as $path) {
            if (is_file($path)) {
                $pngquant = $path;
                break;
            }
        }

        if (!$pngquant) {
            Image::load($sourcePath)->optimize()->save($outputFullPath);

            return;
        }

        $output = [];
        $exitCode = 0;

        /*
         * First pass: strong PNG compression.
         */
        $command = '"' . $pngquant . '"' . ' --force' . ' --strip' . ' --speed 1' . ' --quality=25-65' . ' --output "' . $outputFullPath . '"' . ' "' . $sourcePath . '"' . ' 2>&1';

        exec($command, $output, $exitCode);

        clearstatcache(true, $outputFullPath);

        if (is_file($outputFullPath) && filesize($outputFullPath) > 0) {
            return;
        }

        /*
         * Second pass: more aggressive.
         */
        $output = [];
        $exitCode = 0;

        $aggressiveCommand = '"' . $pngquant . '"' . ' --force' . ' --strip' . ' --speed 1' . ' --quality=10-50' . ' --output "' . $outputFullPath . '"' . ' "' . $sourcePath . '"' . ' 2>&1';

        exec($aggressiveCommand, $output, $exitCode);

        clearstatcache(true, $outputFullPath);

        if (is_file($outputFullPath) && filesize($outputFullPath) > 0) {
            return;
        }

        /*
         * Final fallback: Spatie optimize only.
         */
        Image::load($sourcePath)->optimize()->save($outputFullPath);
    }

    private function compressPngToWebp(string $sourcePath, string $outputFullPath, int $quality): void
    {
        /*
         * Super optimize mode for PNG.
         * Saving with .webp output extension tells Spatie Image to save as WebP.
         * Width is not changed.
         */
        Image::load($sourcePath)->quality($quality)->optimize()->save($outputFullPath);
    }

    private function outputQuality(): int
    {
        return self::DEFAULT_QUALITY;
    }

    public function download(int $index): mixed
    {
        $result = $this->results[$index] ?? null;

        if (!$result || empty($result['compressed_path']) || !Storage::disk('public')->exists($result['compressed_path'])) {
            return null;
        }

        $fileName = pathinfo($result['original_name'], PATHINFO_FILENAME);
        $extension = $result['compressed_ext'] ?: pathinfo($result['original_name'], PATHINFO_EXTENSION);

        $downloadName = $fileName . '_compressed.' . $extension;

        return Storage::disk('public')->download($result['compressed_path'], $downloadName);
    }

    public function downloadAll(): mixed
    {
        $validResults = array_filter($this->results, fn($r) => !empty($r['compressed_path']) && Storage::disk('public')->exists($r['compressed_path']));

        if (empty($validResults)) {
            return null;
        }

        $zip = new ZipArchive();
        $zipPath = tempnam(sys_get_temp_dir(), 'compressed_') . '.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return null;
        }

        foreach ($validResults as $result) {
            $fileName = pathinfo($result['original_name'], PATHINFO_FILENAME);
            $extension = $result['compressed_ext'] ?: pathinfo($result['original_name'], PATHINFO_EXTENSION);
            $downloadName = $fileName . '_compressed.' . $extension;
            $filePath = Storage::disk('public')->path($result['compressed_path']);

            if (is_file($filePath)) {
                $zip->addFile($filePath, $downloadName);
            }
        }

        $zip->close();

        return response()->download($zipPath, 'compressed_images.zip')->deleteFileAfterSend(true);
    }

    public function resetUpload(): void
    {
        $paths = collect($this->results)->flatMap(fn($r) => [$r['compressed_path'] ?? null])->filter()->unique()->reject(fn($path) => str_starts_with($path, 'compressed/users/'))->toArray();

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $this->reset(['images', 'results', 'processing', 'compressionProgress', 'processedImages', 'totalImages']);
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

<div class="min-h-screen text-white">
    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-10 text-center">
            <h1 class="mt-6 text-4xl font-extrabold tracking-tight sm:text-5xl">
                Compress your
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">images</span>
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-blue-100/62">
                Reduce image file size while keeping good visual quality. Keep original extension or convert PNG to WebP
                for stronger compression.
            </p>
        </div>

        {{-- Main --}}
        <div>

            {{-- Compression Progress Overlay --}}
            <div wire:loading.flex wire:target="compress"
                class="fixed inset-0 z-[9999] items-center justify-center bg-slate-950/75 px-4 backdrop-blur-md">
                <div
                    class="w-full max-w-md rounded-3xl border border-cyan-400/20 bg-slate-900/90 p-6 text-center shadow-2xl shadow-cyan-500/20">
                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-cyan-400/10 text-cyan-300">
                        <span
                            class="h-8 w-8 animate-spin rounded-full border-2 border-cyan-100/30 border-t-cyan-100"></span>
                    </div>
                    <h3 class="mt-5 text-xl font-bold text-white">Compressing images...</h3>
                    <p class="mt-2 text-sm text-blue-100/60">
                        <span wire:stream="compression-status">Preparing images...</span>
                    </p>
                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between text-xs text-blue-100/60">
                            <span>Progress</span>
                            <span><span wire:stream="compression-progress">0</span>%</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-white/10">
                            <span wire:stream="compression-bar">
                                <div class="h-full rounded-full bg-linear-to-r from-cyan-400 to-blue-500 transition-all duration-300"
                                    style="width: 0%"></div>
                            </span>
                        </div>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-blue-100/45">
                        Please keep this page open while your images are being optimized.
                    </p>
                </div>
            </div>

            @if (!empty($results))
                {{-- ─────────────────────────────────────────────────────── --}}
                {{-- RESULTS VIEW                                            --}}
                {{-- ─────────────────────────────────────────────────────── --}}
                @php
                    $successfulResults = collect($results)->filter(fn($r) => ($r['status'] ?? null) === 'success');
                    $failedResults = collect($results)->filter(fn($r) => ($r['status'] ?? null) === 'error');
                    $totalOriginalSize = $successfulResults->sum('original_size');
                    $totalCompressedSize = $successfulResults->sum('compressed_size');
                    $totalSavedSize = max(0, $totalOriginalSize - $totalCompressedSize);
                    $overallSavingsPercent =
                        $totalOriginalSize > 0
                            ? max(0, (int) round((1 - $totalCompressedSize / $totalOriginalSize) * 100))
                            : 0;
                @endphp

                <div
                    class="relative overflow-hidden rounded-[2rem] border border-white/15 bg-white/[0.07] p-5 shadow-[0_30px_100px_rgba(0,0,0,0.35)] backdrop-blur-2xl sm:p-8">

                    {{-- Header row --}}
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-white">
                                Compression complete
                                <span
                                    class="ml-2 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-sm font-semibold text-emerald-300">
                                    -{{ $overallSavingsPercent }}%
                                </span>
                            </h2>
                            <p class="mt-1 text-sm text-blue-100/50">
                                {{ $successfulResults->count() }}
                                image{{ $successfulResults->count() !== 1 ? 's' : '' }} compressed
                                &middot; saved {{ $this->formatBytes($totalSavedSize) }}
                                &middot; {{ $this->formatBytes($totalOriginalSize) }} →
                                {{ $this->formatBytes($totalCompressedSize) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button type="button" wire:click="resetUpload"
                                class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/8 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/12">
                                <span class="material-symbols-outlined text-base">refresh</span>
                                New batch
                            </button>

                            @if ($successfulResults->count() === 1)
                                @php $singleResultIndex = collect($results)->search(fn($r) => ($r['status'] ?? null) === 'success'); @endphp
                                <button type="button" wire:click="download({{ $singleResultIndex }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-linear-to-r from-cyan-500 to-blue-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 disabled:opacity-60">
                                    <span class="material-symbols-outlined text-base">download</span>
                                    Download
                                </button>
                            @elseif ($successfulResults->count() > 1)
                                <button type="button" wire:click="downloadAll" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-linear-to-r from-cyan-500 to-blue-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 disabled:opacity-60">
                                    <span class="material-symbols-outlined text-base">folder_zip</span>
                                    Download all
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Overall savings bar --}}
                    @if ($successfulResults->count() > 0)
                        <div class="mb-6 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-linear-to-r from-emerald-400 to-cyan-400 transition-all"
                                style="width: {{ min(100, $overallSavingsPercent) }}%"></div>
                        </div>
                    @endif

                    {{-- Failed notice --}}
                    @if ($failedResults->count() > 0)
                        <div
                            class="mb-4 flex items-center gap-2 rounded-xl border border-red-400/15 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                            <span class="material-symbols-outlined text-base text-red-300">error</span>
                            {{ $failedResults->count() }} image{{ $failedResults->count() !== 1 ? 's' : '' }} failed to
                            compress. Please try again.
                        </div>
                    @endif

                    {{-- File list --}}
                    <div class="space-y-2">
                        @foreach ($results as $index => $result)
                            @php $success = ($result['status'] ?? null) === 'success'; @endphp

                            <div
                                class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 transition hover:border-cyan-400/25 hover:bg-white/8">

                                {{-- Status dot --}}
                                <div @class([
                                    'h-2 w-2 shrink-0 rounded-full',
                                    'bg-emerald-400' => $success,
                                    'bg-red-400' => !$success,
                                ])></div>

                                {{-- File info --}}
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-white">{{ $result['original_name'] }}
                                    </p>
                                    <div
                                        class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-blue-100/50">
                                        @if ($success)
                                            <span>{{ $this->formatBytes($result['original_size']) }}</span>
                                            <span class="text-blue-100/30">→</span>
                                            <span
                                                class="text-cyan-300">{{ $this->formatBytes($result['compressed_size']) }}</span>
                                            <span class="text-emerald-400">-{{ $result['savings_percent'] }}%</span>
                                            @if (!empty($result['compressed_ext']))
                                                <span
                                                    class="rounded bg-white/8 px-1.5 py-0.5 uppercase">{{ $result['compressed_ext'] }}</span>
                                            @endif
                                        @else
                                            <span class="text-red-300/70">Compression failed</span>
                                        @endif
                                        @if (!empty($result['compressed_note']))
                                            <span class="text-amber-300/70">· {{ $result['compressed_note'] }}</span>
                                        @endif
                                        @if (!empty($result['backed_up']))
                                            <span class="text-cyan-300/70">· <span
                                                    class="material-symbols-outlined text-[11px] leading-none align-middle">backup</span>
                                                Backed up</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Mini savings bar (desktop) --}}
                                @if ($success)
                                    <div class="hidden w-20 shrink-0 sm:block">
                                        <div class="h-1 overflow-hidden rounded-full bg-white/10">
                                            <div class="h-full rounded-full bg-linear-to-r from-emerald-400 to-cyan-400"
                                                style="width: {{ min(100, $result['savings_percent']) }}%"></div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Action --}}
                                @if ($success)
                                    <button type="button" wire:click="download({{ $index }})"
                                        class="shrink-0 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/15">
                                        <span class="material-symbols-outlined text-sm leading-none">download</span>
                                    </button>
                                @else
                                    <span
                                        class="shrink-0 rounded-lg bg-red-500/15 px-3 py-1.5 text-xs text-red-300">Failed</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- ─────────────────────────────────────────────────────── --}}
                {{-- UPLOAD VIEW                                             --}}
                {{-- ─────────────────────────────────────────────────────── --}}
                <div class="space-y-8">
                    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">

                        {{-- Left: Image panel --}}
                        <div
                            class="relative rounded-3xl border border-white/10 bg-white/5 p-5 shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl">

                            {{-- Upload loading overlay --}}
                            <div wire:loading.flex wire:target="images"
                                class="absolute inset-0 z-30 items-center justify-center rounded-3xl bg-slate-950/90 backdrop-blur-md">
                                <div class="flex flex-col items-center gap-4 text-center">
                                    <span
                                        class="h-10 w-10 animate-spin rounded-full border-2 border-cyan-100/30 border-t-cyan-300"></span>
                                    <div>
                                        <p class="font-semibold text-white">Uploading images...</p>
                                        <p class="mt-1 text-sm text-blue-100/50">Please wait a moment.</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Panel header --}}
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-white">Selected Images</h3>
                                    <p class="text-sm text-blue-100/45">
                                        {{ empty($images) ? 'No images selected yet.' : count($images) . ' image' . (count($images) > 1 ? 's' : '') . ' ready to compress.' }}
                                    </p>
                                </div>

                                @if (!empty($images))
                                    <label for="image-upload"
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/12">
                                        <span class="material-symbols-outlined text-sm">add</span>
                                        Add more
                                    </label>
                                @endif
                            </div>

                            @if (empty($images))
                                {{-- Drop zone --}}
                                <label for="image-upload"
                                    class="group flex min-h-80 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-white/15 bg-black/10 px-6 py-10 text-center transition hover:border-cyan-400/40 hover:bg-cyan-400/5">
                                    <div
                                        class="flex h-16 w-16 items-center justify-center rounded-2xl border border-cyan-300/15 bg-cyan-400/10 text-cyan-300 transition group-hover:scale-105">
                                        <span class="material-symbols-outlined text-4xl">cloud_upload</span>
                                    </div>
                                    <p class="mt-4 text-base font-bold text-white">Drop images here</p>
                                    <p class="mt-1 text-sm text-blue-100/50">or click to browse from your device</p>
                                    <div class="mt-5 flex flex-wrap justify-center gap-1.5">
                                        @foreach (['JPG', 'PNG', 'WebP'] as $fmt)
                                            <span
                                                class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] font-medium text-blue-100/60">{{ $fmt }}</span>
                                        @endforeach
                                        <span
                                            class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] text-blue-100/60">Max
                                            10MB</span>
                                        <span
                                            class="rounded-full border border-white/10 bg-white/8 px-2.5 py-1 text-[11px] text-blue-100/60">Up
                                            to {{ $this->max_images }} files</span>
                                    </div>
                                </label>
                            @else
                                {{-- Image grid --}}
                                <div class="grid max-h-130 gap-3 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3 scrollbar-thin scrollbar-thumb-white/20">
                                    @foreach ($images as $index => $img)
                                        <div wire:key="selected-image-{{ $index }}-{{ md5($img->getClientOriginalName() . $img->getSize()) }}"
                                            class="group relative overflow-hidden rounded-xl border border-white/10 bg-black/15 transition hover:border-cyan-400/30">
                                            <button type="button" wire:click="removeImage({{ $index }})"
                                                class="absolute right-2 top-2 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white/70 opacity-0 backdrop-blur-sm transition hover:bg-red-500/70 hover:text-white group-hover:opacity-100"
                                                title="Remove">
                                                <span class="material-symbols-outlined text-sm">close</span>
                                            </button>
                                            <div
                                                class="flex h-36 items-center justify-center overflow-hidden bg-white/3">
                                                <img src="{{ $img->temporaryUrl() }}" alt="Preview"
                                                    class="h-full w-full object-contain p-2" />
                                            </div>
                                            <div class="px-3 py-2.5">
                                                <p class="truncate text-xs font-semibold text-white"
                                                    title="{{ $img->getClientOriginalName() }}">
                                                    {{ $img->getClientOriginalName() }}
                                                </p>
                                                <div class="mt-1.5 flex items-center gap-1.5">
                                                    <span
                                                        class="rounded bg-white/8 px-1.5 py-0.5 text-[10px] font-medium uppercase text-blue-100/55">
                                                        {{ $img->extension() }}
                                                    </span>
                                                    <span class="text-[11px] text-blue-100/45">
                                                        {{ $this->formatBytes((int) $img->getSize()) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <input id="image-upload" type="file" wire:model="images" multiple
                                accept="image/png,image/jpeg,image/jpg,image.webp" class="hidden" />
                        </div>

                        {{-- Right: Settings panel --}}
                        <div
                            class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-xl lg:sticky lg:top-24 lg:self-start">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                                <span class="material-symbols-outlined">tune</span>
                            </div>

                            <h3 class="mt-4 text-lg font-bold text-white">Compression Settings</h3>
                            <p class="mt-1 text-sm text-blue-100/45">Automatic compression with original extension
                                preserved.</p>

                            {{-- Add images button --}}
                            <label for="image-upload"
                                class="mt-5 flex cursor-pointer items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/8 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-cyan-400/30 hover:bg-white/12">
                                <span class="material-symbols-outlined text-lg">add_photo_alternate</span>
                                {{ empty($images) ? 'Choose Images' : 'Add More Images' }}
                            </label>

                            {{-- Stats --}}
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div class="rounded-xl border border-white/10 bg-black/10 px-3 py-2.5">
                                    <p class="text-[11px] text-blue-100/45">Images</p>
                                    <p class="mt-0.5 text-lg font-bold text-white">{{ count($images) }}</p>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-black/10 px-3 py-2.5">
                                    <p class="text-[11px] text-blue-100/45">Max Upload</p>
                                    <p class="mt-0.5 text-lg font-bold text-cyan-300">
                                        @php
                                            $isPremium = $this->is_premium_user;
                                        @endphp
                                        <span @class([
                                            'text-[11px] font-semibold px-2 py-0.5 rounded-full',
                                            'text-cyan-300 bg-cyan-400/10' => $isPremium,
                                            'text-blue-100/60 bg-white/8' => !$isPremium,
                                        ])>
                                            {{ $this->max_images }} files
                                            @if ($isPremium)
                                                · Premium
                                            @endif
                                        </span>
                                    </p>
                                </div>
                            </div>

                            {{-- PNG to WebP option --}}
                            <label
                                class="group mt-4 flex cursor-pointer items-center justify-between gap-4 overflow-hidden rounded-2xl border border-cyan-300/15 bg-white/6 p-4 shadow-[0_16px_45px_rgba(6,182,212,0.08)] backdrop-blur-xl transition hover:-translate-y-0.5 hover:border-cyan-300/35 hover:bg-cyan-300/8">

                                <span class="flex min-w-0 items-start gap-3">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-cyan-300/15 bg-cyan-300/10 text-cyan-300 transition group-hover:scale-105 group-hover:bg-cyan-300/15">
                                        <span class="material-symbols-outlined text-xl">auto_awesome</span>
                                    </span>

                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-white">
                                            Super optimize PNG
                                        </span>

                                        <span class="mt-1 block text-xs leading-5 text-blue-100/55">
                                            Convert PNG to WebP for much smaller files. Turn off to keep PNG as .png.
                                        </span>
                                    </span>
                                </span>

                                <span class="relative shrink-0">
                                    <input type="checkbox" wire:model.live="convertPngToWebp" class="peer sr-only" />

                                    <span
                                        class="block h-7 w-12 rounded-full border border-white/15 bg-white/10 transition peer-checked:border-cyan-300/40 peer-checked:bg-cyan-400/30">
                                    </span>

                                    <span
                                        class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow-lg shadow-black/20 transition peer-checked:translate-x-5 peer-checked:bg-cyan-200">
                                    </span>
                                </span>
                            </label>

                            @if (!empty($images))
                                {{-- Auto compression note --}}
                                <p
                                    class="mt-5 rounded-xl border border-amber-400/15 bg-amber-400/8 px-3 py-2.5 text-xs leading-5 text-amber-100/70">
                                    Images will be compressed automatically. JPG, JPEG, and WebP use strong Spatie
                                    quality compression. PNG stays .png unless the WebP option is enabled.
                                </p>

                                {{-- Compress button (desktop) --}}
                                <button type="button" wire:click="compress" wire:loading.attr="disabled"
                                    class="group relative mt-5 flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-linear-to-r from-cyan-500 to-blue-500 px-6 py-3.5 font-bold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 disabled:opacity-60">
                                    <span
                                        class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover:left-full"></span>
                                    <span wire:loading.remove wire:target="compress"
                                        class="relative flex items-center gap-2">
                                        <span class="material-symbols-outlined">compress</span>
                                        Compress {{ count($images) }} Image{{ count($images) > 1 ? 's' : '' }}
                                    </span>
                                    <span wire:loading wire:target="compress"
                                        class="relative flex items-center gap-2">
                                        <span
                                            class="h-5 w-5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                        Compressing...
                                    </span>
                                </button>
                            @else
                                <button type="button" disabled
                                    class="mt-3 flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-2xl bg-white/8 px-6 py-3.5 font-bold text-white/30">
                                    <span class="material-symbols-outlined">compress</span>
                                    Compress Images
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ─────────────────────────────────────────────────────── --}}
                {{-- MOBILE FIXED BOTTOM BAR                                --}}
                {{-- ─────────────────────────────────────────────────────── --}}
                <div
                    class="fixed inset-x-0 bottom-0 z-50 border-t border-white/10 bg-slate-950/90 backdrop-blur-xl lg:hidden">
                    <div class="mx-auto max-w-3xl px-4 py-3">
                        @if (!empty($images))
                            <div class="flex items-center gap-3">

                                {{-- Info --}}
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white">
                                        {{ count($images) }} image{{ count($images) > 1 ? 's' : '' }} selected
                                    </p>
                                    <p class="text-xs text-blue-100/45">
                                        Automatic compression @if ($convertPngToWebp)
                                            · PNG → WebP
                                        @endif
                                    </p>
                                </div>

                                {{-- Add more --}}
                                <label for="image-upload"
                                    class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-white/15 bg-white/8 text-white transition hover:bg-white/12">
                                    <span class="material-symbols-outlined text-lg">add</span>
                                </label>

                                {{-- Compress --}}
                                <button type="button" wire:click="compress" wire:loading.attr="disabled"
                                    class="group relative flex shrink-0 items-center gap-2 overflow-hidden rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/25 transition active:scale-95 disabled:opacity-60">
                                    <span
                                        class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover:left-full"></span>
                                    <span wire:loading.remove wire:target="compress"
                                        class="relative flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-base">compress</span>
                                        Compress
                                    </span>
                                    <span wire:loading wire:target="compress"
                                        class="relative flex items-center gap-1.5">
                                        <span
                                            class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                        Working...
                                    </span>
                                </button>
                            </div>
                        @else
                            {{-- Empty state: full-width choose button --}}
                            <label for="image-upload"
                                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-500/25">
                                <span class="material-symbols-outlined text-base">add_photo_alternate</span>
                                Choose Images to Compress
                            </label>
                        @endif
                    </div>
                </div>

            @endif
        </div>


        @if ($this->premium_plans->isNotEmpty() && (!auth()->check() || !$this->is_premium_user))
            @php
                $premiumPlan = $this->premium_plans->first();

                $monthlyCheckoutUrl = route('client.tool-subscriptions.checkout', $premiumPlan) . '?billing=monthly';
            @endphp

            {{-- Premium Upgrade Banner --}}
            <div class="mt-12">
                <div
                    class="relative overflow-hidden rounded-4xl border border-cyan-300/15 bg-white/6 p-6 shadow-[0_25px_80px_rgba(6,182,212,0.12)] backdrop-blur-2xl sm:p-8">

                    {{-- Background Effects --}}
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute -top-32 right-10 h-80 w-80 rounded-full bg-cyan-400/15 blur-3xl"></div>
                        <div class="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-blue-500/15 blur-3xl"></div>
                        <div
                            class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.10),transparent_36%)]">
                        </div>
                    </div>

                    <div class="relative z-10">
                        <div class="relative overflow-hidden lg:flex lg:items-center lg:justify-between lg:gap-8">



                            {{-- Left Content --}}
                            <div class="relative max-w-2xl">
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-cyan-300">
                                    <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                    Premium Upgrade
                                </div>

                                <h2
                                    class="mt-4 text-2xl font-extrabold tracking-tight text-white sm:text-3xl lg:text-4xl">
                                    Need more upload limit?
                                </h2>

                                <p class="mt-3 text-sm leading-6 text-blue-100/60 sm:text-base">
                                    Upgrade your image tool plan to upload more files, keep compressed image backup, and
                                    use
                                    better limits for your image compression workflow.
                                </p>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-semibold text-blue-100/70">
                                        <span
                                            class="material-symbols-outlined text-sm text-cyan-300">upload_file</span>
                                        Higher upload limit
                                    </span>

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-semibold text-blue-100/70">
                                        <span class="material-symbols-outlined text-sm text-cyan-300">backup</span>
                                        Image backup
                                    </span>

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-semibold text-blue-100/70">
                                        <span class="material-symbols-outlined text-sm text-cyan-300">bolt</span>
                                        Better limits
                                    </span>
                                </div>
                            </div>

                            {{-- Right Action --}}
                            <div
                                class="relative mt-7 rounded-3xl border border-cyan-300/15 bg-cyan-400/8 p-5 lg:mt-0 lg:w-72">
                                <p class="text-xs font-semibold uppercase tracking-wider text-blue-100/45">
                                    Current Free Limit
                                </p>

                                <div class="mt-2 flex items-end gap-2">
                                    <span class="text-4xl font-extrabold text-white">
                                        {{ $this->max_images }}
                                    </span>
                                    <span class="pb-1.5 text-sm font-medium text-blue-100/45">
                                        files
                                    </span>
                                </div>

                                @if ($premiumPlan?->max_file_upload)
                                    <p class="mt-2 text-sm text-blue-100/55">
                                        Premium allows up to
                                        <span class="font-bold text-cyan-300">
                                            {{ number_format($premiumPlan->max_file_upload) }}
                                        </span>
                                        files.
                                    </p>
                                @endif

                                <div class="mt-5">
                                    @auth
                                        <a href="{{ $monthlyCheckoutUrl }}" wire:navigate
                                            class="group/btn relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-linear-to-r from-cyan-500 to-blue-500 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5">
                                            <span
                                                class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover/btn:left-full"></span>
                                            <span
                                                class="material-symbols-outlined relative text-lg">workspace_premium</span>
                                            <span class="relative">Buy Now</span>
                                        </a>
                                    @else
                                        <button type="button" x-data
                                            @click="window.dispatchEvent(new CustomEvent('open-auth', {
                                        detail: {
                                            mode: 'login',
                                            redirect: '{{ $monthlyCheckoutUrl }}'
                                        }
                                    }))"
                                            class="group/btn relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-linear-to-r from-cyan-500 to-blue-500 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5">
                                            <span
                                                class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover/btn:left-full"></span>
                                            <span
                                                class="material-symbols-outlined relative text-lg">workspace_premium</span>
                                            <span class="relative">Buy Now</span>
                                        </button>
                                    @endauth
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- How It Works --}}
        <div class="mt-12 grid gap-6 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center backdrop-blur-xl">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cyan-500/15 text-cyan-300">
                    <span class="material-symbols-outlined">upload</span>
                </div>
                <h3 class="mt-4 font-semibold text-white">1. Upload</h3>
                <p class="mt-2 text-sm text-blue-100/62">
                    Select JPG, PNG, or WebP images — up to {{ $this->max_images }} at a time.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center backdrop-blur-xl">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-500/15 text-blue-300">
                    <span class="material-symbols-outlined">tune</span>
                </div>
                <h3 class="mt-4 font-semibold text-white">2. Choose Mode</h3>
                <p class="mt-2 text-sm text-blue-100/62">
                    Keep original extensions by default, or enable PNG to WebP for stronger compression.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center backdrop-blur-xl">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
                    <span class="material-symbols-outlined">download</span>
                </div>
                <h3 class="mt-4 font-semibold text-white">3. Download</h3>
                <p class="mt-2 text-sm text-blue-100/62">
                    Download individually or grab all compressed images as a ZIP.
                </p>
            </div>
        </div>

    </div>
</div>
