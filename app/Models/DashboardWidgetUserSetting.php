<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidgetUserSetting extends Model
{
    protected $table = 'dashboard_widget_user_settings';

    protected $fillable = [
        'company_id',
        'user_id',
        'widget_key',
        'is_visible',
        'sort_order',
        'settings',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'user_id' => 'integer',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
