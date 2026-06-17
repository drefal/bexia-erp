<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_company_credentials')) {
            return;
        }

        Schema::table('sat_company_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('sat_company_credentials', 'cer_file_path')) {
                $table->string('cer_file_path')->nullable()->after('certificate_serial');
            }

            if (! Schema::hasColumn('sat_company_credentials', 'key_file_path')) {
                $table->string('key_file_path')->nullable()->after('cer_file_path');
            }

            if (! Schema::hasColumn('sat_company_credentials', 'password_encrypted')) {
                $table->text('password_encrypted')->nullable()->after('key_file_path');
            }

            if (! Schema::hasColumn('sat_company_credentials', 'credential_type')) {
                $table->string('credential_type')->default('efirma')->index()->after('password_encrypted');
            }

            if (! Schema::hasColumn('sat_company_credentials', 'certificate_valid_from')) {
                $table->dateTime('certificate_valid_from')->nullable()->after('credential_type');
            }

            if (! Schema::hasColumn('sat_company_credentials', 'certificate_valid_to')) {
                $table->dateTime('certificate_valid_to')->nullable()->after('certificate_valid_from');
            }

            if (! Schema::hasColumn('sat_company_credentials', 'last_error_message')) {
                $table->text('last_error_message')->nullable()->after('last_verified_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sat_company_credentials')) {
            return;
        }

        Schema::table('sat_company_credentials', function (Blueprint $table) {
            foreach ([
                'last_error_message',
                'certificate_valid_to',
                'certificate_valid_from',
                'credential_type',
                'password_encrypted',
                'key_file_path',
                'cer_file_path',
            ] as $column) {
                if (Schema::hasColumn('sat_company_credentials', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
