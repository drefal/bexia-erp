<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StockAdjustment extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'location_id',
        'reference',
        'adjustment_date',
        'adjustment_at',
        'status',
        'reason',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'adjustment_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (StockAdjustment $adjustment): void {
            if (! $adjustment->adjustment_at) {
                if ($adjustment->adjustment_date) {
                    $adjustment->adjustment_at = Carbon::parse($adjustment->adjustment_date)->startOfDay();
                } else {
                    $adjustment->adjustment_at = now();
                }
            }

            $adjustment->adjustment_date = Carbon::parse($adjustment->adjustment_at)->toDateString();

            if (! $adjustment->status) {
                $adjustment->status = 'draft';
            }

            if (! $adjustment->created_by && auth()->id()) {
                $adjustment->created_by = auth()->id();
            }

            if (! $adjustment->reference) {
                $adjustment->reference = static::nextReferenceForLocationOperation(
                    $adjustment->location_id ? (int) $adjustment->location_id : null,
                    'AJU'
                );
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public static function nextReferenceForLocationOperation(?int $locationId, string $operationCode): string
    {
        $locationCode = static::locationCode($locationId);
        $operationCode = static::cleanCode($operationCode) ?: 'AJU';

        $prefix = $locationCode . '/' . $operationCode . '/';

        $lastReference = static::query()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $nextNumber = 1;

        if ($lastReference && preg_match('/\/(\d+)$/', (string) $lastReference, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    protected static function locationCode(?int $locationId): string
    {
        if (! $locationId || ! Schema::hasTable('stock_locations')) {
            return 'SIN-UBICACION';
        }

        $location = DB::table('stock_locations')
            ->where('id', $locationId)
            ->first();

        if (! $location) {
            return 'SIN-UBICACION';
        }

        $code = '';

        if (Schema::hasColumn('stock_locations', 'code')) {
            $code = trim((string) ($location->code ?? ''));
        }

        if ($code === '' && Schema::hasColumn('stock_locations', 'name')) {
            $code = trim((string) ($location->name ?? ''));
        }

        return static::cleanCode($code) ?: 'SIN-UBICACION';
    }

    protected static function cleanCode(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value;
    }
}
