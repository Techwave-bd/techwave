<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class GhostscriptService
{
    public function compress(
        string $inputPath,
        string $outputPath,
        int $imageResolution,
        int $monoResolution,
        int $jpegQuality,
        string $compatibilityLevel,
        int $timeout = 180,
    ): void {
        $binary = (string) config(
            'pdf-compressor.ghostscript_path'
        );

        if ($binary === '') {
            throw new RuntimeException(
                'Ghostscript executable path is not configured.'
            );
        }

        if (! is_file($inputPath)) {
            throw new RuntimeException(
                'Ghostscript input PDF was not found.'
            );
        }

        $outputDirectory = dirname($outputPath);

        if (
            ! is_dir($outputDirectory)
            && ! mkdir($outputDirectory, 0775, true)
            && ! is_dir($outputDirectory)
        ) {
            throw new RuntimeException(
                'Unable to create the PDF output directory.'
            );
        }

        $command = [
            $binary,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=' . $compatibilityLevel,
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-dQUIET',

            // Avoid unnecessary metadata and thumbnail generation.
            '-dPreserveAnnots=true',
            '-dPreserveMarkedContent=true',
            '-dCreateJobTicket=false',
            '-dPrinted=false',

            // Font optimization.
            '-dEmbedAllFonts=true',
            '-dSubsetFonts=true',
            '-dCompressFonts=true',

            // Reuse repeated images where possible.
            '-dDetectDuplicateImages=true',

            // Force predictable and fast JPEG compression for color/gray images.
            '-dAutoFilterColorImages=false',
            '-dAutoFilterGrayImages=false',
            '-dColorImageFilter=/DCTEncode',
            '-dGrayImageFilter=/DCTEncode',

            // Downsampling.
            '-dDownsampleColorImages=true',
            '-dDownsampleGrayImages=true',
            '-dDownsampleMonoImages=true',

            '-dColorImageDownsampleType=/Bicubic',
            '-dGrayImageDownsampleType=/Bicubic',
            '-dMonoImageDownsampleType=/Subsample',

            '-dColorImageResolution=' . $imageResolution,
            '-dGrayImageResolution=' . $imageResolution,
            '-dMonoImageResolution=' . $monoResolution,

            // Do not repeatedly downsample images already near the target DPI.
            '-dColorImageDownsampleThreshold=1.5',
            '-dGrayImageDownsampleThreshold=1.5',
            '-dMonoImageDownsampleThreshold=1.5',

            '-dJPEGQ=' . $jpegQuality,

            // Faster PDF writing.
            '-dFastWebView=false',
            '-dOptimize=false',

            '-sOutputFile=' . $outputPath,
            $inputPath,
        ];

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->setIdleTimeout(null);

        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim(
                $process->getErrorOutput()
                    ?: $process->getOutput()
            );

            throw new RuntimeException(
                $message !== ''
                    ? 'Ghostscript failed: ' . $message
                    : 'Ghostscript failed without an error message.'
            );
        }

        clearstatcache(true, $outputPath);

        if (
            ! is_file($outputPath)
            || ! is_readable($outputPath)
            || (int) filesize($outputPath) <= 0
        ) {
            throw new RuntimeException(
                'Ghostscript did not create a valid output PDF.'
            );
        }
    }
}
