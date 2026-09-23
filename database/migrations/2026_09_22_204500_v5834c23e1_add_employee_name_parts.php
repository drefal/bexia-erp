<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('first_name')->nullable();
            $table->string('paternal_surname')->nullable();
            $table->string('maternal_surname')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn([
                'first_name',
                'paternal_surname',
                'maternal_surname',
            ]);
        });
    }
};
