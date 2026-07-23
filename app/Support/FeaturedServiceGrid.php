<?php

namespace App\Support;

class FeaturedServiceGrid
{
    /**
     * Each visual style owns its exact service count.
     * This keeps the admin preview and the public homepage identical.
     */
    public const STYLES = [
        'original_bento' => [
            'label' => 'Original Techwave',
            'description' => 'Your original two-row layout with alternating compact and wide cards.',
            'icon' => 'view_quilt',
            'count' => 4,
        ],
        'analytics_bento' => [
            'label' => 'Analytics Bento',
            'description' => 'One tall feature card with two stacked cards, matching your analytics reference.',
            'icon' => 'monitoring',
            'count' => 3,
        ],
        'finance_panel' => [
            'label' => 'Finance Panel',
            'description' => 'Three cards on top and two wide cards below, matching your finance reference.',
            'icon' => 'account_balance_wallet',
            'count' => 5,
        ],
        'cloud_stack' => [
            'label' => 'Cloud Stack',
            'description' => 'Six cards arranged 6/3/3 on top and 3/3/6 below.',
            'icon' => 'cloud',
            'count' => 6,
        ],
        'notification_bento' => [
            'label' => 'Notification Bento',
            'description' => 'Three cards on top and two wider notification panels below.',
            'icon' => 'notifications_active',
            'count' => 5,
        ],
        'stats_board' => [
            'label' => 'Stats Board',
            'description' => 'A seven-card KPI dashboard inspired by your final reference image.',
            'icon' => 'bar_chart',
            'count' => 7,
        ],
    ];

    public static function normalizeStyle(?string $style): string
    {
        return array_key_exists((string) $style, self::STYLES)
            ? (string) $style
            : 'original_bento';
    }

    public static function countForStyle(?string $style): int
    {
        $style = self::normalizeStyle($style);

        return (int) self::STYLES[$style]['count'];
    }

    /**
     * Kept for compatibility with existing calls.
     */
    public static function normalizeCount(int $count): int
    {
        return in_array($count, [3, 4, 5, 6, 7], true)
            ? $count
            : 4;
    }

    public static function gridClass(
        string $style,
        int $count = 0,
        bool $preview = false,
    ): string {
        $style = self::normalizeStyle($style);

        if ($preview) {
            return match ($style) {
                'stats_board' => 'grid grid-cols-12 auto-rows-[48px] gap-2',
                'original_bento' => 'grid grid-cols-12 auto-rows-[78px] gap-2',
                'analytics_bento' => 'grid grid-cols-12 auto-rows-[92px] gap-2',
                'finance_panel' => 'grid grid-cols-12 auto-rows-[82px] gap-2',
                'cloud_stack' => 'grid grid-cols-12 auto-rows-[82px] gap-2',
                'notification_bento' => 'grid grid-cols-12 auto-rows-[86px] gap-2',
            };
        }

        return match ($style) {
            'stats_board' => 'grid w-full grid-cols-1 gap-5 md:grid-cols-12 md:auto-rows-[165px] lg:auto-rows-[190px]',
            'original_bento' => 'grid w-full grid-cols-1 gap-5 md:grid-cols-12 md:auto-rows-[310px] lg:auto-rows-[360px]',
            'analytics_bento' => 'grid w-full grid-cols-1 gap-5 md:grid-cols-12 md:auto-rows-[270px] lg:auto-rows-[310px]',
            'finance_panel' => 'grid w-full grid-cols-1 gap-5 md:grid-cols-12 md:auto-rows-[265px] lg:auto-rows-[300px]',
            'cloud_stack' => 'grid w-full grid-cols-1 gap-5 md:grid-cols-12 md:auto-rows-[265px] lg:auto-rows-[300px]',
            'notification_bento' => 'grid w-full grid-cols-1 gap-5 md:grid-cols-12 md:auto-rows-[280px] lg:auto-rows-[320px]',
        };
    }

