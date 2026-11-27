<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hr_access_policies',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->nullable()
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->boolean('enabled')
                    ->default(false);

                $table->boolean('hold_on_late')
                    ->default(false);

                $table->unsignedSmallInteger(
                    'access_tolerance_minutes'
                )->nullable();

                $table->unsignedSmallInteger(
                    'early_arrival_minutes'
                )->nullable();

                $table->boolean('hold_on_rest_day')
                    ->default(true);

                $table->boolean('hold_on_approved_absence')
                    ->default(true);

                $table->boolean('allow_manager_approval')
                    ->default(true);

                $table->boolean('allow_department_approval')
                    ->default(false);

                $table->boolean('allow_hr_approval')
                    ->default(true);

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestampsTz();

                $table->unique(
                    ['company_id', 'branch_id'],
                    'hr_access_policy_branch_unique'
                );
            }
        );

        // Una sola politica general por empresa.
        // PostgreSQL permite multiples NULL en UNIQUE.
        DB::statement("
            CREATE UNIQUE INDEX
                hr_access_policy_company_unique
            ON hr_access_policies (company_id)
            WHERE branch_id IS NULL
        ");

        Schema::create(
            'hr_access_authorizations',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->nullable()
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('employee_id')
                    ->constrained('employees')
                    ->restrictOnDelete();

                $table->string('type', 30);

                $table->string('status', 30)
                    ->default('pending');

                $table->timestampTz('valid_from');

                $table->timestampTz('valid_until');

                $table->text('reason');

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestampTz('approved_at')
                    ->nullable();

                $table->foreignId('revoked_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestampTz('revoked_at')
                    ->nullable();

                $table->text('revocation_reason')
                    ->nullable();

                $table->timestampsTz();

                $table->index(
                    [
                        'company_id',
                        'employee_id',
                        'status',
                        'valid_from',
                        'valid_until',
                    ],
                    'hr_access_auth_employee_idx'
                );
            }
        );

        DB::statement("
            ALTER TABLE hr_access_authorizations
            ADD CONSTRAINT hr_access_auth_type_check
            CHECK (
                type IN (
                    'allow',
                    'temporary_hold',
                    'hard_deny'
                )
            )
        ");

        DB::statement("
            ALTER TABLE hr_access_authorizations
            ADD CONSTRAINT hr_access_auth_status_check
            CHECK (
                status IN (
                    'pending',
                    'approved',
                    'rejected',
                    'revoked'
                )
            )
        ");

        DB::statement("
            ALTER TABLE hr_access_authorizations
            ADD CONSTRAINT hr_access_auth_dates_check
            CHECK (valid_until >= valid_from)
        ");

        DB::statement("
            ALTER TABLE hr_access_authorizations
            ADD CONSTRAINT hr_access_auth_approval_check
            CHECK (
                status <> 'approved'
                OR (
                    approved_by_user_id IS NOT NULL
                    AND approved_at IS NOT NULL
                )
            )
        ");

        DB::statement("
            ALTER TABLE hr_access_authorizations
            ADD CONSTRAINT hr_access_auth_revocation_check
            CHECK (
                status <> 'revoked'
                OR (
                    revoked_by_user_id IS NOT NULL
                    AND revoked_at IS NOT NULL
                    AND revocation_reason IS NOT NULL
                )
            )
        ");

        Schema::create(
            'hr_access_decisions',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('employee_id')
                    ->constrained('employees')
                    ->restrictOnDelete();

                $table->foreignId('employee_attendance_id')
                    ->nullable()
                    ->constrained('employee_attendances')
                    ->nullOnDelete();

                $table->foreignId('attendance_terminal_id')
                    ->constrained('attendance_terminals')
                    ->restrictOnDelete();

                $table->foreignId('access_policy_id')
                    ->nullable()
                    ->constrained('hr_access_policies')
                    ->restrictOnDelete();

                $table->foreignId('access_authorization_id')
                    ->nullable()
                    ->constrained(
                        'hr_access_authorizations'
                    )
                    ->restrictOnDelete();

                $table->timestampTz('entry_at');

                $table->timestampTz('evaluated_at');

                $table->string('initial_status', 30);

                $table->string('initial_reason_code', 80);

                $table->string('status', 30);

                $table->string('reason_code', 80);

                $table->jsonb('evaluation_snapshot');

                $table->boolean('requires_authorization')
                    ->default(false);

                $table->foreignId('assigned_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->string('assignment_source', 40)
                    ->nullable();

                $table->foreignId('resolved_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestampTz('resolved_at')
                    ->nullable();

                $table->text('resolution_reason')
                    ->nullable();

                $table->unsignedInteger('version')
                    ->default(1);

                $table->timestampsTz();

                $table->index(
                    ['company_id', 'branch_id', 'entry_at'],
                    'hr_access_decisions_monitor_idx'
                );

                $table->index(
                    ['company_id', 'assigned_user_id', 'status'],
                    'hr_access_decisions_pending_idx'
                );

                $table->index(
                    ['company_id', 'employee_id', 'entry_at'],
                    'hr_access_decisions_employee_idx'
                );

                $table->unique(
                    ['company_id', 'employee_attendance_id'],
                    'hr_access_decisions_attendance_unique'
                );
            }
        );

        foreach (['initial_status', 'status'] as $column) {

            DB::statement("
                ALTER TABLE hr_access_decisions
                ADD CONSTRAINT
                    hr_access_decisions_{$column}_check
                CHECK (
                    {$column} IN (
                        'authorized',
                        'hold',
                        'denied',
                        'review',
                        'not_evaluated'
                    )
                )
            ");
        }

        Schema::create(
            'hr_access_events',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->restrictOnDelete();

                $table->foreignId('employee_id')
                    ->nullable()
                    ->constrained('employees')
                    ->restrictOnDelete();

                $table->foreignId('access_decision_id')
                    ->nullable()
                    ->constrained('hr_access_decisions')
                    ->restrictOnDelete();

                $table->foreignId('access_authorization_id')
                    ->nullable()
                    ->constrained('hr_access_authorizations')
                    ->restrictOnDelete();

                $table->foreignId('access_policy_id')
                    ->nullable()
                    ->constrained('hr_access_policies')
                    ->restrictOnDelete();

                $table->string('event_type', 60);

                $table->string('from_status', 30)
                    ->nullable();

                $table->string('to_status', 30)
                    ->nullable();

                $table->foreignId('actor_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->string('actor_type', 30)
                    ->default('system');

                $table->text('reason')
                    ->nullable();

                $table->jsonb('metadata')
                    ->nullable();

                $table->timestampTz('occurred_at');

                $table->index(
                    ['company_id', 'occurred_at'],
                    'hr_access_events_company_idx'
                );

                $table->index(
                    ['access_decision_id', 'occurred_at'],
                    'hr_access_events_decision_idx'
                );

                $table->index(
                    ['access_authorization_id', 'occurred_at'],
                    'hr_access_events_auth_idx'
                );
            }
        );

        DB::statement("
            ALTER TABLE hr_access_events
            ADD CONSTRAINT hr_access_events_target_check
            CHECK (
                num_nonnulls(
                    access_decision_id,
                    access_authorization_id,
                    access_policy_id
                ) = 1
            )
        ");

        DB::unprepared(<<<'SQL'
CREATE FUNCTION bexia.hr_access_validate_scope()
RETURNS trigger
LANGUAGE plpgsql
SET search_path = pg_catalog, bexia
AS $guard$
BEGIN

    IF TG_TABLE_NAME = 'hr_access_policies' THEN

        IF NEW.branch_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.branches b
               WHERE b.id = NEW.branch_id
                 AND b.company_id = NEW.company_id
           )
        THEN
            RAISE EXCEPTION 'ACCESS_POLICY_BRANCH_SCOPE'
                USING ERRCODE = '23514';
        END IF;

    ELSIF TG_TABLE_NAME = 'hr_access_authorizations' THEN

        IF NOT EXISTS (
            SELECT 1
            FROM bexia.employees e
            WHERE e.id = NEW.employee_id
              AND e.company_id = NEW.company_id
        )
        THEN
            RAISE EXCEPTION 'ACCESS_AUTH_EMPLOYEE_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NEW.branch_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.branches b
               WHERE b.id = NEW.branch_id
                 AND b.company_id = NEW.company_id
           )
        THEN
            RAISE EXCEPTION 'ACCESS_AUTH_BRANCH_SCOPE'
                USING ERRCODE = '23514';
        END IF;

    ELSIF TG_TABLE_NAME = 'hr_access_decisions' THEN

        IF NOT EXISTS (
            SELECT 1
            FROM bexia.employees e
            WHERE e.id = NEW.employee_id
              AND e.company_id = NEW.company_id
        )
        THEN
            RAISE EXCEPTION 'ACCESS_DECISION_EMPLOYEE_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM bexia.branches b
            WHERE b.id = NEW.branch_id
              AND b.company_id = NEW.company_id
        )
        THEN
            RAISE EXCEPTION 'ACCESS_DECISION_BRANCH_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM bexia.attendance_terminals t
            WHERE t.id = NEW.attendance_terminal_id
              AND t.company_id = NEW.company_id
              AND t.branch_id = NEW.branch_id
        )
        THEN
            RAISE EXCEPTION 'ACCESS_DECISION_TERMINAL_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NEW.employee_attendance_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.employee_attendances a
               WHERE a.id = NEW.employee_attendance_id
                 AND a.company_id = NEW.company_id
                 AND a.employee_id = NEW.employee_id
           )
        THEN
            RAISE EXCEPTION 'ACCESS_DECISION_ATTENDANCE_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NEW.access_policy_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.hr_access_policies p
               WHERE p.id = NEW.access_policy_id
                 AND p.company_id = NEW.company_id
                 AND (
                     p.branch_id IS NULL
                     OR p.branch_id = NEW.branch_id
                 )
           )
        THEN
            RAISE EXCEPTION 'ACCESS_DECISION_POLICY_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NEW.access_authorization_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.hr_access_authorizations a
               WHERE a.id = NEW.access_authorization_id
                 AND a.company_id = NEW.company_id
                 AND a.employee_id = NEW.employee_id
                 AND (
                     a.branch_id IS NULL
                     OR a.branch_id = NEW.branch_id
                 )
           )
        THEN
            RAISE EXCEPTION 'ACCESS_DECISION_AUTH_SCOPE'
                USING ERRCODE = '23514';
        END IF;

    ELSIF TG_TABLE_NAME = 'hr_access_events' THEN

        IF NEW.employee_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.employees e
               WHERE e.id = NEW.employee_id
                 AND e.company_id = NEW.company_id
           )
        THEN
            RAISE EXCEPTION 'ACCESS_EVENT_EMPLOYEE_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NEW.access_decision_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.hr_access_decisions d
               WHERE d.id = NEW.access_decision_id
                 AND d.company_id = NEW.company_id
                 AND (
                     NEW.employee_id IS NULL
                     OR d.employee_id = NEW.employee_id
                 )
           )
        THEN
            RAISE EXCEPTION 'ACCESS_EVENT_DECISION_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NEW.access_authorization_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.hr_access_authorizations a
               WHERE a.id = NEW.access_authorization_id
                 AND a.company_id = NEW.company_id
                 AND (
                     NEW.employee_id IS NULL
                     OR a.employee_id = NEW.employee_id
                 )
           )
        THEN
            RAISE EXCEPTION 'ACCESS_EVENT_AUTH_SCOPE'
                USING ERRCODE = '23514';
        END IF;

        IF NEW.access_policy_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM bexia.hr_access_policies p
               WHERE p.id = NEW.access_policy_id
                 AND p.company_id = NEW.company_id
           )
        THEN
            RAISE EXCEPTION 'ACCESS_EVENT_POLICY_SCOPE'
                USING ERRCODE = '23514';
        END IF;

    ELSE

        RAISE EXCEPTION 'ACCESS_UNKNOWN_TABLE'
            USING ERRCODE = '23514';

    END IF;

    RETURN NEW;
