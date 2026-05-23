<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSerialSpecialMovement extends Model
{
    public const TYPE_SCRAP_LOSS = 'scrap_loss';
    public const TYPE_SERIAL_CORRECTION = 'serial_correction';
    public const TYPE_INTERNAL_RELOCATION = 'internal_relocation';
    public const TYPE_EXTERNAL_RELOCATION_OUT = 'external_relocation_out';
    public const TYPE_EXTERNAL_RELOCATION_IN = 'external_relocation_in';
    public const TYPE_DUPLICATE_CONFLICT = 'duplicate_conflict';

    protected $fillable = [
        'company_id',
        'stock_serial_number_id',
        'product_id',
        'product_variant_id',
        'lot_id',
        'movement_type',
        'status',
        'serial_number_before',
        'serial_number_after',
        'source_warehouse_id',
        'source_location_id',
        'destination_warehouse_id',
        'destination_location_id',
        'reason',
        'reference',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_SCRAP_LOSS => 'Baja por merma / destrucción',
            self::TYPE_SERIAL_CORRECTION => 'Corrección de número de serie',
            self::TYPE_INTERNAL_RELOCATION => 'Reubicación interna',
            self::TYPE_EXTERNAL_RELOCATION_OUT => 'Baja por reubicación externa',
            self::TYPE_EXTERNAL_RELOCATION_IN => 'Alta por reubicación externa',
            self::TYPE_DUPLICATE_CONFLICT => 'Marcar duplicado / conflicto',
        ];
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(StockSerialNumber::class, 'stock_serial_number_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
