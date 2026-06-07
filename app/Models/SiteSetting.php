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
    'bkash_number',
    'bkash_instructions',
])]
class SiteSetting extends Model
{
    protected static ?self $currentSetting = null;

    public static function current(): self
    {
        if (static::$currentSetting instanceof self) {
            return static::$currentSetting;
        }

        static::$currentSetting = static::query()->firstOrCreate(
            ['id' => 1],
            ['site_name' => config('app.name')]
        );

        return static::$currentSetting;
    }

    public static function forgetCurrent(): void
    {
        static::$currentSetting = null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (blank($this->logo)) {
            return null;
        }

        if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://')) {
            return $this->logo;
        }

        return asset('storage/' . $this->logo);
    }

    public function getFaviconUrlAttribute(): string
    {
        if (filled($this->favicon)) {
            if (str_starts_with($this->favicon, 'http://') || str_starts_with($this->favicon, 'https://')) {
                return $this->favicon;
            }

            return asset('storage/' . $this->favicon);
        }

        return asset('assets/images/logo/logo.png');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCurrent());
        static::deleted(fn () => static::forgetCurrent());
    }
}