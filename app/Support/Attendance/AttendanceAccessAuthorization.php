<?php

declare(strict_types=1);

namespace App\Support\Attendance;

final class AttendanceAccessAuthorization
{
    public const VIEW =
        'rrhh.accesos.ver';

    public const MANAGER =
        'rrhh.accesos.autorizar.jefe';

    public const DEPARTMENT =
        'rrhh.accesos.autorizar.departamento';

    public const HR =
        'rrhh.accesos.autorizar.rrhh';

    public const RESTRICT =
        'rrhh.accesos.restringir';

    public const CONFIGURE =
        'rrhh.accesos.configurar';

    public const AUDIT =
        'rrhh.accesos.auditoria';

    public static function permissions(): array
    {
        return [
            self::VIEW,
            self::MANAGER,
            self::DEPARTMENT,
            self::HR,
            self::RESTRICT,
            self::CONFIGURE,
            self::AUDIT,
        ];
    }

    public static function evaluate(
        array $context,
        string $action
    ): array {
        $validActions = [
            'authorize_prior',
            'authorize_now',
            'reject_access',
            'restrict_access',
            'revoke_restriction',
        ];

        if (!in_array(
            $action,
            $validActions,
            true
        )) {
            return self::deny('invalid_action');
        }

        $userId = (int) (
            $context['actor_user_id'] ?? 0
        );

        $companyId = (int) (
            $context['company_id'] ?? 0
        );

        $employeeId = (int) (
            $context['employee_id'] ?? 0
        );

        if (
            $userId <= 0
            || $companyId <= 0
            || $employeeId <= 0
        ) {
            return self::deny('invalid_identity');
        }

        if (!($context['actor_exists'] ?? false)) {
            return self::deny('actor_not_found');
        }

        if (!($context['tenant_access'] ?? false)) {
            return self::deny('tenant_access_denied');
        }

        if (
            (int) (
                $context['employee_company_id'] ?? 0
            ) !== $companyId
        ) {
            return self::deny('employee_company_mismatch');
        }

        if (!($context['employee_active'] ?? false)) {
            return self::deny('employee_inactive');
        }

        if (!($context['branch_scope_valid'] ?? false)) {
            return self::deny('invalid_branch_scope');
        }

        if (!($context['policy_enabled'] ?? false)) {
            return self::deny('access_policy_disabled');
        }

        $employeeUserId = (int) (
            $context['employee_user_id'] ?? 0
        );

        if (
            $employeeUserId > 0
            && $employeeUserId === $userId
        ) {
            return self::deny('self_approval_forbidden');
        }

        $permissions =
            $context['permissions'] ?? [];

        if (!is_array($permissions)) {
            return self::deny('invalid_permissions');
        }

        $has = static fn (
            string $permission
        ): bool => in_array(
            $permission,
            $permissions,
            true
        );

        $hrEnabled = (bool) (
            $context['allow_hr_approval'] ?? false
        );

        $isHr = $hrEnabled && $has(self::HR);

        if (in_array(
            $action,
            [
                'restrict_access',
                'revoke_restriction',
            ],
            true
        )) {
            if ($isHr && $has(self::RESTRICT)) {
                return self::allow(
                    'hr_restriction_authority'
                );
            }

            return self::deny(
                'restriction_permission_required'
            );
        }

        if (
            $context['hard_restriction'] ?? false
        ) {
            return self::deny(
                'explicit_restriction_requires_revocation'
            );
        }

        if (
            ($context['temporary_restriction'] ?? false)
            && in_array(
                $action,
                ['authorize_prior', 'authorize_now'],
                true
            )
        ) {
            return self::deny(
                'temporary_restriction_requires_revocation'
            );
        }

        $managerId = (int) (
            $context['manager_user_id'] ?? 0
        );

        $managerCompanyId = (int) (
            $context['manager_company_id'] ?? 0
        );

        if (
            ($context['allow_manager_approval'] ?? false)
            && $has(self::MANAGER)
            && ($context['manager_active'] ?? false)
            && $managerCompanyId === $companyId
            && $managerId > 0
            && $managerId === $userId
        ) {
            return self::allow('direct_manager');
        }

        $departmentManagerId = (int) (
            $context['department_manager_user_id'] ?? 0
        );

        $departmentCompanyId = (int) (
            $context['department_company_id'] ?? 0
        );

        $departmentManagerCompanyId = (int) (
            $context['department_manager_company_id'] ?? 0
        );

        if (
            ($context['allow_department_approval'] ?? false)
            && $has(self::DEPARTMENT)
            && ($context['department_active'] ?? false)
            && ($context['department_manager_active'] ?? false)
            && $departmentCompanyId === $companyId
            && $departmentManagerCompanyId === $companyId
            && $departmentManagerId > 0
            && $departmentManagerId === $userId
        ) {
            return self::allow('department_manager');
        }

        if ($isHr) {
            return self::allow('hr_authority');
        }

        return self::deny(
            'no_authorized_relationship'
        );
    }

    private static function allow(
        string $source
    ): array {
        return [
            'allowed' => true,
            'source' => $source,
            'reason' => null,
        ];
    }

    private static function deny(
        string $reason
    ): array {
        return [
            'allowed' => false,
            'source' => null,
            'reason' => $reason,
        ];
    }
}
