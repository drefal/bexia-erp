<?php

namespace App\Support;

use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollRunCloseService
{
    public static function close(PayrollRun $run, int $userId, string $reason): PayrollRun
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \RuntimeException('Debes indicar el motivo del cierre.');
        }

        return DB::transaction(function () use ($run, $userId, $reason): PayrollRun {
            $lockedRun = PayrollRun::query()
                ->lockForUpdate()
                ->findOrFail($run->id);

            if ((bool) ($lockedRun->is_locked ?? false)) {
                throw new \RuntimeException('Esta nómina ya está bloqueada.');
            }

            if ((string) $lockedRun->status !== 'approved') {
                throw new \RuntimeException('Solo una pre-nómina aprobada puede cerrarse.');
            }

            if (Schema::hasColumn('payroll_runs', 'approval_status') && (string) ($lockedRun->approval_status ?? '') !== 'approved') {
                throw new \RuntimeException('La pre-nómina debe tener aprobación formal antes de cerrarse.');
            }

            if (! $lockedRun->lines()->exists()) {
                throw new \RuntimeException('No se puede cerrar una pre-nómina sin líneas calculadas.');
            }

            $now = now();

            $lockedRun->forceFill(static::filterModel($lockedRun, [
                'status' => 'closed',
                'is_locked' => true,
                'closed_at' => $now,
                'closed_by_user_id' => $userId,
                'close_reason' => $reason,
                'locked_at' => $now,
                'locked_by_user_id' => $userId,
                'lock_reason' => 'Cierre definitivo de nómina: ' . $reason,
                'updated_by_user_id' => $userId,
            ]))->save();

            return $lockedRun->fresh();
        });
    }

    public static function ensureCanRecalculate(PayrollRun $run): void
    {
        $run->refresh();

        if ((bool) ($run->is_locked ?? false)) {
            throw new \RuntimeException('La nómina está bloqueada y no puede recalcularse.');
        }

        if (in_array((string) $run->status, ['pending_approval', 'approved', 'closed', 'cancelled'], true)) {
            throw new \RuntimeException('Solo se puede recalcular una pre-nómina en borrador o calculada.');
        }
    }

    public static function ensureCanEdit(PayrollRun $run): void
    {
        $run->refresh();

        if ((bool) ($run->is_locked ?? false) || (string) $run->status === 'closed') {
            throw new \RuntimeException('La nómina está cerrada/bloqueada y no puede editarse.');
        }
    }

    public static function isLocked(PayrollRun $run): bool
    {
        return (bool) ($run->is_locked ?? false) || (string) $run->status === 'closed';
    }

    protected static function filterModel(PayrollRun $run, array $data): array
    {
        $columns = Schema::getColumnListing($run->getTable());

        return array_filter(
            $data,
            fn ($value, $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
