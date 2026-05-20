<?php

use App\Http\Controllers\Inventory\SuggestedPurchaseListPdfController;

use App\Http\Controllers\Inventory\ReplenishmentReportPdfController;

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Dominios (puedes cambiarlos por env si quieres)
$appDomain  = env('BEXIA_APP_DOMAIN', 'app.bexiaerp.com');
$rootDomain = env('BEXIA_ROOT_DOMAIN', 'bexiaerp.com');
$wwwDomain  = env('BEXIA_WWW_DOMAIN', 'www.bexiaerp.com');

/*
|--------------------------------------------------------------------------
| 1) Marketing / Landing (bexiaerp.com)
|--------------------------------------------------------------------------
*/
Route::domain($rootDomain)->group(function () use ($appDomain) {
    Route::view('/', 'landing')->name('home');

    // /login en el sitio marketing manda al login real de la app
    Route::redirect('/login', "https://{$appDomain}/admin/login")->name('login.redirect');
});

/*
|--------------------------------------------------------------------------
| 2) Canonical (www -> root)
|--------------------------------------------------------------------------
*/
Route::domain($wwwDomain)->group(function () use ($rootDomain) {
    Route::redirect('/', "https://{$rootDomain}")->name('www.redirect.home');
});

/*
|--------------------------------------------------------------------------
| 3) App (app.bexiaerp.com)
|--------------------------------------------------------------------------
*/
Route::domain($appDomain)->group(function () {

    // Si alguien entra a app.bexiaerp.com/ lo mandamos al login del admin
    Route::redirect('/', '/admin/login')->name('app.home');

    // Atajo dentro del subdominio app
    Route::redirect('/login', '/admin/login')->name('app.login.redirect');

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__ . '/auth.php';
});

/*
|--------------------------------------------------------------------------
| Contactos - Constancia de Situación Fiscal
|--------------------------------------------------------------------------
| Archivos privados de Constancia SAT ligados al contacto.
*/
\Illuminate\Support\Facades\Route::middleware(['web', 'auth'])->group(function (): void {
    \Illuminate\Support\Facades\Route::get('/contacts/{contact}/constancia-sat/ver', [\App\Http\Controllers\ContactCsfFileController::class, 'show'])
        ->name('contacts.csf.show');

    \Illuminate\Support\Facades\Route::get('/contacts/{contact}/constancia-sat/descargar', [\App\Http\Controllers\ContactCsfFileController::class, 'download'])
        ->name('contacts.csf.download');
});


// Inventory PDF documents
\Illuminate\Support\Facades\Route::middleware(['auth'])->group(function (): void {
    \Illuminate\Support\Facades\Route::get(
        '/inventory/stock-adjustments/{record}/pdf',
        [\App\Http\Controllers\InventoryDocumentPdfController::class, 'stockAdjustment']
    )->name('inventory.stock-adjustments.pdf');

    \Illuminate\Support\Facades\Route::get(
        '/inventory/stock-movements/{record}/pdf',
        [\App\Http\Controllers\InventoryDocumentPdfController::class, 'stockMovement']
    )->name('inventory.stock-movements.pdf');
});

// Inventory initial load template download
\Illuminate\Support\Facades\Route::middleware(['auth'])->get(
    '/inventory/initial-load-template/download',
    \App\Http\Controllers\InventoryInitialLoadTemplateController::class
)->name('inventory.initial-load-template.download');

Route::get('/inventory/replenishment-report/pdf', ReplenishmentReportPdfController::class)->middleware(['web', 'auth'])->name('inventory.replenishment-report.pdf');

Route::get('/inventory/suggested-purchase-list/pdf', SuggestedPurchaseListPdfController::class)->middleware(['web', 'auth'])->name('inventory.suggested-purchase-list.pdf');