    public static function cardClass(
        string $style,
        int $index,
        int $count = 0,
        bool $preview = false,
    ): string {
        $style = self::normalizeStyle($style);
        $classes = self::layoutMap($style)[$index] ?? 'col-span-12';

        if ($preview) {
            return $classes;
        }

        return self::toResponsiveClasses($classes);
    }

    public static function isLarge(
        string $style,
        int $index,
        int $count = 0,
    ): bool {
        $class = self::cardClass($style, $index, $count, true);

        return str_contains($class, 'row-span-2')
            || str_contains($class, 'row-span-3')
            || str_contains($class, 'col-span-12')
            || str_contains($class, 'col-span-8')
            || str_contains($class, 'col-span-7')
            || str_contains($class, 'col-span-6')
            || str_contains($class, '[grid-area:');
    }

    public static function isCompactStyle(string $style): bool
    {
        return false;
    }

    public static function isDashboardStyle(string $style): bool
    {
        return self::normalizeStyle($style) !== 'original_bento';
    }

    private static function layoutMap(string $style): array
    {
        return match ($style) {
            // Original Techwave: compact + wide / wide + compact.
            'original_bento' => [
                'col-span-4',
                'col-span-8',
                'col-span-8',
                'col-span-4',
            ],

            // Reference 1: one tall left card, two stacked right cards.
            'analytics_bento' => [
                'col-span-6 row-span-2',
                'col-span-6',
                'col-span-6',
            ],

            // Reference 2: three cards on top, two wide cards below.
            'finance_panel' => [
                'col-span-4',
                'col-span-4',
                'col-span-4',
                'col-span-6',
                'col-span-6',
            ],

            // Reference 3: 6/3/3 then 3/3/6.
            'cloud_stack' => [
                'col-span-6',
                'col-span-3',
                'col-span-3',
                'col-span-3',
                'col-span-3',
                'col-span-6',
            ],

            // Reference 4: 4/5/3 then 5/7.
            'notification_bento' => [
                'col-span-4',
                'col-span-5',
                'col-span-3',
                'col-span-5',
                'col-span-7',
            ],

            // Reference 5: seven-card KPI dashboard.
            'stats_board' => [
                '[grid-area:1/1/4/5]',
                '[grid-area:1/5/2/13]',
                '[grid-area:2/5/4/9]',
                '[grid-area:2/9/5/13]',
                '[grid-area:4/1/6/5]',
                '[grid-area:4/5/6/9]',
                '[grid-area:5/9/6/13]',
            ],
        };
    }

    private static function toResponsiveClasses(string $classes): string
    {
        return collect(explode(' ', $classes))
            ->map(fn(string $class): string => match ($class) {
                'col-span-3' => 'md:col-span-3',
                'col-span-4' => 'md:col-span-4',
                'col-span-5' => 'md:col-span-5',
                'col-span-6' => 'md:col-span-6',
                'col-span-7' => 'md:col-span-7',
                'col-span-8' => 'md:col-span-8',
                'col-span-12' => 'md:col-span-12',
                'row-span-2' => 'md:row-span-2',
                'row-span-3' => 'md:row-span-3',
                '[grid-area:1/1/4/5]' => 'md:[grid-area:1/1/4/5]',
                '[grid-area:1/5/2/13]' => 'md:[grid-area:1/5/2/13]',
                '[grid-area:2/5/4/9]' => 'md:[grid-area:2/5/4/9]',
                '[grid-area:2/9/5/13]' => 'md:[grid-area:2/9/5/13]',
                '[grid-area:4/1/6/5]' => 'md:[grid-area:4/1/6/5]',
                '[grid-area:4/5/6/9]' => 'md:[grid-area:4/5/6/9]',
                '[grid-area:5/9/6/13]' => 'md:[grid-area:5/9/6/13]',
                default => '',
            })
            ->filter()
            ->implode(' ');
    }
}
