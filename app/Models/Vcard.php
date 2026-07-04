<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'name',
    'slug',

    'first_name',
    'last_name',
    'company',
    'job_title',
    'email',
    'phone_work',
    'phone_mobile',
    'website',
    'street',
    'city',
    'state',
    'zip',
    'country',
    'note',

    'facebook',
    'linkedin',
    'twitter',
    'instagram',

    'theme',
    'font_family',
    'accent_color',
    'bg_color',
    'text_color',
    'card_bg',
    'card_style',

    'banner_path',
    'profile_path',

    'qr_logo_path',
    'qr_has_logo',

    'is_active',

    'phones',
    'emails',
    'sites',
    'location_input_type',
    'location_label',
    'location_icon',
    'location_search',
    'location_url',
    'latitude',
    'longitude',
    'companies',
    'social_links',
    'show_social_name',
    'show_social_as_cards',
    'social_icon_mode',
    'social_custom_icons',
    'loading_path',
    'loading_screen_enabled',
    'loading_time',
    'qr_logo_mode',
    'preview_section_order',
    'contact_button_text',
    'contact_button_position',
    'button_text_color',
    'avatar_ring_enabled',
    'avatar_ring_color',
    'avatar_ring_width',
    'floating_button_ring_enabled',
    'floating_button_ring_color',
    'floating_button_ring_width',
    'floating_button_ring_shape',
    'floating_button_placement',
    'floating_button_border_radius',
    'avatar_border_radius',
    'field_border_color',
    'field_border_radius',
    'field_border_width',
    'field_border_style',
    'field_shadow',
])]
class Vcard extends Model
{
    protected $casts = [
        'qr_has_logo' => 'boolean',
        'is_active' => 'boolean',

        'phones' => 'array',
        'emails' => 'array',
        'sites' => 'array',
        'companies' => 'array',
        'social_links' => 'array',
        'social_icon_mode' => 'array',
        'social_custom_icons' => 'array',
        'preview_section_order' => 'array',

        'show_social_name' => 'boolean',
        'show_social_as_cards' => 'boolean',
        'loading_screen_enabled' => 'boolean',
        'avatar_ring_enabled' => 'boolean',
        'floating_button_ring_enabled' => 'boolean',

        'loading_time' => 'integer',
        'avatar_ring_width' => 'integer',
        'floating_button_ring_width' => 'integer',
        'floating_button_border_radius' => 'integer',
        'avatar_border_radius' => 'integer',
        'field_border_radius' => 'integer',
        'field_border_width' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scans()
    {
        return $this->hasMany(VcardScan::class);
    }

    public function getFullNameAttribute()
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: 'Your Name';
    }
}
