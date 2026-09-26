<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('pos_order_payments')
            || Schema::hasColumn(
                'pos_order_payments',
                'pos_session_id'
            )
        ) {
            return;
        }

        Schema::table(
            'pos_order_payments',
            function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('pos_session_id')
                    ->nullable();

                $table->index(
                    'pos_session_id',
                    'pos_order_payments_pos_session_id_idx'
                );
            }
        );

        /*
         * BEXIA_V5836G5H4B_PAYMENT_SESSION
         *
         * Base histórica:
         * un pago pertenecía normalmente a la misma sesión
         * donde se generó el ticket.
         */
        DB::statement("
            UPDATE pos_order_payments p
            SET pos_session_id = o.pos_session_id
            FROM pos_orders o
            WHERE o.id = p.pos_order_id
              AND p.pos_session_id IS NULL
        ");

        /*
         * Si el pago ocurrió FUERA de la ventana de la
         * sesión original, buscar la sesión del mismo PDV
         * que estaba abierta en el momento real del cobro.
         *
         * Esto corrige apartados cobrados días después.
         */
        DB::statement("
            UPDATE pos_order_payments p
            SET pos_session_id = (
                SELECT ps.id
                FROM pos_orders o2
                JOIN pos_sessions ps
                  ON ps.pos_point_id = o2.pos_point_id
                 AND ps.company_id = o2.company_id
                WHERE o2.id = p.pos_order_id
                  AND COALESCE(
                        ps.opened_at,
                        ps.created_at
                      ) <= p.created_at
                  AND (
                        ps.closed_at IS NULL
                        OR ps.closed_at >= p.created_at
                      )
                ORDER BY
                    COALESCE(
                        ps.opened_at,
                        ps.created_at
                    ) DESC,
                    ps.id DESC
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1
                FROM pos_orders o3
                JOIN pos_sessions original_session
                  ON original_session.id = o3.pos_session_id
                WHERE o3.id = p.pos_order_id
                  AND (
                        p.created_at
                            < COALESCE(
                                original_session.opened_at,
                                original_session.created_at
                              )
                        OR (
                            original_session.closed_at
                                IS NOT NULL
                            AND p.created_at
                                > original_session.closed_at
                        )
                      )
            )
              AND EXISTS (
                SELECT 1
                FROM pos_orders o4
                JOIN pos_sessions ps2
                  ON ps2.pos_point_id = o4.pos_point_id
                 AND ps2.company_id = o4.company_id
                WHERE o4.id = p.pos_order_id
                  AND COALESCE(
                        ps2.opened_at,
                        ps2.created_at
                      ) <= p.created_at
                  AND (
                        ps2.closed_at IS NULL
                        OR ps2.closed_at >= p.created_at
                      )
            )
        ");
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('pos_order_payments')
            || ! Schema::hasColumn(
                'pos_order_payments',
                'pos_session_id'
            )
        ) {
            return;
        }

        Schema::table(
            'pos_order_payments',
            function (Blueprint $table) {
                $table->dropIndex(
                    'pos_order_payments_pos_session_id_idx'
                );

                $table->dropColumn('pos_session_id');
            }
        );
    }
};
