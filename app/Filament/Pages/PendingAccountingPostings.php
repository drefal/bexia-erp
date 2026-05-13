<?php

namespace App\Filament\Pages;

use App\Support\Accounting\PendingAccountingPostingService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Throwable;

class PendingAccountingPostings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $navigationLabel = 'Contabilización pendiente';

    protected static ?string $title = 'Contabilización pendiente';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.pending-accounting-postings';

    public ?array $pendingConfirmation = null;

    public ?string $lastResult = null;

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function documents(): array
    {
        return app(PendingAccountingPostingService::class)
            ->pendingDocuments($this->tenantId());
    }

    public function counters(): array
    {
        return app(PendingAccountingPostingService::class)
            ->counters($this->tenantId());
    }

    public function askPostDocument(string $type, int $id): void
    {
        $document = $this->findPendingDocument($type, $id);

        if (! $document) {
            Notification::make()
                ->title('Documento no disponible')
                ->body('El documento ya no aparece como pendiente o no pertenece a esta empresa.')
                ->warning()
                ->send();

            return;
        }

        $this->pendingConfirmation = $document;
        $this->lastResult = null;
    }

    public function cancelPosting(): void
    {
        $this->pendingConfirmation = null;

        Notification::make()
            ->title('Acción cancelada')
            ->body('No se realizó ningún movimiento contable.')
            ->info()
            ->send();
    }

    public function confirmPostDocument(): void
    {
        if (! $this->pendingConfirmation) {
            Notification::make()
                ->title('No hay documento seleccionado')
                ->warning()
                ->send();

            return;
        }

        $type = (string) $this->pendingConfirmation['type'];
        $id = (int) $this->pendingConfirmation['id'];

        $this->pendingConfirmation = null;

        $this->postDocument($type, $id);
    }

    public function postDocument(string $type, int $id): void
    {
        try {
            $document = $this->findPendingDocument($type, $id);

            if (! $document) {
                Notification::make()
                    ->title('Documento no disponible')
                    ->body('El documento ya no aparece como pendiente o no pertenece a esta empresa.')
                    ->warning()
                    ->send();

                return;
            }

            $result = app(PendingAccountingPostingService::class)
                ->post($type, $id, false);

            $this->lastResult = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (($result['error_count'] ?? 0) > 0) {
                Notification::make()
                    ->title('No se pudo completar la contabilización')
                    ->body('Revisa el detalle de errores al final de la pantalla.')
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Contabilización realizada')
                ->body('El documento fue procesado correctamente.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            $this->lastResult = $e->getMessage();

            Notification::make()
                ->title('Error al contabilizar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function findPendingDocument(string $type, int $id): ?array
    {
        foreach ($this->documents() as $section) {
            foreach ($section as $row) {
                if (($row['type'] ?? null) === $type && (int) ($row['id'] ?? 0) === $id) {
                    return $row;
                }
            }
        }

        return null;
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
                $user?->can('pos.menu.view')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

}
