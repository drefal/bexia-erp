<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeCredentialResource\Pages;
use App\Models\Branch;
use App\Models\Employee;
use App\Support\Attendance\EmployeeCredentialPdfService;
use App\Support\Navigation\BexiaMenuRuntime;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class EmployeeCredentialResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Credenciales QR';

    protected static ?string $modelLabel = 'credencial QR';

    protected static ?string $pluralModelLabel = 'credenciales QR';

    protected static ?int $navigationSort = 47;

    protected static ?string $slug = 'credenciales-empleados';

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return BexiaMenuRuntime::shouldRegister(
            'resources.employeecredentialresource',
            fn (): bool => static::baseCanView(),
        );
    }

    protected static function baseCanView(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('contacts.view')
                || $user?->can('company.update')
            );
    }

    public static function canViewAny(): bool
    {
        return static::baseCanView();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function currentCompanyId(): int
    {
        return (int) (Filament::getTenant()?->getKey() ?? 0);
    }

    public static function eligibleQuery(?int $companyId = null): Builder
    {
        $companyId = (int) ($companyId ?: static::currentCompanyId());

        $query = Employee::query()
            ->with(['company', 'branch', 'hrJobPosition'])
            ->where('active', true)
            ->where('attendance_qr_enabled', true)
            ->whereNotNull('attendance_qr_token')
            ->where('attendance_qr_token', '<>', '');

        if ($companyId < 1) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('company_id', $companyId);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::eligibleQuery();
    }

    public static function branchOptions(): array
    {
        $companyId = static::currentCompanyId();

        if ($companyId < 1) {
            return [];
        }

        return Branch::query()
            ->where('company_id', $companyId)
            ->orderByDesc('active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_number')
                    ->label('No. empleado')
                    ->placeholder('Sin numero')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Empleado')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->placeholder('Sin sucursal')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('hrJobPosition.name')
                    ->label('Puesto')
                    ->formatStateUsing(function ($state, Employee $record): string {
                        return trim((string) ($state ?: $record->position ?: 'Colaborador'));
                    })
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('avatar_path')
                    ->label('Foto')
                    ->boolean()
                    ->getStateUsing(fn (Employee $record): bool => filled($record->avatar_path))
                    ->trueIcon('heroicon-o-photo')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('attendance_qr_generated_at')
                    ->label('QR generado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin fecha')
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => static::branchOptions())
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('download_credential')
                    ->label('Descargar tarjeta')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function (Employee $record) {
                        $companyId = static::currentCompanyId();

                        if ($companyId < 1 || (int) $record->company_id !== $companyId) {
                            abort(403);
                        }

                        $service = app(EmployeeCredentialPdfService::class);
                        $contents = $service->renderIndividual($record);
                        $filename = $service->individualFilename($record);

                        return response()->streamDownload(
                            static function () use ($contents): void {
                                echo $contents;
                            },
                            $filename,
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('download_selected_credentials')
                    ->label('Descargar seleccionadas')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(function (EloquentCollection $records) {
                        $companyId = static::currentCompanyId();

                        if ($companyId < 1) {
                            abort(403);
                        }

                        $records = $records
                            ->filter(fn (Employee $employee): bool => (int) $employee->company_id === $companyId)
                            ->values();

                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('No hay credenciales para descargar')
                                ->warning()
                                ->send();

                            return null;
                        }

                        $records->loadMissing(['company', 'branch', 'hrJobPosition']);

                        $service = app(EmployeeCredentialPdfService::class);
                        $contents = $service->renderBulk($records);
                        $companyName = (string) ($records->first()?->company?->name ?: 'empresa');
                        $filename = $service->bulkFilename($companyName);

                        return response()->streamDownload(
                            static function () use ($contents): void {
                                echo $contents;
                            },
                            $filename,
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
            ])
            ->recordUrl(null)
            ->emptyStateHeading('No hay empleados activos con QR')
            ->emptyStateDescription('Las credenciales aparecen cuando el empleado esta activo, tiene QR de asistencia habilitado y cuenta con token QR.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeCredentials::route('/'),
        ];
    }
}
