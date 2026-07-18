<?php

namespace App\Services;

use App\Models\CompressedPdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PdfCompressionService
{
    public function __construct(
        private readonly GhostscriptService $ghostscript,
    ) {}

    public function validatePdf(string $filePath): array
    {
        if (! is_file($filePath)) {
            return [
                'valid' => false,
                'error' => 'The uploaded PDF file could not be found.',
            ];
        }

        if (! is_readable($filePath)) {
            return [
                'valid' => false,
                'error' => 'The uploaded PDF file cannot be read.',
            ];
        }

        $fileSize = @filesize($filePath);

        if ($fileSize === false || $fileSize <= 0) {
            return [
                'valid' => false,
                'error' => 'The uploaded PDF file is empty.',
            ];
        }

        $header = @file_get_contents(
            $filePath,
            false,
            null,
            0,
            5
        );

        if (
            $header === false
            || ! str_starts_with($header, '%PDF-')
        ) {
            return [
                'valid' => false,
                'error' => 'The uploaded file is not a valid PDF.',
            ];
        }

        return ['valid' => true];
    }

    public function compress(CompressedPdf $record): void
    {
        $totalStartedAt = microtime(true);

        $diskName = (string) config(
            'pdf-compressor.storage_disk',
            'local'
        );

        $disk = Storage::disk($diskName);
        $inputPath = $disk->path($record->original_path);

        if (! is_file($inputPath)) {
            throw new RuntimeException(
                'Original PDF file was not found on disk.'
            );
        }

        $validation = $this->validatePdf($inputPath);

        if (! $validation['valid']) {
            $record->update([
                'status' => 'failed',
                'error_message' => $validation['error']
                    ?? 'Invalid PDF file.',
                'processed_at' => now(),
            ]);

            return;
        }

        $level = config(
            "pdf-compressor.levels.{$record->compression_level}"
        );

        if (! is_array($level)) {
            $record->update([
                'status' => 'failed',
                'error_message' => 'Invalid compression level: '
                    . $record->compression_level,
                'processed_at' => now(),
            ]);

            return;
        }

        $compressedDirectory = $this->compressedDirectory($record);

        $disk->makeDirectory($compressedDirectory);

        $compressedPath = $compressedDirectory
            . '/' . Str::random(30) . '.pdf';

        $outputFullPath = $disk->path($compressedPath);

        Log::info('PDF compression started', [
            'pdf_id' => $record->id,
            'compression_level' => $record->compression_level,
            'original_size_mb' => round(
                ((int) $record->original_size) / 1024 / 1024,
                2
            ),
        ]);

        try {
            $ghostscriptStartedAt = microtime(true);

            $this->ghostscript->compress(
                inputPath: $inputPath,
                outputPath: $outputFullPath,
                imageResolution: (int) $level['image_resolution'],
                monoResolution: (int) $level['mono_resolution'],
                jpegQuality: (int) $level['jpeg_quality'],
                compatibilityLevel: (string) $level['compatibility_level'],
                timeout: (int) config(
                    'pdf-compressor.processing_timeout',
                    180
                ),
            );

            Log::info('Ghostscript compression finished', [
                'pdf_id' => $record->id,
                'seconds' => round(
                    microtime(true) - $ghostscriptStartedAt,
                    2
                ),
            ]);

            clearstatcache(true, $outputFullPath);

            $compressedSize = @filesize($outputFullPath);

            if ($compressedSize === false || $compressedSize <= 0) {
                throw new RuntimeException(
                    'The compressed PDF output is empty.'
                );
            }

            $compressedSize = (int) $compressedSize;
            $originalSize = (int) $record->original_size;

            /*
             * If Ghostscript creates a larger result, keep the original.
             */
            if ($compressedSize >= $originalSize) {
                $disk->delete($compressedPath);

                $record->update([
                    'compressed_path' => null,
                    'compressed_size' => null,
                    'no_reduction' => true,
                    'status' => 'completed',
                    'error_message' => null,
                    'processed_at' => now(),
                ]);

                Log::info('PDF completed without size reduction', [
                    'pdf_id' => $record->id,
                    'seconds' => round(
                        microtime(true) - $totalStartedAt,
                        2
                    ),
                ]);

                return;
            }

            $record->update([
                'compressed_path' => $compressedPath,
                'compressed_size' => $compressedSize,
                'no_reduction' => false,
                'status' => 'completed',
                'error_message' => null,
                'processed_at' => now(),
            ]);

            if (! $record->is_backup_enabled) {
                $record->deleteOriginalFile();
            }

            Log::info('PDF compression completed', [
                'pdf_id' => $record->id,
                'compressed_size_mb' => round(
                    $compressedSize / 1024 / 1024,
                    2
                ),
                'saved_percent' => $record->fresh()->savingsPercent(),
                'total_seconds' => round(
                    microtime(true) - $totalStartedAt,
                    2
                ),
            ]);
        } catch (Throwable $e) {
            if (is_file($outputFullPath)) {
                @unlink($outputFullPath);
            }

            $record->update([
                'compressed_path' => null,
                'compressed_size' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('PDF compression failed', [
                'pdf_id' => $record->id,
                'seconds' => round(
                    microtime(true) - $totalStartedAt,
                    2
                ),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function compressedDirectory(
        CompressedPdf $record
    ): string {
        if ($record->is_backup_enabled && $record->user_id) {
            return 'compressed-pdfs/users/' . $record->user_id;
        }

        if ($record->session_id) {
            return 'compressed-pdfs/guests/' . $record->session_id;
        }

        return 'compressed-pdfs/temporary';
    }
}
