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
            if (! Schema::hasColumn('companies', 'billing_pac_provider')) {
                $table->string('billing_pac_provider', 40)->nullable()->default('sw')->after('id');
            }

            if (! Schema::hasColumn('companies', 'billing_pac_username')) {
                $table->string('billing_pac_username')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_pac_password')) {
                $table->text('billing_pac_password')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_pac_test_env')) {
                $table->boolean('billing_pac_test_env')->default(true);
            }

            if (! Schema::hasColumn('companies', 'billing_trusted_exporter_number')) {
                $table->string('billing_trusted_exporter_number', 80)->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_pac_last_test_status')) {
                $table->string('billing_pac_last_test_status', 40)->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_pac_last_test_message')) {
                $table->text('billing_pac_last_test_message')->nullable();
            }

            if (! Schema::hasColumn('companies', 'billing_pac_last_test_at')) {
                $table->timestamp('billing_pac_last_test_at')->nullable();
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
                'billing_pac_provider',
                'billing_pac_username',
                'billing_pac_password',
                'billing_pac_test_env',
                'billing_trusted_exporter_number',
                'billing_pac_last_test_status',
                'billing_pac_last_test_message',
                'billing_pac_last_test_at',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
