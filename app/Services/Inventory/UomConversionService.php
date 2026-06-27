<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UomConversionService
{
    /** @var array<string, float|null> */
    private array $cache = [];

    public function convert(int $schoolId, float $quantity, int $fromUomId, int $toUomId): float
    {
        if ($fromUomId === $toUomId || $quantity == 0.0) {
            return $quantity;
        }

        $factor = $this->factor($schoolId, $fromUomId, $toUomId);

        return $factor !== null ? round($quantity * $factor, 6) : $quantity;
    }

    public function factor(int $schoolId, int $fromUomId, int $toUomId): ?float
    {
        $key = "{$schoolId}:{$fromUomId}:{$toUomId}";
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if (! Schema::hasTable('inv_uom_conversions')) {
            return $this->cache[$key] = null;
        }

        $direct = DB::table('inv_uom_conversions')
            ->where('school_id', $schoolId)
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId)
            ->value('factor');

        if ($direct !== null) {
            return $this->cache[$key] = (float) $direct;
        }

        $reverse = DB::table('inv_uom_conversions')
            ->where('school_id', $schoolId)
            ->where('from_uom_id', $toUomId)
            ->where('to_uom_id', $fromUomId)
            ->value('factor');

        if ($reverse !== null && (float) $reverse != 0.0) {
            return $this->cache[$key] = 1 / (float) $reverse;
        }

        return $this->cache[$key] = null;
    }

    public function symbol(int $uomId): ?string
    {
        if (! Schema::hasTable('inv_uoms') || $uomId <= 0) {
            return null;
        }

        return DB::table('inv_uoms')->where('id', $uomId)->value('symbol');
    }
}