use App\Http\Controllers\Purchases\PurchaseRequestPrintController;

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/requests/{purchaseRequest}/print', PurchaseRequestPrintController::class)
        ->name('purchases.requests.print');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/requests/{purchaseRequest}/create-order', \App\Http\Controllers\Purchases\CreatePurchaseOrderFromRequestController::class)
        ->name('purchases.requests.create-order');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/orders/{purchaseOrder}/confirm', \App\Http\Controllers\Purchases\ConfirmPurchaseOrderController::class)
        ->name('purchases.orders.confirm');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/orders/{purchaseOrder}/print', \App\Http\Controllers\Purchases\PrintPurchaseOrderController::class)
        ->name('purchases.orders.print');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/requests/{purchaseRequest}/open-order', \App\Http\Controllers\Purchases\OpenPurchaseOrderFromRequestController::class)
        ->name('purchases.requests.open-order');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/requests/{purchaseRequest}/duplicate', \App\Http\Controllers\Purchases\DuplicatePurchaseRequestController::class)
        ->name('purchases.requests.duplicate');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/requests/{purchaseRequest}/approve', [\App\Http\Controllers\Purchases\PurchaseRequestApprovalController::class, 'approve'])
        ->name('purchases.requests.approve');

    Route::post('/purchases/requests/{purchaseRequest}/reject', [\App\Http\Controllers\Purchases\PurchaseRequestApprovalController::class, 'reject'])
        ->name('purchases.requests.reject');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/purchases/orders/import-xml', \App\Http\Controllers\Purchases\ImportPurchaseOrderXmlController::class)
        ->name('purchases.orders.import-xml');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/orders/{purchaseOrder}/xml-mapping', [\App\Http\Controllers\Purchases\PurchaseOrderXmlLineMappingController::class, 'edit'])
        ->name('purchases.orders.xml-mapping.edit');

    Route::post('/purchases/orders/{purchaseOrder}/xml-mapping', [\App\Http\Controllers\Purchases\PurchaseOrderXmlLineMappingController::class, 'update'])
        ->name('purchases.orders.xml-mapping.update');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchases/orders/{purchaseOrder}/receive', [\App\Http\Controllers\Purchases\PurchaseOrderReceiptController::class, 'edit'])
        ->name('purchases.orders.receipts.edit');

    Route::post('/purchases/orders/{purchaseOrder}/receive', [\App\Http\Controllers\Purchases\PurchaseOrderReceiptController::class, 'store'])
        ->name('purchases.orders.receipts.store');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/admin/{tenant}/purchase-receipts/{purchaseReceipt}', [\App\Http\Controllers\Purchases\PurchaseReceiptViewController::class, 'show'])
        ->name('purchases.receipts.show');

    Route::get('/admin/{tenant}/purchase-receipts/{purchaseReceipt}/pdf', [\App\Http\Controllers\Purchases\PurchaseReceiptViewController::class, 'pdf'])
        ->name('purchases.receipts.pdf');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/admin/{tenant}/stock-movements/{stockMovement}/pdf', [\App\Http\Controllers\Inventory\StockMovementPdfController::class, 'pdf'])
        ->name('inventory.stock-movements.general-pdf');
});


Route::middleware(['web', 'auth'])
    ->get('/sales/orders/{saleOrder}/print', \App\Http\Controllers\Sales\SalesOrderPrintController::class)
    ->name('sales.orders.print');

// BEXIA V5.29.3 sales delivery routes
if (class_exists(\App\Http\Controllers\SaleDeliveryController::class)) {
    \Illuminate\Support\Facades\Route::middleware(['auth'])->group(function () {
        \Illuminate\Support\Facades\Route::post(
            '/sales-orders/{saleOrder}/deliveries/full',
            [\App\Http\Controllers\SaleDeliveryController::class, 'storeFull']
        )->name('sales-orders.deliveries.full');

        \Illuminate\Support\Facades\Route::post(
            '/sales-orders/{saleOrder}/deliveries/partial',
            [\App\Http\Controllers\SaleDeliveryController::class, 'storePartial']
        )->name('sales-orders.deliveries.partial');

        \Illuminate\Support\Facades\Route::post(
            '/sales-deliveries/{saleDelivery}/cancel',
            [\App\Http\Controllers\SaleDeliveryController::class, 'cancel']
        )->name('sales-deliveries.cancel');
    });
}
// END BEXIA V5.29.3 sales delivery routes

// BEXIA V5.29.3 sales delivery standalone page
if (class_exists(\App\Http\Controllers\SaleDeliveryController::class)) {
    \Illuminate\Support\Facades\Route::middleware(['auth'])->group(function () {
        \Illuminate\Support\Facades\Route::get(
            '/sales-orders/{saleOrder}/delivery',
            [\App\Http\Controllers\SaleDeliveryController::class, 'show']
        )->name('sales-orders.deliveries.page');
    });
}
// END BEXIA V5.29.3 sales delivery standalone page


// BEXIA V5.29.3 sales delivery print route
if (class_exists(\App\Http\Controllers\SaleDeliveryController::class)) {
    \Illuminate\Support\Facades\Route::middleware(['auth'])->group(function () {
        \Illuminate\Support\Facades\Route::get(
            '/sales-deliveries/{saleDelivery}/print',
            [\App\Http\Controllers\SaleDeliveryController::class, 'printDelivery']
        )->name('sales-deliveries.print');
    });
}
// END BEXIA V5.29.3 sales delivery print route


// BEXIA V5.29.3 sales delivery PDF route
\Illuminate\Support\Facades\Route::middleware(['web', 'auth'])
    ->get('/sales/deliveries/{saleDelivery}/print', \App\Http\Controllers\Sales\SaleDeliveryPrintController::class)
    ->name('sales.deliveries.print');
