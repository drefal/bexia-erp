<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION bexia.bexia_guard_approval_step_actor()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    v_company_id bigint;
    v_role_allowed boolean := false;
BEGIN
    IF NEW.status IN ('approved', 'rejected')
       AND (
            OLD.status IS DISTINCT FROM NEW.status
            OR OLD.acted_by_user_id IS DISTINCT FROM NEW.acted_by_user_id
       )
    THEN
        IF NEW.acted_by_user_id IS NULL THEN
            RAISE EXCEPTION
                'Approval step requires acted_by_user_id for decision step %',
                NEW.id;
        END IF;

        /*
         * Camino 1:
         * usuario explícitamente materializado en la etapa.
         */
        IF NEW.approver_user_id IS NOT NULL
           AND NEW.acted_by_user_id = NEW.approver_user_id
        THEN
            RETURN NEW;
        END IF;

        /*
         * Camino 2:
         * miembro del rol alternativo dentro de la misma empresa.
         */
        IF NEW.approver_role_name IS NOT NULL
           AND btrim(NEW.approver_role_name) <> ''
        THEN
            SELECT ar.company_id
              INTO v_company_id
              FROM bexia.approval_requests ar
             WHERE ar.id = NEW.approval_request_id;

            IF v_company_id IS NOT NULL THEN
                SELECT EXISTS (
                    SELECT 1
                      FROM bexia.model_has_roles mr
                      JOIN bexia.roles r
                        ON r.id = mr.role_id
                     WHERE mr.model_type = 'App\Models\User'
                       AND mr.model_id = NEW.acted_by_user_id
                       AND mr.company_id = v_company_id
                       AND r.name = NEW.approver_role_name
                       AND (
                            r.company_id = v_company_id
                            OR r.company_id IS NULL
                       )
                )
                INTO v_role_allowed;
            END IF;

            IF v_role_allowed THEN
                RETURN NEW;
            END IF;
        END IF;

        RAISE EXCEPTION
            'Approval step % cannot be acted by user %; explicit approver=% role=%',
            NEW.id,
            NEW.acted_by_user_id,
            NEW.approver_user_id,
            NEW.approver_role_name;
    END IF;

    RETURN NEW;
END;
$function$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION bexia.bexia_guard_approval_step_actor()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    IF NEW.status IN ('approved', 'rejected')
       AND (
            OLD.status IS DISTINCT FROM NEW.status
            OR OLD.acted_by_user_id IS DISTINCT FROM NEW.acted_by_user_id
       )
    THEN
        IF NEW.approver_user_id IS NOT NULL THEN
            IF NEW.acted_by_user_id IS NULL THEN
                RAISE EXCEPTION
                    'Approval step requires acted_by_user_id for explicit approver step %',
                    NEW.id;
            END IF;

            IF NEW.acted_by_user_id <> NEW.approver_user_id THEN
                RAISE EXCEPTION
                    'Approval step % belongs to user %, but was acted by user %',
                    NEW.id,
                    NEW.approver_user_id,
                    NEW.acted_by_user_id;
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$function$;
SQL);
    }
};
