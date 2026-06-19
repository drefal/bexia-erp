<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderApproval extends Model
{
    protected $fillable = [
        'company_id',
        'service_case_id',
        'repair_order_id',
        'approval_type',
        'status',
        'requested_reason',
        'requested_by',
        'requested_at',
        'decided_by',
        'decided_at',
        'amount',
        'reason',
        'comments',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public const STATUSES = [
        'pendiente' => 'Pendiente',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
        'cancelado' => 'Cancelado',
    ];

    public const TYPES = [
        'aceptar_garantia' => 'Aceptar garantia',
        'rechazar_garantia' => 'Rechazar garantia',
        'cotizacion_mayor' => 'Cotizacion mayor',
        'usar_refaccion_cara' => 'Usar refaccion cara',
        'reemplazar_producto' => 'Reemplazar producto',
        'nota_credito' => 'Nota de credito',
        'descuento_reparacion' => 'Descuento en reparacion',
        'cerrar_no_procede' => 'Cerrar como no procede',
        'entrega_sin_cobro' => 'Entrega sin cobro',
        'cambiar_diagnostico' => 'Cambiar diagnostico',
        'reabrir_cerrado' => 'Reabrir cerrado',
    ];

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}
