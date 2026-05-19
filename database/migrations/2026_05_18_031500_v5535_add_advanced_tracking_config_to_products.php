<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'advanced_tracking_mode')) {
                $table->string('advanced_tracking_mode', 30)
                    ->default('none')
                    ->after('tracking');
            }

            if (! Schema::hasColumn('products', 'advanced_tracking_fields')) {
                $table->json('advanced_tracking_fields')
                    ->nullable()
                    ->after('advanced_tracking_mode');
            }

            if (! Schema::hasColumn('products', 'advanced_tracking_notes')) {
                $table->text('advanced_tracking_notes')
                    ->nullable()
                    ->after('advanced_tracking_fields');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'advanced_tracking_notes',
                'advanced_tracking_fields',
                'advanced_tracking_mode',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
