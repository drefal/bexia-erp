<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cashier_shifts', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('pos_point_id');
            $table->unsignedBigInteger('pos_session_id');
            $table->unsignedBigInteger('employee_id');

            $table->uuid('shift_uuid')->unique();

            // Huella de terminal, nunca el token original ni el NIP.
            $table->string('terminal_hash', 64);

            $table->unsignedBigInteger('opened_by_user_id')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();

            $table->string('status', 16)->default('open');

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            // Resumen derivado de pagos y movimientos al cerrar.
            $table->jsonb('close_summary')->nullable();

            $table->text('closing_notes')->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'pos_point_id', 'pos_session_id'],
                'pos_cashier_shifts_session_idx'
            );

            $table->index(
                ['employee_id', 'status'],
                'pos_cashier_shifts_employee_idx'
            );
        });

        // Regla PostgreSQL: un turno activo por empleado
        // en una misma sesion fisica.
        DB::statement(
            "CREATE UNIQUE INDEX pos_cashier_shifts_one_open_idx
             ON pos_cashier_shifts (pos_session_id, employee_id)
             WHERE status = 'open'"
        );

        DB::statement(
            "ALTER TABLE pos_cashier_shifts
             ADD CONSTRAINT pos_cashier_shifts_status_check
             CHECK (status IN ('open', 'closed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cashier_shifts');
    }
};
