<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'company_group_id')) {
                $table->foreignId('company_group_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('company_groups')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'company_group_id')) {
                $table->dropConstrainedForeignId('company_group_id');
            }
        });
    }
};
