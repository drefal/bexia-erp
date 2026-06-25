<?php

namespace App\Filament\Resources\EmployeeAttendanceResource\Pages;

use App\Filament\Resources\EmployeeAttendanceResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;

class EditEmployeeAttendance extends EditRecord
{
    protected static string $resource = EmployeeAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveMobileLocation')
                ->label('Aceptar ubicación')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aceptar ubicación móvil')
                ->modalDescription('La asistencia quedará marcada como ubicación móvil aceptada. Las incidencias relacionadas conservan su propio flujo de aprobación.')
                ->visible(fn (): bool => EmployeeAttendanceResource::canReviewMobileAttendance($this->record)
                    && $this->record->mobile_review_status === 'pending')
                ->action(function (): void {
                    $this->record->forceFill([
                        'mobile_review_status' => 'accepted',
                        'mobile_review_notes' => trim((string) ($this->record->mobile_review_notes ?? '')) ?: 'Ubicación revisada y aceptada.',
                        'mobile_reviewed_by_user_id' => auth()->id(),
                        'mobile_reviewed_at' => now(),
                    ])->save();

                    $this->refreshFormData([
                        'mobile_review_status',
                        'mobile_review_notes',
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Ubicación aceptada')
                        ->body('La revisión móvil quedó marcada como aceptada.')
                        ->success()
                        ->send();
                }),

            Action::make('rejectMobileLocation')
                ->label('Rechazar ubicación')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => EmployeeAttendanceResource::canReviewMobileAttendance($this->record)
                    && $this->record->mobile_review_status === 'pending')
                ->form([
                    \Filament\Forms\Components\Textarea::make('mobile_review_notes')
                        ->label('Motivo del rechazo')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->forceFill([
                        'mobile_review_status' => 'rejected',
                        'mobile_review_notes' => $data['mobile_review_notes'] ?? null,
                        'mobile_reviewed_by_user_id' => auth()->id(),
                        'mobile_reviewed_at' => now(),
                    ])->save();

                    $this->refreshFormData([
                        'mobile_review_status',
                        'mobile_review_notes',
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Ubicación rechazada')
                        ->body('La revisión móvil quedó marcada como rechazada.')
                        ->danger()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('mobile_review_status', $data) || array_key_exists('mobile_review_notes', $data)) {
            if (! EmployeeAttendanceResource::canReviewMobileAttendance($this->record)) {
                unset($data['mobile_review_status'], $data['mobile_review_notes']);

                return $data;
            }

            $newStatus = $data['mobile_review_status'] ?? $this->record->mobile_review_status;
            $newNotes = $data['mobile_review_notes'] ?? $this->record->mobile_review_notes;

            if ($newStatus !== $this->record->mobile_review_status || $newNotes !== $this->record->mobile_review_notes) {
                $data['mobile_reviewed_by_user_id'] = auth()->id();
                $data['mobile_reviewed_at'] = now();
            }
        }

        return $data;
    }
}
