<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_cfdi_cancellation_reasons')) {
            Schema::create('sat_cfdi_cancellation_reasons', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 2)->unique();
                $table->string('name');
                $table->boolean('requires_replacement_uuid')->default(false);
                $table->boolean('active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        DB::table('sat_cfdi_cancellation_reasons')->upsert([
            [
                'code' => '01',
                'name' => 'Comprobante emitido con errores con relación',
                'requires_replacement_uuid' => true,
                'active' => true,
                'notes' => 'Requiere UUID del CFDI sustituto.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '02',
                'name' => 'Comprobante emitido con errores sin relación',
                'requires_replacement_uuid' => false,
                'active' => true,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '03',
                'name' => 'No se llevó a cabo la operación',
                'requires_replacement_uuid' => false,
                'active' => true,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '04',
                'name' => 'Operación nominativa relacionada en una factura global',
                'requires_replacement_uuid' => false,
                'active' => true,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['code'], ['name', 'requires_replacement_uuid', 'active', 'notes', 'updated_at']);

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'cfdi_cancel_reason_code')) {
                $table->string('cfdi_cancel_reason_code', 2)->nullable()->after('cfdi_uuid');
            }

            if (! Schema::hasColumn('invoices', 'cfdi_cancel_replacement_uuid')) {
                $table->string('cfdi_cancel_replacement_uuid', 36)->nullable()->after('cfdi_cancel_reason_code');
            }

            if (! Schema::hasColumn('invoices', 'cfdi_cancel_status')) {
                $table->string('cfdi_cancel_status', 50)->nullable()->after('cfdi_cancel_replacement_uuid');
            }

            if (! Schema::hasColumn('invoices', 'cfdi_cancel_status_message')) {
                $table->text('cfdi_cancel_status_message')->nullable()->after('cfdi_cancel_status');
            }

            if (! Schema::hasColumn('invoices', 'cfdi_cancel_internal_comment')) {
                $table->text('cfdi_cancel_internal_comment')->nullable()->after('cfdi_cancel_status_message');
            }

            if (! Schema::hasColumn('invoices', 'cfdi_cancel_requested_at')) {
                $table->timestamp('cfdi_cancel_requested_at')->nullable()->after('cfdi_cancel_internal_comment');
            }

            if (! Schema::hasColumn('invoices', 'cfdi_cancelled_at')) {
                $table->timestamp('cfdi_cancelled_at')->nullable()->after('cfdi_cancel_requested_at');
            }

            if (! Schema::hasColumn('invoices', 'cfdi_cancel_ack_path')) {
                $table->string('cfdi_cancel_ack_path')->nullable()->after('cfdi_cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            foreach ([
                'cfdi_cancel_reason_code',
                'cfdi_cancel_replacement_uuid',
                'cfdi_cancel_status',
                'cfdi_cancel_status_message',
                'cfdi_cancel_internal_comment',
                'cfdi_cancel_requested_at',
                'cfdi_cancelled_at',
                'cfdi_cancel_ack_path',
            ] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('sat_cfdi_cancellation_reasons');
    }
};
