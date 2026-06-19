<?php

namespace App\Models;

use App\Support\Service\BusinessHoursCalculator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'service_case_id',
        'customer_id',
        'folio',
        'product_id',
        'sale_id',
        'invoice_id',
        'invoice_reference',
        'sale_reference',
        'product_name',
        'serial_number',
        'lot_number',
        'status',
        'repair_finished_at',
        'ready_for_delivery_at',
        'supervisor_reviewed_at',
        'supervisor_review_requested_at',
        'repair_started_at',
        'quote_approved_at',
        'quote_submitted_at',
        'workflow_stage',
        'customer_rejected_at',
        'customer_approved_at',
        'quote_notes',
        'quote_status',
        'internal_approval_document_type',
        'internal_approval_flow_id',
        'requires_customer_approval',
        'requires_internal_approval',
        'quote_total',
        'other_cost_estimate',
        'labor_cost_estimate',
        'labor_rate_source',
        'labor_hour_rate',
        'labor_hours_estimate',
        'parts_cost_estimate',
        'parts_required',
        'warranty_status',
        'warranty_expires_at',
        'received_at',
        'promised_at',
        'started_at',
        'finished_at',
        'delivered_at',
        'closed_at',
        'received_condition',
        'initial_diagnosis',
        'technical_diagnosis',
        'resolution',
        'estimated_cost',
        'actual_cost',
        'assigned_user_id',
        'assigned_employee_id',
        'assigned_at',
        'assigned_by',
        'created_by',
        'metadata',
    
        'actual_labor_hours',
        'actual_labor_cost',
        'business_hours_per_day',
        'delivered_to',
        'delivery_notes',];

    protected $casts = [
        'warranty_expires_at' => 'datetime',
        'received_at' => 'datetime',
        'promised_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'delivered_at' => 'datetime',
        'closed_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'assigned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = [
        'recibido' => 'Recibido',
        'en_diagnostico' => 'En diagnostico',
        'cotizacion_pendiente' => 'Cotizacion pendiente',
        'cotizacion_enviada' => 'Cotizacion enviada',
        'aprobado' => 'Aprobado',
        'en_reparacion' => 'En reparacion',
        'esperando_refaccion' => 'Esperando refaccion',
        'en_pruebas' => 'En pruebas',
        'listo_entrega' => 'Listo para entrega',
        'entregado' => 'Entregado',
        'cerrado' => 'Cerrado',
        'rechazado' => 'Rechazado',
        'cancelado' => 'Cancelado',
    ];

    public const WARRANTY_STATUSES = [
        'pendiente' => 'Pendiente',
        'vigente' => 'Vigente',
        'vencida' => 'Vencida',
        'aceptada' => 'Aceptada',
        'rechazada' => 'Rechazada',
        'no_aplica' => 'No aplica',
    ];

    protected static function booted(): void
    {

        static::saving(function (self $repair): void {
            $stage = (string) ($repair->workflow_stage ?? '');

            if (
                in_array($stage, ['repaired', 'supervisor_review', 'ready_for_delivery', 'delivered'], true)
                && empty($repair->repair_finished_at)
            ) {
                $repair->repair_finished_at = now();
            }

            if (! empty($repair->repair_started_at) && ! empty($repair->repair_finished_at)) {
                $actualHours = BusinessHoursCalculator::between(
                    $repair->repair_started_at,
                    $repair->repair_finished_at,
                );

                $repair->actual_labor_hours = $actualHours;

                $rate = (float) ($repair->labor_hour_rate ?? 0);
                $repair->actual_labor_cost = $rate > 0
                    ? round($actualHours * $rate, 2)
                    : null;

                $repair->business_hours_per_day = $repair->business_hours_per_day ?: 8;
            }
        });

        static::creating(function (RepairOrder $repair) {
            if (blank($repair->folio)) {
                $repair->folio = static::nextFolio();
            }

            if (blank($repair->created_by) && auth()->check()) {
                $repair->created_by = auth()->id();
            }
        });
    }

    public static function nextFolio(): string
    {
        $prefix = 'REP-' . now()->format('Ym') . '-';
        $next = ((int) static::withTrashed()
            ->where('folio', 'like', $prefix . '%')
            ->count()) + 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceCaseEvent::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RepairOrderApproval::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(RepairOrderPart::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceAttachment::class);
    }
}