// END BEXIA V5.29.3 sales delivery PDF route


// BEXIA V5.29.4 sales delivery validate route
if (class_exists(\App\Http\Controllers\SaleDeliveryController::class)) {
    \Illuminate\Support\Facades\Route::middleware(['auth'])->group(function () {
        \Illuminate\Support\Facades\Route::post(
            '/sales-deliveries/{saleDelivery}/validate',
            [\App\Http\Controllers\SaleDeliveryController::class, 'validateDelivery']
        )->name('sales-deliveries.validate');
    });
}
// END BEXIA V5.29.4 sales delivery validate route


// BEXIA V5.29.4 sales delivery show route
if (class_exists(\App\Http\Controllers\SaleDeliveryController::class)) {
    \Illuminate\Support\Facades\Route::middleware(['auth'])->group(function () {
        \Illuminate\Support\Facades\Route::get(
            '/sales-deliveries/{saleDelivery}/show',
            [\App\Http\Controllers\SaleDeliveryController::class, 'showDelivery']
        )->name('sales-deliveries.show');
    });
}
// END BEXIA V5.29.4 sales delivery show route


// V5.31.1 - Punto de Venta
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/pos/{posPoint}/open', [\App\Http\Controllers\PosController::class, 'open'])
        ->name('pos.open');

    Route::get('/pos/{posPoint}/cashiers/{cashier}', [\App\Http\Controllers\PosController::class, 'selectCashier'])
        ->name('pos.cashiers.select');

    Route::post('/pos/{posPoint}/cashiers/{cashier}/login', [\App\Http\Controllers\PosController::class, 'loginCashier'])
        ->name('pos.cashiers.login');

    Route::get('/pos/sessions/{session}/screen', [\App\Http\Controllers\PosController::class, 'screen'])
        ->name('pos.sessions.screen');
Route::get('/pos/sessions/{session}/close-summary', [\App\Http\Controllers\PosController::class, 'closeSessionSummary'])->name('pos.sessions.close-summary');
Route::get('/pos/sessions/{session}/close-ticket/print', [\App\Http\Controllers\PosController::class, 'printCloseSessionTicket'])->name('pos.sessions.close-ticket.print');
Route::get('/pos/sessions/{session}/sales-report', [\App\Http\Controllers\PosController::class, 'sessionSalesReport'])->name('pos.sessions.sales-report');
Route::get('/pos/sessions/{session}/payment-methods', [\App\Http\Controllers\PosController::class, 'paymentMethods'])->name('pos.sessions.payment-methods');
Route::post('/pos/sessions/{session}/stock-refresh', [\App\Http\Controllers\PosController::class, 'stockRefresh'])->name('pos.sessions.stock-refresh');
Route::get('/pos/orders/{order}/pending-ticket/print', [\App\Http\Controllers\PosController::class, 'printPendingTicket'])->name('pos.orders.pending-ticket.print');

Route::get('/pos/tickets/{order}/inventory-output/pdf', [\App\Http\Controllers\PosController::class, 'inventoryOutputPdf'])
    ->middleware(['web', 'auth'])
    ->name('pos.tickets.inventory-output.pdf');

Route::post('/pos/tickets/{order}/billing/request', [\App\Http\Controllers\PosController::class, 'requestTicketBilling'])
    ->middleware(['web', 'auth'])
    ->name('pos.tickets.billing.request');

Route::get('/pos/orders/{order}/receipt/print', [\App\Http\Controllers\PosController::class, 'printPaidTicket'])
    ->name('pos.orders.receipt.print');

Route::post('/pos/sessions/{session}/orders', [\App\Http\Controllers\PosController::class, 'storeOrder'])->name('pos.sessions.orders.store');
Route::post('/pos/orders/{order}/pay', [\App\Http\Controllers\PosController::class, 'payOrder'])->name('pos.orders.pay');
Route::get('/pos/orders/{order}', [\App\Http\Controllers\PosController::class, 'showOrder'])->name('pos.orders.show');
Route::post('/pos/orders/{order}/cancel-pending', [\App\Http\Controllers\PosController::class, 'cancelPendingOrder'])->name('pos.orders.pending.cancel');
Route::get('/pos/sessions/{session}/customers', [\App\Http\Controllers\PosController::class, 'posCustomers'])->name('pos.sessions.customers');
Route::get('/pos/sessions/{session}/pending-orders', [\App\Http\Controllers\PosController::class, 'pendingOrders'])->name('pos.sessions.pending-orders');
Route::get('/pos/sessions/{session}/pending-orders/{order}', [\App\Http\Controllers\PosController::class, 'pendingOrderDetail'])->name('pos.sessions.pending-orders.detail');
Route::get('/pos/sessions/{session}/pending-orders-search', [\App\Http\Controllers\PosController::class, 'pendingOrderSearch'])->name('pos.sessions.pending-orders.search');
Route::get('/pos/sessions/{session}/customers/{customer}/price-list', [\App\Http\Controllers\PosController::class, 'customerPriceListForSession'])->name('pos.sessions.customer-price-list');
Route::get('/pos/sessions/{session}/products-refresh', [\App\Http\Controllers\PosController::class, 'refreshSessionProducts'])->name('pos.sessions.products-refresh');
Route::post('/pos/sessions/{session}/price-list-changes', [\App\Http\Controllers\PosController::class, 'storePriceListChangeAudit'])->name('pos.sessions.price-list-changes.store');
});



