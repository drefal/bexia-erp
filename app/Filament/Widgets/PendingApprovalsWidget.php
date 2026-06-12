<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use App\Support\ApprovalInbox;
use Filament\Widgets\Widget;

class PendingApprovalsWidget extends Widget
{
    protected static string $view = 'filament.widgets.pending-approvals-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 20;

    public static function canView(): bool
    {
        // BEXIA_V57210G2_DASHBOARD_WIDGET_PERMISSION
        if (! \App\Support\Security\BexiaAccess::dashboard()) {
            return false;
        }

        if (! auth()->check()) {
            return false;
        }

        try {
            return app(DashboardWidgetRegistry::class)
                ->visibleForUser(auth()->user())
                ->pluck('key')
                ->contains('approvals_pending');
        } catch (\Throwable) {
            return true;
        }
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
