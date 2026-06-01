<?php

namespace App\Http\Controllers;

use App\Models\ToolCategory;
use App\Models\UserBgRemovedImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BgRemovedImageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:png', 'max:10240'],
            'original_name' => ['required', 'string', 'max:255'],
            'original_size' => ['required', 'integer', 'min:0'],
        ]);

        $category = ToolCategory::query()->where('slug', 'image')->first();

        if (! $category || ! auth()->user()->hasActiveToolSubscription($category)) {
            abort(403);
        }

        $file = $request->file('image');
        $userId = auth()->id();
        $uniqueId = Str::random(20);
        $path = "bg-removed/users/{$userId}/{$uniqueId}.png";

        Storage::disk('public')->put($path, (string) file_get_contents($file->getRealPath()));

        UserBgRemovedImage::query()->create([
            'user_id' => $userId,
            'tool_category_id' => $category->id,
            'original_name' => $request->input('original_name'),
            'result_path' => $path,
            'result_ext' => 'png',
            'original_size' => (int) $request->input('original_size'),
            'result_size' => $file->getSize(),
            'expires_at' => now()->addMonth(),
        ]);

        return response()->json(['success' => true]);
    }
}