/*
 * BEXIA_V5528A_PUBLIC_INVOICE_PORTAL_VALIDATE_TICKET
 * Portal público de autofacturación. Fase 1: validar ticket por folio + total.
 */
Route::get('/facturar', [\App\Http\Controllers\PublicInvoicePortalController::class, 'show'])
    ->name('public.invoice-placeholder');

Route::post('/facturar/validar', [\App\Http\Controllers\PublicInvoicePortalController::class, 'validateTicket'])
    ->name('public.invoice.validate');

Route::post('/facturar/solicitar', [\App\Http\Controllers\PublicInvoicePortalController::class, 'requestInvoice'])
    ->name('public.invoice.request');


/*
|--------------------------------------------------------------------------
| Portal público de facturación - descargas CFDI
|--------------------------------------------------------------------------
| BEXIA_V5528B8_PUBLIC_CFDI_DOWNLOAD_LINKS
*/
Route::middleware(['web'])->get(
    '/facturar/descargar/{invoice}/{type}/{token}',
    \App\Http\Controllers\PublicInvoiceDownloadController::class
)
    ->where('type', 'pdf|xml|zip')
    ->name('public.invoice.download');



Route::get('/pos/sessions/{session}/cash-employees', [\App\Http\Controllers\PosController::class, 'cashMovementEmployees'])->name('pos.sessions.cash-employees');
Route::post('/pos/sessions/{session}/cash-movements', [\App\Http\Controllers\PosController::class, 'storeCashMovement'])->name('pos.sessions.cash-movements.store');
Route::get('/pos/cash-movements/{movement}/print', [\App\Http\Controllers\PosController::class, 'printCashMovement'])->name('pos.cash-movements.print');

Route::post('/pos/sessions/{session}/close', [\App\Http\Controllers\PosController::class, 'closeSession'])->name('pos.sessions.close');
Route::get('/pos/sessions/{session}/close', [\App\Http\Controllers\PosController::class, 'closeSessionRedirect'])->name('pos.sessions.close.redirect');



// V5.51.5I - Auditoría cambio lista de precios PDV.
// V5.52.0C3 deshabilitado: ruta vieja rota sin namespace completo.
// Reemplazo vigente: pos.sessions.price-list-changes.store
// Route::post('/pos/audit-price-list-change', [PosController::class, 'auditPriceListChange'])
//     ->middleware(['web', 'auth'])
//     ->name('pos.audit-price-list-change');

Route::middleware(['auth'])->get(
    '/billing/invoices/{invoice}/download/{type}',
    \App\Http\Controllers\BillingInvoiceDownloadController::class
)->name('billing.invoices.download');


/*
|--------------------------------------------------------------------------
| BEXIA_V5524B9_TREASURY_MOVEMENT_PRINT_ROUTE
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->get(
    '/treasury/movements/{movement}/print',
    \App\Http\Controllers\TreasuryMovementPrintController::class
)->name('treasury.movements.print');


use App\Http\Controllers\Inventory\TrackingPrintPdfController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/{tenant}/stock-serial-numbers/{record}/pdf', [TrackingPrintPdfController::class, 'serial'])
        ->name('bexia.inventory.stock-serial-numbers.pdf');

    Route::get('/admin/{tenant}/stock-lots/{record}/pdf', [TrackingPrintPdfController::class, 'lot'])
        ->name('bexia.inventory.stock-lots.pdf');
});

// BEXIA_V5543B1_PDV_SERIALS_ENDPOINT
Route::middleware(['web', 'auth'])
    ->get('/pos/sessions/{session}/serials', [\App\Http\Controllers\PosController::class, 'serialsForProduct'])
    ->name('pos.sessions.serials');

Route::middleware(['web', 'auth'])
    ->get('/pos/sessions/{session}/lots', [\App\Http\Controllers\PosController::class, 'lotsForProduct'])
    ->name('pos.sessions.lots');
