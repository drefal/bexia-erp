<?php

namespace App\Support\Expenses;

use App\Models\Attachment;
use App\Models\ExpenseReportLine;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ExpenseReceiptService
{
    public static function extractCfdiDataFromUpload(mixed $state): ?array
    {
        if (blank($state)) {
            return null;
        }

        $path = static::resolveUploadPath($state);

        if (! $path || ! is_file($path)) {
            return null;
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xml') {
            return null;
        }

        return static::extractCfdiDataFromXmlFile($path);
    }

    public static function extractCfdiDataFromXmlFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string(
            $contents,
            \SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOCDATA
        );

        if ($xml === false) {
            return null;
        }

        $rootAttrs = $xml->attributes();

        $subtotal = static::floatAttribute(
            $rootAttrs,
            ['SubTotal', 'Subtotal', 'subTotal', 'subtotal']
        );

        $total = static::floatAttribute(
            $rootAttrs,
            ['Total', 'total']
        );

        $currency = static::stringAttribute(
            $rootAttrs,
            ['Moneda', 'moneda']
        );

        $satPaymentForm = static::stringAttribute(
            $rootAttrs,
            ['FormaPago', 'formaPago', 'formapago']
        );

        $satPaymentMethod = static::stringAttribute(
            $rootAttrs,
            ['MetodoPago', 'metodoPago', 'metodopago']
        );

        $paymentMethod = static::mapSatPaymentForm(
            $satPaymentForm
        );

        $fechaRaw = static::stringAttribute(
            $rootAttrs,
            ['Fecha', 'fecha']
        );

        $expenseDate = null;

        if ($fechaRaw) {
            try {
                $expenseDate = \Carbon\Carbon::parse(
                    $fechaRaw
                )->toDateString();
            } catch (\Throwable) {
                $expenseDate = substr($fechaRaw, 0, 10);
            }
        }

        $issuer = null;

        $issuerNodes = $xml->xpath(
            '/*[local-name()="Comprobante"]/*[local-name()="Emisor"]'
        );

        if ($issuerNodes && isset($issuerNodes[0])) {
            $issuer = $issuerNodes[0];
        }

        $supplierName = null;
        $supplierRfc = null;

        if ($issuer) {
            $attrs = $issuer->attributes();

            $supplierName = static::stringAttribute(
                $attrs,
                ['Nombre', 'nombre']
            );

            $supplierRfc = static::stringAttribute(
                $attrs,
                ['Rfc', 'RFC', 'rfc']
            );
        }

        $uuid = null;

        $stampNodes = $xml->xpath(
            '//*[local-name()="TimbreFiscalDigital"]'
        );

        if ($stampNodes && isset($stampNodes[0])) {
            $attrs = $stampNodes[0]->attributes();

            $uuid = static::normalizeUuid(
                static::stringAttribute(
                    $attrs,
                    ['UUID', 'Uuid', 'uuid']
                )
            );
        }

        $ivaAmount = 0.0;
        $ivaRates = [];
        $hasExemptIva = false;

        /*
         * Solo usamos los impuestos globales del Comprobante.
         * No sumamos impuestos de conceptos para evitar duplicarlos.
         */
        $taxNodes = $xml->xpath(
            '/*[local-name()="Comprobante"]'
            . '/*[local-name()="Impuestos"]'
            . '/*[local-name()="Traslados"]'
            . '/*[local-name()="Traslado"]'
        );

        if ($taxNodes) {
            foreach ($taxNodes as $taxNode) {
                $attrs = $taxNode->attributes();

                $taxCode = static::stringAttribute(
                    $attrs,
                    ['Impuesto', 'impuesto']
                );

                /*
                 * SAT 002 = IVA.
                 */
                if ((string) $taxCode !== '002') {
                    continue;
                }

                $factor = static::stringAttribute(
                    $attrs,
                    ['TipoFactor', 'tipoFactor']
                );

                if (strcasecmp((string) $factor, 'Exento') === 0) {
                    $hasExemptIva = true;
                    continue;
                }

                $amount = static::floatAttribute(
                    $attrs,
                    ['Importe', 'importe']
                );

                if ($amount !== null) {
                    $ivaAmount += $amount;
                }

                $rate = static::floatAttribute(
                    $attrs,
                    ['TasaOCuota', 'tasaOCuota']
                );

                if ($rate !== null) {
                    $ivaRates[] = round($rate, 6);
                }
            }
        }

        $ivaAmount = round($ivaAmount, 2);
        $ivaRates = array_values(
            array_unique($ivaRates)
        );

        $ivaMode = 'manual';

        if (count($ivaRates) === 1) {
            $rate = (float) $ivaRates[0];

            if (abs($rate - 0.16) < 0.000001) {
                $ivaMode = 'iva_16';
            } elseif (abs($rate - 0.08) < 0.000001) {
                $ivaMode = 'iva_8';
            } elseif (abs($rate) < 0.000001) {
                $ivaMode = 'iva_0';
            }
        } elseif (
            count($ivaRates) === 0
            && $hasExemptIva
            && abs($ivaAmount) < 0.01
        ) {
            $ivaMode = 'exempt';
        } elseif (
            count($ivaRates) === 0
            && $subtotal !== null
            && $total !== null
            && abs($total - $subtotal) < 0.01
        ) {
            $ivaMode = 'iva_0';
        }

        return [
            'supplier_name' => $supplierName,
            'supplier_rfc' => $supplierRfc
                ? strtoupper(trim($supplierRfc))
                : null,
            'expense_date' => $expenseDate,
            'cfdi_uuid' => $uuid,
            'subtotal' => $subtotal !== null
                ? round($subtotal, 2)
                : null,
            'tax_amount' => round($ivaAmount, 2),
            'total_amount' => $total !== null
                ? round($total, 2)
                : null,
            'currency_code' => $currency
                ? strtoupper(trim($currency))
                : 'MXN',
            'sat_payment_form' => $satPaymentForm,
            'sat_payment_method' => $satPaymentMethod,
            'payment_method' => $paymentMethod,
            'iva_mode' => $ivaMode,
            'iva_rates' => $ivaRates,
            'has_exempt_iva' => $hasExemptIva,
        ];
    }

    public static function mapSatPaymentForm(
        ?string $satCode
    ): ?string {
        if (blank($satCode)) {
            return null;
        }

        $satCode = str_pad(
            trim((string) $satCode),
            2,
            '0',
            STR_PAD_LEFT
        );

        return match ($satCode) {
            '01' => 'cash',

            '03' => 'transfer',

            '04',
            '28',
            '29' => 'card',

            /*
             * El catálogo interno de gastos actualmente
             * es más simple que el catálogo SAT.
             * Los demás códigos se conservan en metadata
             * y se presentan como "Otro".
             */
            default => 'other',
        };
    }

    protected static function stringAttribute(
        mixed $attributes,
        array $names
    ): ?string {
        foreach ($names as $name) {
            if (isset($attributes[$name])) {
                $value = trim(
                    (string) $attributes[$name]
                );

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    protected static function floatAttribute(
        mixed $attributes,
        array $names
    ): ?float {
        $value = static::stringAttribute(
            $attributes,
            $names
        );

        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', $value);
    }

    public static function extractUuidFromUpload(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $path = static::resolveUploadPath($state);

        if (! $path || ! is_file($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml' => static::extractUuidFromXmlFile($path),
            'pdf' => static::extractUuidFromPdfFile($path),
            default => null,
        };
    }

    public static function extractUuidFromXmlFile(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string(
            $contents,
            \SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOCDATA
        );

        if ($xml !== false) {
            $nodes = $xml->xpath(
                '//*[local-name()="TimbreFiscalDigital"]'
            );

            if ($nodes && isset($nodes[0])) {
                $attributes = $nodes[0]->attributes();

                foreach (['UUID', 'Uuid', 'uuid'] as $attribute) {
                    if (isset($attributes[$attribute])) {
                        $uuid = static::normalizeUuid(
                            (string) $attributes[$attribute]
                        );

                        if ($uuid) {
                            return $uuid;
                        }
                    }
                }
            }
        }

        return static::findUuidInText($contents);
    }

    public static function extractUuidFromPdfFile(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $command = [
            'pdftotext',
            '-layout',
            $path,
            '-',
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(
            $command,
            $descriptors,
            $pipes
        );

        if (! is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || ! is_string($stdout)) {
            return null;
        }

        return static::findUuidInText($stdout);
    }

    public static function findUuidInText(string $text): ?string
    {
        if (preg_match(
            '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5]?[0-9a-fA-F]{3}-[89abAB]?[0-9a-fA-F]{3}-[0-9a-fA-F]{12}\b/',
            $text,
            $matches
        )) {
            return static::normalizeUuid($matches[0]);
        }

        if (preg_match(
            '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/',
            $text,
            $matches
        )) {
            return static::normalizeUuid($matches[0]);
        }

        return null;
    }

    public static function normalizeUuid(?string $uuid): ?string
    {
        $uuid = strtoupper(trim((string) $uuid));

        if ($uuid === '') {
            return null;
        }

        if (! preg_match(
            '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/',
            $uuid
        )) {
            return null;
        }

        return $uuid;
    }

    public static function resolveUploadPath(mixed $state): ?string
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state->getRealPath();
        }

        if (is_array($state)) {
            $state = reset($state);
        }

        if (! is_string($state) || trim($state) === '') {
            return null;
        }

        if (is_file($state)) {
            return $state;
        }

        $disk = Storage::disk('local');

        if ($disk->exists($state)) {
            return $disk->path($state);
        }

        return null;
    }

    public static function prepareLineForFill(array $data): array
    {
        $metadata = static::metadataArray(
            $data['metadata'] ?? null
        );

        $receipt = is_array($metadata['receipt'] ?? null)
            ? $metadata['receipt']
            : [];

        $data['receipt_xml_path'] =
            $receipt['xml_path'] ?? null;

        $data['receipt_pdf_path'] =
            $receipt['pdf_path'] ?? null;

        $data['evidence_image_path'] =
            $receipt['evidence_image_path'] ?? null;

        $tax = is_array($metadata['tax'] ?? null)
            ? $metadata['tax']
            : [];

        $data['iva_mode'] =
            $tax['mode'] ?? 'iva_16';

        return $data;
    }

    public static function prepareLineForSave(array $data): array
    {
        $metadata = static::metadataArray(
            $data['metadata'] ?? null
        );

        $receipt = is_array($metadata['receipt'] ?? null)
            ? $metadata['receipt']
            : [];

        if (array_key_exists('receipt_xml_path', $data)) {
            $receipt['xml_path'] =
                $data['receipt_xml_path'] ?: null;

            unset($data['receipt_xml_path']);
        }

        if (array_key_exists('receipt_pdf_path', $data)) {
            $receipt['pdf_path'] =
                $data['receipt_pdf_path'] ?: null;

            unset($data['receipt_pdf_path']);
        }

        if (array_key_exists('evidence_image_path', $data)) {
            $receipt['evidence_image_path'] =
                $data['evidence_image_path'] ?: null;

            unset($data['evidence_image_path']);
        }

        $receipt['updated_at'] = now()->toIso8601String();

        if (isset($data['sat_payment_form'])) {
            $receipt['sat_payment_form'] =
                $data['sat_payment_form'] ?: null;

            unset($data['sat_payment_form']);
        }

        if (isset($data['sat_payment_method'])) {
            $receipt['sat_payment_method'] =
                $data['sat_payment_method'] ?: null;

            unset($data['sat_payment_method']);
        }

        $metadata['receipt'] = $receipt;

        if (array_key_exists('iva_mode', $data)) {
            $mode = (string) ($data['iva_mode'] ?: 'iva_16');

            $metadata['tax'] = [
                'mode' => $mode,
                'rate' => match ($mode) {
                    'iva_16' => 16,
                    'iva_8' => 8,
                    'iva_0' => 0,
                    'exempt' => 0,
                    default => null,
                },
                'automatic' => $mode !== 'manual',
                'updated_at' => now()->toIso8601String(),
            ];

            unset($data['iva_mode']);
        }

        $data['metadata'] = $metadata;

        return $data;
    }

    public static function syncAttachments(
        ExpenseReportLine $line
    ): void {
        $line->loadMissing('report');

        $metadata = static::metadataArray(
            $line->metadata
        );

        $receipt = is_array($metadata['receipt'] ?? null)
            ? $metadata['receipt']
            : [];

        $paths = array_filter([
            'xml' => $receipt['xml_path'] ?? null,
            'pdf' => $receipt['pdf_path'] ?? null,
            'evidence' => $receipt['evidence_image_path'] ?? null,
        ]);

        $currentPaths = array_values($paths);

        Attachment::query()
            ->where(
                'attachable_type',
                ExpenseReportLine::class
            )
            ->where('attachable_id', $line->id)
            ->where('source_system', 'bexia_expenses')
            ->whereNotIn(
                'storage_path',
                $currentPaths ?: ['__none__']
            )
            ->delete();

        foreach ($paths as $kind => $storagePath) {
            if (! Storage::disk('local')->exists(
                $storagePath
            )) {
                continue;
            }

            $disk = Storage::disk('local');

            Attachment::query()->updateOrCreate(
                [
                    'attachable_type' =>
                        ExpenseReportLine::class,
                    'attachable_id' => $line->id,
                    'storage_path' => $storagePath,
                ],
                [
                    'company_id' =>
                        $line->report?->company_id,
                    'target_table' =>
                        'expense_report_lines',
                    'target_id' => $line->id,
                    'title' => match ($kind) {
                        'xml' => 'XML CFDI',
                        'pdf' => 'PDF comprobante',
                        'evidence' => 'Foto / evidencia',
                        default => 'Adjunto de gasto',
                    },
                    'description' => $kind === 'evidence'
                        ? 'Evidencia fotográfica del gasto'
                        : 'Comprobante de gasto',
                    'disk' => 'local',
                    'original_filename' =>
                        basename($storagePath),
                    'mimetype' =>
                        $disk->mimeType($storagePath)
                            ?: null,
                    'file_size' =>
                        $disk->size($storagePath),
                    'checksum' =>
                        hash_file(
                            'sha256',
                            $disk->path($storagePath)
                        ),
                    'store_fname' =>
                        basename($storagePath),
                    'source_system' =>
                        'bexia_expenses',
                    'source_model' =>
                        'expense_report_line',
                    'source_id' => $line->id,
                    'source_reference' =>
                        $line->report?->number,
                    'is_legacy' => false,
                    'locked' => false,
                    'is_active' => true,
                ]
            );
        }
    }

    protected static function metadataArray(
        mixed $metadata
    ): array {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (
            is_string($metadata)
            && trim($metadata) !== ''
        ) {
            $decoded = json_decode($metadata, true);

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return [];
    }
}
