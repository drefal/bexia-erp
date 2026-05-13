<?php

namespace App\Filament\Pages;

use App\Support\Accounting\AccountingReportsService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AccountingReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $navigationLabel = 'Reportes contables';

    protected static ?string $title = 'Reportes contables';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.accounting-reports';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function report(): array
    {
        return app(AccountingReportsService::class)
            ->dashboard($this->tenantId());
    }

    public function exportTrialBalance(): StreamedResponse
    {
        $rows = app(AccountingReportsService::class)->trialBalance($this->tenantId());

        return $this->downloadCsv('balanza_comprobacion', [
            'Empresa',
            'Código cuenta',
            'Cuenta',
            'Movimientos',
            'Debe',
            'Haber',
            'Saldo',
        ], array_map(fn ($row) => [
            $row['company_id'] ?? '',
            $row['code'] ?? '',
            $row['name'] ?? '',
            $row['line_count'] ?? 0,
            $this->decimal($row['debit'] ?? 0),
            $this->decimal($row['credit'] ?? 0),
            $this->decimal($row['balance'] ?? 0),
        ], $rows));
    }

    public function exportSourceTotals(): StreamedResponse
    {
        $rows = app(AccountingReportsService::class)->sourceTotals($this->tenantId());

        return $this->downloadCsv('totales_por_operacion', [
            'Operación',
            'Código origen',
            'Asientos',
            'Debe',
            'Haber',
        ], array_map(fn ($row) => [
            $row['source_label'] ?? '',
            $row['source_type'] ?? '',
            $row['entries'] ?? 0,
            $this->decimal($row['debit'] ?? 0),
            $this->decimal($row['credit'] ?? 0),
        ], $rows));
    }

    public function exportLedger(): StreamedResponse
    {
        $rows = app(AccountingReportsService::class)->ledger($this->tenantId());

        return $this->downloadCsv('mayor_auxiliar_reciente', [
            'Fecha',
            'Asiento ID',
            'Asiento',
            'Código cuenta',
            'Cuenta',
            'Concepto',
            'Origen',
            'ID origen',
            'Debe',
            'Haber',
        ], array_map(fn ($row) => [
            $row['entry_date'] ?? '',
            $row['entry_id'] ?? '',
            $row['entry_number'] ?? '',
            $row['account_code'] ?? '',
            $row['account_name'] ?? '',
            $row['label'] ?? '',
            $row['source_type'] ?? '',
            $row['source_id'] ?? '',
            $this->decimal($row['debit'] ?? 0),
            $this->decimal($row['credit'] ?? 0),
        ], $rows));
    }

    public function exportInventoryValuation(): StreamedResponse
    {
        $rows = app(AccountingReportsService::class)->inventoryValuation($this->tenantId());

        return $this->downloadCsv('inventario_contable', [
            'Empresa',
            'Producto ID',
            'Referencia',
            'Producto',
            'Capas',
            'Cantidad neta',
            'Valor neto',
        ], array_map(fn ($row) => [
            $row['company_id'] ?? '',
            $row['product_id'] ?? '',
            $row['product_reference'] ?? '',
            $row['product_name'] ?? '',
            $row['layers'] ?? 0,
            $this->decimal($row['net_quantity'] ?? 0, 6),
            $this->decimal($row['net_value'] ?? 0),
        ], $rows));
    }

    private function downloadCsv(string $baseName, array $headers, array $rows): StreamedResponse
    {
        $tenantId = $this->tenantId() ?: 'global';
        $filename = $baseName . '_empresa_' . $tenantId . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            // BOM para que Excel abra acentos correctamente.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function decimal($value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    private function tenantId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            return $tenant ? (int) $tenant->getKey() : null;
        } catch (Throwable $e) {
            return null;
        }
    }
public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('accounting.view')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('accounting.view')
            );
    }

}
