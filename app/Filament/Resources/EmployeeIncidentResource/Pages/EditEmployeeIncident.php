<?php

namespace App\Filament\Resources\EmployeeIncidentResource\Pages;




use Throwable;
use Illuminate\Database\QueryException;
use Filament\Notifications\Notification;
use App\Filament\Resources\EmployeeIncidentResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeIncident extends EditRecord
{
    protected static string $resource = EmployeeIncidentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        if (($data['status'] ?? null) === 'approved' && blank($this->record->approved_at)) {
            $data['approved_by_user_id'] = auth()->id();
            $data['approved_at'] = now();
        }

        return $data;
    }
    protected function approveIncidentWithExplanation(string $explanation): void
    {
        $explanation = trim($explanation);

        \Illuminate\Support\Facades\DB::transaction(function () use ($explanation): void {
            $incident = $this->record->fresh();
            $request = $this->pendingApprovalRequest();

            if ($request) {
                $step = $this->currentPendingStep($request);

                if ($step) {
                    if (! $this->safeApprovalStepUpdate((int) $step->id, $this->filterColumns('approval_request_steps', [
                            'status' => 'approved',
                            'acted_by_user_id' => auth()->id(),
                            'acted_by_name' => $this->userDisplayName(),
                            'acted_at' => now(),
                            'comments' => $explanation,
                            'decision_reason' => $explanation,
                            'updated_at' => now(),
                        ]))) {
                return;
            }

                    $nextStep = $this->nextWaitingStep($request, (int) $step->step_order);

                    if ($nextStep) {
                        \Illuminate\Support\Facades\DB::table('approval_request_steps')
                            ->where('id', $nextStep->id)
                            ->update($this->filterColumns('approval_request_steps', [
                                'status' => 'pending',
                                'updated_at' => now(),
                            ]));

                        \Illuminate\Support\Facades\DB::table('approval_requests')
                            ->where('id', $request->id)
                            ->update($this->filterColumns('approval_requests', [
                                'current_step_order' => (int) $nextStep->step_order,
                                'updated_at' => now(),
                            ]));

                        \Illuminate\Support\Facades\DB::table('employee_incidents')
                            ->where('id', $incident->id)
                            ->update($this->filterColumns('employee_incidents', [
                                'status' => 'pending',
                                'resolution_notes' => $this->appendResolutionNote('Aprobación de etapa', $explanation),
                                'updated_by_user_id' => auth()->id(),
                                'updated_at' => now(),
                            ]));

                        return;
                    
                \Filament\Notifications\Notification::make()
                    ->title('Fase aprobada')
                    ->body('La fase fue aprobada correctamente. La incidencia avanzó a la siguiente etapa de aprobación.')
                    ->success()
                    ->send();

                $this->refreshApprovalScreenAfterDecision();

                return;
            }
                }

                \Illuminate\Support\Facades\DB::table('approval_requests')
                    ->where('id', $request->id)
                    ->update($this->filterColumns('approval_requests', [
                        'status' => 'approved',
                        'completed_at' => now(),
                        'last_decision_reason' => $explanation,
                        'updated_at' => now(),
                    ]));
            }

            \Illuminate\Support\Facades\DB::table('employee_incidents')
                ->where('id', $incident->id)
                ->update($this->filterColumns('employee_incidents', [
                    'status' => 'approved',
                    'approved_by_user_id' => auth()->id(),
                    'approved_at' => now(),
                    'resolution_notes' => $this->appendResolutionNote('Aprobación final', $explanation),
                    'updated_by_user_id' => auth()->id(),
                    'updated_at' => now(),
                ]));
        });

        $this->record->refresh();
    }

    protected function rejectIncidentWithExplanation(string $explanation): void
    {
        $explanation = trim($explanation);

        \Illuminate\Support\Facades\DB::transaction(function () use ($explanation): void {
            $incident = $this->record->fresh();
            $request = $this->pendingApprovalRequest();

            if ($request) {
                $step = $this->currentPendingStep($request);

                if ($step) {
                    if (! $this->safeApprovalStepUpdate((int) $step->id, $this->filterColumns('approval_request_steps', [
                            'status' => 'rejected',
                            'acted_by_user_id' => auth()->id(),
                            'acted_by_name' => $this->userDisplayName(),
                            'acted_at' => now(),
                            'comments' => $explanation,
                            'decision_reason' => $explanation,
                            'updated_at' => now(),
                        ]))) {
                return;
            }
                }

                \Illuminate\Support\Facades\DB::table('approval_requests')
                    ->where('id', $request->id)
                    ->update($this->filterColumns('approval_requests', [
                        'status' => 'rejected',
                        'completed_at' => now(),
                        'last_decision_reason' => $explanation,
                        'updated_at' => now(),
                    ]));
            }

            \Illuminate\Support\Facades\DB::table('employee_incidents')
                ->where('id', $incident->id)
                ->update($this->filterColumns('employee_incidents', [
                    'status' => 'rejected',
                    'resolution_notes' => $this->appendResolutionNote('Rechazo', $explanation),
                    'updated_by_user_id' => auth()->id(),
                    'updated_at' => now(),
                ]));
        });

        $this->record->refresh();
    }

    protected function pendingApprovalRequest(): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('approval_requests')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('approval_requests')
            ->where('approvable_type', \App\Models\EmployeeIncident::class)
            ->where('approvable_id', $this->record->id)
            ->where('document_type', 'employee_incident')
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
    }

    protected function currentPendingStep(object $request): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('approval_request_steps')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->orderBy('id')
            ->first();
    }

    protected function nextWaitingStep(object $request, int $currentStepOrder): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('approval_request_steps')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('status', 'waiting')
            ->where('step_order', '>', $currentStepOrder)
            ->orderBy('step_order')
            ->orderBy('id')
            ->first();
    }

    protected function appendResolutionNote(string $heading, string $explanation): string
    {
        $current = (string) ($this->record->resolution_notes ?? '');
        $line = '[' . now()->format('Y-m-d H:i') . '] ' . $heading . ' por ' . $this->userDisplayName() . ': ' . $explanation;

        return trim($current . PHP_EOL . PHP_EOL . $line);
    }

    protected function userDisplayName(): string
    {
        $user = auth()->user();

        return (string) (($user?->name ?: $user?->email) ?: 'Sistema');
    }

    protected function filterColumns(string $table, array $values): array
    {
        return collect($values)
            ->filter(fn ($value, string $column): bool => \Illuminate\Support\Facades\Schema::hasColumn($table, $column))
            ->toArray();
    }


    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('approve_incident')
                ->label('Aprobar incidencia')
                ->visible(fn (): bool => $this->canCurrentUserActOnCurrentApprovalStep())
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Aprobar incidencia')
                ->modalDescription('Captura la explicación o justificación de la aprobación. Esta nota quedará registrada en la incidencia y, si aplica, en el flujo de aprobación.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('explanation')
                        ->label('Explicación / justificación')
                        ->required()
                        ->rows(5)
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    
                if (! $this->canCurrentUserActOnCurrentApprovalStep()) {
                    $this->notifyApprovalStepNoLongerAvailable();

                    return;
                }

$this->approveIncidentWithExplanation((string) ($data['explanation'] ?? ''));

                    \Filament\Notifications\Notification::make()
                        ->title('Aprobación registrada')
                        ->body('La aprobación fue registrada correctamente.')
                        ->success()
                        ->send();

                $this->refreshApprovalScreenAfterDecision();
                }),

            \Filament\Actions\Action::make('reject_incident')
                ->label('Rechazar incidencia')
                ->visible(fn (): bool => $this->canCurrentUserActOnCurrentApprovalStep())
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Rechazar incidencia')
                ->modalDescription('Captura el motivo del rechazo. Esta explicación quedará registrada en la incidencia y, si aplica, en el flujo de aprobación.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('explanation')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->rows(5)
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    
                if (! $this->canCurrentUserActOnCurrentApprovalStep()) {
                    $this->notifyApprovalStepNoLongerAvailable();

                    return;
                }

$this->rejectIncidentWithExplanation((string) ($data['explanation'] ?? ''));

                    \Filament\Notifications\Notification::make()
                        ->title('Incidencia rechazada')
                        ->body('El rechazo fue registrado correctamente.')
                        ->warning()
                        ->send();

                $this->refreshApprovalScreenAfterDecision();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        /*
         * Las incidencias se conservan como evidencia/auditoría.
         * No se permite edición libre con Guardar desde esta pantalla.
         * La operación normal debe hacerse con Aprobar incidencia / Rechazar incidencia.
         */
        return [];
    }


    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        /*
         * Pantalla de incidencia en modo solo lectura.
         * La decisión operativa se realiza con:
         * - Aprobar incidencia
         * - Rechazar incidencia
         */
        return parent::form($form)->disabled();
    }


    protected function canCurrentUserActOnCurrentApprovalStep(): bool
    {
        $recordId = (int) ($this->record?->id ?? 0);

        if ($recordId <= 0) {
            return false;
        }

        $request = \Illuminate\Support\Facades\DB::table('approval_requests')
            ->where('document_type', 'employee_incident')
            ->where('approvable_id', $recordId)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if (! $request) {
            return false;
        }

        $step = \Illuminate\Support\Facades\DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('step_order', $request->current_step_order)
            ->where('status', 'pending')
            ->first();

        if (! $step) {
            return false;
        }

        return (int) ($step->approver_user_id ?? 0) === (int) auth()->id();
    }
    protected function notifyApprovalStepNoLongerAvailable(): void
    {
        try {
            $this->js(<<<'JS'
                const existing = document.getElementById('bexia-approval-step-processed-modal');
                if (existing) {
                    existing.remove();
                }

                const overlay = document.createElement('div');
                overlay.id = 'bexia-approval-step-processed-modal';
                overlay.style.position = 'fixed';
                overlay.style.inset = '0';
                overlay.style.zIndex = '999999';
                overlay.style.background = 'rgba(15, 23, 42, 0.58)';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.style.padding = '24px';

                const modal = document.createElement('div');
                modal.style.width = '100%';
                modal.style.maxWidth = '460px';
                modal.style.background = '#ffffff';
                modal.style.borderRadius = '18px';
                modal.style.boxShadow = '0 24px 80px rgba(15, 23, 42, 0.35)';
                modal.style.padding = '26px';
                modal.style.textAlign = 'center';
                modal.style.fontFamily = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

                const icon = document.createElement('div');
                icon.textContent = '!';
                icon.style.width = '52px';
                icon.style.height = '52px';
                icon.style.margin = '0 auto 14px auto';
                icon.style.borderRadius = '999px';
                icon.style.background = '#f59e0b';
                icon.style.color = '#ffffff';
                icon.style.fontWeight = '800';
                icon.style.fontSize = '30px';
                icon.style.lineHeight = '52px';

                const title = document.createElement('h2');
                title.textContent = 'Esta fase ya fue procesada';
                title.style.margin = '0 0 10px 0';
                title.style.fontSize = '20px';
                title.style.fontWeight = '700';
                title.style.color = '#111827';

                const body = document.createElement('p');
                body.textContent = 'La fase actual ya fue aprobada o rechazada, o la incidencia avanzó a la siguiente etapa. Se actualizará la pantalla para ocultar los botones que ya no aplican.';
                body.style.margin = '0 0 22px 0';
                body.style.fontSize = '14px';
                body.style.lineHeight = '1.55';
                body.style.color = '#4b5563';

                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = 'Entendido';
                button.style.width = '100%';
                button.style.border = '0';
                button.style.borderRadius = '10px';
                button.style.padding = '11px 16px';
                button.style.background = '#2563eb';
                button.style.color = '#ffffff';
                button.style.fontWeight = '700';
                button.style.cursor = 'pointer';

                const closeAndReload = () => {
                    button.disabled = true;
                    button.textContent = 'Actualizando...';
                    window.location.reload();
                };

                button.addEventListener('click', closeAndReload);
                overlay.addEventListener('click', (event) => {
                    if (event.target === overlay) {
                        closeAndReload();
                    }
                });

                modal.appendChild(icon);
                modal.appendChild(title);
                modal.appendChild(body);
                modal.appendChild(button);
                overlay.appendChild(modal);
                document.body.appendChild(overlay);
            JS);
        } catch (\Throwable $e) {
            report($e);

            \Filament\Notifications\Notification::make()
                ->title('Esta fase ya fue procesada')
                ->body('La incidencia ya avanzó de etapa. Recarga la pantalla para ver el estado actualizado.')
                ->warning()
                ->send();
        }
    }


    protected function safeApprovalStepUpdate(int $stepId, array $payload): bool
    {
        try {
            $updated = \Illuminate\Support\Facades\DB::table('approval_request_steps')
                ->where('id', $stepId)
                ->where('status', 'pending')
                ->where('approver_user_id', auth()->id())
                ->update($payload);

            if ($updated < 1) {
                $this->notifyApprovalStepNoLongerAvailable();

                return false;
            }

            return true;
        } catch (QueryException $e) {
            report($e);

            $this->notifyApprovalStepNoLongerAvailable();

            return false;
        } catch (Throwable $e) {
            report($e);

            $this->notifyApprovalStepNoLongerAvailable();

            return false;
        }
    }


    protected function refreshApprovalScreenAfterDecision(): void
    {
        $this->dispatch('$refresh');

        try {
            $this->js('setTimeout(() => window.location.reload(), 900)');
        } catch (\Throwable $e) {
            report($e);
        }
    }


}
