<?php

namespace App\Policies;

use App\Models\CompressedPdf;
use App\Models\User;

class CompressedPdfPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(
        ?User $user,
        CompressedPdf $compressedPdf
    ): bool {
        return $this->ownsRecord(
            $user,
            $compressedPdf
        );
    }

    public function download(
        ?User $user,
        CompressedPdf $compressedPdf
    ): bool {
        if ($compressedPdf->isExpired()) {
            return false;
        }

        return $this->ownsRecord(
            $user,
            $compressedPdf
        );
    }

    public function delete(
        ?User $user,
        CompressedPdf $compressedPdf
    ): bool {
        return $this->ownsRecord(
            $user,
            $compressedPdf
        );
    }

    private function ownsRecord(
        ?User $user,
        CompressedPdf $compressedPdf
    ): bool {
        if ($compressedPdf->user_id !== null) {
            return $user !== null
                && $user->id === $compressedPdf->user_id;
        }

        if ($compressedPdf->session_id === null) {
            return false;
        }

        return hash_equals(
            (string) $compressedPdf->session_id,
            (string) session()->getId()
        );
    }
}
