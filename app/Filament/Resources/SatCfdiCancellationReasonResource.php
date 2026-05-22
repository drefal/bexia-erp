<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatCfdiCancellationReasonResource\Pages;
use App\Models\SatCfdiCancellationReason;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatCfdiCancellationReasonResource extends Resource
{
    protected static ?string $model = SatCfdiCancellationReason::class;

    protected static ?string $navigationIcon = 'heroicon-o-x-circle';

    protected static ?string $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Motivos cancelación CFDI';

    protected static ?string $modelLabel = 'motivo de cancelación CFDI';

    protected static ?string $pluralModelLabel = 'motivos de cancelación CFDI';

    protected static ?int $navigationSort = 50;

public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Clave SAT')
                    ->required()
                    ->maxLength(2)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')
                    ->label('Descripción')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('requires_replacement_uuid')
                    ->label('Requiere UUID sustituto'),
                Forms\Components\Toggle::make('active')
                    ->label('Activo')
                    ->default(true),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Clave')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\IconColumn::make('requires_replacement_uuid')
                    ->label('UUID sustituto')
                    ->boolean(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSatCfdiCancellationReasons::route('/'),
            'create' => Pages\CreateSatCfdiCancellationReason::route('/create'),
            'edit' => Pages\EditSatCfdiCancellationReason::route('/{record}/edit'),
        ];
    }
}
