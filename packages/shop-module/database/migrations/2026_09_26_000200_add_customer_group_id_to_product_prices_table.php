<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR 10C — customer-group price overrides.
     *
     * Adds the group dimension to the ledger. A row with `customer_group_id`
     * null stays the public/default-currency price; a row with a group id is an
     * override that wins over the public row for members of that group.
     *
     * The unique key gains a `group_scope` generated column for the same reason
     * `price_scope` exists: nullable columns are distinct in both SQLite and
     * MySQL, so NULL would otherwise let duplicate public rows in.
     *
     * The table is rebuilt rather than altered. A generated column
     * cannot be added by `ALTER TABLE` on SQLite, and every app upgrading
     * from 0.1.57 already has the table — so the fresh install path is the only
     * one a from-scratch migration check exercises. The copy-then-swap below is
     * identical on SQLite and MySQL, which keeps one code path instead of a
     * per-driver branch. The unique index is added after the old table is
     * dropped because index names are schema-global in SQLite.
     *
     * The scope columns must be VIRTUAL rather than STORED: MySQL refuses a
     * foreign key on the base column of a STORED generated column (error 138,
     * reported as 1215 "Cannot add foreign key constraint"). `product_variation_id`
     * and `customer_group_id` are both base columns and both need a real FK for
     * cascade deletes, so STORED is unusable here. Verified on MySQL 8.0: the
     * index still collapses NULL to 0, so a group override can coexist with the
     * public row while a duplicate group row is still rejected.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // A previous attempt on a host that rejected the generated columns can
        // leave the staging table behind. Drop it so a retry starts clean.
        Schema::dropIfExists('product_prices_group_scope');

        Schema::create('product_prices_group_scope', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained()->cascadeOnDelete();
            // Cascades, never nulls: a group row demoted to
            // `customer_group_id = null` would collide with the public row on
            // `group_scope`, which would make the group impossible to delete.
            // Deleting a group discards its overrides, which is the intent.
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->cascadeOnDelete();
            $table->unsignedBigInteger('price_scope')->virtualAs('COALESCE(product_variation_id, 0)');
            $table->unsignedBigInteger('group_scope')->virtualAs('COALESCE(customer_group_id, 0)');
            $table->string('currency', 3)->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO product_prices_group_scope
                (id, product_id, product_variation_id, currency, price, compare_price, sale_price, min_quantity, created_at, updated_at)
             SELECT id, product_id, product_variation_id, currency, price, compare_price, sale_price, min_quantity, created_at, updated_at
             FROM product_prices'
        );

        Schema::drop('product_prices');
        Schema::rename('product_prices_group_scope', 'product_prices');

        Schema::table('product_prices', function (Blueprint $table) {
            $table->unique(
                ['product_id', 'price_scope', 'group_scope', 'currency', 'min_quantity'],
                'product_prices_lookup_unique'
            );
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('product_prices_public_scope');

        Schema::create('product_prices_public_scope', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_scope')->virtualAs('COALESCE(product_variation_id, 0)');
            $table->string('currency', 3)->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->timestamps();
        });

        // Group overrides cannot exist without the group dimension, so they go
        // with it; the public rows they were overriding survive.
        DB::statement(
            'INSERT INTO product_prices_public_scope
                (id, product_id, product_variation_id, currency, price, compare_price, sale_price, min_quantity, created_at, updated_at)
             SELECT id, product_id, product_variation_id, currency, price, compare_price, sale_price, min_quantity, created_at, updated_at
             FROM product_prices
             WHERE customer_group_id IS NULL'
        );

        Schema::drop('product_prices');
        Schema::rename('product_prices_public_scope', 'product_prices');

        Schema::table('product_prices', function (Blueprint $table) {
            $table->unique(
                ['product_id', 'price_scope', 'currency', 'min_quantity'],
                'product_prices_lookup_unique'
            );
        });

        Schema::enableForeignKeyConstraints();
    }
};
