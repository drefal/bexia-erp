<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_terminals', function (Blueprint $table) {
            $table->id();

            /*
             * Alcance empresarial.
             *
             * La terminal siempre pertenece a una empresa.
             * La sucursal puede quedar temporalmente vacia durante
             * aprovisionamiento/configuracion inicial.
             */
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            /*
             * Identidad funcional de la terminal.
             */
            $table->string('code', 50);
            $table->string('name', 150);
            $table->uuid('uuid')->unique();

            /*
             * Autenticacion futura del dispositivo.
             *
             * Nunca se guardara el token plano.
             * token_hash sera SHA-256 del token entregado una sola vez
             * durante el aprovisionamiento.
             *
             * pairing_code_hash se utilizara posteriormente para el
             * codigo temporal de vinculacion.
             */
            $table->string('token_hash', 64)
                ->nullable()
                ->unique();

            $table->string('pairing_code_hash')
                ->nullable();

            $table->timestampTz('pairing_expires_at')
                ->nullable();

            /*
             * Estado operativo.
             */
            $table->boolean('active')
                ->default(true);

            $table->timestampTz('blocked_at')
                ->nullable();

            $table->string('blocked_reason', 500)
                ->nullable();

            /*
             * Informacion declarada por el dispositivo.
             *
             * mac_address es exclusivamente informativa.
             * NO se utilizara como credencial de autenticacion.
             */
            $table->string('device_name', 150)
                ->nullable();

            $table->string('device_model', 150)
                ->nullable();

            $table->string('platform', 100)
                ->nullable();

            $table->string('app_version', 50)
                ->nullable();

            $table->string('mac_address', 32)
                ->nullable();

            /*
             * Ultima comunicacion conocida.
             */
            $table->timestampTz('last_seen_at')
                ->nullable();

            $table->string('last_ip_address', 45)
                ->nullable();

            $table->text('last_user_agent')
                ->nullable();

            /*
             * Capacidades detectadas:
             *   qr_reader
             *   camera
             *   offline_queue
             *   etc.
             *
             * settings queda disponible para configuracion especifica
             * de la terminal sin alterar configuracion global de RRHH.
             */
            $table->json('capabilities')
                ->nullable();

            $table->json('settings')
                ->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            /*
             * Un codigo de terminal puede repetirse en otra empresa,
             * pero no dentro de la misma empresa.
             */
            $table->unique(
                ['company_id', 'code'],
                'attendance_terminals_company_code_unique'
            );

            $table->index(
                ['company_id', 'branch_id', 'active'],
                'attendance_terminals_scope_active_idx'
            );

            $table->index(
                'last_seen_at',
                'attendance_terminals_last_seen_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_terminals');
    }
};
