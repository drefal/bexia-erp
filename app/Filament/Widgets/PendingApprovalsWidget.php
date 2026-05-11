<?php

namespace App\Filament\Widgets;

use App\Support\ApprovalInbox;
use Filament\Widgets\Widget;

class PendingApprovalsWidget extends Widget
{
    protected static string $view = 'filament.widgets.pending-approvals-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function getPendingRows()
    {
        return ApprovalInbox::rowsForCurrentUser(5);
    }

    public function getPendingCountProperty(): int
    {
        return ApprovalInbox::countForCurrentUser();
    }
}
