<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StockMovement extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'stock_operation_type_id',
        'source_location_id',
        'destination_location_id',
        'reference',
        'movement_at',
        'status',
        'origin_document',
        'contact_id',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'movement_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (StockMovement $movement): void {
            if (! $movement->movement_at) {
                $movement->movement_at = now();
            }

            if (! $movement->status) {
                $movement->status = 'draft';
            }

            if (! $movement->created_by && auth()->id()) {
                $movement->created_by = auth()->id();
            }

            if (! $movement->reference) {
                $movement->reference = static::nextReferenceForMovement($movement);
            }
        });
    }

    public function operationType(): BelongsTo
    {
        return $this->belongsTo(StockOperationType::class, 'stock_operation_type_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public static function nextReferenceForMovement(StockMovement $movement): string
    {
        $operation = null;

        if ($movement->stock_operation_type_id && Schema::hasTable('stock_operation_types')) {
            $operation = DB::table('stock_operation_types')
                ->where('id', $movement->stock_operation_type_id)
                ->first();
        }

        $prefixCode = $operation?->reference_prefix ?: static::operationKindToPrefix($operation?->operation_kind ?? null);

        $locationId = static::referenceLocationId(
            operationKind: $operation?->operation_kind ?? null,
            sourceLocationId: $movement->source_location_id ? (int) $movement->source_location_id : null,
            destinationLocationId: $movement->destination_location_id ? (int) $movement->destination_location_id : null,
        );

        $locationCode = static::locationCode($locationId);
        $prefixCode = static::cleanCode($prefixCode) ?: 'MOV';

        $prefix = $locationCode . '/' . $prefixCode . '/';

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

    protected static function operationKindToPrefix(?string $kind): string
    {
        return match ($kind) {
            'receipt' => 'IN',
            'delivery' => 'OUT',
            'internal_transfer' => 'INT',
            'manufacturing' => 'FAB',
            'inventory_adjustment' => 'AJU',
            default => 'MOV',
        };
    }

    protected static function referenceLocationId(?string $operationKind, ?int $sourceLocationId, ?int $destinationLocationId): ?int
    {
        if ($operationKind === 'receipt') {
            return $destinationLocationId ?: $sourceLocationId;
        }

        return $sourceLocationId ?: $destinationLocationId;
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
