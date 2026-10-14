<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BEXIA_V5836_POS_AREAS3J
final class PosSalesAreasDisplay
{
    public static function resolve(
        int $sessionId,
        object $pos,
        array $summary
    ): array {
        if (
            ! Schema::hasColumn(
                'pos_points',
                'sales_breakdown_mode'
            )
        ) {
            return [];
        }

        $mode = strtolower(trim(
            (string) ($pos->sales_breakdown_mode ?? 'default')
        ));

        $status = (string) (
            $summary['session']['status'] ?? ''
        );

        $snapshot = $summary['session'][
            'papelon_close_snapshot'
        ] ?? null;

        /*
         * BEXIA_V5836_POS_AREAS3I
         *
         * Sesiones cerradas:
         * nunca recalcular las areas usando categorias actuales.
         */
        if ($status === 'closed') {
            if (
                is_array($snapshot)
                && (int) ($snapshot['version'] ?? 0) === 2
                && ($snapshot['breakdown_mode'] ?? '') === 'areas'
                && is_array($snapshot['sections'] ?? null)
            ) {
                return [
                    'sections' => $snapshot['sections'],
                    'products_by_section' =>
                        $snapshot['products_by_section'] ?? [],
                ];
            }

            return [];
        }

        if ($mode !== 'areas') {
            return [];
        }

        $companyId = (int) ($pos->company_id ?? 0);

        if (
            $companyId <= 0
            || ! Schema::hasColumn(
                'companies',
                'pos_sales_areas'
            )
        ) {
            return [];
        }

        $raw = DB::table('companies')
            ->where('id', $companyId)
            ->value('pos_sales_areas');

        $areas = is_array($raw)
            ? $raw
            : json_decode((string) ($raw ?? '[]'), true);

        if (! is_array($areas) || $areas === []) {
            return [];
        }

        $hasValidArea = false;

        foreach ($areas as $area) {
            if (
                is_array($area)
                && trim((string) ($area['name'] ?? '')) !== ''
                && ! empty($area['category_ids'])
            ) {
                $hasValidArea = true;
                break;
            }
        }

        if (! $hasValidArea) {
            return [];
        }

        $data = PosPapelonCloseSummary::build($sessionId, true);

        if (! ($data['ok'] ?? false)) {
            return [];
        }

        return [
            'sections' => $data['sections'] ?? [],
            'products_by_section' =>
                $data['products_by_section'] ?? [],
        ];
    }
}
