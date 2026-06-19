<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCaseEventResource\Pages;
use App\Models\ServiceCaseEvent;
use App\Support\Service\ServiceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceCaseEventResource extends Resource
{
    protected static ?string $model = ServiceCaseEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Bitacora de servicio';

    protected static ?string $modelLabel = 'evento de servicio';

    protected static ?string $pluralModelLabel = 'bitacora de servicio';

    protected static ?int $navigationSort = 90;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return ServiceAccess::can('service.events.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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

    public static function getEloquentQuery(): Builder
    {
        $model = static::getModel();

        $query = $model::query();

        $companyId = ServiceAccess::currentCompanyId();

        if ($companyId && ServiceAccess::tableHasCompany('service_case_events')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Evento')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('event_type')
                            ->label('Tipo de evento')
                            ->disabled(),

                        Forms\Components\TextInput::make('from_status')
                            ->label('Estado anterior')
                            ->disabled(),

                        Forms\Components\TextInput::make('to_status')
                            ->label('Estado nuevo')
                            ->disabled(),

                        Forms\Components\TextInput::make('service_case_id')
                            ->label('Ticket')
                            ->disabled(),

                        Forms\Components\TextInput::make('repair_order_id')
                            ->label('Reparacion')
                            ->disabled(),

                        Forms\Components\TextInput::make('performed_by')
                            ->label('Usuario')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('performed_at')
                            ->label('Fecha')
                            ->disabled(),

                        Forms\Components\TextInput::make('company_id')
                            ->label('Empresa')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Notas')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(5)
                            ->disabled(),

                        Forms\Components\Textarea::make('metadata')
                            ->label('Metadata')
                            ->rows(4)
                            ->disabled()
                            ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $state),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('performed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Evento')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_case_id')
                    ->label('Ticket')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('repair_order_id')
                    ->label('Reparacion')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('from_status')
                    ->label('Antes')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('to_status')
                    ->label('Despues')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('performed_by')
                    ->label('Usuario')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(70)
                    ->searchable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Evento')
                    ->options(fn (): array => ServiceCaseEvent::query()
                        ->select('event_type')
                        ->distinct()
                        ->orderBy('event_type')
                        ->pluck('event_type', 'event_type')
                        ->filter()
                        ->toArray()),

                Tables\Filters\Filter::make('solo_tickets')
                    ->label('Solo tickets')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('service_case_id')->whereNull('repair_order_id')),

                Tables\Filters\Filter::make('solo_reparaciones')
                    ->label('Solo reparaciones')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('repair_order_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceCaseEvents::route('/'),
        ];
    }
}
