<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_request_status_logs')) {
            Schema::create('purchase_request_status_logs', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('purchase_request_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name')->nullable();

                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();

                $table->string('event', 80)->default('status_changed')->index();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->foreign('purchase_request_id')
                    ->references('id')
                    ->on('purchase_requests')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('purchase_requests') && Schema::hasTable('purchase_request_status_logs')) {
            $requests = DB::table('purchase_requests')
                ->whereNotExists(function ($query): void {
                    $query
                        ->select(DB::raw(1))
                        ->from('purchase_request_status_logs')
                        ->whereColumn('purchase_request_status_logs.purchase_request_id', 'purchase_requests.id');
                })
                ->get();

            foreach ($requests as $request) {
                DB::table('purchase_request_status_logs')->insert([
                    'purchase_request_id' => $request->id,
                    'company_id' => $request->company_id ?? null,
                    'user_id' => $request->requested_by_user_id ?? null,
                    'user_name' => null,
                    'from_status' => null,
                    'to_status' => $request->status ?? 'draft',
                    'event' => 'created',
                    'notes' => 'Registro inicial de historial.',
                    'created_at' => $request->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_status_logs');
    }
};
