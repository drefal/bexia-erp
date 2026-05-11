<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sat_account_groupings')) {
            return;
        }

        Schema::create('sat_account_groupings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->unsignedSmallInteger('level')->nullable();
            $table->string('name');
            $table->string('account_type', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_account_groupings');
    }
};
