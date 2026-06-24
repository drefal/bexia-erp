<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION bexia_guard_approval_step_actor()
RETURNS trigger AS $$
BEGIN
    IF NEW.status IN ('approved', 'rejected')
       AND (
            OLD.status IS DISTINCT FROM NEW.status
            OR OLD.acted_by_user_id IS DISTINCT FROM NEW.acted_by_user_id
       )
    THEN
        IF NEW.approver_user_id IS NOT NULL THEN
            IF NEW.acted_by_user_id IS NULL THEN
                RAISE EXCEPTION 'Approval step requires acted_by_user_id for explicit approver step %', NEW.id;
            END IF;

            IF NEW.acted_by_user_id <> NEW.approver_user_id THEN
                RAISE EXCEPTION 'Approval step % belongs to user %, but was acted by user %',
                    NEW.id,
                    NEW.approver_user_id,
                    NEW.acted_by_user_id;
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bexia_guard_approval_step_actor ON approval_request_steps;

CREATE TRIGGER trg_bexia_guard_approval_step_actor
BEFORE UPDATE ON approval_request_steps
FOR EACH ROW
EXECUTE FUNCTION bexia_guard_approval_step_actor();

CREATE OR REPLACE FUNCTION bexia_guard_approval_request_final()
RETURNS trigger AS $$
BEGIN
    IF NEW.status = 'approved'
       AND OLD.status IS DISTINCT FROM NEW.status
    THEN
        IF EXISTS (
            SELECT 1
            FROM approval_request_steps
            WHERE approval_request_id = NEW.id
              AND status <> 'approved'
        ) THEN
            RAISE EXCEPTION 'Approval request % cannot be approved while it has non-approved steps', NEW.id;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bexia_guard_approval_request_final ON approval_requests;

CREATE TRIGGER trg_bexia_guard_approval_request_final
BEFORE UPDATE ON approval_requests
FOR EACH ROW
EXECUTE FUNCTION bexia_guard_approval_request_final();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_bexia_guard_approval_step_actor ON approval_request_steps;
DROP TRIGGER IF EXISTS trg_bexia_guard_approval_request_final ON approval_requests;
DROP FUNCTION IF EXISTS bexia_guard_approval_step_actor();
DROP FUNCTION IF EXISTS bexia_guard_approval_request_final();
SQL);
    }
};
