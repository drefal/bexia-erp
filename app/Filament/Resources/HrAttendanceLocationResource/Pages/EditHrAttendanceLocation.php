<?php

namespace App\Filament\Resources\HrAttendanceLocationResource\Pages;

use App\Filament\Resources\HrAttendanceLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditHrAttendanceLocation extends EditRecord
{
    protected static string $resource = HrAttendanceLocationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return HrAttendanceLocationResource::mutateFormDataBeforeSave($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $state = $this->form->getRawState();

        if (! array_key_exists('assigned_employee_ids', $state)) {
            return;
        }

        $employeeIds = collect($state['assigned_employee_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $userId = auth()->id();
        $now = now();

        DB::transaction(function () use ($record, $employeeIds, $userId, $now): void {
            DB::table('employee_attendance_location_assignments')
                ->where('company_id', $record->company_id)
                ->where('hr_attendance_location_id', $record->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_by_user_id' => $userId,
                    'updated_at' => $now,
                ]);

            foreach ($employeeIds as $employeeId) {
                $exists = DB::table('employee_attendance_location_assignments')
                    ->where('company_id', $record->company_id)
                    ->where('employee_id', $employeeId)
                    ->where('hr_attendance_location_id', $record->id)
                    ->exists();

                if ($exists) {
                    DB::table('employee_attendance_location_assignments')
                        ->where('company_id', $record->company_id)
                        ->where('employee_id', $employeeId)
                        ->where('hr_attendance_location_id', $record->id)
                        ->update([
                            'is_active' => true,
                            'notes' => 'Asignación actualizada desde formulario de geocerca.',
                            'updated_by_user_id' => $userId,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('employee_attendance_location_assignments')
                        ->insert([
                            'company_id' => $record->company_id,
                            'employee_id' => $employeeId,
                            'hr_attendance_location_id' => $record->id,
                            'is_active' => true,
                            'notes' => 'Asignación creada desde formulario de geocerca.',
                            'created_by_user_id' => $userId,
                            'updated_by_user_id' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }
        });
    }

}
