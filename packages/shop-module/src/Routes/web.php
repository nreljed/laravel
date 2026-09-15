<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\AttributesController;
use Nreljed\ShopModule\Http\Controllers\BrandsController;
use Nreljed\ShopModule\Http\Controllers\CategoriesController;
use Nreljed\ShopModule\Http\Controllers\CheckoutController;
use Nreljed\ShopModule\Http\Controllers\CouponsController;
use Nreljed\ShopModule\Http\Controllers\CurrenciesController;
use Nreljed\ShopModule\Http\Controllers\CustomersController;
use Nreljed\ShopModule\Http\Controllers\OrdersController;
use Nreljed\ShopModule\Http\Controllers\ProductsController;
use Nreljed\ShopModule\Http\Controllers\ProductSeoController;
use Nreljed\ShopModule\Http\Controllers\ProductVariationsController;
use Nreljed\ShopModule\Http\Controllers\ReportsController;
use Nreljed\ShopModule\Http\Controllers\ReviewsController;
use Nreljed\ShopModule\Http\Controllers\ShippingMethodsController;
use Nreljed\ShopModule\Http\Controllers\ShippingZonesController;
use Nreljed\ShopModule\Http\Controllers\ShopDashboardController;
use Nreljed\ShopModule\Http\Controllers\TagsController;
use Nreljed\ShopModule\Http\Controllers\TaxRatesController;
use Nreljed\ShopModule\Http\Controllers\WebhooksController;

// Dashboard
Route::get('/dashboard', [ShopDashboardController::class, 'index'])->name('dashboard');

// Products
Route::get('/products', [ProductsController::class, 'index'])->name('products.index');
Route::get('/products/create', [ProductsController::class, 'create'])->name('products.create');
Route::post('/products', [ProductsController::class, 'store'])->name('products.store');
Route::get('/products/export', [ProductsController::class, 'export'])->name('products.export');
Route::post('/products/import', [ProductsController::class, 'import'])->name('products.import');
Route::post('/products/upload-image', [ProductsController::class, 'uploadImage'])->name('products.upload-image');
Route::delete('/products/delete-image', [ProductsController::class, 'deleteImage'])->name('products.delete-image');
Route::post('/products/bulk', [ProductsController::class, 'bulkAction'])->name('products.bulk');
Route::get('/products/{product}', [ProductsController::class, 'show'])->name('products.show');
Route::get('/products/{product}/edit', [ProductsController::class, 'edit'])->name('products.edit');
Route::put('/products/{product}', [ProductsController::class, 'update'])->name('products.update');
Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');
Route::patch('/products/{product}/status', [ProductsController::class, 'updateStatus'])->name('products.update-status');
Route::post('/products/{product}/adjust-stock', [ProductsController::class, 'adjustStock'])->name('products.adjust-stock');
Route::get('/products/{product}/stock-history', [ProductsController::class, 'stockHistory'])->name('products.stock-history');
Route::post('/products/seo/generate', [ProductSeoController::class, 'generate'])->name('products.seo.generate');

// Variations
Route::get('/products/{product}/variations', [ProductVariationsController::class, 'index'])->name('products.variations.index');
Route::post('/products/{product}/variations', [ProductVariationsController::class, 'store'])->name('products.variations.store');
Route::put('/products/{product}/variations/{variation}', [ProductVariationsController::class, 'update'])->name('products.variations.update');
Route::delete('/products/{product}/variations/{variation}', [ProductVariationsController::class, 'destroy'])->name('products.variations.destroy');
Route::post('/products/{product}/variations/generate', [ProductVariationsController::class, 'generate'])->name('products.variations.generate');
Route::post('/products/{product}/variations/bulk', [ProductVariationsController::class, 'storeBulk'])->name('products.variations.bulk');
Route::post('/products/{product}/variations/delete-bulk', [ProductVariationsController::class, 'destroyBulk'])->name('products.variations.delete-bulk');
Route::post('/products/{product}/variations/reorder', [ProductVariationsController::class, 'reorder'])->name('products.variations.reorder');

