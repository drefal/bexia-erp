<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bexia_menu_snapshots')) {
            return;
        }

        Schema::create('bexia_menu_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label')->nullable();
            $table->json('payload');
            $table->unsignedBigInteger('saved_by_user_id')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bexia_menu_snapshots');
    }
};
