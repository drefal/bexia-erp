<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        Schema::table('pos_points', function (Blueprint $table): void {
            if (! Schema::hasColumn('pos_points', 'product_name_font_size_px')) {
                $table
                    ->unsignedSmallInteger('product_name_font_size_px')
                    ->default(11);
            }

            if (! Schema::hasColumn('pos_points', 'session_close_email_enabled')) {
                $table
                    ->boolean('session_close_email_enabled')
                    ->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'session_close_email_recipients')) {
                $table
                    ->json('session_close_email_recipients')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        Schema::table('pos_points', function (Blueprint $table): void {
            foreach ([
                'session_close_email_recipients',
                'session_close_email_enabled',
                'product_name_font_size_px',
            ] as $column) {
                if (Schema::hasColumn('pos_points', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
