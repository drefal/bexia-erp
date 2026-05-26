<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeDocumentResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\HrDocumentType;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocumentResource extends Resource
{
    protected static ?string $model = EmployeeDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Expedientes';

    protected static ?string $modelLabel = 'documento de empleado';

    protected static ?string $pluralModelLabel = 'expediente de empleados';

    protected static ?int $navigationSort = 15;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanExpedientePermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can($permission)
            || $user->can('rrhh.catalogos.ver')
            || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.eliminar');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['employee', 'documentType'])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Documento del expediente')
                    ->description('Captura documentos del expediente laboral del empleado.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('hr_document_type_id')
                                    ->label('Tipo de documento')
                                    ->options(fn () => self::documentTypeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('name')
                                    ->label('Nombre del documento')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('document_number')
                                    ->label('Folio / número')
                                    ->maxLength(255),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'pending' => 'Pendiente',
                                        'valid' => 'Vigente',
                                        'expired' => 'Vencido',
                                        'archived' => 'Archivado',
                                    ])
                                    ->default('pending')
                                    ->required(),

                                DatePicker::make('issued_at')
                                    ->label('Fecha de emisión')
                                    ->native(false),

                                DatePicker::make('expires_at')
                                    ->label('Fecha de vencimiento')
                                    ->native(false),

                                FileUpload::make('file_path')
                                    ->label('Archivo')
                                    ->disk('public')
                                    ->directory('employee-documents')
                                    ->visibility('public')
                                    ->downloadable()
                                    ->openable()
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }

    protected static function employeeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return Employee::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function documentTypeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return HrDocumentType::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('documentType.name')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Folio')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'valid' => 'Vigente',
                        'expired' => 'Vencido',
                        'archived' => 'Archivado',
                        default => $state ?: '-',
                    }),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('file_path')
                    ->label('Archivo')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->file_path)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'valid' => 'Vigente',
                        'expired' => 'Vencido',
                        'archived' => 'Archivado',
                    ]),

                SelectFilter::make('hr_document_type_id')
                    ->label('Tipo de documento')
                    ->options(fn () => self::documentTypeOptions()),

                Filter::make('expired')
                    ->label('Vencidos')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->whereDate('expires_at', '<', today())),

                Filter::make('expires_soon')
                    ->label('Por vencer 30 días')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->whereBetween('expires_at', [today(), today()->addDays(30)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionados'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeDocuments::route('/'),
            'create' => Pages\CreateEmployeeDocument::route('/create'),
            'edit' => Pages\EditEmployeeDocument::route('/{record}/edit'),
        ];
    }
}
