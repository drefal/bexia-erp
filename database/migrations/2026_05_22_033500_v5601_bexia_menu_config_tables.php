<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bexia_menu_groups')) {
            Schema::create('bexia_menu_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->string('default_label')->nullable();
                $table->unsignedInteger('sort')->default(9999);
                $table->boolean('is_visible')->default(true);
                $table->boolean('is_system')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['sort', 'label']);
                $table->index('is_visible');
            });
        }

        if (! Schema::hasTable('bexia_menu_items')) {
            Schema::create('bexia_menu_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('group_id')
                    ->constrained('bexia_menu_groups')
                    ->cascadeOnDelete();

                $table->string('key')->unique();
                $table->string('label');
                $table->string('default_label')->nullable();
                $table->unsignedInteger('sort')->default(9999);
                $table->boolean('is_visible')->default(true);
                $table->boolean('is_system')->default(true);

                $table->string('source')->nullable();
                $table->string('file_path')->nullable();
                $table->string('class_name')->nullable();
                $table->string('route_name')->nullable();
                $table->string('permission_name')->nullable();

                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['group_id', 'sort', 'label']);
                $table->index('is_visible');
                $table->index('source');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bexia_menu_items');
        Schema::dropIfExists('bexia_menu_groups');
    }
};
