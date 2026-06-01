<?php

namespace App\Http\Controllers;

use App\Models\UserCompressedImage;
use Illuminate\Support\Facades\Storage;

class CompressedImageController extends Controller
{
    public function show(UserCompressedImage $image)
    {
        abort_unless(auth()->check(), 403);

        abort_unless($image->user_id === auth()->id(), 403);

        abort_if($image->isExpired(), 404);

        if (! Storage::disk('public')->exists($image->compressed_path)) {
            abort(404);
        }

        $response = Storage::disk('public')->response($image->compressed_path);
        $response->headers->set('Content-Disposition', 'inline');

        return $response;
    }
}
