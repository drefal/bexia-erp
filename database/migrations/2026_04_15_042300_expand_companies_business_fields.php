<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'membership_status')) {
                $table->string('membership_status')->default('active')->after('active');
            }

            if (! Schema::hasColumn('companies', 'paid_until')) {
                $table->date('paid_until')->nullable()->after('membership_status');
            }

            if (! Schema::hasColumn('companies', 'last_payment_at')) {
                $table->date('last_payment_at')->nullable()->after('paid_until');
            }

            if (! Schema::hasColumn('companies', 'max_branches')) {
                $table->unsignedInteger('max_branches')->default(1)->after('last_payment_at');
            }

            if (! Schema::hasColumn('companies', 'max_users')) {
                $table->unsignedInteger('max_users')->default(1)->after('max_branches');
            }

            if (! Schema::hasColumn('companies', 'free_trial')) {
                $table->boolean('free_trial')->default(false)->after('max_users');
            }

            if (! Schema::hasColumn('companies', 'notes')) {
                $table->text('notes')->nullable()->after('free_trial');
            }

            if (! Schema::hasColumn('companies', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('companies', 'logo_compact_path')) {
                $table->string('logo_compact_path')->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'membership_status',
                'paid_until',
                'last_payment_at',
                'max_branches',
                'max_users',
                'free_trial',
                'notes',
                'logo_path',
                'logo_compact_path',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
