<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('computer_rental_stations')) {
            return;
        }

        Schema::table(
            'computer_rental_stations',
            function (Blueprint $table): void {
                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_token_hash'
                )) {
                    $table->string(
                        'agent_token_hash',
                        64
                    )->nullable();
                }

                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_hostname'
                )) {
                    $table->string(
                        'agent_hostname',
                        255
                    )->nullable();
                }

                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_machine_guid'
                )) {
                    $table->string(
                        'agent_machine_guid',
                        255
                    )->nullable();
                }

                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_version'
                )) {
                    $table->string(
                        'agent_version',
                        80
                    )->nullable();
                }

                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_windows_user'
                )) {
                    $table->string(
                        'agent_windows_user',
                        255
                    )->nullable();
                }

                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_last_ip'
                )) {
                    $table->string(
                        'agent_last_ip',
                        64
                    )->nullable();
                }

                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_status'
                )) {
                    $table->string(
                        'agent_status',
                        40
                    )
                        ->nullable()
                        ->index(
                            'computer_rental_stations_agent_status_idx'
                        );
                }

                if (! Schema::hasColumn(
                    'computer_rental_stations',
                    'agent_last_error'
                )) {
                    $table->text(
                        'agent_last_error'
                    )->nullable();
                }
            }
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('computer_rental_stations')) {
            return;
        }

        if (
            Schema::hasColumn(
                'computer_rental_stations',
                'agent_status'
            )
        ) {
            Schema::table(
                'computer_rental_stations',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'computer_rental_stations_agent_status_idx'
                    );
                }
            );
        }

        Schema::table(
            'computer_rental_stations',
            function (Blueprint $table): void {
                $columns = [
                    'agent_token_hash',
                    'agent_hostname',
                    'agent_machine_guid',
                    'agent_version',
                    'agent_windows_user',
                    'agent_last_ip',
                    'agent_status',
                    'agent_last_error',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn(
                        'computer_rental_stations',
                        $column
                    )) {
                        $table->dropColumn($column);
                    }
                }
            }
        );
    }
};