END;
$guard$;


CREATE TRIGGER hr_access_policy_scope_guard
BEFORE INSERT OR UPDATE
ON bexia.hr_access_policies
FOR EACH ROW
EXECUTE FUNCTION bexia.hr_access_validate_scope();


CREATE TRIGGER hr_access_authorization_scope_guard
BEFORE INSERT OR UPDATE
ON bexia.hr_access_authorizations
FOR EACH ROW
EXECUTE FUNCTION bexia.hr_access_validate_scope();


CREATE TRIGGER hr_access_decision_scope_guard
BEFORE INSERT OR UPDATE
ON bexia.hr_access_decisions
FOR EACH ROW
EXECUTE FUNCTION bexia.hr_access_validate_scope();


CREATE TRIGGER hr_access_event_scope_guard
BEFORE INSERT
ON bexia.hr_access_events
FOR EACH ROW
EXECUTE FUNCTION bexia.hr_access_validate_scope();


CREATE FUNCTION bexia.hr_access_protect_audit()
RETURNS trigger
LANGUAGE plpgsql
SET search_path = pg_catalog, bexia
AS $guard$
BEGIN
    RAISE EXCEPTION 'ACCESS_AUDIT_IMMUTABLE'
        USING ERRCODE = '23514';
END;
$guard$;


