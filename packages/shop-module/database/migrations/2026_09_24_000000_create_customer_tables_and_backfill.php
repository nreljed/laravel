<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR 9.2 / 0.1.53 — customer entity + backfill.
     *
     * Creates the four customer tables and backfills registered customers
     * from existing orders (`orders.user_id` + `orders.customer_email`).
     * The orders' `user_id` columns stay intact; this migration only adds
     * the `customers` / `customer_user` mirror on top.
     */
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->boolean('allows_business_pricing')->default(false);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('email')->unique();
            $table->string('display_name')->nullable();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['customer_id', 'user_id']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('type'); // billing | shipping
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('postal_code')->nullable();
            $table->string('country_code', 2);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        $this->seedGroups();
        $this->backfillFromOrders();
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_user');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }

    private function seedGroups(): void
    {
        if (DB::table('customer_groups')->where('code', 'wholesale')->exists()) {
            return;
        }

        DB::table('customer_groups')->insert([
            'code' => 'wholesale',
            'label' => 'Wholesale',
            'allows_business_pricing' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function backfillFromOrders(): void
    {
        $orders = DB::table('orders')
            ->whereNotNull('user_id')
            ->whereNotNull('customer_email')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $customerId = $this->findOrCreateCustomer($order);

            $pivotExists = DB::table('customer_user')
                ->where('customer_id', $customerId)
                ->where('user_id', $order->user_id)
                ->exists();

            if (! $pivotExists) {
                DB::table('customer_user')->insert([
                    'customer_id' => $customerId,
                    'user_id' => $order->user_id,
                ]);
            }

            foreach (['billing_address' => 'billing', 'shipping_address' => 'shipping'] as $column => $type) {
                $this->createAddressFromOrder($customerId, $type, $order->{$column});
            }
        }
    }

    private function findOrCreateCustomer(object $order): int
    {
        $customer = DB::table('customers')->where('email', $order->customer_email)->first();

        if ($customer) {
            return (int) $customer->id;
        }

        $id = DB::table('customers')->insertGetId([
            'code' => 'pending', // set to cus-<id> right after insert; unique constraint needs a placeholder
            'email' => $order->customer_email,
            'display_name' => $order->customer_name ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customers')->where('id', $id)->update([
            'code' => 'cus-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
        ]);

        return (int) $id;
    }

    private function createAddressFromOrder(int $customerId, string $type, ?string $json): void
    {
        if (blank($json)) {
            return;
        }

        $address = json_decode($json, true);

        if (! is_array($address) || blank($address['address_line1'] ?? null)) {
            return;
        }

        $id = DB::table('customer_addresses')->insertGetId([
            'code' => 'pending', // set to addr-<id> right after insert
            'customer_id' => $customerId,
            'type' => $type,
            'line1' => $address['address_line1'],
            'line2' => $address['address_line2'] ?? null,
            'city' => $address['city'] ?? '',
            'postal_code' => $address['postcode'] ?? null,
            'country_code' => strtoupper((string) ($address['country'] ?? '')),
            'is_primary' => $type === 'billing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customer_addresses')->where('id', $id)->update([
            'code' => 'addr-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
        ]);
    }
};