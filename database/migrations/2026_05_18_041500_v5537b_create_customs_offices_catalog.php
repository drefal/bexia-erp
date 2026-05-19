<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customs_offices')) {
            Schema::create('customs_offices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->index();
                $table->string('code', 20)->nullable();
                $table->string('name', 160);
                $table->string('display_name', 220)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name']);
            });
        }

        $now = now();

        foreach ([
            ['code' => '16', 'name' => 'MANZANILLO', 'display_name' => 'MANZANILLO'],
            ['code' => '24', 'name' => 'NUEVO LAREDO', 'display_name' => 'NUEVO LAREDO'],
            ['code' => '43', 'name' => 'VERACRUZ', 'display_name' => 'VERACRUZ'],
            ['code' => '47', 'name' => 'AEROPUERTO INTERNACIONAL DE LA CIUDAD DE MEXICO', 'display_name' => 'AICM / CIUDAD DE MEXICO'],
            ['code' => '48', 'name' => 'GUADALAJARA', 'display_name' => 'GUADALAJARA'],
            ['code' => '52', 'name' => 'MONTERREY', 'display_name' => 'MONTERREY'],
            ['code' => '65', 'name' => 'TOLUCA', 'display_name' => 'TOLUCA'],
            ['code' => '80', 'name' => 'COLOMBIA', 'display_name' => 'COLOMBIA'],
        ] as $row) {
            DB::table('customs_offices')->updateOrInsert(
                [
                    'company_id' => null,
                    'name' => $row['name'],
                ],
                [
                    'code' => $row['code'],
                    'display_name' => $row['display_name'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_offices');
    }
};
