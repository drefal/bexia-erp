<?php

namespace App\Filament\Pages;

use App\Models\BexiaMenuGroup;
use App\Models\BexiaMenuItem;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class BexiaMenuBuilder extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?string $navigationGroup = 'Configuración Bexia';

    protected static ?string $navigationLabel = 'Menú lateral';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.bexia-menu-builder';

    public array $groupLabels = [];

    public array $itemLabels = [];

    public function mount(): void
    {
        $this->loadLabels();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public function getTitle(): string
    {
        return 'Menú lateral';
    }

    public function getBreadcrumb(): string
    {
        return 'Menú lateral';
    }

    public function groups()
    {
        return BexiaMenuGroup::query()
            ->with(['items' => fn ($query) => $query->orderBy('sort')->orderBy('label')])
            ->orderBy('sort')
            ->orderBy('label')
            ->get();
    }

    public function loadLabels(): void
    {
        $this->groupLabels = BexiaMenuGroup::query()
            ->pluck('label', 'id')
            ->map(fn ($label) => (string) $label)
            ->all();

        $this->itemLabels = BexiaMenuItem::query()
            ->pluck('label', 'id')
            ->map(fn ($label) => (string) $label)
            ->all();
    }

    public function saveGroupLabel(int $groupId): void
    {
        $label = trim((string) ($this->groupLabels[$groupId] ?? ''));

        if ($label === '') {
            $this->loadLabels();

            Notification::make()
                ->title('El nombre del grupo no puede quedar vacío.')
                ->danger()
                ->send();

            return;
        }

        BexiaMenuGroup::query()
            ->whereKey($groupId)
            ->update(['label' => $label]);

        Notification::make()
            ->title('Grupo actualizado.')
            ->success()
            ->send();
    }

    public function saveItemLabel(int $itemId): void
    {
        $label = trim((string) ($this->itemLabels[$itemId] ?? ''));

        if ($label === '') {
            $this->loadLabels();

            Notification::make()
                ->title('El nombre de la opción no puede quedar vacío.')
                ->danger()
                ->send();

            return;
        }

        BexiaMenuItem::query()
            ->whereKey($itemId)
            ->update(['label' => $label]);

        Notification::make()
            ->title('Opción actualizada.')
            ->success()
            ->send();
    }

    public function toggleGroupVisibility(int $groupId): void
    {
        $group = BexiaMenuGroup::query()->findOrFail($groupId);

        $group->update([
            'is_visible' => ! $group->is_visible,
        ]);

        Notification::make()
            ->title($group->is_visible ? 'Grupo visible.' : 'Grupo oculto.')
            ->success()
            ->send();
    }

    public function toggleItemVisibility(int $itemId): void
    {
        $item = BexiaMenuItem::query()->findOrFail($itemId);

        $item->update([
            'is_visible' => ! $item->is_visible,
        ]);

        Notification::make()
            ->title($item->is_visible ? 'Opción visible.' : 'Opción oculta.')
            ->success()
            ->send();
    }

    public function moveGroup(int $groupId, string $direction): void
    {
        $groups = BexiaMenuGroup::query()
            ->orderBy('sort')
            ->orderBy('label')
            ->get();

        $this->moveModelWithinCollection($groups, $groupId, $direction);

        Notification::make()
            ->title('Orden de grupos actualizado.')
            ->success()
            ->send();
    }

    public function moveItem(int $itemId, string $direction): void
    {
        $item = BexiaMenuItem::query()->findOrFail($itemId);

        $items = BexiaMenuItem::query()
            ->where('group_id', $item->group_id)
            ->orderBy('sort')
            ->orderBy('label')
            ->get();

        $this->moveModelWithinCollection($items, $itemId, $direction);

        Notification::make()
            ->title('Orden de opciones actualizado.')
            ->success()
            ->send();
    }

    public function moveItemToGroup(int $itemId, int $groupId): void
    {
        $item = BexiaMenuItem::query()->findOrFail($itemId);
        $targetGroup = BexiaMenuGroup::query()->findOrFail($groupId);

        DB::transaction(function () use ($item, $targetGroup): void {
            $maxSort = (int) BexiaMenuItem::query()
                ->where('group_id', $targetGroup->id)
                ->max('sort');

            $item->update([
                'group_id' => $targetGroup->id,
                'sort' => $maxSort + 10,
            ]);

            $this->normalizeItemSorts($targetGroup->id);
        });

        Notification::make()
            ->title('Opción movida al grupo: ' . $targetGroup->label)
            ->success()
            ->send();
    }

    public function resetGroupLabel(int $groupId): void
    {
        $group = BexiaMenuGroup::query()->findOrFail($groupId);

        $group->update([
            'label' => $group->default_label ?: $group->label,
        ]);

        $this->loadLabels();

        Notification::make()
            ->title('Nombre del grupo restaurado.')
            ->success()
            ->send();
    }

    public function resetItemLabel(int $itemId): void
    {
        $item = BexiaMenuItem::query()->findOrFail($itemId);

        $item->update([
            'label' => $item->default_label ?: $item->label,
        ]);

        $this->loadLabels();

        Notification::make()
            ->title('Nombre de la opción restaurado.')
            ->success()
            ->send();
    }

    public function resetDefaultOrder(): void
    {
        DB::transaction(function (): void {
            $groups = BexiaMenuGroup::query()
                ->orderBy('id')
                ->get();

            foreach ($groups as $index => $group) {
                $group->update([
                    'label' => $group->default_label ?: $group->label,
                    'sort' => ($index + 1) * 10,
                    'is_visible' => true,
                ]);

                $items = BexiaMenuItem::query()
                    ->where('group_id', $group->id)
                    ->orderBy('id')
                    ->get();

                foreach ($items as $itemIndex => $item) {
                    $item->update([
                        'label' => $item->default_label ?: $item->label,
                        'sort' => ($itemIndex + 1) * 10,
                    ]);
                }
            }
        });

        $this->loadLabels();

        Notification::make()
            ->title('Menú restaurado a valores base.')
            ->success()
            ->send();
    }

    protected function moveModelWithinCollection($collection, int $modelId, string $direction): void
    {
        $ids = $collection->pluck('id')->values()->all();
        $index = array_search($modelId, $ids, true);

        if ($index === false) {
            return;
        }

        $newIndex = $direction === 'up'
            ? max(0, $index - 1)
            : min(count($ids) - 1, $index + 1);

        if ($newIndex === $index) {
            return;
        }

        $moved = $ids[$index];
        array_splice($ids, $index, 1);
        array_splice($ids, $newIndex, 0, [$moved]);

        DB::transaction(function () use ($ids, $collection): void {
            $modelClass = $collection->first()::class;

            foreach ($ids as $position => $id) {
                $modelClass::query()
                    ->whereKey($id)
                    ->update(['sort' => ($position + 1) * 10]);
            }
        });
    }

    protected function normalizeItemSorts(int $groupId): void
    {
        $ids = BexiaMenuItem::query()
            ->where('group_id', $groupId)
            ->orderBy('sort')
            ->orderBy('label')
            ->pluck('id')
            ->values()
            ->all();

        foreach ($ids as $index => $id) {
            BexiaMenuItem::query()
                ->whereKey($id)
                ->update(['sort' => ($index + 1) * 10]);
        }
    }
}
