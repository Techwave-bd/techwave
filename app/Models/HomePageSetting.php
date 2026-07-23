<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomePageSetting extends Model
{
    protected $fillable = [
        'hero',
        'services',
        'about',
    ];

    protected function casts(): array
    {
        return [
            'hero' => 'array',
            'services' => 'array',
            'about' => 'array',
        ];
    }

    public static function defaults(): array
    {
        return [
            'hero' => [
                'enabled' => true,
                'title' => 'Smarter IT for',
                'highlighted_title' => 'Smart Businesses.',
                'description' => 'We deliver secure, cloud-ready, and future-proof technology solutions tailored to your business needs.',
                'primary_button_text' => 'Get Started',
                'primary_button_url' => '#services',
                'secondary_button_text' => 'View All Services',
                'secondary_button_url' => '/services',
                'main_image' => null,
                'top_left_image' => null,
                'top_right_image' => null,
                'bottom_left_image' => null,
                'bottom_right_image' => null,
                'top_left_title' => 'Creative Strategy',
                'top_left_description' => 'Strong ideas. Clear direction.',
                'top_right_title' => 'Business Planning',
                'top_right_description' => 'Built around real growth goals.',
                'trusted_title' => 'Trusted by Global Innovators',
                'show_trusted_logos' => true,
            ],

            'services' => [
                'enabled' => true,
                'badge' => 'Our Services',
                'title' => 'Precision-Engineered',
                'highlighted_title' => 'Services',
                'description' => 'Elite digital solutions designed to work in harmony with your growth strategy.',
                'grid_count' => 4,
                'layout_style' => 'original_bento',
                'button_text' => 'Show All Services',
                'button_url' => '/services',
            ],

            'about' => [
                'enabled' => true,
                'badge' => 'Get To Know Us',
                'title' => 'We build modern digital experiences',
                'highlighted_title' => 'that move businesses forward',
                'description' => "Our team blends strategy, design, and technology to deliver scalable solutions for fast-growing brands. From websites and SaaS platforms to automation and digital transformation, we prioritize performance, usability, and long-term value.\n\nWith 12+ years of experience, we offer end-to-end IT services, including Virtual IT Department, Web Development, Cybersecurity, Email, Hosting, CCTV, Networking, and Digital Marketing.",
                'stats' => [
                    ['value' => '120+', 'label' => 'Projects delivered'],
                    ['value' => '98%', 'label' => 'Client satisfaction'],
                    ['value' => '24/7', 'label' => 'Support availability'],
                ],
            ],
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            static::defaults(),
        );
    }

    public static function resolved(): array
    {
        $defaults = static::defaults();
        $settings = static::query()->first();

        if (! $settings) {
            return $defaults;
        }

        return [
            'hero' => array_replace_recursive($defaults['hero'], $settings->hero ?? []),
            'services' => array_replace_recursive($defaults['services'], $settings->services ?? []),
            'about' => array_replace_recursive($defaults['about'], $settings->about ?? []),
        ];
    }
}
