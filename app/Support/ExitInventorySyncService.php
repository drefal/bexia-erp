<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExitInventorySyncService
{
    public function syncSubmission(int $submissionId): int
    {
        if (! Schema::hasTable('form_submissions') || ! Schema::hasTable('exit_inventory_items')) {
            return 0;
        }

        $submission = DB::table('form_submissions')
            ->where('id', $submissionId)
            ->first();

        if (! $submission) {
            return 0;
        }

        $data = $this->decodeData($submission->data ?? null);
        $items = $data['items'] ?? [];

        if (! is_array($items)) {
            return 0;
        }

        $count = 0;

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['descripcion'] ?? $item['description'] ?? ''));
            $quantity = (float) ($item['cantidad'] ?? $item['quantity'] ?? 0);

            if ($description === '' && $quantity <= 0) {
                continue;
            }

            $existing = DB::table('exit_inventory_items')
                ->where('form_submission_id', $submission->id)
                ->where('item_index', $index)
                ->first();

            $base = [
                'form_submission_id' => $submission->id,
                'company_id' => $submission->company_id ?? null,
                'item_index' => $index,
                'folio' => $submission->folio ?? null,
                'item_description' => $description,
                'requested_quantity' => $quantity,
                'source_warehouse_label' => $data['almacen_envio'] ?? null,
                'destination_warehouse_label' => $data['almacen_recepcion'] ?? null,
                'project_label' => $data['proyecto'] ?? null,
                'raw_item' => json_encode($item, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('exit_inventory_items')
                    ->where('id', $existing->id)
                    ->update($base);
            } else {
                $base['delivery_status'] = 'pending';
                $base['created_at'] = now();

                DB::table('exit_inventory_items')->insert($base);
            }

            $count++;
        }

        $this->refreshSubmissionDeliveryStatus($submission->id);

        return $count;
    }

    public function syncRecent(int $limit = 200): int
    {
        if (! Schema::hasTable('form_submissions')) {
            return 0;
        }

        $ids = DB::table('form_submissions')
            ->whereNotNull('folio')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        $total = 0;

        foreach ($ids as $id) {
            $total += $this->syncSubmission((int) $id);
        }

        return $total;
    }

    public function refreshSubmissionDeliveryStatus(int $submissionId): void
    {
        if (! Schema::hasTable('exit_inventory_items')) {
            return;
        }

        $items = DB::table('exit_inventory_items')
            ->where('form_submission_id', $submissionId)
            ->get();

        foreach ($items as $item) {
            $requested = (float) ($item->requested_quantity ?? 0);
            $delivered = (float) ($item->delivered_quantity ?? 0);

            $status = 'pending';

            if ($requested > 0 && $delivered >= $requested) {
                $status = 'delivered';
            } elseif ($delivered > 0) {
                $status = 'partial';
            }

            DB::table('exit_inventory_items')
                ->where('id', $item->id)
                ->update([
                    'delivery_status' => $status,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function decodeData($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data) && trim($data) !== '') {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
