<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movement_receipt_lines', function (Blueprint $table): void {
            $table->string('disposition', 24)
                ->default('received')
                ->after('quantity')
                ->index();

            $table->foreignId('stock_serial_number_id')
                ->nullable()
                ->after('disposition')
                ->constrained('stock_serial_numbers')
                ->nullOnDelete();

            $table->text('reason')
                ->nullable()
                ->after('stock_serial_number_id');
        });

        DB::statement("
            ALTER TABLE bexia.stock_movement_receipt_lines
            ADD CONSTRAINT sm_receipt_lines_disposition_check
            CHECK (
                disposition IN (
                    'received',
                    'damaged',
                    'missing',
                    'rejected'
                )
            )
        ");

        Schema::create('stock_movement_incidents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements')
                ->cascadeOnDelete();

            $table->foreignId('stock_movement_line_id')
                ->constrained('stock_movement_lines')
                ->cascadeOnDelete();

            $table->foreignId('stock_movement_receipt_id')
                ->constrained('stock_movement_receipts')
                ->cascadeOnDelete();

            $table->foreignId('stock_movement_receipt_line_id')
                ->constrained('stock_movement_receipt_lines')
                ->cascadeOnDelete();

            $table->foreignId('stock_serial_number_id')
                ->nullable()
                ->constrained('stock_serial_numbers')
                ->nullOnDelete();

            $table->string('incident_type', 24);
            $table->decimal('quantity', 18, 6);

            $table->string('status', 24)
                ->default('open');

            $table->text('reason')
                ->nullable();

            $table->string('resolution_type', 32)
                ->nullable();

            $table->foreignId('resolution_location_id')
                ->nullable()
                ->constrained('stock_locations')
                ->nullOnDelete();

            $table->foreignId('resolution_stock_movement_id')
                ->nullable()
                ->constrained('stock_movements')
                ->nullOnDelete();

            $table->text('resolution_notes')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'status'],
                'sm_incidents_company_status_idx'
            );

            $table->index(
                ['stock_movement_id', 'status'],
                'sm_incidents_movement_status_idx'
            );

            $table->index(
                ['stock_movement_line_id', 'incident_type'],
                'sm_incidents_line_type_idx'
            );

            $table->index(
                'stock_serial_number_id',
                'sm_incidents_serial_idx'
            );
        });

        DB::statement("
            ALTER TABLE bexia.stock_movement_incidents
            ADD CONSTRAINT sm_incidents_type_check
            CHECK (
                incident_type IN (
                    'damaged',
                    'missing',
                    'rejected'
                )
            )
        ");

        DB::statement("
            ALTER TABLE bexia.stock_movement_incidents
            ADD CONSTRAINT sm_incidents_status_check
            CHECK (
                status IN (
                    'open',
                    'resolved',
                    'cancelled'
                )
            )
        ");

        DB::statement("
            ALTER TABLE bexia.stock_movement_incidents
            ADD CONSTRAINT sm_incidents_quantity_check
            CHECK (quantity > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_incidents');

        DB::statement("
            ALTER TABLE bexia.stock_movement_receipt_lines
            DROP CONSTRAINT IF EXISTS sm_receipt_lines_disposition_check
        ");

        Schema::table('stock_movement_receipt_lines', function (Blueprint $table): void {
            $table->dropForeign(
                ['stock_serial_number_id']
            );

            $table->dropColumn([
                'disposition',
                'stock_serial_number_id',
                'reason',
            ]);
        });
    }
};
