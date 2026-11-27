<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class AttendanceAccessPolicyService
{
    public const BOOLEAN_FIELDS = [
        'enabled',
        'hold_on_late',
        'hold_on_rest_day',
        'hold_on_approved_absence',
        'allow_manager_approval',
        'allow_department_approval',
        'allow_hr_approval',
    ];

    public const MINUTE_FIELDS = [
        'access_tolerance_minutes',
        'early_arrival_minutes',
    ];

    public static function fields(): array
    {
        return array_merge(
            self::BOOLEAN_FIELDS,
            self::MINUTE_FIELDS
        );
    }

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'hold_on_late' => false,
            'hold_on_rest_day' => false,
            'hold_on_approved_absence' => false,
            'allow_manager_approval' => true,
            'allow_department_approval' => false,
            'allow_hr_approval' => false,
            'access_tolerance_minutes' => null,
            'early_arrival_minutes' => null,
        ];
    }

    public static function normalize(
        array $input
    ): array {

        $fields = self::fields();

        if (
            array_diff(array_keys($input), $fields)
            || array_diff($fields, array_keys($input))
        ) {
            throw new InvalidArgumentException(
                'INVALID_POLICY_FIELDS'
            );
        }

        $data = [];

        foreach (self::BOOLEAN_FIELDS as $field) {

            $value = $input[$field];

            if (!is_bool($value)) {
                throw new InvalidArgumentException(
                    'INVALID_BOOLEAN:' . $field
                );
            }

            $data[$field] = $value;
        }

        foreach (self::MINUTE_FIELDS as $field) {

            $value = $input[$field];

            if (
                $value !== null
                && (
                    !is_int($value)
                    || $value < 0
                    || $value > 1440
                )
            ) {
                throw new InvalidArgumentException(
                    'INVALID_MINUTES:' . $field
                );
            }

            $data[$field] = $value;
        }

        return $data;
    }

    public static function validateActivation(
        array $data
    ): void {

        $data = self::normalize($data);

        if ($data['hold_on_approved_absence']) {
            throw new RuntimeException(
                'ABSENCE_INTEGRATION_PENDING'
            );
        }

        if (!$data['enabled']) {
            return;
        }

        if (
            $data['hold_on_late']
            || $data['hold_on_rest_day']
            || $data['early_arrival_minutes'] !== null
        ) {
            // Temporary protection until coverage
            // validation is integrated and tested.
            throw new RuntimeException(
                'RETENTION_ACTIVATION_NOT_READY'
            );
        }

        if (
            !$data['allow_manager_approval']
            && !$data['allow_department_approval']
            && !$data['allow_hr_approval']
        ) {
            throw new RuntimeException(
                'AT_LEAST_ONE_AUTHORITY_REQUIRED'
            );
        }
    }

    public static function canConfigure(
        User $actor,
        Company $company
    ): bool {

        return $company->active
            && $actor->canAccessTenant($company)
            && AttendanceAccessPermissionResolver
                ::hasPermission(
                    $actor,
                    $company,
                    AttendanceAccessAuthorization::CONFIGURE
                );
    }

    private static function authorizedCompany(
        int $companyId
    ): array {

        $sessionActor = auth()->user();

        if (
            !$sessionActor instanceof User
            || $companyId <= 0
        ) {
            throw new AuthorizationException();
        }

        $actor = User::query()
            ->find($sessionActor->getKey());

        $company = Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->first();

        if (
            !$actor
            || !$company
            || !self::canConfigure($actor, $company)
        ) {
            throw new AuthorizationException();
        }

        return [$actor, $company];
    }

    private static function validateBranch(
        int $companyId,
        ?int $branchId,
        bool $lock = false
    ): void {

        if ($branchId === null) {
            return;
        }

        if ($branchId <= 0) {
            throw new InvalidArgumentException(
                'INVALID_BRANCH'
            );
        }

        $query = DB::table('branches')
            ->where('id', $branchId)
            ->where('company_id', $companyId)
            ->where('active', true);

        if ($lock) {
            $query->sharedLock();
        }

        if (!$query->first(['id'])) {
            throw new AuthorizationException();
        }
    }

    private static function scopedQuery(
        int $companyId,
        ?int $branchId
    ) {

        $query = DB::table('hr_access_policies')
            ->where('company_id', $companyId);

        if ($branchId === null) {
            $query->whereNull('branch_id');
        } else {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    private static function rowValues(
        ?object $row
    ): array {

        if (!$row) {
            return self::defaults();
        }

        $values = [];

        foreach (self::BOOLEAN_FIELDS as $field) {
            $values[$field] = (bool) $row->$field;
        }

        foreach (self::MINUTE_FIELDS as $field) {
            $values[$field] = $row->$field === null
                ? null
                : (int) $row->$field;
        }

        return self::normalize($values);
    }

    private static function token(
        int $companyId,
        ?int $branchId,
        ?object $row,
        ?object $inherited = null
    ): string {

        return hash(
            'sha256',
            json_encode([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'id' => $row?->id,
                'updated_at' => $row?->updated_at,
                'values' => self::rowValues($row),
                'inherited_id' => $inherited?->id,
                'inherited_updated_at' =>
                    $inherited?->updated_at,
                'inherited_values' =>
                    self::rowValues($inherited),
            ], JSON_THROW_ON_ERROR)
        );
    }

    private static function state(
        int $companyId,
        ?int $branchId,
        bool $lock = false
    ): array {

        $query = self::scopedQuery(
            $companyId,
            $branchId
        );

        if ($lock) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        $inherited = null;

        if (!$row && $branchId !== null) {

            $query = self::scopedQuery(
                $companyId,
                null
            );

            if ($lock) {
                $query->sharedLock();
            }

            $inherited = $query->first();
        }

        return [
            'row' => $row,
            'inherited' => $inherited,
            'policy_id' => $row
                ? (int) $row->id
                : null,
            'source' => $row
                ? ($branchId === null
                    ? 'company'
                    : 'branch')
                : ($inherited
                    ? 'inherited'
                    : 'default'),
            'values' => self::rowValues(
                $row ?? $inherited
            ),
            'token' => self::token(
                $companyId,
                $branchId,
                $row,
                $inherited
            ),
        ];
    }

    public static function read(
        int $companyId,
        ?int $branchId
    ): array {

        self::authorizedCompany($companyId);

        self::validateBranch(
            $companyId,
            $branchId
        );

        $state = self::state(
            $companyId,
            $branchId
        );

        unset(
            $state['row'],
            $state['inherited']
        );

        return $state;
    }

    public static function save(
        int $companyId,
        ?int $branchId,
        array $input,
        string $expectedToken,
        string $reason
    ): array {

        self::authorizedCompany($companyId);

        $data = self::normalize($input);

        self::validateActivation($data);

        $reason = trim($reason);

        if (
            mb_strlen($reason) < 5
            || mb_strlen($reason) > 1000
        ) {
            throw new InvalidArgumentException(
                'INVALID_CHANGE_REASON'
            );
        }

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/D',
                $expectedToken
            )
        ) {
            throw new InvalidArgumentException(
                'INVALID_POLICY_TOKEN'
            );
        }

        return DB::transaction(function () use (
            $companyId,
            $branchId,
            $data,
            $expectedToken,
            $reason
        ): array {

            // Serialize policy writers per company,
            // including concurrent first creation.
            $company = Company::query()
                ->whereKey($companyId)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if (!$company) {
                throw new AuthorizationException();
            }

            [$actor] = self::authorizedCompany(
                $companyId
            );

            self::validateBranch(
                $companyId,
                $branchId,
                true
            );

            $state = self::state(
                $companyId,
                $branchId,
                true
            );

            if (!hash_equals(
                $state['token'],
                $expectedToken
            )) {
                throw new RuntimeException(
                    'STALE_POLICY_RELOAD_REQUIRED'
                );
            }

            if (
                $state['row']
                && $state['values'] === $data
            ) {

                return [
                    'policy_id' => $state['policy_id'],
                    'changed' => false,
                    'token' => $state['token'],
                ];
            }

            // Avoid orphaning currently held entries.
            $pending = DB::table('hr_access_decisions')
                ->where('company_id', $companyId)
                ->where('status', 'hold')
                ->where('requires_authorization', true)
                ->whereNull('resolved_at')
                ->where(
                    'entry_at',
                    '>=',
                    now('America/Mexico_City')
                        ->startOfDay()
                        ->format('Y-m-d H:i:sP')
                );

            if ($branchId !== null) {
                $pending->where(
                    'branch_id',
                    $branchId
                );
            }

            if ($pending->exists()) {
                throw new RuntimeException(
                    'PENDING_ACCESS_REQUESTS_EXIST'
                );
            }

            $now = now('UTC');

            if ($state['row']) {

                $id = (int) $state['row']->id;

                $updated = self::scopedQuery(
                    $companyId,
                    $branchId
                )->where('id', $id)
                    ->update(array_merge(
                        $data,
                        [
                            'updated_by_user_id' =>
                                $actor->id,
                            'updated_at' => $now,
                        ]
                    ));

                if ($updated !== 1) {
                    throw new RuntimeException(
                        'POLICY_UPDATE_FAILED'
                    );
                }

                $event = 'access_policy_updated';

            } else {

                $id = (int) DB::table(
                    'hr_access_policies'
                )->insertGetId(array_merge(
                    $data,
                    [
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'created_by_user_id' =>
                            $actor->id,
                        'updated_by_user_id' =>
                            $actor->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                ));

                $event = 'access_policy_created';
            }

            DB::table('hr_access_events')->insert([
                'company_id' => $companyId,
                'access_policy_id' => $id,
                'event_type' => $event,
                'actor_user_id' => $actor->id,
                'actor_type' => 'user',
                'reason' => $reason,
                'metadata' => json_encode([
                    'branch_id' => $branchId,
                    'previous_source' =>
                        $state['source'],
                    'before' => $state['values'],
                    'after' => $data,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
            ]);

            $newState = self::state(
                $companyId,
                $branchId
            );

            return [
                'policy_id' => $id,
                'changed' => true,
                'token' => $newState['token'],
            ];

        }, 3);
    }
}
