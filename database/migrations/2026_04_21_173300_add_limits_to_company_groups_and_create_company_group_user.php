<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('company_groups', 'max_companies')) {
                $table->unsignedInteger('max_companies')->nullable()->after('active');
            }
            if (! Schema::hasColumn('company_groups', 'max_branches')) {
                $table->unsignedInteger('max_branches')->nullable()->after('max_companies');
            }
            if (! Schema::hasColumn('company_groups', 'max_users')) {
                $table->unsignedInteger('max_users')->nullable()->after('max_branches');
            }
            if (! Schema::hasColumn('company_groups', 'free_trial')) {
                $table->boolean('free_trial')->default(false)->after('max_users');
            }
        });

        if (! Schema::hasTable('company_group_user')) {
            Schema::create('company_group_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_group_id')->constrained('company_groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_group_admin')->default(false);
                $table->timestamps();

                $table->unique(['company_group_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_group_user')) {
            Schema::dropIfExists('company_group_user');
        }

        Schema::table('company_groups', function (Blueprint $table) {
            foreach (['max_companies', 'max_branches', 'max_users', 'free_trial'] as $column) {
                if (Schema::hasColumn('company_groups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
