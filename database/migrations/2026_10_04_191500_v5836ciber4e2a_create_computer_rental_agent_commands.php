<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('computer_rental_agent_commands')) {
            return;
        }

        Schema::create(
            'computer_rental_agent_commands',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->index();

                $table->foreignId('station_id')
                    ->index();

                $table->uuid('command_uuid')
                    ->unique();

                $table->string('command_type', 40);

                $table->string('status', 40)
                    ->default('pending')
                    ->index();

                $table->foreignId('requested_by_user_id')
                    ->nullable()
                    ->index();

                $table->timestamp('requested_at')
                    ->nullable();

                $table->timestamp('delivered_at')
                    ->nullable();

                $table->timestamp('acknowledged_at')
                    ->nullable();

                $table->timestamp('failed_at')
                    ->nullable();

                $table->text('error_message')
                    ->nullable();

                $table->jsonb('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'station_id',
                        'status',
                        'id',
                    ],
                    'cr_agent_cmd_station_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'computer_rental_agent_commands'
        );
    }
};
