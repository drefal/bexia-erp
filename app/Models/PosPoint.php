<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosPoint extends Model
{
    protected $table = 'pos_points';

    protected $guarded = [];

    protected $casts = [
        'payment_method_ids' => 'array',
        'show_order_reference_on_ticket' => 'boolean',
        'allow_partial_payment' => 'boolean',
        'is_bar_restaurant' => 'boolean',
        'multiple_cashiers_per_session' => 'boolean',
        'restrict_categories' => 'boolean',
        'allowed_category_ids' => 'array',
        'show_product_info' => 'boolean',
        'hide_cost' => 'boolean',
        'hide_margin' => 'boolean',
        'show_stock' => 'boolean',
        'allow_out_of_stock_sales' => 'boolean',
        'show_pos_orders' => 'boolean',
        'show_draft_orders' => 'boolean',
        'show_published_orders' => 'boolean',
        'show_barcode_on_ticket' => 'boolean',
        'flexible_taxes' => 'boolean',
        'available_price_list_ids' => 'array',
        'price_control' => 'boolean',
        'line_discounts' => 'boolean',
        'global_discounts' => 'boolean',
        'promotions_enabled' => 'boolean',
        'custom_receipt_header_footer' => 'boolean',
        'auto_print_receipt' => 'boolean',
        'skip_receipt_preview' => 'boolean',
        'use_qr_on_receipt' => 'boolean',
        'payment_method_names' => 'array',
        'cash_rounding' => 'boolean',
        'cash_denominations' => 'array',
        'tips_enabled' => 'boolean',
        'printer_enabled' => 'boolean',
        'customer_display_enabled' => 'boolean',
        'iot_box_enabled' => 'boolean',
        'send_later' => 'boolean',
        'limited_products_loading' => 'boolean',
        'load_products_in_background' => 'boolean',
        'limited_customers_loading' => 'boolean',
        'load_customers_in_background' => 'boolean',
    ];
}