CREATE TRIGGER hr_access_event_immutable_guard
BEFORE UPDATE OR DELETE
ON bexia.hr_access_events
FOR EACH ROW
EXECUTE FUNCTION bexia.hr_access_protect_audit();


CREATE TRIGGER hr_access_event_truncate_guard
BEFORE TRUNCATE
ON bexia.hr_access_events
FOR EACH STATEMENT
EXECUTE FUNCTION bexia.hr_access_protect_audit();


ALTER TABLE bexia.hr_access_policies
ADD CONSTRAINT hr_access_policy_minutes_check
CHECK (
    (access_tolerance_minutes IS NULL
     OR access_tolerance_minutes >= 0)
    AND
    (early_arrival_minutes IS NULL
     OR early_arrival_minutes >= 0)
);


ALTER TABLE bexia.hr_access_decisions
ADD CONSTRAINT hr_access_decision_version_check
CHECK (version >= 1);


ALTER TABLE bexia.hr_access_decisions
ADD CONSTRAINT hr_access_decision_resolution_check
CHECK (
    (
        resolved_by_user_id IS NULL
        AND resolved_at IS NULL
        AND resolution_reason IS NULL
    )
    OR
    (
        resolved_by_user_id IS NOT NULL
        AND resolved_at IS NOT NULL
        AND resolution_reason IS NOT NULL
        AND length(btrim(resolution_reason)) > 0
    )
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_access_events');

        Schema::dropIfExists('hr_access_decisions');

        Schema::dropIfExists('hr_access_authorizations');

        Schema::dropIfExists('hr_access_policies');

        DB::statement(
            'DROP FUNCTION IF EXISTS '
            . 'bexia.hr_access_validate_scope()'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS '
            . 'bexia.hr_access_protect_audit()'
        );

    }
};
