<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AttendanceAccessPriorAuthorizationService
{
    public function create(
        int $companyId,
        int $branchId,
        int $employeeId,
        string $validFrom,
        string $validUntil,
        string $reason
    ): array {
        $actor = auth()->user();

        if (!$actor instanceof User) {
            throw new AuthorizationException(
                'AUTHENTICATION_REQUIRED'
            );
        }

        if (
            $companyId <= 0
            || $branchId <= 0
            || $employeeId <= 0
        ) {
            throw new AuthorizationException(
                'INVALID_ACCESS_SCOPE'
            );
        }

        return DB::transaction(function () use (
            $actor,
            $companyId,
            $branchId,
            $employeeId,
            $validFrom,
            $validUntil,
            $reason
        ): array {

            // Validar el alcance antes de bloquear filas.
            $initialContext =
                AttendanceAccessContextResolver::resolve(
                    $actor,
                    $companyId,
                    $employeeId,
                    $branchId
                );

            if (!$initialContext) {
                throw new AuthorizationException(
                    'ACCESS_SCOPE_DENIED'
                );
            }

            if (
                !in_array(
                    AttendanceAccessAuthorization::VIEW,
                    $initialContext['permissions'],
                    true
                )
            ) {
                throw new AuthorizationException(
                    'ACCESS_PERMISSION_REQUIRED'
                );
            }

            // Las futuras altas, revocaciones y resoluciones
            // deben compartir este mismo bloqueo por empleado.
            $employee = DB::table('employees')
                ->where('id', $employeeId)
                ->where('company_id', $companyId)
                ->where('active', true)
                ->lockForUpdate()
                ->first(['id']);

            if (!$employee) {
                throw new AuthorizationException(
                    'EMPLOYEE_NOT_AVAILABLE'
                );
            }

            $now = new DateTimeImmutable(
                'now',
                new DateTimeZone(
                    AttendanceAccessValidity::TIMEZONE
                )
            );

            $validity = AttendanceAccessValidity::validate(
                $validFrom,
                $validUntil,
                $reason,
                $now
            );

            // Revalidar identidad y jerarquia despues
            // de obtener el bloqueo.
            $context =
                AttendanceAccessContextResolver::resolve(
                    $actor,
                    $companyId,
                    $employeeId,
                    $branchId
                );

            if (!$context) {
                throw new AuthorizationException(
                    'ACCESS_CONTEXT_CHANGED'
                );
            }

            if (
                !in_array(
                    AttendanceAccessAuthorization::VIEW,
                    $context['permissions'],
                    true
                )
            ) {
                throw new AuthorizationException(
                    'ACCESS_PERMISSION_REQUIRED'
                );
            }

            $policy =
                AttendanceAccessPolicyResolver::resolve(
                    $companyId,
                    $branchId,
                    true
                );

            $context = array_replace(
                $context,
                $policy
            );

            $from =
                AttendanceAccessValidity::databaseValue(
                    $validity['valid_from']
                );

            $until =
                AttendanceAccessValidity::databaseValue(
                    $validity['valid_until']
                );

            $recordedAt =
                AttendanceAccessValidity::databaseValue(
                    $now
                );

            $overlapping = DB::table(
                'hr_access_authorizations'
            )
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->whereNull('revoked_at')
                ->where(function ($query) use ($branchId) {
                    $query
                        ->where('branch_id', $branchId)
                        ->orWhereNull('branch_id');
                })
                ->where('valid_from', '<=', $until)
                ->where('valid_until', '>=', $from)
                ->lockForUpdate()
                ->get([
                    'id',
                    'type',
                    'branch_id',
                ]);

            $context['hard_restriction'] =
                $overlapping->contains(
                    'type',
                    'hard_deny'
                );

            $context['temporary_restriction'] =
                $overlapping->contains(
                    'type',
                    'temporary_hold'
                );

            $permission =
                AttendanceAccessAuthorization::evaluate(
                    $context,
                    'authorize_prior'
                );

            if (!$permission['allowed']) {
                throw new AuthorizationException(
                    $permission['reason']
                    ?? 'AUTHORIZATION_DENIED'
                );
            }

            // No crear autorizaciones superpuestas.
            // Las ampliaciones se gestionaran mediante
            // un flujo separado y auditado.
            if ($overlapping->contains('type', 'allow')) {
                throw new RuntimeException(
                    'OVERLAPPING_AUTHORIZATION_EXISTS'
                );
            }

            $actorId = (int) $actor->getKey();

            $authorizationId = DB::table(
                'hr_access_authorizations'
            )->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'employee_id' => $employeeId,
                'type' => 'allow',
                'status' => 'approved',
                'valid_from' => $from,
                'valid_until' => $until,
                'reason' => $validity['reason'],
                'created_by_user_id' => $actorId,
                'approved_by_user_id' => $actorId,
                'approved_at' => $recordedAt,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ]);

            DB::table('hr_access_events')->insert([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'access_authorization_id' =>
                    $authorizationId,
                'event_type' =>
                    'prior_authorization_created',
                'from_status' => null,
                'to_status' => 'approved',
                'actor_user_id' => $actorId,
                'actor_type' => 'user',
                'reason' => $validity['reason'],
                'metadata' => json_encode([
                    'authorization_source' =>
                        $permission['source'],
                    'policy_id' =>
                        $policy['policy_id'],
                    'branch_id' => $branchId,
                    'valid_from' => $from,
                    'valid_until' => $until,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => $recordedAt,
            ]);

            return [
                'authorization_id' =>
                    (int) $authorizationId,
                'status' => 'approved',
                'source' => $permission['source'],
                'valid_from' => $from,
                'valid_until' => $until,
            ];

        }, 3);
    }
}
