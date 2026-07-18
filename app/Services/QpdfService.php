<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class QpdfService
{
    private ?string $binaryPath = null;

    public function __construct()
    {
        $this->binaryPath = config('pdf-compressor.qpdf_path');
    }

    public function isAvailable(): bool
    {
        return $this->findBinary() !== null;
    }

    public function getBinaryPath(): ?string
    {
        return $this->findBinary();
    }

    public function optimize(string $inputPath, string $outputPath, int $timeout = 120): void
    {
        $binary = $this->findBinary();

        if ($binary === null) {
            throw new RuntimeException('qpdf is not installed or not found on this system.');
        }

        $args = [
            $binary,
            '--linearize',
            '--compress-streams=y',
            '--decode-level=specialized',
            '--replace-input',
        ];

        $tempOutput = $outputPath.'.qpdf_tmp';

        $args[] = $inputPath;
        $args[] = $tempOutput;

        $result = Process::timeout($timeout)->run($args);

        if (! $result->successful()) {
            if (file_exists($tempOutput)) {
                unlink($tempOutput);
            }

            $error = $result->errorOutput() ?: $result->output();

            throw new RuntimeException('qpdf optimization failed: '.trim($error));
        }

        if (! file_exists($tempOutput) || filesize($tempOutput) === 0) {
            throw new RuntimeException('qpdf produced no output file.');
        }

        rename($tempOutput, $outputPath);
    }

    private function findBinary(): ?string
    {
        if ($this->binaryPath && is_executable($this->binaryPath)) {
            return $this->binaryPath;
        }

        $candidates = [
            'qpdf',
            'C:\\Program Files\\qpdf\\bin\\qpdf.exe',
            '/usr/bin/qpdf',
            '/usr/local/bin/qpdf',
            '/opt/homebrew/bin/qpdf',
        ];

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                $this->binaryPath = $candidate;

                return $candidate;
            }
        }

        $result = Process::run(['which', 'qpdf']);

        if ($result->successful()) {
            $path = trim($result->output());

            if (is_executable($path)) {
                $this->binaryPath = $path;

                return $path;
            }
        }

        return null;
    }
}
