<?php

namespace App\Providers;

use App\Models\PurchaseRequest;
use App\Observers\PurchaseRequestObserver;

use App\Observers\ProductPriceCostAuditObserver;
use App\Models\Product;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (class_exists(PurchaseRequest::class) && class_exists(PurchaseRequestObserver::class)) {
            PurchaseRequest::observe(PurchaseRequestObserver::class);
        }

        Product::observe(ProductPriceCostAuditObserver::class);

        if (class_exists(\Filament\Support\Facades\FilamentView::class)) {
            \Filament\Support\Facades\FilamentView::registerRenderHook(
                'panels::styles.after',
                fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                    '<link rel="stylesheet" href="' . asset('css/bexia-filament-compact.css') . '?v=1">'
                ),
            );
        }

        // En producción, forzamos https para que las Signed URLs (Livewire upload) no fallen.
        if (app()->environment('production')) {
            URL::forceScheme('https');

            // Usa APP_URL como raíz (debe ser https://app.bexiaerp.com)
            $root = config('app.url');
            if (! empty($root)) {
                URL::forceRootUrl($root);
            }
        }
    }
}
