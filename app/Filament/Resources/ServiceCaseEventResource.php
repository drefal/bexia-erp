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

    /*
     * BEXIA_SERVICE_CASE_EVENT_RESOURCE_RESPONSIVE_V5_79_78C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Evento')
                    ->extraAttributes(['class' => 'bexia-scer-section bexia-scer-section-event bexia-scer-section-main'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('event_type')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-event-type-field bexia-scer-code-field bexia-scer-primary-field'])
                            ->label('Tipo de evento')
                            ->disabled(),

                        Forms\Components\TextInput::make('from_status')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-status-field bexia-scer-from-status-field bexia-scer-state-field'])
                            ->label('Estado anterior')
                            ->disabled(),

                        Forms\Components\TextInput::make('to_status')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-status-field bexia-scer-to-status-field bexia-scer-state-field'])
                            ->label('Estado nuevo')
                            ->disabled(),

                        Forms\Components\TextInput::make('service_case_id')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-reference-field bexia-scer-service-case-field bexia-scer-related-field'])
                            ->label('Ticket')
                            ->disabled(),

                        Forms\Components\TextInput::make('repair_order_id')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-reference-field bexia-scer-repair-order-field bexia-scer-related-field'])
                            ->label('Reparacion')
                            ->disabled(),

                        Forms\Components\TextInput::make('performed_by')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-user-field bexia-scer-performed-by-field bexia-scer-related-field'])
                            ->label('Usuario')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('performed_at')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-date-field bexia-scer-performed-at-field bexia-scer-timeline-field'])
                            ->label('Fecha')
                            ->disabled(),

                        Forms\Components\TextInput::make('company_id')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-company-field bexia-scer-reference-field bexia-scer-related-field'])
                            ->label('Empresa')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Notas')
                    ->extraAttributes(['class' => 'bexia-scer-section bexia-scer-section-notes bexia-scer-section-long-text'])
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-notes-field bexia-scer-long-field bexia-scer-bitacora-field'])
                            ->label('Notas')
                            ->rows(5)
                            ->disabled(),

                        Forms\Components\Textarea::make('metadata')
                            ->extraAttributes(['class' => 'bexia-scer-field bexia-scer-metadata-field bexia-scer-long-field bexia-scer-technical-field'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-performed-at bexia-scer-col-date bexia-scer-col-timeline bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-performed-at bexia-scer-col-date bexia-scer-col-timeline bexia-scer-col-context'])
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-event-type bexia-scer-col-primary bexia-scer-col-code bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-event-type bexia-scer-col-primary bexia-scer-col-code bexia-scer-col-context'])
                    ->label('Evento')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_case_id')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-service-case bexia-scer-col-reference bexia-scer-col-related bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-service-case bexia-scer-col-reference bexia-scer-col-related bexia-scer-col-context'])
                    ->label('Ticket')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('repair_order_id')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-repair-order bexia-scer-col-reference bexia-scer-col-related bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-repair-order bexia-scer-col-reference bexia-scer-col-related bexia-scer-col-context'])
                    ->label('Reparacion')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('from_status')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-from-status bexia-scer-col-status bexia-scer-col-state bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-from-status bexia-scer-col-status bexia-scer-col-state bexia-scer-col-context'])
                    ->label('Antes')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('to_status')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-to-status bexia-scer-col-status bexia-scer-col-state bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-to-status bexia-scer-col-status bexia-scer-col-state bexia-scer-col-context'])
                    ->label('Despues')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('performed_by')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-performed-by bexia-scer-col-user bexia-scer-col-related bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-performed-by bexia-scer-col-user bexia-scer-col-related bexia-scer-col-context'])
                    ->label('Usuario')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-notes bexia-scer-col-long-text bexia-scer-col-bitacora bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-notes bexia-scer-col-long-text bexia-scer-col-bitacora bexia-scer-col-context'])
                    ->label('Notas')
                    ->limit(70)
                    ->searchable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->extraHeaderAttributes(['class' => 'bexia-scer-col-company bexia-scer-col-reference bexia-scer-col-related bexia-scer-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-scer-col-company bexia-scer-col-reference bexia-scer-col-related bexia-scer-col-context'])
                    ->label('Empresa')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->extraAttributes(['class' => 'bexia-scer-filter bexia-scer-filter-event-type bexia-scer-filter-primary'])
                    ->label('Evento')
                    ->options(fn (): array => ServiceCaseEvent::query()
                        ->select('event_type')
                        ->distinct()
                        ->orderBy('event_type')
                        ->pluck('event_type', 'event_type')
                        ->filter()
                        ->toArray()),

                Tables\Filters\Filter::make('solo_tickets')
                    ->extraAttributes(['class' => 'bexia-scer-filter bexia-scer-filter-solo-tickets bexia-scer-filter-case'])
                    ->label('Solo tickets')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('service_case_id')->whereNull('repair_order_id')),

                Tables\Filters\Filter::make('solo_reparaciones')
                    ->extraAttributes(['class' => 'bexia-scer-filter bexia-scer-filter-solo-reparaciones bexia-scer-filter-repair'])
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
