<?php

namespace App\Jobs;

use App\Models\CompressedPdf;
use App\Services\PdfCompressionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CompressPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
     * One attempt prevents a 3-minute compression from being repeated.
     */
    public int $timeout = 210;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $pdfId
    ) {
        $this->onQueue('pdf-compression');
    }

    public function handle(
        PdfCompressionService $service
    ): void {
        $pdf = CompressedPdf::find($this->pdfId);

        if (! $pdf) {
            return;
        }

        if ($pdf->isExpired()) {
            $pdf->deleteAllFiles();
            $pdf->delete();

            return;
        }

        /*
         * Avoid processing the same completed record again.
         */
        if ($pdf->isCompleted()) {
            return;
        }

        $pdf->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        $service->compress($pdf);
    }

    public function failed(?Throwable $exception): void
    {
        $pdf = CompressedPdf::find($this->pdfId);

        if (! $pdf) {
            return;
        }

        $pdf->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage()
                ?? 'PDF compression job failed.',
            'processed_at' => now(),
        ]);
    }
}
