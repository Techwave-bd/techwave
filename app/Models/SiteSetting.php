<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'site_name',
    'logo',
    'favicon',
    'email',
    'phone',
    'location',
    'map_embed_link',
    'facebook_url',
    'linkedin_url',
    'twitter_url',
    'instagram_url',
    'youtube_url',
    'github_url',
    'whatsapp_url',
    'terms_conditions',
    'privacy_policy',
])]
class SiteSetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate([
            'id' => 1,
        ], [
            'site_name' => config('app.name'),
        ]);
    }
}
