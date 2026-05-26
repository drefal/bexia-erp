<?php

namespace App\Filament\Pages;

use App\Support\BexiaUserNotification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MyNotifications extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Mis avisos';

    protected static ?string $title = 'Mis avisos';

    protected static ?string $navigationGroup = 'Inicio';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.my-notifications';

    public array $rows = [];

    public function mount(): void
    {
        $this->refreshRows();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = class_exists(BexiaUserNotification::class)
            ? BexiaUserNotification::unreadCountForUser()
            : 0;

        return $count > 0 ? (string) $count : null;
    }

    public function refreshRows(): void
    {
        if (! auth()->check() || ! Schema::hasTable('bexia_notifications')) {
            $this->rows = [];
            return;
        }

        $this->rows = DB::table('bexia_notifications')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'body' => (string) ($row->body ?? ''),
                'url' => (string) ($row->url ?? ''),
                'type' => (string) ($row->type ?? ''),
                'read_at' => $row->read_at,
                'created_at' => (string) $row->created_at,
            ])
            ->all();
    }

    public function markAsRead(int $id): void
    {
        BexiaUserNotification::markAsRead($id);
        $this->refreshRows();
    }

    public function markAllAsRead(): void
    {
        BexiaUserNotification::markAllAsRead();
        $this->refreshRows();
    }
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }


}