// Orders
Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
Route::get('/orders/create', [OrdersController::class, 'create'])->name('orders.create');
Route::post('/orders', [OrdersController::class, 'storeAdmin'])->name('orders.store');
Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');
Route::get('/orders/{order}/edit', [OrdersController::class, 'edit'])->name('orders.edit');
Route::put('/orders/{order}', [OrdersController::class, 'updateAdmin'])->name('orders.update');
Route::get('/orders/export', [OrdersController::class, 'export'])->name('orders.export');
Route::patch('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');
Route::get('/orders/{order}/invoice', [OrdersController::class, 'invoice'])->name('orders.invoice');
Route::post('/orders/{order}/mark-paid', [OrdersController::class, 'markAsPaid'])->name('orders.mark-paid');
Route::post('/orders/{order}/refund', [OrdersController::class, 'refund'])->name('orders.refund');

// Order notes
Route::post('/orders/{order}/notes', [OrdersController::class, 'storeNote'])->name('orders.notes.store');
Route::delete('/orders/{order}/notes/{note}', [OrdersController::class, 'destroyNote'])->name('orders.notes.destroy');

// Draft order conversion
Route::post('/orders/{order}/convert-from-draft', [OrdersController::class, 'convertFromDraft'])->name('orders.convert-from-draft');

// Order search (JSON)
Route::get('/orders/search/products', [OrdersController::class, 'searchProducts'])->name('orders.search-products');
Route::get('/orders/search/customers', [OrdersController::class, 'searchCustomers'])->name('orders.search-customers');

// Checkout
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/checkout/payment-intent', [CheckoutController::class, 'createPaymentIntent'])->name('checkout.payment-intent');

// Reports
Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

// Customers
Route::get('/customers', [CustomersController::class, 'index'])->name('customers.index');
Route::get('/customers/{user}', [CustomersController::class, 'show'])->name('customers.show');
Route::get('/customers/export', [CustomersController::class, 'export'])->name('customers.export');

// Categories
Route::get('/categories', [CategoriesController::class, 'index'])->name('categories.index');
Route::post('/categories', [CategoriesController::class, 'store'])->name('categories.store');
Route::put('/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');

// Tags
Route::get('/tags', [TagsController::class, 'index'])->name('tags.index');
Route::post('/tags', [TagsController::class, 'store'])->name('tags.store');
Route::put('/tags/{tag}', [TagsController::class, 'update'])->name('tags.update');
Route::delete('/tags/{tag}', [TagsController::class, 'destroy'])->name('tags.destroy');

// Brands
Route::get('/brands', [BrandsController::class, 'index'])->name('brands.index');
Route::post('/brands', [BrandsController::class, 'store'])->name('brands.store');
Route::put('/brands/{brand}', [BrandsController::class, 'update'])->name('brands.update');
Route::delete('/brands/{brand}', [BrandsController::class, 'destroy'])->name('brands.destroy');

// Attributes
Route::get('/attributes', [AttributesController::class, 'index'])->name('attributes.index');
Route::post('/attributes', [AttributesController::class, 'store'])->name('attributes.store');
Route::put('/attributes/{attribute}', [AttributesController::class, 'update'])->name('attributes.update');
Route::delete('/attributes/{attribute}', [AttributesController::class, 'destroy'])->name('attributes.destroy');
Route::post('/attributes/{attribute}/terms', [AttributesController::class, 'storeTerm'])->name('attributes.terms.store');
Route::put('/attributes/{attribute}/terms/{term}', [AttributesController::class, 'updateTerm'])->name('attributes.terms.update');
Route::delete('/attributes/{attribute}/terms/{term}', [AttributesController::class, 'destroyTerm'])->name('attributes.terms.destroy');
Route::get('/attributes/{attribute}/terms', [AttributesController::class, 'listTerms'])->name('attributes.terms');
Route::post('/attributes/{attribute}/terms/reorder', [AttributesController::class, 'reorderTerms'])->name('attributes.terms.reorder');
Route::post('/attributes/reorder', [AttributesController::class, 'reorder'])->name('attributes.reorder');

// Coupons
Route::get('/coupons', [CouponsController::class, 'index'])->name('coupons.index');
Route::get('/coupons/create', [CouponsController::class, 'create'])->name('coupons.create');
Route::post('/coupons', [CouponsController::class, 'store'])->name('coupons.store');
Route::get('/coupons/{coupon}/edit', [CouponsController::class, 'edit'])->name('coupons.edit');
Route::put('/coupons/{coupon}', [CouponsController::class, 'update'])->name('coupons.update');
Route::delete('/coupons/{coupon}', [CouponsController::class, 'destroy'])->name('coupons.destroy');
Route::post('/coupons/{coupon}/toggle-active', [CouponsController::class, 'toggleActive'])->name('coupons.toggle-active');

