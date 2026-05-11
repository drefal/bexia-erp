<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('companies') && !Schema::hasColumn('companies','organization_id')) {
            Schema::table('companies', function (Blueprint $t) {
                $t->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });
        }
        if (Schema::hasTable('users') && !Schema::hasColumn('users','organization_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });
        }
    }
    public function down(): void {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies','organization_id')) {
            Schema::table('companies', function (Blueprint $t) {
                $t->dropColumn('organization_id');
            });
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users','organization_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('organization_id');
            });
        }
    }
};
