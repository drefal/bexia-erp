<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\Company;
use App\Support\Attendance\AttendanceAccessPendingInbox;
use App\Support\Attendance\AttendanceAccessResolutionService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Throwable;

class AttendanceAccessApprovals extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-shield-check';

    protected static ?string $navigationGroup =
        'RRHH';

    protected static ?string $navigationLabel =
        'Aprobaciones RRHH';

    protected static ?string $title =
        'Aprobaciones RRHH';

    protected static ?int $navigationSort = 47;

    protected static ?string $slug =
        'aprobaciones-rrhh';

    protected static string $view =
        'filament.pages.attendance-access-approvals';

    #[Locked]
    public int $pageCompanyId = 0;

    #[Locked]
    public ?int $selectedDecisionId = null;

    #[Locked]
    public ?int $selectedVersion = null;

    public string $resolutionReason = '';

    public static function canAccess(): bool
    {
        $actor = auth()->user();
        $company = Filament::getTenant();

        return $actor instanceof User
            && $company instanceof Company
            && Schema::hasTable('hr_access_decisions')
            && AttendanceAccessPendingInbox
                ::canAccess($actor, $company);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && \App\Support\Navigation\BexiaMenuRuntime
                ::shouldRegister(
                    'pages.attendanceaccessapprovals',
                    fn (): bool => true
                );
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->pageCompanyId =
            (int) Filament::getTenant()->id;
    }

    private function identity(): array
    {
        abort_unless(static::canAccess(), 403);

        $actor = auth()->user();
        $company = Filament::getTenant();

        abort_unless(
            (int) $company->id === $this->pageCompanyId,
            403
        );

        return [$actor, $company];
    }

    protected function getViewData(): array
    {
        [$actor, $company] = $this->identity();

        $rows = AttendanceAccessPendingInbox::rows(
            $actor,
            $company
        );

        $selectedRow = collect($rows)->first(
            fn (array $row): bool =>
                $row['id'] === $this->selectedDecisionId
                && $row['version'] === $this->selectedVersion
        );

        return [
            'selectedRow' => $selectedRow,
            'companyName' => $company->name,
            'pendingRows' => $rows,
            'pendingCount' => count($rows),
        ];
    }

    #[\Livewire\Attributes\On('bexia-hr-access-changed')]
    public function refreshPendingAccess(): void
    {
        // Revalidate current session and company.
        $this->identity();
    }

    public function selectDecision(
        int $id,
        int $version
    ): void {

        [$actor, $company] = $this->identity();

        $row = collect(
            AttendanceAccessPendingInbox::rows(
                $actor,
                $company
            )
        )->first(
            fn (array $row): bool =>
                $row['id'] === $id
                && $row['version'] === $version
        );

        if (!$row) {

            $this->cancelResolution();

            Notification::make()
                ->title('La solicitud ya no esta disponible')
                ->warning()
                ->send();

            return;
        }

        $this->selectedDecisionId = $id;
        $this->selectedVersion = $version;
        $this->resolutionReason = '';
        $this->resetValidation();

        $this->dispatch(
            'open-modal',
            id: 'hr-access-resolution-modal'
        );
    }

    public function cancelResolution(): void
    {
        $this->selectedDecisionId = null;
        $this->selectedVersion = null;
        $this->resolutionReason = '';
        $this->resetValidation();

        $this->dispatch(
            'close-modal',
            id: 'hr-access-resolution-modal'
        );
    }

    public function authorizeEntry(): void
    {
        $this->resolveSelected('authorize_now');
    }

    public function rejectEntry(): void
    {
        $this->resolveSelected('reject_access');
    }

    private function resolveSelected(
        string $action
    ): void {

        [$actor, $company] = $this->identity();

        $this->validate([
            'selectedDecisionId' =>
                'required|integer|min:1',
            'selectedVersion' =>
                'required|integer|min:1',
            'resolutionReason' =>
                'required|string|min:5|max:1000',
        ]);

        try {

            AttendanceAccessResolutionService::resolve(
                (int) $company->id,
                (int) $this->selectedDecisionId,
                (int) $this->selectedVersion,
                $action,
                $this->resolutionReason
            );

            $this->cancelResolution();

            Notification::make()
                ->title(
                    $action === 'authorize_now'
                        ? 'Ingreso autorizado'
                        : 'Ingreso rechazado'
                )
                ->success()
                ->send();

        } catch (AuthorizationException) {

            $this->cancelResolution();

            Notification::make()
                ->title('No tienes facultad para esta resolucion')
                ->danger()
                ->send();

        } catch (Throwable $e) {

            report($e);

            Notification::make()
                ->title('No se pudo resolver la solicitud')
                ->body(
                    'Verifica si sigue pendiente o requiere revision de RRHH.'
                )
                ->warning()
                ->send();
        }
    }
}
