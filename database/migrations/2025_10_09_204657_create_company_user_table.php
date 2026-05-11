<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('user_id');

            $t->primary(['company_id','user_id']);

            // Si quieres FKs (requiere que las tablas existan ya):
            // $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            // $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
