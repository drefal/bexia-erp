<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashboard_widget_user_settings')) {
            Schema::create('dashboard_widget_user_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('user_id');
                $table->string('widget_key', 100);
                $table->boolean('is_visible')->default(true);
                $table->integer('sort_order')->default(100);
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'user_id', 'widget_key'], 'dwus_company_user_widget_unique');
                $table->index(['company_id', 'user_id'], 'dwus_company_user_index');
                $table->index(['company_id', 'widget_key'], 'dwus_company_widget_index');
                $table->index(['user_id', 'is_visible'], 'dwus_user_visible_index');
            });
        }

        if (Schema::hasTable('permissions')) {
            foreach ([
                'dashboard.ver',
                'dashboard.configurar',
            ] as $permissionName) {
                $existing = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();

                if ($existing) {
                    DB::table('permissions')
                        ->where('id', $existing->id)
                        ->update([
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('permissions')->insert([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_user_settings');
    }
};
