<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'company_id',
        'number',
        'status',
        'supplier_id',
        'supplier_name',
        'warehouse_id',
        'location_id',
        'warehouse_label',
        'location_label',
        'requested_by_user_id',
        'source',
        'budget_amount',
        'total_without_tax',
        'total_tax',
        'total_with_tax',
        'notes',
        'requested_at',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:4',
        'total_without_tax' => 'decimal:4',
        'total_tax' => 'decimal:4',
        'total_with_tax' => 'decimal:4',
        'requested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (PurchaseRequest $request): void {
            $request->writeStatusLog(
                fromStatus: null,
                toStatus: $request->status ?: 'draft',
                event: 'created',
                notes: 'Solicitud creada.'
            );
        });

        static::updated(function (PurchaseRequest $request): void {
            if (! $request->wasChanged('status')) {
                return;
            }

            $request->writeStatusLog(
                fromStatus: $request->getOriginal('status'),
                toStatus: $request->status,
                event: 'status_changed',
                notes: static::statusChangeNote($request->getOriginal('status'), $request->status)
            );
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PurchaseRequestStatusLog::class);
    }

    public function writeStatusLog(?string $fromStatus, ?string $toStatus, string $event = 'status_changed', ?string $notes = null): void
    {
        if (! Schema::hasTable('purchase_request_status_logs')) {
            return;
        }

        $user = Auth::user();

        PurchaseRequestStatusLog::create([
            'purchase_request_id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? $user?->email,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event' => $event,
            'notes' => $notes,
        ]);
    }

    protected static function statusChangeNote(?string $fromStatus, ?string $toStatus): string
    {
        return match ($toStatus) {
            'review' => 'Solicitud enviada a revisión.',
            'approved' => 'Solicitud aprobada.',
            'cancelled' => 'Solicitud cancelada.',
            'draft' => 'Solicitud regresada a borrador.',
            'converted' => 'Solicitud convertida a orden de compra.',
            default => 'Cambio de estado de solicitud.',
        };
    }
}
