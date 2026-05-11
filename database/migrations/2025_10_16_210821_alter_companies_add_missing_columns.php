<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'name')) {
                $table->string('name')->index()->after('id');
            }
            if (!Schema::hasColumn('companies', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }
            if (!Schema::hasColumn('companies', 'tax_id')) {
                $table->string('tax_id')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('companies', 'active')) {
                $table->boolean('active')->default(true)->after('tax_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'active')) {
                $table->dropColumn('active');
            }
            if (Schema::hasColumn('companies', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
            if (Schema::hasColumn('companies', 'slug')) {
                $table->dropUnique('companies_slug_unique');
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('companies', 'name')) {
                $table->dropIndex(['name']);
                $table->dropColumn('name');
            }
        });
    }
};
