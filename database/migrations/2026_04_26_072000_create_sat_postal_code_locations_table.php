<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sat_postal_code_locations')) {
            return;
        }

        Schema::create('sat_postal_code_locations', function (Blueprint $table): void {
            $table->id();

            $table->string('postal_code', 10)->index();

            $table->string('state_code', 20)->nullable()->index();
            $table->string('state_name', 120)->nullable();

            $table->string('municipality_code', 20)->nullable()->index();
            $table->string('municipality_name', 255)->nullable();

            $table->string('locality_code', 20)->nullable()->index();
            $table->string('locality_name', 255)->nullable();

            $table->string('neighborhood_code', 40)->nullable()->index();
            $table->string('neighborhood_name', 255)->nullable()->index();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['postal_code', 'neighborhood_name']);
            $table->unique(['postal_code', 'neighborhood_code'], 'sat_postal_locations_postal_neighborhood_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_postal_code_locations');
    }
};
