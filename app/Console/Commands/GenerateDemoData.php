<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Posts\Models\Post;
use App\Modules\Posts\Models\PostCategory;
use App\Modules\Posts\Models\PostTag;
use App\Modules\Properties\Models\City;
use App\Modules\Properties\Models\Property;
use App\Modules\Properties\Models\PropertyType;
use App\Modules\Shop\Models\Coupon;
use App\Modules\Shop\Models\CouponUsage;
use App\Modules\Shop\Models\Order;
use App\Modules\Shop\Models\OrderAction;
use App\Modules\Shop\Models\OrderItem;
use App\Modules\Shop\Models\OrderNote;
use App\Modules\Shop\Models\OrderRefund;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductReview;
use App\Modules\Shop\Models\ShippingMethod;
use App\Modules\Shop\Models\StockAdjustment;
use App\Modules\Shop\Models\TaxRate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateDemoData extends Command
{
    protected $signature = 'demo:generate {--fresh : Clear existing demo data first}';

    protected $description = 'Generate comprehensive demo data simulating real e-commerce activity';

    private ?User $adminUser = null;

    private array $customerUsers = [];

    private array $products = [];

    private array $shippingMethods = [];

    private array $taxRates = [];

    private array $coupons = [];

    private array $orderStatuses = ['pending', 'confirmed', 'processing', 'completed', 'cancelled', 'refunded', 'on-hold', 'failed'];

    private array $paymentMethods = ['stripe', 'bank_transfer', 'cash_on_delivery'];

    private array $addresses = [
        [
            'street' => 'Rue de la Liberté',
            'city' => 'Tunis',
            'state' => 'Tunis',
            'zip' => '1001',
            'country' => 'TN',
        ],
        [
            'street' => 'Avenue Habib Bourguiba',
            'city' => 'Sfax',
            'state' => 'Sfax',
            'zip' => '3000',
            'country' => 'TN',
        ],
        [
            'street' => 'Boulevard du 14 Janvier',
            'city' => 'Sousse',
            'state' => 'Sousse',
            'zip' => '4000',
            'country' => 'TN',
        ],
        [
            'street' => 'Rue de la Kasbah',
            'city' => 'Tunis',
            'state' => 'Tunis',
            'zip' => '1008',
            'country' => 'TN',
        ],
        [
            'street' => 'Avenue de Paris',
            'city' => 'Bizerte',
            'state' => 'Bizerte',
            'zip' => '7000',
            'country' => 'TN',
        ],
        [
            'street' => 'Rue Farhat Hached',
            'city' => 'Nabeul',
            'state' => 'Nabeul',
            'zip' => '8000',
            'country' => 'TN',
        ],
        [
            'street' => 'Rue Mongi Slim',
            'city' => 'Gabès',
            'state' => 'Gabès',
            'zip' => '6000',
            'country' => 'TN',
        ],
    ];

    public function handle(): int
    {
        $this->info('=== Cost Breaker 360 Demo Data Generator ===');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->warn('Clearing existing demo data (orders, reviews, coupons, adjustments, notes, refunds)...');
            $this->clearDemoData();
            $this->info('Done.');
            $this->newLine();
        }

        $this->adminUser = User::where('email', 'admin@example.com')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->first();

        if (! $this->adminUser) {
            $this->error('No admin user found. Run `php artisan db:seed` first.');

            return 1;
        }

        $this->line("Admin: {$this->adminUser->name} <{$this->adminUser->email}>");
        $this->newLine();

        $this->createCustomers();
        $this->createShippingMethods();
        $this->createTaxRates();
        $this->createCoupons();
        $this->createOrders();
        $this->createProductReviews();
        $this->createStockHistory();
        $this->maybeCreatePosts();
        $this->maybeCreatePropertiesAndBookings();

        $this->newLine();
        $this->info('=== Demo data generation complete! ===');
        $this->table(
            ['Entity', 'Count'],
            array_map(fn ($name, $count) => [$name, (string) $count], array_keys($this->getCounts()), $this->getCounts())
        );

        return 0;
    }

    private function createCustomers(): void
    {
        $this->info('Creating customers...');

        $customers = [
            ['name' => 'Amine Ben Ali', 'email' => 'amine.benali@example.com'],
            ['name' => 'Sarra Mansour', 'email' => 'sarra.mansour@example.com'],
            ['name' => 'Mehdi Khelifi', 'email' => 'mehdi.khelifi@example.com'],
            ['name' => 'Nadia Trabelsi', 'email' => 'nadia.trabelsi@example.com'],
            ['name' => 'Omar Haddad', 'email' => 'omar.haddad@example.com'],
            ['name' => 'Lina Bouazizi', 'email' => 'lina.bouazizi@example.com'],
            ['name' => 'Youssef Mhiri', 'email' => 'youssef.mhiri@example.com'],
            ['name' => 'Ines Chtourou', 'email' => 'ines.chtourou@example.com'],
            ['name' => 'Khaled Jelliti', 'email' => 'khaled.jelliti@example.com'],
            ['name' => 'Rim Gharbi', 'email' => 'rim.gharbi@example.com'],
            ['name' => 'Walid Saidi', 'email' => 'walid.saidi@example.com'],
            ['name' => 'Mariem Frikha', 'email' => 'mariem.frikha@example.com'],
        ];

        $bar = $this->output->createProgressBar(count($customers));
        $bar->start();

        foreach ($customers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => Str::slug($data['name']),
                    'password' => bcrypt('password'),
                    'email_verified_at' => now()->subDays(rand(10, 90)),
                    'billing_address' => $this->randomAddress(),
                    'shipping_address' => $this->randomAddress(),
                ]
            );
            $this->customerUsers[] = $user;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function createShippingMethods(): void
    {
        if (ShippingMethod::count() > 0) {
            $this->line('[SKIP] Shipping methods already exist');
            $this->shippingMethods = ShippingMethod::all()->all();

            return;
        }

        $this->info('Creating shipping methods...');

        $methods = [
            ['name' => 'Standard Shipping', 'description' => 'Delivery within 5-7 business days', 'price' => 5.99, 'free_above' => 50, 'estimated_days_min' => 5, 'estimated_days_max' => 7, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Express Shipping', 'description' => 'Delivery within 2-3 business days', 'price' => 12.99, 'free_above' => 100, 'estimated_days_min' => 2, 'estimated_days_max' => 3, 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Next Day Delivery', 'description' => 'Order before 2 PM for next business day delivery', 'price' => 24.99, 'free_above' => null, 'estimated_days_min' => 1, 'estimated_days_max' => 1, 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Free Pickup', 'description' => 'Pick up from our store at your convenience', 'price' => 0, 'free_above' => null, 'estimated_days_min' => 1, 'estimated_days_max' => 1, 'is_active' => true, 'sort_order' => 4],
        ];

        foreach ($methods as $data) {
            $this->shippingMethods[] = ShippingMethod::create($data);
        }

        $this->line(count($methods).' shipping methods created.');
    }

    private function createTaxRates(): void
    {
        if (TaxRate::count() > 0) {
            $this->line('[SKIP] Tax rates already exist');
            $this->taxRates = TaxRate::all()->all();

            return;
        }

        $this->info('Creating tax rates...');

        $rates = [
            ['name' => 'TVA Standard (TN)', 'rate' => 19, 'type' => 'percentage', 'country' => 'TN', 'is_active' => true, 'is_default' => true],
            ['name' => 'TVA Réduit (TN)', 'rate' => 7, 'type' => 'percentage', 'country' => 'TN', 'is_active' => true, 'is_default' => false],
            ['name' => 'TVA Super Réduit (TN)', 'rate' => 13, 'type' => 'percentage', 'country' => 'TN', 'is_active' => true, 'is_default' => false],
            ['name' => 'VAT Standard (FR)', 'rate' => 20, 'type' => 'percentage', 'country' => 'FR', 'is_active' => true, 'is_default' => false],
            ['name' => 'VAT Reduced (DE)', 'rate' => 7, 'type' => 'percentage', 'country' => 'DE', 'is_active' => true, 'is_default' => false],
        ];

        foreach ($rates as $data) {
            $this->taxRates[] = TaxRate::create($data);
        }

        $this->line(count($rates).' tax rates created.');
    }

    private function createCoupons(): void
    {
        $this->info('Creating coupons...');

        $couponData = [
            [
                'code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10,
                'min_order_amount' => 30, 'max_discount' => 25,
                'usage_limit' => 100, 'per_user_limit' => 1, 'is_active' => true,
                'starts_at' => now()->subDays(30), 'expires_at' => now()->addDays(60),
                'created_by' => $this->adminUser->id,
            ],
            [
                'code' => 'FLAT5', 'type' => 'fixed', 'value' => 5,
                'min_order_amount' => 20, 'max_discount' => 5,
                'usage_limit' => 50, 'per_user_limit' => 1, 'is_active' => true,
                'starts_at' => now()->subDays(15), 'expires_at' => now()->addDays(30),
                'created_by' => $this->adminUser->id,
            ],
            [
                'code' => 'FREESHIP', 'type' => 'free_shipping', 'value' => 0,
                'min_order_amount' => 40, 'max_discount' => 0,
                'usage_limit' => 30, 'per_user_limit' => 2, 'is_active' => true,
                'starts_at' => now()->subDays(7), 'expires_at' => now()->addDays(90),
                'created_by' => $this->adminUser->id,
            ],
            [
                'code' => 'VIP25', 'type' => 'percentage', 'value' => 25,
                'min_order_amount' => 100, 'max_discount' => 50,
                'usage_limit' => 10, 'per_user_limit' => 1, 'is_active' => true,
                'starts_at' => now()->subDays(1), 'expires_at' => now()->addDays(20),
                'created_by' => $this->adminUser->id,
            ],
            [
                'code' => 'SUMMER20', 'type' => 'percentage', 'value' => 20,
                'min_order_amount' => 50, 'max_discount' => 40,
                'usage_limit' => 200, 'per_user_limit' => 3, 'is_active' => true,
                'is_automatic' => true, 'auto_discount_label' => 'Summer Sale 20% off',
                'starts_at' => now()->subDays(3), 'expires_at' => now()->addDays(25),
                'created_by' => $this->adminUser->id,
            ],
            [
                'code' => 'EXPIRED10', 'type' => 'percentage', 'value' => 10,
                'min_order_amount' => 20, 'max_discount' => 15,
                'usage_limit' => 10, 'per_user_limit' => 1, 'is_active' => true,
                'starts_at' => now()->subDays(60), 'expires_at' => now()->subDays(1),
                'created_by' => $this->adminUser->id,
            ],
        ];

        $bar = $this->output->createProgressBar(count($couponData));
        $bar->start();

        foreach ($couponData as $data) {
            $this->coupons[] = Coupon::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function createOrders(): void
    {
        $this->info('Creating orders...');

        $this->products = Product::published()->get()->all();

        if (empty($this->products)) {
            $this->warn('[SKIP] No products found. Run `php artisan db:seed` first.');

            return;
        }

        if (empty($this->customerUsers)) {
            $this->warn('[SKIP] No customers created.');

            return;
        }

        $soloCustomer = $this->customerUsers[0];

        // Realistic order scenarios
        $scenarios = $this->buildOrderScenarios();

        $bar = $this->output->createProgressBar(count($scenarios));
        $bar->start();

        foreach ($scenarios as $i => $scenario) {
            $this->createSingleOrder($scenario, $i);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function buildOrderScenarios(): array
    {
        // Pick products for variety
        $prod = fn ($offset) => $this->products[array_keys($this->products)[$offset % count($this->products)]] ?? $this->products[0];

        $customers = $this->customerUsers;
        $methods = $this->shippingMethods;
        $method = fn ($offset) => $methods[$offset % count($methods)];
        $today = now();

        $scenarios = [];

        // Scenario 1: Recent completed order with notes — the Solo user
        $scenarios[] = [
            'customer' => $customers[0],
            'status' => 'completed',
            'payment_method' => 'stripe',
            'items' => [
                ['product' => $prod(0), 'qty' => 1],
                ['product' => $prod(1), 'qty' => 2],
            ],
            'shipping_method' => $method(1),
            'created_at' => $today->copy()->subDays(2),
            'paid_at' => $today->copy()->subDays(2)->addHours(1),
            'shipped_at' => $today->copy()->subDays(1),
            'coupon' => $this->coupons[0] ?? null,
            'notes' => [
                ['content' => 'Please leave the package with the concierge.', 'is_public' => true, 'days_after' => 0],
                ['content' => 'Customer called to confirm delivery address.', 'is_public' => false, 'days_after' => 1],
            ],
        ];

        // Scenario 2: Pending order waiting payment
        $scenarios[] = [
            'customer' => $customers[1],
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'items' => [
                ['product' => $prod(2), 'qty' => 1],
            ],
            'shipping_method' => $method(0),
            'created_at' => $today->copy()->subHours(3),
            'coupon' => null,
            'notes' => [],
        ];

        // Scenario 3: Processing order
        $scenarios[] = [
            'customer' => $customers[2],
            'status' => 'processing',
            'payment_method' => 'stripe',
            'items' => [
                ['product' => $prod(3), 'qty' => 1],
                ['product' => $prod(4), 'qty' => 1],
                ['product' => $prod(5), 'qty' => 3],
            ],
            'shipping_method' => $method(2),
            'created_at' => $today->copy()->subDays(1),
            'paid_at' => $today->copy()->subDays(1)->addMinutes(30),
            'coupon' => $this->coupons[0] ?? null,
            'notes' => [
                ['content' => 'Can I change the color to Midnight Black?', 'is_public' => true, 'days_after' => 0],
            ],
        ];

        // Scenario 4: Confirmed order
        $scenarios[] = [
            'customer' => $customers[3],
            'status' => 'confirmed',
            'payment_method' => 'cash_on_delivery',
            'items' => [
                ['product' => $prod(6), 'qty' => 1],
            ],
            'shipping_method' => $method(0),
            'created_at' => $today->copy()->subHours(12),
            'coupon' => $this->coupons[1] ?? null,
            'notes' => [],
        ];

        // Scenario 5: Cancelled order
        $scenarios[] = [
            'customer' => $customers[4],
            'status' => 'cancelled',
            'payment_method' => 'stripe',
            'items' => [
                ['product' => $prod(0), 'qty' => 1],
            ],
            'shipping_method' => $method(0),
            'created_at' => $today->copy()->subDays(5),
            'paid_at' => $today->copy()->subDays(5)->addMinutes(10),
            'coupon' => null,
            'notes' => [
                ['content' => 'I need to cancel this order, I ordered the wrong item.', 'is_public' => true, 'days_after' => 0],
                ['content' => 'Order cancelled and refunded via Stripe.', 'is_public' => false, 'days_after' => 0],
            ],
        ];

        // Scenario 6: Refunded order
        $scenarios[] = [
            'customer' => $customers[5],
            'status' => 'refunded',
            'payment_method' => 'stripe',
            'items' => [
                ['product' => $prod(1), 'qty' => 2],
            ],
            'shipping_method' => $method(1),
            'created_at' => $today->copy()->subDays(10),
            'paid_at' => $today->copy()->subDays(10)->addMinutes(5),
            'shipped_at' => $today->copy()->subDays(9),
            'completed_at' => $today->copy()->subDays(8),
            'refunded_at' => $today->copy()->subDays(3),
            'refund_items' => [
                ['qty' => 1, 'amount' => 49.99],
            ],
            'refund_reason' => 'Product arrived damaged. Customer requested full refund.',
            'coupon' => $this->coupons[2] ?? null,
            'notes' => [
                ['content' => 'The screen has a crack on arrival. Sent photos.', 'is_public' => true, 'days_after' => 0],
                ['content' => 'RMA created, return label sent to customer.', 'is_public' => false, 'days_after' => 1],
            ],
        ];

        // Scenario 7: On-hold order
        $scenarios[] = [
            'customer' => $customers[6],
            'status' => 'on-hold',
            'payment_method' => 'bank_transfer',
            'items' => [
                ['product' => $prod(2), 'qty' => 1],
                ['product' => $prod(0), 'qty' => 1],
            ],
            'shipping_method' => $method(0),
            'created_at' => $today->copy()->subDays(3),
            'coupon' => null,
            'notes' => [
                ['content' => 'Payment pending verification from bank. Will update once confirmed.', 'is_public' => false, 'days_after' => 0],
            ],
        ];

        // Scenario 8: Failed payment order
        $scenarios[] = [
            'customer' => $customers[7],
            'status' => 'failed',
            'payment_method' => 'stripe',
            'items' => [
                ['product' => $prod(3), 'qty' => 1],
            ],
            'shipping_method' => $method(1),
            'created_at' => $today->copy()->subHours(6),
            'coupon' => null,
            'notes' => [
                ['content' => 'Payment declined by bank. Customer notified to try another card.', 'is_public' => false, 'days_after' => 0],
            ],
        ];

        // Scenario 9: Large completed order with no coupon, guest-style
        $scenarios[] = [
            'customer' => $customers[8],
            'status' => 'completed',
            'payment_method' => 'stripe',
            'items' => [
                ['product' => $prod(4), 'qty' => 2],
                ['product' => $prod(5), 'qty' => 1],
                ['product' => $prod(6), 'qty' => 4],
            ],
            'shipping_method' => $method(2),
            'created_at' => $today->copy()->subDays(7),
            'paid_at' => $today->copy()->subDays(7)->addMinutes(15),
            'shipped_at' => $today->copy()->subDays(6),
            'coupon' => $this->coupons[3] ?? null,
            'notes' => [],
        ];

        // Scenario 10: Pending order with coupon
        $scenarios[] = [
            'customer' => $customers[9],
            'status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'items' => [
                ['product' => $prod(0), 'qty' => 1],
            ],
            'shipping_method' => $method(3),
            'created_at' => $today->copy()->subHours(1),
            'coupon' => $this->coupons[1] ?? null,
            'notes' => [],
        ];

        // More completed orders for dashboard stats
        for ($i = 10; $i < 18; $i++) {
            $daysAgo = rand(1, 14);
            $qty = rand(1, 3);
            $scenarios[] = [
                'customer' => $customers[$i % count($customers)],
                'status' => 'completed',
                'payment_method' => $this->paymentMethods[array_rand($this->paymentMethods)],
                'items' => [
                    ['product' => $prod($i), 'qty' => $qty],
                ],
                'shipping_method' => $method($i),
                'created_at' => $today->copy()->subDays($daysAgo),
                'paid_at' => $today->copy()->subDays($daysAgo)->addMinutes(rand(5, 120)),
                'shipped_at' => $today->copy()->subDays($daysAgo - 1),
                'coupon' => rand(0, 3) > 0 ? ($this->coupons[array_rand($this->coupons)] ?? null) : null,
                'notes' => [],
            ];
        }

        return $scenarios;
    }

    private function createSingleOrder(array $scenario, int $index): void
    {
        $customer = $scenario['customer'];
        $createdAt = $scenario['created_at'];
        $paidAt = $scenario['paid_at'] ?? null;
        $shippedAt = $scenario['shipped_at'] ?? null;
        $completedAt = $scenario['completed_at'] ?? null;
        $refundedAt = $scenario['refunded_at'] ?? null;

        $billing = $customer->billing_address ?? $this->randomAddress();
        $shipping = $customer->shipping_address ?? $this->randomAddress();

        $subtotal = 0;
        $orderItemsData = [];

        foreach ($scenario['items'] as $itemData) {
            $product = $itemData['product'];
            $qty = $itemData['qty'];
            $price = $product->active_price ?? $product->price ?? 29.99;
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;

            $orderItemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'unit_price' => $price,
                'quantity' => $qty,
                'subtotal' => $lineTotal,
                'total' => $lineTotal,
                'image_url' => $product->thumbnail_url,
            ];
        }

        // Shipping
        $shippingMethod = $scenario['shipping_method'];
        $shippingTotal = (float) $shippingMethod->price;
        $freeAbove = $shippingMethod->free_above;
        if ($freeAbove !== null && $subtotal >= (float) $freeAbove) {
            $shippingTotal = 0;
        }

        // Tax (19% of subtotal)
        $taxRate = 0.19;
        $taxTotal = round($subtotal * $taxRate, 2);

        // Discount
        $discountTotal = 0;
        $appliedCoupons = null;
        $couponId = null;

        if ($coupon = $scenario['coupon'] ?? null) {
            if ($coupon->type === 'free_shipping') {
                $shippingTotal = 0;
            } else {
                $discountTotal = $coupon->calculateDiscount($subtotal);
            }
            $couponId = $coupon->id;
            $appliedCoupons = [
                ['coupon_id' => $coupon->id, 'code' => $coupon->code, 'type' => $coupon->type],
            ];
        }

        $total = max(0, $subtotal - $discountTotal + $shippingTotal + $taxTotal);

        $now = now();

        $order = Order::create([
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone ?? '+216 50 000 000',
            'status' => $scenario['status'],
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'currency' => 'EUR',
            'payment_method' => $scenario['payment_method'],
            'payment_status' => in_array($scenario['status'], ['completed', 'processing', 'confirmed', 'refunded']) ? 'paid' : 'pending',
            'payment_intent_id' => $scenario['payment_method'] === 'stripe' ? 'pi_'.Str::random(24) : null,
            'paid_at' => $paidAt,
            'shipped_at' => $shippedAt,
            'refunded_at' => $refundedAt,
            'billing_address' => $billing,
            'shipping_address' => $shipping,
            'applied_coupons' => $appliedCoupons,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        // Force created_at to scenario date
        $order->timestamps = false;
        $order->save();
        $order->timestamps = true;

        // Order items
        foreach ($orderItemsData as $item) {
            OrderItem::create(array_merge($item, ['order_id' => $order->id]));
        }

        // Order actions (status transition history)
        $statusFlow = $this->getStatusFlow($scenario['status'], $createdAt, $paidAt, $shippedAt, $completedAt, $refundedAt);
        foreach ($statusFlow as $action) {
            OrderAction::create([
                'order_id' => $order->id,
                'action_type' => 'status_change',
                'old_value' => $action['from'],
                'new_value' => $action['to'],
                'user_id' => $this->adminUser->id,
                'created_at' => $action['at'],
                'updated_at' => $action['at'],
            ]);

            // Payment action for stripe orders
            if ($action['to'] === 'processing' && $scenario['payment_method'] === 'stripe' && $paidAt) {
                OrderAction::create([
                    'order_id' => $order->id,
                    'action_type' => 'payment',
                    'old_value' => 'pending',
                    'new_value' => 'paid',
                    'user_id' => $this->adminUser->id,
                    'notes' => 'Payment received via Stripe',
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);
            }
        }

        // Order notes
        foreach ($scenario['notes'] ?? [] as $note) {
            $noteCreatedAt = $createdAt->copy()->addDays($note['days_after']);
            OrderNote::create([
                'order_id' => $order->id,
                'user_id' => $note['is_public'] ? ($customer->id) : $this->adminUser->id,
                'content' => $note['content'],
                'is_public' => $note['is_public'],
                'created_at' => $noteCreatedAt,
                'updated_at' => $noteCreatedAt,
            ]);
        }

        // Refund
        if ($scenario['status'] === 'refunded' && isset($scenario['refund_items'])) {
            $totalRefund = array_sum(array_column($scenario['refund_items'], 'amount'));
            $refund = OrderRefund::create([
                'order_id' => $order->id,
                'items' => $scenario['refund_items'],
                'shipping_refund' => $shippingTotal,
                'total' => $totalRefund + $shippingTotal,
                'reason' => $scenario['refund_reason'] ?? 'Customer request',
                'created_by' => $this->adminUser->id,
                'created_at' => $refundedAt ?? $createdAt->copy()->addDays(3),
                'updated_at' => $refundedAt ?? $createdAt->copy()->addDays(3),
            ]);

            $order->timestamps = false;
            $order->update([
                'total_refunded' => $refund->total,
                'refund_amount' => $refund->total,
                'refund_notes' => $scenario['refund_reason'] ?? null,
            ]);
            $order->timestamps = true;

            OrderAction::create([
                'order_id' => $order->id,
                'action_type' => 'refund',
                'old_value' => null,
                'new_value' => number_format($refund->total, 2),
                'user_id' => $this->adminUser->id,
                'notes' => $scenario['refund_reason'] ?? null,
                'created_at' => $refundedAt ?? $createdAt->copy()->addDays(3),
                'updated_at' => $refundedAt ?? $createdAt->copy()->addDays(3),
            ]);
        }

        // Coupon usage
        if ($couponId && $discountTotal > 0) {
            CouponUsage::create([
                'coupon_id' => $couponId,
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'discount_amount' => $discountTotal,
            ]);

            Coupon::find($couponId)?->increment('used_count');
        }
    }

    private function getStatusFlow(string $finalStatus, Carbon $createdAt, ?Carbon $paidAt, ?Carbon $shippedAt, ?Carbon $completedAt, ?Carbon $refundedAt): array
    {
        $flow = [['from' => null, 'to' => 'pending', 'at' => $createdAt]];

        return match ($finalStatus) {
            'pending' => $flow,
            'confirmed' => [
                ...$flow,
                ['from' => 'pending', 'to' => 'confirmed', 'at' => $createdAt->copy()->addHours(rand(1, 4))],
            ],
            'processing' => [
                ...$flow,
                ['from' => 'pending', 'to' => 'confirmed', 'at' => $createdAt->copy()->addHours(1)],
                ['from' => 'confirmed', 'to' => 'processing', 'at' => $paidAt ?? $createdAt->copy()->addHours(2)],
            ],
            'completed' => [
                ...$flow,
                ['from' => 'pending', 'to' => 'confirmed', 'at' => $createdAt->copy()->addMinutes(30)],
                ['from' => 'confirmed', 'to' => 'processing', 'at' => $paidAt ?? $createdAt->copy()->addHours(1)],
                ['from' => 'processing', 'to' => 'completed', 'at' => $shippedAt?->copy()->addDay() ?? $completedAt ?? $createdAt->copy()->addDays(2)],
            ],
            'cancelled' => [
                ...$flow,
                ['from' => 'pending', 'to' => 'cancelled', 'at' => $createdAt->copy()->addHours(rand(2, 12))],
            ],
            'refunded' => [
                ['from' => null, 'to' => 'pending', 'at' => $createdAt],
                ['from' => 'pending', 'to' => 'confirmed', 'at' => $createdAt->copy()->addMinutes(15)],
                ['from' => 'confirmed', 'to' => 'processing', 'at' => $paidAt ?? $createdAt->copy()->addHours(1)],
                ['from' => 'processing', 'to' => 'completed', 'at' => $shippedAt?->copy()->addDay() ?? $completedAt ?? $createdAt->copy()->addDays(2)],
                ['from' => 'completed', 'to' => 'refunded', 'at' => $refundedAt ?? $createdAt->copy()->addDays(7)],
            ],
            'on-hold' => [
                ...$flow,
                ['from' => 'pending', 'to' => 'on-hold', 'at' => $createdAt->copy()->addHours(2)],
            ],
            'failed' => [
                ...$flow,
                ['from' => 'pending', 'to' => 'failed', 'at' => $createdAt->copy()->addMinutes(30)],
            ],
            default => $flow,
        };
    }

    private function createProductReviews(): void
    {
        $this->info('Creating product reviews...');

        if (empty($this->customerUsers) || empty($this->products)) {
            return;
        }

        $reviewData = [
            ['product' => 0, 'user' => 0, 'rating' => 5, 'title' => 'Excellent product!', 'content' => 'Exceeded my expectations. Fast shipping and great quality. Highly recommended!'],
            ['product' => 0, 'user' => 1, 'rating' => 4, 'title' => 'Very good, minor issues', 'content' => 'Great product overall. The packaging could be better but the item itself is perfect.'],
            ['product' => 1, 'user' => 2, 'rating' => 5, 'title' => 'Best purchase this year', 'content' => 'I have been using it for a week and I am extremely satisfied. Worth every penny.'],
            ['product' => 1, 'user' => 3, 'rating' => 3, 'title' => 'Good but not great', 'content' => 'It works as described but I expected better build quality for the price.'],
            ['product' => 2, 'user' => 4, 'rating' => 5, 'title' => 'Amazing quality', 'content' => 'The attention to detail is incredible. I will definitely buy from here again.'],
            ['product' => 2, 'user' => 0, 'rating' => 4, 'title' => 'Solid product', 'content' => 'Does exactly what it should. No complaints.'],
            ['product' => 3, 'user' => 1, 'rating' => 2, 'title' => 'Not what I expected', 'content' => 'The item arrived with a scratch. Customer service was helpful though.'],
            ['product' => 3, 'user' => 2, 'rating' => 5, 'title' => 'Perfect gift', 'content' => 'Bought as a gift and they loved it! Fast delivery too.'],
            ['product' => 4, 'user' => 3, 'rating' => 4, 'title' => 'Great value for money', 'content' => 'Comparable to premium brands at half the price. Very happy.'],
            ['product' => 4, 'user' => 5, 'rating' => 1, 'title' => 'Defective unit', 'content' => 'Stopped working after 2 days. Requesting a replacement.', 'approved' => false],
            ['product' => 5, 'user' => 6, 'rating' => 4, 'title' => 'Good purchase', 'content' => 'Arrived on time and works perfectly. Would recommend.'],
        ];

        $bar = $this->output->createProgressBar(count($reviewData));
        $bar->start();

        foreach ($reviewData as $data) {
            $product = Product::skip($data['product'])->first() ?? $this->products[array_rand($this->products)];
            if (! $product) {
                continue;
            }

            $userIdx = $data['user'] % count($this->customerUsers);
            $user = $this->customerUsers[$userIdx] ?? $this->customerUsers[0];

            ProductReview::firstOrCreate(
                ['product_id' => $product->id, 'user_id' => $user->id],
                [
                    'rating' => $data['rating'],
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'is_approved' => $data['approved'] ?? true,
                    'approved_at' => ($data['approved'] ?? true) ? now()->subDays(rand(1, 10)) : null,
                    'approved_by' => ($data['approved'] ?? true) ? $this->adminUser->id : null,
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function createStockHistory(): void
    {
        $this->info('Creating stock adjustment history...');

        if (empty($this->products)) {
            return;
        }

        $bar = $this->output->createProgressBar(min(count($this->products), 5));
        $bar->start();

        foreach (array_slice($this->products, 0, 5) as $product) {
            if (! $product->track_stock) {
                $bar->advance();

                continue;
            }

            // Initial stock
            StockAdjustment::create([
                'product_id' => $product->id,
                'type' => 'correction',
                'quantity' => $product->stock_quantity ?? 50,
                'stock_before' => 0,
                'stock_after' => $product->stock_quantity ?? 50,
                'reason' => 'Initial stock setup',
                'user_id' => $this->adminUser->id,
                'created_at' => now()->subDays(60),
                'updated_at' => now()->subDays(60),
            ]);

            $currentStock = $product->stock_quantity ?? 50;

            // Simulate a sale a few days ago
            $saleQty = rand(2, 5);
            $newStock = max(0, $currentStock - $saleQty);
            StockAdjustment::create([
                'product_id' => $product->id,
                'type' => 'sale',
                'quantity' => -$saleQty,
                'stock_before' => $currentStock,
                'stock_after' => $newStock,
                'reason' => 'Order fulfillment',
                'reference_type' => 'order',
                'reference_id' => 1,
                'user_id' => $this->adminUser->id,
                'created_at' => now()->subDays(rand(3, 10)),
                'updated_at' => now()->subDays(rand(3, 10)),
            ]);

            $currentStock = $newStock;

            // Simulate a restock
            $restockQty = rand(10, 30);
            StockAdjustment::create([
                'product_id' => $product->id,
                'type' => 'manual',
                'quantity' => $restockQty,
                'stock_before' => $currentStock,
                'stock_after' => $currentStock + $restockQty,
                'reason' => 'Supplier restock',
                'user_id' => $this->adminUser->id,
                'created_at' => now()->subDays(rand(1, 5)),
                'updated_at' => now()->subDays(rand(1, 5)),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function maybeCreatePosts(): void
    {
        if (! class_exists('App\Modules\Posts\Models\Post')) {
            $this->line('[SKIP] Posts module not available');

            return;
        }

        $this->info('Creating blog posts...');

        $categoriesData = [
            ['name' => 'Technology', 'description' => 'Latest tech news and reviews'],
            ['name' => 'Tutorials', 'description' => 'Step-by-step guides and how-tos'],
            ['name' => 'Industry News', 'description' => 'Updates from the industry'],
            ['name' => 'Tips & Tricks', 'description' => 'Helpful tips for our customers'],
        ];

        $tagsData = ['Featured', 'Popular', 'New', 'Trending', 'How-to'];

        $postsData = [
            [
                'title' => 'Getting Started with Our Platform',
                'content' => 'Welcome to our platform! This guide will walk you through the basics of setting up your account, browsing products, and making your first purchase.\n\n## Step 1: Create Your Account\nSign up with your email address and choose a secure password.\n\n## Step 2: Browse Products\nExplore our catalog with advanced filtering options.\n\n## Step 3: Place Your Order\nAdd items to your cart and proceed to checkout.',
                'excerpt' => 'New to our platform? Here is everything you need to know.',
                'category' => 'Tutorials',
                'tags' => ['Featured', 'New'],
            ],
            [
                'title' => '5 Tips for a Better Shopping Experience',
                'content' => 'Shopping online should be enjoyable. Here are our top tips:\n\n1. **Use filters** - Narrow down products by category, price, and features\n2. **Check reviews** - Learn from other customers experiences\n3. **Coupon stacking** - Combine multiple coupons for maximum savings\n4. **Wishlist** - Save items for later and get notified of price drops\n5. **Express checkout** - Save your address and payment details for faster checkout',
                'excerpt' => 'Make the most of your shopping with these simple tips.',
                'category' => 'Tips & Tricks',
                'tags' => ['Popular', 'Trending'],
            ],
            [
                'title' => 'Understanding Our Shipping Policy',
                'content' => 'We offer multiple shipping options to suit your needs:\n\n- **Standard Shipping**: 5-7 business days - Free on orders over 50 EUR\n- **Express Shipping**: 2-3 business days - Free on orders over 100 EUR\n- **Next Day Delivery**: Order before 2 PM\n\nAll orders are tracked and insured.',
                'excerpt' => 'Everything you need to know about shipping times and costs.',
                'category' => 'Industry News',
                'tags' => ['How-to'],
            ],
            [
                'title' => 'New Product Line: Smart Home Devices',
                'content' => 'We are excited to announce our new line of smart home devices!\n\nOur new collection includes:\n- Smart speakers\n- Home automation hubs\n- Security cameras\n- Smart lighting\n\nAll devices are compatible with major smart home ecosystems.',
                'excerpt' => 'Discover our latest collection of smart home products.',
                'category' => 'Technology',
                'tags' => ['Featured', 'New', 'Trending'],
            ],
            [
                'title' => 'How to Choose the Right Product for Your Needs',
                'content' => 'Choosing the right product can be overwhelming. Follow this framework:\n\n1. **Identify your needs** - What problem are you solving?\n2. **Set a budget** - How much are you willing to spend?\n3. **Research** - Read reviews and compare specifications\n4. **Check compatibility** - Does it work with your existing setup?\n5. **Consider future use** - Will it still meet your needs in 6 months?',
                'excerpt' => 'A practical guide to making informed purchase decisions.',
                'category' => 'Tips & Tricks',
                'tags' => ['Popular', 'How-to'],
            ],
        ];

        // Create categories
        $categories = [];
        foreach ($categoriesData as $catData) {
            $categories[] = PostCategory::firstOrCreate(
                ['slug' => Str::slug($catData['name'])],
                ['name' => $catData['name'], 'description' => $catData['description']]
            );
        }

        // Create tags
        $tags = [];
        foreach ($tagsData as $tagName) {
            $tags[] = PostTag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
        }

        // Create posts
        $bar = $this->output->createProgressBar(count($postsData));
        $bar->start();

        foreach ($postsData as $i => $postData) {
            $catIdx = array_search($postData['category'], array_column($categoriesData, 'name'));
            $categoryId = $categories[$catIdx]->id ?? null;

            $post = Post::firstOrCreate(
                ['slug' => Str::slug($postData['title'])],
                [
                    'user_id' => $this->adminUser->id,
                    'title' => $postData['title'],
                    'content' => $postData['content'],
                    'excerpt' => $postData['excerpt'],
                    'status' => 'published',
                    'published_at' => now()->subDays(count($postsData) - $i),
                    'category_id' => $categoryId,
                ]
            );

            // Attach tags
            $tagIds = array_map(fn ($tagName) => ($tags[array_search($tagName, $tagsData)] ?? null)?->id, $postData['tags']);
            $tagIds = array_filter($tagIds);
            if ($tagIds) {
                $post->tags()->syncWithoutDetaching($tagIds);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function maybeCreatePropertiesAndBookings(): void
    {
        if (! class_exists('App\Modules\Properties\Models\Property')) {
            $this->line('[SKIP] Properties module not available');

            return;
        }

        if (! class_exists('App\Modules\Booking\Models\Booking')) {
            $this->line('[SKIP] Bookings module not available');

            return;
        }

        $this->info('Creating properties and bookings...');

        // Seed property terms if needed
        if (City::count() === 0) {
            $this->line('Seeding property terms (types, cities, features, statuses)...');
            $this->callSilent('db:seed', ['--class' => 'PropertyTermSeeder']);
        }

        $propertyTypes = PropertyType::pluck('id', 'name')->all();
        $cities = City::inRandomOrder()->take(5)->get();

        if ($cities->isEmpty()) {
            $this->warn('[SKIP] No cities found in properties module.');

            return;
        }

        $propertyData = [
            [
                'name' => 'Modern Apartment in Tunis Centre',
                'description' => 'Beautiful modern apartment in the heart of Tunis. Walking distance to all major attractions.',
                'price' => 120,
                'surface' => 85,
                'city' => $cities[0],
                'type' => 'Apartment',
            ],
            [
                'name' => 'Villa with Pool in Sidi Bou Said',
                'description' => 'Stunning villa with panoramic sea views and private swimming pool.',
                'price' => 350,
                'surface' => 250,
                'city' => $cities[1] ?? $cities[0],
                'type' => 'Villa',
            ],
            [
                'name' => 'Cozy Studio Near Beach',
                'description' => 'Perfect for couples. Fully equipped studio steps from the beach.',
                'price' => 65,
                'surface' => 35,
                'city' => $cities[2] ?? $cities[0],
                'type' => 'Studio',
            ],
        ];

        $bar = $this->output->createProgressBar(count($propertyData));
        $bar->start();

        foreach ($propertyData as $data) {
            $property = Property::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'user_id' => $this->adminUser->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'surface' => $data['surface'],
                    'surface_unit' => 'sqm',
                    'city' => $data['city']->name,
                    'state' => $data['city']->governorate ?? 'Tunis',
                    'country' => 'TN',
                    'address' => $data['city']->name.', Tunisia',
                    'status' => 'published',
                    'published_at' => now()->subDays(rand(10, 30)),
                ]
            );

            // Attach type
            if ($typeId = $propertyTypes[$data['type']] ?? null) {
                $property->types()->syncWithoutDetaching([$typeId]);
            }

            // Create a booking if in the past
            if (rand(0, 1)) {
                $start = now()->subDays(rand(5, 20));
                $end = $start->copy()->addDays(rand(2, 5));
                try {
                    Booking::create([
                        'property_id' => $property->id,
                        'user_id' => $this->customerUsers[array_rand($this->customerUsers)]->id,
                        'start_date' => $start,
                        'end_date' => $end,
                        'status' => 'completed',
                        'total_amount' => $data['price'] * $start->diffInDays($end),
                        'guest_count' => rand(1, 4),
                        'guest_name' => $this->customerUsers[array_rand($this->customerUsers)]->name,
                        'guest_email' => $this->customerUsers[array_rand($this->customerUsers)]->email,
                        'nightly_rate' => $data['price'],
                    ]);
                } catch (\Exception $e) {
                    // Skip if booking fails (duplicate, validation, etc)
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function randomAddress(): array
    {
        $addr = $this->addresses[array_rand($this->addresses)];

        return [
            'street' => $addr['street'].' '.rand(1, 200),
            'city' => $addr['city'],
            'state' => $addr['state'],
            'zip' => $addr['zip'],
            'country' => $addr['country'],
        ];
    }

    private function clearDemoData(): void
    {
        $demoEmails = [
            'amine.benali@example.com', 'sarra.mansour@example.com',
            'mehdi.khelifi@example.com', 'nadia.trabelsi@example.com',
            'omar.haddad@example.com', 'lina.bouazizi@example.com',
            'youssef.mhiri@example.com', 'ines.chtourou@example.com',
            'khaled.jelliti@example.com', 'rim.gharbi@example.com',
            'walid.saidi@example.com', 'mariem.frikha@example.com',
        ];

        $demoUsers = User::whereIn('email', $demoEmails)->pluck('id');

        // Delete orders by demo users (cascades to items, actions, notes, refunds)
        Order::whereIn('user_id', $demoUsers)->delete();

        // Delete reviews by demo users
        ProductReview::whereIn('user_id', $demoUsers)->delete();

        // Delete demo user created coupons
        foreach (['WELCOME10', 'FLAT5', 'FREESHIP', 'VIP25', 'SUMMER20', 'EXPIRED10'] as $code) {
            Coupon::where('code', $code)->delete();
        }

        // Delete stock adjustments by admin
        StockAdjustment::where('user_id', $this->adminUser?->id)->delete();

        // Delete demo posts
        Post::where('user_id', $this->adminUser?->id)->delete();
        PostCategory::whereIn('slug', ['technology', 'tutorials', 'industry-news', 'tips-tricks'])->delete();
        PostTag::whereIn('slug', ['featured', 'popular', 'new', 'trending', 'how-to'])->delete();

        // Delete demo shipping methods and tax rates
        ShippingMethod::whereIn('name', ['Standard Shipping', 'Express Shipping', 'Next Day Delivery', 'Free Pickup'])->delete();
        TaxRate::whereIn('name', ['TVA Standard (TN)', 'TVA Réduit (TN)', 'TVA Super Réduit (TN)', 'VAT Standard (FR)', 'VAT Reduced (DE)'])->delete();

        // Delete demo properties
        Property::where('user_id', $this->adminUser?->id)->delete();

        // Delete demo users
        User::whereIn('id', $demoUsers)->delete();
    }

    private function getCounts(): array
    {
        return [
            'Customers' => count($this->customerUsers),
            'Coupons' => count($this->coupons),
            'Shipping Methods' => count($this->shippingMethods),
            'Tax Rates' => count($this->taxRates),
            'Orders' => Order::count(),
            'Blog Posts' => Post::count(),
        ];
    }
}
