<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('treasury_accounts')) {
            return;
        }

        Schema::table('treasury_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('treasury_accounts', 'is_default_concentrator')) {
                $table->boolean('is_default_concentrator')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('treasury_accounts')) {
            return;
        }

        Schema::table('treasury_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('treasury_accounts', 'is_default_concentrator')) {
                $table->dropColumn('is_default_concentrator');
            }
        });
    }
};
