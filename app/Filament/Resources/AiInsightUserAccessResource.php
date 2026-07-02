<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiInsightUserAccessResource\Pages;
use App\Models\AiInsightUserAccess;
use App\Models\User;
use App\Support\AiInsights\AiInsightsSecurityScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AiInsightUserAccessResource extends Resource
{
    /**
     * BEXIA_AI_INSIGHT_USER_ACCESS_RESOURCE_RESPONSIVE_V5_79_91C
     *
     * Visual-only responsive classes for AiInsightUserAccessResource.
     */
    protected static ?string $model = AiInsightUserAccess::class;

    // BEXIA_V57211C_AI_INSIGHTS_ACCESS_NO_TENANT_SCOPE
    // Esta pantalla es de administracion global para superadmin.
    // No debe filtrarse por tenant porque el modelo no pertenece a una empresa especifica.
    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Dirección';

    protected static ?string $navigationLabel = 'Accesos Bexia Insights AI';

    protected static ?string $modelLabel = 'Acceso Bexia Insights AI';

    protected static ?string $pluralModelLabel = 'Accesos Bexia Insights AI';

    protected static ?int $navigationSort = 20;

    public static function shouldRegisterNavigation(): bool
    {
        return AiInsightsSecurityScope::canManageAccess(auth()->user());
    }

    public static function canViewAny(): bool
    {
        return AiInsightsSecurityScope::canManageAccess(auth()->user());
    }

    public static function canCreate(): bool
    {
        return AiInsightsSecurityScope::canManageAccess(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return AiInsightsSecurityScope::canManageAccess(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return AiInsightsSecurityScope::canManageAccess(auth()->user());
    }

    // BEXIA_V57917C_AI_INSIGHTS_DELETE_ANY
    public static function canDeleteAny(): bool
    {
        return AiInsightsSecurityScope::canManageAccess(auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'bexia-aiu-form bexia-aiu-form-main bexia-aiu-shell',
            ])
            ->schema([
                Forms\Components\Section::make('Usuario autorizado')
                    ->extraAttributes([
                        'class' => 'bexia-aiu-section bexia-aiu-section-main',
                    ])
                    ->description('Esta lista solo habilita el acceso a Bexia Insights AI. Las empresas consultables siguen saliendo de la asignación real del usuario.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->extraAttributes([
                                'class' => 'bexia-aiu-field bexia-aiu-user-field bexia-aiu-select-field bexia-aiu-wide-field',
                            ])
                            ->label('Usuario')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                $like = '%' . mb_strtolower($search) . '%';

                                return User::query()
                                    ->where(function ($query) use ($like): void {
                                        $query
                                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                                    })
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (User $user): array => [
                                        $user->id => "#{$user->id} {$user->name} <{$user->email}>",
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $user = User::find($value);

                                return $user
                                    ? "#{$user->id} {$user->name} <{$user->email}>"
                                    : null;
                            })
                            ->disabledOn('edit'),

                        Forms\Components\Toggle::make('is_enabled')
                            ->extraAttributes([
                                'class' => 'bexia-aiu-field bexia-aiu-enabled-field bexia-aiu-toggle-field',
                            ])
                            ->label('Acceso activo')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Select::make('access_level')
                            ->extraAttributes([
                                'class' => 'bexia-aiu-field bexia-aiu-access-level-field bexia-aiu-select-field',
                            ])
                            ->label('Nivel de acceso')
                            ->required()
                            ->default('director')
                            ->options([
                                'director' => 'Director',
                                'admin' => 'Admin Insights',
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes([
                                'class' => 'bexia-aiu-field bexia-aiu-notes-field bexia-aiu-wide-field',
                            ])
                            ->label('Notas internas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.id')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aiu-header bexia-aiu-col-id',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aiu-cell bexia-aiu-col-id bexia-aiu-col-compact',
                    ])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aiu-header bexia-aiu-col-user',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aiu-cell bexia-aiu-col-user bexia-aiu-col-wide',
                    ])
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aiu-header bexia-aiu-col-email',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aiu-cell bexia-aiu-col-email bexia-aiu-col-wide',
                    ])
                    ->label('Correo')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aiu-header bexia-aiu-col-enabled',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aiu-cell bexia-aiu-col-enabled bexia-aiu-col-bool',
                    ])
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('access_level')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aiu-header bexia-aiu-col-access-level',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aiu-cell bexia-aiu-col-access-level bexia-aiu-col-badge',
                    ])
                    ->label('Nivel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'admin' => 'Admin Insights',
                        'director' => 'Director',
                        default => $state ?: 'Sin nivel',
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aiu-header bexia-aiu-col-updated',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aiu-cell bexia-aiu-col-updated bexia-aiu-col-date',
                    ])
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aiu-header bexia-aiu-col-updated-by',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aiu-cell bexia-aiu-col-updated-by bexia-aiu-col-relation',
                    ])
                    ->label('Actualizado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiInsightUserAccesses::route('/'),
            'create' => Pages\CreateAiInsightUserAccess::route('/create'),
            'edit' => Pages\EditAiInsightUserAccess::route('/{record}/edit'),
        ];
    }
}
