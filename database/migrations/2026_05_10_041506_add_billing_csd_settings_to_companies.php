<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'billing_csd_certificate_path')) {
                $table->string('billing_csd_certificate_path')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_key_path')) {
                $table->string('billing_csd_key_path')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_password')) {
                $table->text('billing_csd_password')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_serial_number')) {
                $table->string('billing_csd_serial_number', 120)->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_rfc')) {
                $table->string('billing_csd_rfc', 30)->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_valid_from')) {
                $table->timestamp('billing_csd_valid_from')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_valid_to')) {
                $table->timestamp('billing_csd_valid_to')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_last_test_status')) {
                $table->string('billing_csd_last_test_status', 40)->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_last_test_message')) {
                $table->text('billing_csd_last_test_message')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_csd_last_test_at')) {
                $table->timestamp('billing_csd_last_test_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            foreach ([
                'billing_csd_certificate_path',
                'billing_csd_key_path',
                'billing_csd_password',
                'billing_csd_serial_number',
                'billing_csd_rfc',
                'billing_csd_valid_from',
                'billing_csd_valid_to',
                'billing_csd_last_test_status',
                'billing_csd_last_test_message',
                'billing_csd_last_test_at',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
