<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Support\Billing\PosGlobalInvoiceService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Throwable;

class PosGlobalInvoicePage extends Page
{
    /*
     * BEXIA_V5526B_POS_GLOBAL_INVOICE_PAGE
     */

    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    protected static ?string $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Factura global PDV';

    protected static ?string $title = 'Factura global PDV';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.pos-global-invoice-page';

    public ?string $date_from = null;

    public ?string $date_to = null;

    public string $periodicity = '01';

    public ?string $month = null;

    public ?string $year = null;

    public array $selectedTicketIds = [];

    public ?string $lastInvoiceUrl = null;

    public function mount(): void
    {
        $this->date_from = now()->toDateString();
        $this->date_to = now()->toDateString();
        $this->month = now()->format('m');
        $this->year = now()->format('Y');
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function tickets(): array
    {
        return app(PosGlobalInvoiceService::class)
            ->eligibleTickets($this->tenantId(), $this->filters())
            ->all();
    }

    public function counters(): array
    {
        $tickets = collect($this->tickets());

        return [
            'count' => $tickets->count(),
            'selected_count' => count($this->selectedTicketIds),
            'subtotal' => round((float) $tickets->sum('subtotal'), 4),
            'tax_total' => round((float) $tickets->sum('tax_total'), 4),
            'total' => round((float) $tickets->sum('total'), 4),
        ];
    }

    public function selectAllFiltered(): void
    {
        $this->selectedTicketIds = collect($this->tickets())
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        Notification::make()
            ->title('Tickets seleccionados')
            ->body('Se seleccionaron todos los tickets filtrados.')
            ->success()
            ->send();
    }

    public function clearSelection(): void
    {
        $this->selectedTicketIds = [];
    }

    public function createDraft(): void
    {
        try {
            $invoice = app(PosGlobalInvoiceService::class)
                ->createDraftGlobalInvoice(
                    $this->tenantId(),
                    $this->selectedTicketIds,
                    $this->filters() + [
                        'periodicity' => $this->periodicity,
                        'month' => $this->month,
                        'year' => $this->year,
                    ],
                    auth()->id()
                );

            $this->selectedTicketIds = [];

            $this->lastInvoiceUrl = InvoiceResource::getUrl('view', [
                'record' => $invoice,
            ]);

            Notification::make()
                ->title('Factura global creada')
                ->body('Se creó la factura global en borrador. Revísala antes de timbrar.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('No se pudo crear la factura global')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    private function filters(): array
    {
        return [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ];
    }

    private function tenantId(): int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        return (int) (request()->route('tenant') ?? auth()->user()?->company_id ?? 0);
    }
}
