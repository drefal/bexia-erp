<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'invoice_reference',
        'sale_reference',
        'sale_id',
        'lot_number',
        'serial_number',
        'product_name',
        'product_id',
        'folio',
        'subject',
        'description',
        'channel',
        'case_type',
        'attention_route',
        'classified_at',
        'classified_by',
        'classification_notes',
        'non_repair_type',
        'resolution_type',
        'resolution_notes',
        'priority',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
        'assigned_team',
        'assigned_user_id',
        'assigned_employee_id',
        'assigned_at',
        'assigned_by',
        'first_response_at',
        'due_at',
        'closed_at',
        'closed_by',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'classified_at' => 'datetime',
        'assigned_at' => 'datetime',
        'first_response_at' => 'datetime',
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = [
        'nuevo' => 'Nuevo',
        'asignado' => 'Asignado',
        'en_revision' => 'En revision',
        'esperando_cliente' => 'Esperando cliente',
        'esperando_producto' => 'Esperando producto',
        'producto_recibido' => 'Producto recibido',
        'en_diagnostico' => 'En diagnostico',
        'cotizacion_pendiente' => 'Cotizacion pendiente',
        'cotizacion_enviada' => 'Cotizacion enviada',
        'aprobado' => 'Aprobado',
        'en_reparacion' => 'En reparacion',
        'esperando_refaccion' => 'Esperando refaccion',
        'en_pruebas' => 'En pruebas',
        'listo_entrega' => 'Listo para entrega',
        'resuelto' => 'Resuelto',
        'entregado' => 'Entregado',
        'cerrado' => 'Cerrado',
        'rechazado' => 'Rechazado',
        'cancelado' => 'Cancelado',
    ];

    public const PRIORITIES = [
        'baja' => 'Baja',
        'media' => 'Normal',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    public const OPERATIONAL_PRIORITIES = [
        'media' => 'Normal',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    public const CHANNELS = [
        'manual' => 'Presencial',
        'whatsapp' => 'WhatsApp',
        'correo' => 'Correo',
        'telefono' => 'Telefono',
        'portal' => 'Portal',
        'otro' => 'Otro',
    ];

    public const OPERATIONAL_CHANNELS = [
        'manual' => 'Presencial',
        'whatsapp' => 'WhatsApp',
        'correo' => 'Correo',
        'telefono' => 'Telefono',
        'otro' => 'Otro',
    ];

    public const CASE_TYPES = [
        'general' => 'General',
        'duda' => 'Duda',
        'queja' => 'Queja',
        'garantia' => 'Garantia',
        'devolucion' => 'Devolucion',
        'reparacion' => 'Reparacion',
        'instalacion' => 'Instalacion',
        'mantenimiento' => 'Mantenimiento',
        'refaccion' => 'Refaccion',
        'soporte_interno' => 'Soporte interno',
    ];

    public const ATTENTION_ROUTES = [
        'repair' => 'Revisión / reparación de equipo',
        'non_repair' => 'Respuesta / gestión',
    ];

    /*
     * BEXIA_ATC_REPAIR_ARRIVAL_METHODS_V5_83_4C1
     *
     * El equipo todavía NO está recibido en esta etapa.
     * Sólo describe cómo está previsto que llegue.
     */
    public const REPAIR_ARRIVAL_METHODS = [
        'cliente_entrega' => 'Cliente lo lleva',
        'recoleccion_chofer' => 'Recolección por chofer',
        'ya_en_sucursal' => 'Ya está en sucursal',
        'transferencia_interna' => 'Transferencia interna',
        'otro' => 'Otro',
    ];

    public const NON_REPAIR_TYPES = [
        'asesoria' => 'Asesoría',
        'soporte_tecnico' => 'Soporte técnico',
        'configuracion' => 'Configuración',
        'instalacion' => 'Instalación',
        'diagnostico_sin_reparacion' => 'Diagnóstico sin reparación',
        'garantia_administrativa' => 'Garantía administrativa',
        'reclamacion' => 'Reclamación',
        'devolucion' => 'Devolución',
        'seguimiento_venta' => 'Seguimiento de venta',
        'seguimiento_factura' => 'Seguimiento de factura',
        'documentos' => 'Solicitud de documentos',
        'visita_tecnica' => 'Visita técnica',
        'otro' => 'Otro servicio',
    ];


    public const VISIBLE_STATUSES = [
        'nuevo' => 'Nuevo',
        'en_atencion' => 'En atención',
        'resuelto' => 'Resuelto',
        'cerrado' => 'Cerrado',
    ];

    public const TERMINAL_STATUSES = [
        'cerrado',
        'rechazado',
        'cancelado',
    ];

    public function visibleStatusKey(): string
    {
        $status = (string) ($this->status ?: 'nuevo');

        if ($status === 'nuevo') {
            return 'nuevo';
        }

        if (in_array($status, ['resuelto', 'entregado'], true)) {
            return 'resuelto';
        }

        if (in_array($status, self::TERMINAL_STATUSES, true)) {
            return 'cerrado';
        }

        return 'en_atencion';
    }

    public function visibleStatusLabel(): string
    {
        return self::VISIBLE_STATUSES[
            $this->visibleStatusKey()
        ] ?? 'En atención';
    }

    public function operationalSubstatusLabel(): string
    {
        $status = (string) ($this->status ?: 'nuevo');

        if ($status === 'nuevo') {
            return 'Pendiente de atención';
        }

        if ($status === 'resuelto') {
            return 'Resuelto';
        }

        if ($status === 'entregado') {
            return 'Equipo entregado';
        }

        return self::STATUSES[$status]
            ?? ucfirst(str_replace('_', ' ', $status));
    }

    public function attentionTypeLabel(): string
    {
        return self::ATTENTION_ROUTES[
            (string) ($this->attention_route ?? '')
        ] ?? 'Pendiente de definir';
    }

    public function nextActionLabel(): string
    {
        $status = (string) ($this->status ?: 'nuevo');

        if ($this->visibleStatusKey() === 'cerrado') {
            return 'Ver expediente';
        }

        if (in_array($status, ['resuelto', 'entregado'], true)) {
            return 'Cerrar';
        }

        if (blank($this->attention_route)) {
            return 'Atender';
        }

        if ((string) $this->attention_route === 'non_repair') {
            return 'Continuar';
        }

        return match ($status) {
            'esperando_producto' => 'Recibir equipo',
            'producto_recibido',
            'en_diagnostico' => 'Diagnosticar',
            'en_reparacion' => 'Continuar reparación',
            'esperando_refaccion' => 'Continuar',
            'en_pruebas' => 'Registrar pruebas',
            'listo_entrega' => 'Entregar',
            default => 'Continuar',
        };
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceCase $case) {
            if (blank($case->folio)) {
                $case->folio = static::nextFolio();
            }

            if (blank($case->created_by) && auth()->check()) {
                $case->created_by = auth()->id();
            }
        });
    }

    public static function nextFolio(): string
    {
        $prefix = 'AS-' . now()->format('Ym') . '-';
        $next = ((int) static::withTrashed()
            ->where('folio', 'like', $prefix . '%')
            ->count()) + 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function repairOrders(): HasMany
    {
        return $this->hasMany(RepairOrder::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceCaseEvent::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceAttachment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RepairOrderApproval::class);
    }
}
