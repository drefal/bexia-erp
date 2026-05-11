<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si NO existe, la creamos
        if (! Schema::hasTable('company_user')) {
            Schema::create('company_user', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('user_id');

                // claves foráneas (ajusta nombres de tablas si difieren)
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

                // clave compuesta (o al menos unique)
                $table->primary(['company_id', 'user_id']);
            });
            return;
        }

        // Si YA existe, solo agregamos lo que falte (idempotente)
        Schema::table('company_user', function (Blueprint $table) {
            if (! Schema::hasColumn('company_user', 'company_id')) {
                $table->unsignedBigInteger('company_id')->after('id'); // si no hay id, quita ->after(...)
            }
            if (! Schema::hasColumn('company_user', 'user_id')) {
                $table->unsignedBigInteger('user_id');
            }

            // añade foráneas si no existen (nombres de constraint genéricos)
            try {
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            } catch (\Throwable $e) {}
            try {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            } catch (\Throwable $e) {}

            // garantiza unicidad de la pareja
            try {
                $table->unique(['company_id', 'user_id'], 'company_user_unique');
            } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
