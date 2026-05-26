<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Expediente';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

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
            || $user->can('rrhh.expediente.ver')
            || $user->can('company.update');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::bexiaCanExpedientePermission('rrhh.expediente.ver');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Documento del expediente')
                    ->description('Documentos laborales, identificaciones, contratos y comprobantes ligados a este empleado.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('hr_document_type_id')
                                    ->label('Tipo de documento')
                                    ->options(fn () => $this->documentTypeOptions())
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
                                    ->directory(fn (): string => 'employee-documents/' . ($this->getOwnerRecord()->company_id ?? 'company') . '/' . ($this->getOwnerRecord()->id ?? 'employee'))
                                    ->visibility('public')
                                    ->downloadable()
                                    ->openable()
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function documentTypeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey() ?? $this->getOwnerRecord()->company_id;

        return HrDocumentType::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
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

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
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
                    ->options(fn () => $this->documentTypeOptions()),

                Filter::make('expired')
                    ->label('Vencidos')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->whereDate('expires_at', '<', today())),

                Filter::make('expires_soon')
                    ->label('Por vencer 30 días')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->whereBetween('expires_at', [today(), today()->addDays(30)])),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar documento')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();

                        $data['company_id'] = $owner->company_id;
                        $data['created_by_user_id'] = auth()->id();
                        $data['updated_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanExpedientePermission('rrhh.expediente.crear')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['updated_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanExpedientePermission('rrhh.expediente.editar')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => static::bexiaCanExpedientePermission('rrhh.expediente.eliminar')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionados')
                    ->visible(fn (): bool => static::bexiaCanExpedientePermission('rrhh.expediente.eliminar')),
            ]);
    }
}