// Reviews
Route::get('/reviews', [ReviewsController::class, 'index'])->name('reviews.index');
Route::post('/products/{product}/reviews', [ReviewsController::class, 'store'])->name('reviews.store');
Route::post('/reviews/{review}/approve', [ReviewsController::class, 'approve'])->name('reviews.approve');
Route::post('/reviews/{review}/reject', [ReviewsController::class, 'reject'])->name('reviews.reject');
Route::delete('/reviews/{review}', [ReviewsController::class, 'destroy'])->name('reviews.destroy');

// Shipping Methods
Route::get('/shipping-methods', [ShippingMethodsController::class, 'index'])->name('shipping-methods.index');
Route::post('/shipping-methods', [ShippingMethodsController::class, 'store'])->name('shipping-methods.store');
Route::put('/shipping-methods/{shippingMethod}', [ShippingMethodsController::class, 'update'])->name('shipping-methods.update');
Route::delete('/shipping-methods/{shippingMethod}', [ShippingMethodsController::class, 'destroy'])->name('shipping-methods.destroy');
Route::post('/shipping-methods/{shippingMethod}/toggle-active', [ShippingMethodsController::class, 'toggleActive'])->name('shipping-methods.toggle-active');

// Shipping Zones
Route::get('/shipping-zones', [ShippingZonesController::class, 'index'])->name('shipping-zones.index');
Route::get('/shipping-zones/create', [ShippingZonesController::class, 'create'])->name('shipping-zones.create');
Route::post('/shipping-zones', [ShippingZonesController::class, 'store'])->name('shipping-zones.store');
Route::get('/shipping-zones/{shippingZone}/edit', [ShippingZonesController::class, 'edit'])->name('shipping-zones.edit');
Route::put('/shipping-zones/{shippingZone}', [ShippingZonesController::class, 'update'])->name('shipping-zones.update');
Route::delete('/shipping-zones/{shippingZone}', [ShippingZonesController::class, 'destroy'])->name('shipping-zones.destroy');

// Tax Rates
Route::get('/tax-rates', [TaxRatesController::class, 'index'])->name('tax-rates.index');
Route::post('/tax-rates', [TaxRatesController::class, 'store'])->name('tax-rates.store');
Route::put('/tax-rates/{taxRate}', [TaxRatesController::class, 'update'])->name('tax-rates.update');
Route::delete('/tax-rates/{taxRate}', [TaxRatesController::class, 'destroy'])->name('tax-rates.destroy');
Route::post('/tax-rates/{taxRate}/toggle-active', [TaxRatesController::class, 'toggleActive'])->name('tax-rates.toggle-active');

// Currencies
Route::get('/currencies', [CurrenciesController::class, 'index'])->name('currencies.index');
Route::post('/currencies', [CurrenciesController::class, 'store'])->name('currencies.store');
Route::put('/currencies/{currency}', [CurrenciesController::class, 'update'])->name('currencies.update');
Route::delete('/currencies/{currency}', [CurrenciesController::class, 'destroy'])->name('currencies.destroy');
Route::post('/currencies/{currency}/toggle-active', [CurrenciesController::class, 'toggleActive'])->name('currencies.toggle-active');

// Webhooks
Route::get('/webhooks', [WebhooksController::class, 'index'])->name('webhooks.index');
Route::post('/webhooks', [WebhooksController::class, 'store'])->name('webhooks.store');
Route::put('/webhooks/{webhook}', [WebhooksController::class, 'update'])->name('webhooks.update');
Route::delete('/webhooks/{webhook}', [WebhooksController::class, 'destroy'])->name('webhooks.destroy');
Route::post('/webhooks/{webhook}/toggle-active', [WebhooksController::class, 'toggleActive'])->name('webhooks.toggle-active');
Route::post('/webhooks/{webhook}/test', [WebhooksController::class, 'test'])->name('webhooks.test');
Route::get('/webhooks/{webhook}/calls', [WebhooksController::class, 'calls'])->name('webhooks.calls');
