<?php

namespace App\Support\CustomBalloons;

use App\Models\CustomBalloonOrder;
use App\Support\CustomBalloonsAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BalloonOrderPdfService
{
    public function download(
        int $orderId,
        string $mode
    ): StreamedResponse {
        abort_unless(
            CustomBalloonsAccess::can('custom_balloons.view'),
            403
        );

        $bytes = $this->renderBytes($orderId, $mode);
        $order = $this->findOrder($orderId);

        $name = 'pedido-globos-'
            . preg_replace('/[^A-Za-z0-9_-]/', '-', $order->number)
            . ($mode === 'double' ? '-doble' : '')
            . '.pdf';

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            $name,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function renderBytes(
        int $orderId,
        string $mode
    ): string {
        if (! in_array($mode, ['single', 'double'], true)) {
            throw new \InvalidArgumentException('Modo PDF inválido.');
        }

        $order = $this->findOrder($orderId);
        $order->load('lines');

        $company = DB::table('companies')
            ->where('id', 3)
            ->first(['logo_path', 'logo_compact_path']);

        $logo = $this->imageDataUri(
            $company?->logo_path
                ?: $company?->logo_compact_path
        );

        $lines = [];

        foreach ($order->lines as $line) {
            $metadata = is_array($line->metadata)
                ? $line->metadata
                : [];

            $lines[] = [
                'name' => (string) (
                    $metadata['product_name']
                    ?? 'Producto personalizado'
                ),
                'size' => (string) (
                    $metadata['size']
                    ?? $line->size_name_snapshot
                    ?? ''
                ),
                'inflation' => (string) (
                    $metadata['inflation'] ?? ''
                ),
                'design' => $line->model_id
                    ? (string) $line->model_name_snapshot
                    : '',
                'personalization' => (string) (
                    $line->personalization_text ?? ''
                ),
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
                'image' => $this->imageDataUri(
                    $metadata['model_image_path']
                    ?? $line->design_image_path
                    ?? null
                ),
            ];
        }

        $data = [
            'order' => $order,
            'lines' => $lines,
            'logo' => $logo,
            'double' => $mode === 'double',
            'createdLocal' => $order->ordered_at
                ? $order->ordered_at->copy()
                    ->timezone('America/Mexico_City')
                    ->format('d/m/Y H:i')
                : '—',
            'deliveryLocal' => $order->promised_at
                ? $order->promised_at->copy()
                    ->timezone('America/Mexico_City')
                    ->format('d/m/Y H:i')
                : '—',
            'printedLocal' => now('America/Mexico_City')
                ->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView(
            'pdf.custom-balloons.order',
            $data
        )->setPaper(
            'letter',
            $mode === 'double' ? 'landscape' : 'portrait'
        );

        return $pdf->output();
    }

    private function findOrder(int $orderId): CustomBalloonOrder
    {
        return CustomBalloonOrder::query()
            ->where('company_id', 3)
            ->findOrFail($orderId);
    }

    private function imageDataUri(?string $value): ?string
    {
        $value = trim((string) $value);

        if (
            $value === ''
            || str_contains($value, '..')
            || preg_match('~^[a-z]+://~i', $value)
        ) {
            return null;
        }

        $paths = [
            Storage::disk('public')->path($value),
            public_path(ltrim($value, '/')),
        ];

        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $mime = mime_content_type($path);

            if (! in_array(
                $mime,
                ['image/png', 'image/jpeg', 'image/gif'],
                true
            )) {
                continue;
            }

            $bytes = file_get_contents($path);

            if ($bytes === false || strlen($bytes) > 5000000) {
                continue;
            }

            return 'data:' . $mime . ';base64,'
                . base64_encode($bytes);
        }

        return null;
    }
}
