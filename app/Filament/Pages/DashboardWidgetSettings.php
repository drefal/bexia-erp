<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Dashboard\DashboardWidgetRegistry;
use App\Support\Dashboard\UserDashboardPreferenceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardWidgetSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Configurar escritorio';

    protected static ?string $navigationGroup = 'Configuración empresa';

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'Configurar escritorio';

    protected static ?string $slug = 'configurar-escritorio';

    protected static string $view = 'filament.pages.dashboard-widget-settings';

    public int $companyId = 0;

    public ?int $selectedUserId = null;

    public array $users = [];

    public array $rows = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        foreach (['dashboard.configurar', 'company.update', 'company.settings.update'] as $permission) {
            try {
                if ($user->can($permission)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->companyId = app(DashboardWidgetRegistry::class)->currentCompanyId();
        $this->loadUsers();

        if (! $this->selectedUserId && $this->users !== []) {
            $authId = (int) auth()->id();
            $userIds = collect($this->users)->pluck('id')->map(fn ($id) => (int) $id)->all();

            $this->selectedUserId = in_array($authId, $userIds, true)
                ? $authId
                : (int) ($this->users[0]['id'] ?? 0);
        }

        $this->loadSettings();
    }

    public function updatedSelectedUserId($value): void
    {
        $this->selectedUserId = $value ? (int) $value : null;
        $this->loadSettings();
    }

    public function refreshDashboardSettings(): void
    {
        $this->loadUsers();
        $this->loadSettings();

        Notification::make()
            ->title('Escritorio actualizado')
            ->success()
            ->send();
    }

    public function toggleWidget(string $widgetKey): void
    {
        if (! $this->selectedUserId || $this->companyId <= 0) {
            return;
        }

        $registry = app(DashboardWidgetRegistry::class);
        $user = User::query()->find((int) $this->selectedUserId);

        if (! $user) {
            return;
        }

        $catalog = $this->catalogForUser($user);

        if (! $catalog->has($widgetKey)) {
            Notification::make()
                ->title('Widget no permitido')
                ->body('El usuario seleccionado no tiene permiso para este widget.')
                ->danger()
                ->send();

            return;
        }

        $definition = (array) $catalog->get($widgetKey);
        $current = collect($this->rows)->firstWhere('key', $widgetKey);
        $currentVisible = (bool) ($current['is_visible'] ?? false);

        app(UserDashboardPreferenceService::class)->setVisibility(
            companyId: $this->companyId,
            userId: (int) $this->selectedUserId,
            widgetKey: $widgetKey,
            isVisible: ! $currentVisible,
            actorUserId: auth()->id(),
        );

        $this->loadSettings();

        Notification::make()
            ->title($currentVisible ? 'Widget oculto' : 'Widget visible')
            ->body($definition['label'] ?? $widgetKey)
            ->success()
            ->send();
    }

    public function moveWidgetUp(string $widgetKey): void
    {
        $this->moveWidget($widgetKey, -1);
    }

    public function moveWidgetDown(string $widgetKey): void
    {
        $this->moveWidget($widgetKey, 1);
    }

    public function resetSelectedUser(): void
    {
        if (! $this->selectedUserId || $this->companyId <= 0) {
            return;
        }

        app(UserDashboardPreferenceService::class)->resetUser(
            companyId: $this->companyId,
            userId: (int) $this->selectedUserId,
        );

        app(UserDashboardPreferenceService::class)->syncUserDefaults(
            companyId: $this->companyId,
            userId: (int) $this->selectedUserId,
            actorUserId: auth()->id(),
        );

        $this->loadSettings();

        Notification::make()
            ->title('Configuración restaurada')
            ->body('Se aplicaron los widgets por defecto para el usuario.')
            ->success()
            ->send();
    }

    protected function moveWidget(string $widgetKey, int $direction): void
    {
        if (! $this->selectedUserId || $this->companyId <= 0) {
            return;
        }

        $keys = collect($this->rows)->pluck('key')->values()->all();
        $index = array_search($widgetKey, $keys, true);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= count($keys)) {
            return;
        }

        [$keys[$index], $keys[$target]] = [$keys[$target], $keys[$index]];

        app(UserDashboardPreferenceService::class)->setOrder(
            companyId: $this->companyId,
            userId: (int) $this->selectedUserId,
            orderedWidgetKeys: $keys,
            actorUserId: auth()->id(),
        );

        $this->loadSettings();
    }

    protected function loadUsers(): void
    {
        $companyId = $this->companyId;

        $ids = collect();

        if (Schema::hasTable('model_has_roles')) {
            $columns = Schema::getColumnListing('model_has_roles');

            if (in_array('company_id', $columns, true)) {
                $ids = $ids->merge(
                    DB::table('model_has_roles')
                        ->where('model_type', User::class)
                        ->where('company_id', $companyId)
                        ->pluck('model_id')
                );
            }
        }

        foreach (['company_user', 'company_user_access', 'company_users'] as $pivotTable) {
            if (! Schema::hasTable($pivotTable)) {
                continue;
            }

            $columns = Schema::getColumnListing($pivotTable);

            if (! in_array('user_id', $columns, true) || ! in_array('company_id', $columns, true)) {
                continue;
            }

            $ids = $ids->merge(
                DB::table($pivotTable)
                    ->where('company_id', $companyId)
                    ->pluck('user_id')
            );
        }

        if (auth()->id()) {
            $ids->push((int) auth()->id());
        }

        $ids = $ids
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $query = User::query()
            ->select(['id', 'name', 'email', 'is_system_admin'])
            ->orderBy('name')
            ->orderBy('email');

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        } elseif (! (bool) (auth()->user()?->is_system_admin ?? false)) {
            $query->where('id', auth()->id());
        }

        $this->users = $query
            ->limit(200)
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?: $user->email),
                'email' => (string) $user->email,
                'is_system_admin' => (bool) ($user->is_system_admin ?? false),
            ])
            ->values()
            ->all();
    }

    protected function loadSettings(): void
    {
        $this->rows = [];

        if (! $this->selectedUserId || $this->companyId <= 0) {
            return;
        }

        $user = User::query()->find((int) $this->selectedUserId);

        if (! $user) {
            return;
        }

        $registry = app(DashboardWidgetRegistry::class);
        $preferences = app(UserDashboardPreferenceService::class);

        $preferences->syncUserDefaults(
            companyId: $this->companyId,
            userId: (int) $user->id,
            actorUserId: auth()->id(),
        );

        $saved = $registry->preferencesFor($this->companyId, (int) $user->id);
        $catalog = $this->catalogForUser($user);

        $this->rows = $catalog
            ->map(function (array $definition, string $key) use ($saved, $registry, $user): array {
                $setting = $saved->get($key);

                return [
                    'key' => $key,
                    'label' => (string) ($definition['label'] ?? $key),
                    'description' => (string) ($definition['description'] ?? ''),
                    'module' => (string) ($definition['module'] ?? 'General'),
                    'is_visible' => $setting ? (bool) $setting->is_visible : (bool) ($definition['default_visible'] ?? true),
                    'sort_order' => $setting ? (int) $setting->sort_order : (int) ($definition['sort_order'] ?? 100),
                    'allowed_by_permission' => $registry->userCanViewDefinition($user, $definition),
                ];
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * BEXIA_V582_P7G10A_FILTER_SELECTED_USER_PERMISSIONS
     */
    protected function catalogForUser(User $user): Collection
    {
        $registry = app(DashboardWidgetRegistry::class);

        return collect($registry->catalog())
            ->filter(
                fn (array $definition): bool =>
                    $registry->userCanViewDefinition(
                        $user,
                        $definition,
                        $this->companyId,
                    )
            );
    }

    public function selectedUserLabel(): string
    {
        $user = collect($this->users)->firstWhere('id', (int) $this->selectedUserId);

        if (! $user) {
            return 'Sin usuario seleccionado';
        }

        return trim(($user['name'] ?? '') . ' · ' . ($user['email'] ?? ''));
    }
}
