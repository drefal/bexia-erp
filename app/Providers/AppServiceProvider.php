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
        // BEXIA_V5550G_INTERNAL_REFERENCE_MODAL_RENDER_HOOK
        if (
            class_exists(\Filament\Support\Facades\FilamentView::class)
            && class_exists(\Filament\View\PanelsRenderHook::class)
            && view()->exists('filament.products.internal-reference-duplicate-modal')
        ) {
            \Filament\Support\Facades\FilamentView::registerRenderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => view('filament.products.internal-reference-duplicate-modal')->render(),
            );
        }

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
