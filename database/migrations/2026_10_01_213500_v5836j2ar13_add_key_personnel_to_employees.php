<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('employees')
            && ! Schema::hasColumn('employees', 'is_key_personnel')
        ) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->boolean('is_key_personnel')
                    ->default(false);
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('employees')
            && Schema::hasColumn('employees', 'is_key_personnel')
        ) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropColumn('is_key_personnel');
            });
        }
    }
};
