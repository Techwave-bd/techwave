<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable([
    'is_enabled',
    'percentage',
    'apply_to',
    'title',
    'note',
])]
class VatSetting extends Model
{
    protected $casts = [
        'is_enabled' => 'boolean',
        'percentage' => 'decimal:2',
    ];

    public const TYPE_SERVICE = 'service';
    public const TYPE_PRICING_PLAN = 'pricing_plan';
    public const TYPE_BOTH = 'both';

    public static function options(): array
    {
        return [
            self::TYPE_SERVICE => 'Services',
            self::TYPE_PRICING_PLAN => 'IT Plans',
            self::TYPE_BOTH => 'Both',
        ];
    }

    public static function ensureDefaultRecords(): void
    {
        static::query()->firstOrCreate(
            ['apply_to' => self::TYPE_SERVICE],
            [
                'title' => 'Service VAT',
                'is_enabled' => false,
                'percentage' => null,
                'note' => null,
            ]
        );

        static::query()->firstOrCreate(
            ['apply_to' => self::TYPE_PRICING_PLAN],
            [
                'title' => 'IT Plan VAT',
                'is_enabled' => false,
                'percentage' => null,
                'note' => null,
            ]
        );

        static::query()->firstOrCreate(
            ['apply_to' => self::TYPE_BOTH],
            [
                'title' => 'Global VAT',
                'is_enabled' => false,
                'percentage' => null,
                'note' => null,
            ]
        );
    }

    public static function records(): Collection
    {
        static::ensureDefaultRecords();

        return static::query()
            ->orderByRaw("
                CASE apply_to
                    WHEN 'service' THEN 1
                    WHEN 'pricing_plan' THEN 2
                    WHEN 'both' THEN 3
                    ELSE 4
                END
            ")
            ->get();
    }

    public static function forService(): ?self
    {
        static::ensureDefaultRecords();

        return static::query()
            ->where('apply_to', self::TYPE_SERVICE)
            ->where('is_enabled', true)
            ->first()
            ?? static::query()
            ->where('apply_to', self::TYPE_BOTH)
            ->where('is_enabled', true)
            ->first();
    }

    public static function forPricingPlan(): ?self
    {
        static::ensureDefaultRecords();

        return static::query()
            ->where('apply_to', self::TYPE_PRICING_PLAN)
            ->where('is_enabled', true)
            ->first()
            ?? static::query()
            ->where('apply_to', self::TYPE_BOTH)
            ->where('is_enabled', true)
            ->first();
    }

    public function calculate(float $amount): float
    {
        if (! $this->is_enabled || ! $this->percentage) {
            return 0;
        }

        return round(($amount * (float) $this->percentage) / 100, 2);
    }

    public function totalWithVat(float $amount): float
    {
        return round($amount + $this->calculate($amount), 2);
    }
}
