<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'sat_constancia_path')) {
                $table->string('sat_constancia_path')->nullable()->after('fiscal_postal_code');
            }

            if (! Schema::hasColumn('companies', 'sat_constancia_uploaded_at')) {
                $table->timestamp('sat_constancia_uploaded_at')->nullable()->after('sat_constancia_path');
            }

            if (! Schema::hasColumn('companies', 'sat_constancia_parsed_at')) {
                $table->timestamp('sat_constancia_parsed_at')->nullable()->after('sat_constancia_uploaded_at');
            }

            if (! Schema::hasColumn('companies', 'sat_constancia_parsed_data')) {
                $table->json('sat_constancia_parsed_data')->nullable()->after('sat_constancia_parsed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            foreach ([
                'sat_constancia_parsed_data',
                'sat_constancia_parsed_at',
                'sat_constancia_uploaded_at',
                'sat_constancia_path',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
