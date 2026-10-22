<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Checkout (Step 16): what a shopper has told us on the way from their cart
 * to an order, and the quote they were last shown, so placing the order can
 * prove nothing changed. One open checkout per cart. The exchange rate shown
 * is kept with the quote for its short lifetime. Nothing is held for an open
 * checkout: stock and promotion uses are held only once the order is placed.
 *
 * What a payment method holds for an order at placement (a module's gift
 * card balance, or core's own methods, which hold nothing of their own) is
 * tracked here too: held at placement, committed once the order is paid,
 * released if it expires or is cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkouts', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->string('contact_email', 254)->nullable()->comment('In lower case');
            $table->string('contact_name', 200)->nullable();
            $table->string('contact_phone', 32)->nullable()->comment('E.164');
            $table->jsonb('shipping_address')->nullable();
            $table->jsonb('billing_address')->nullable()->comment('Null while the billing address is the shipping address');
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->string('payment_method', 96)->nullable()->comment('The key of core\'s method chosen to pay what is left, such as "gateway:paystack" or "bank_transfer"');
            $table->jsonb('module_payment_methods')->default('[]')->comment('Keys of modules\' methods applied first, such as a gift card, in order');
            $table->string('buyer_tax_id', 32)->nullable();
            $table->char('buyer_tax_id_country', 2)->nullable();
            $table->boolean('marketing_email_consent')->default(false)->comment('Only when the shopper explicitly said yes');
            $table->boolean('marketing_sms_consent')->default(false)->comment('Only when the shopper explicitly said yes');
            $table->text('customer_note')->nullable();
            $table->string('locale', 12);
            $table->unsignedInteger('quote_version')->default(0);
            $table->char('quote_hash', 64)->nullable()->comment('SHA-256 of everything the last quote charged');
            $table->timestampTz('quote_expires_at')->nullable();
            $table->unsignedInteger('cart_version')->nullable()->comment('The cart version the last quote was made from');
            $table->jsonb('exchange_rate')->nullable()->comment('The rate the last quote was priced at, kept for its lifetime');
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestampTz('placed_at')->nullable();
            $table->timestampTz('last_activity_at')->index();
            $table->timestampsTz();
        });

        DB::statement("create unique index checkouts_one_open_per_cart on checkouts (cart_id) where status = 'open'");
        DB::statement(<<<'SQL'
            alter table checkouts
                add constraint checkouts_status_check check (status in ('open', 'placed')),
                add constraint checkouts_placed_with_order check ((status = 'placed') = (order_id is not null and placed_at is not null)),
                add constraint checkouts_buyer_tax_id_with_country check ((buyer_tax_id is null) = (buyer_tax_id_country is null)),
                add constraint checkouts_email_lower_case check (contact_email is null or contact_email = lower(contact_email)),
                add constraint checkouts_module_payment_methods_array check (jsonb_typeof(module_payment_methods) = 'array'),
                add constraint checkouts_quote_complete check ((quote_hash is null) = (quote_expires_at is null) and (quote_hash is null) = (exchange_rate is null))
            SQL);

        Schema::create('checkout_payment_holds', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('method', 96)->comment('The payment method\'s key');
            $table->string('reference', 128)->nullable()->comment('What the method calls its hold, if anything');
            $table->char('currency', 3);
            $table->bigInteger('amount_amount')->comment('What the hold covers of the order, in minor units; zero for a method that holds nothing of its own');
            $table->string('status', 16)->default('held');
            $table->timestampTz('committed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
            $table->unique(['order_id', 'method']);
        });

        DB::statement(<<<'SQL'
            alter table checkout_payment_holds
                add constraint checkout_payment_holds_status_check check (status in ('held', 'committed', 'released')),
                add constraint checkout_payment_holds_amount_not_negative check (amount_amount >= 0),
                add constraint checkout_payment_holds_committed_when_committed check ((status = 'committed') = (committed_at is not null)),
                add constraint checkout_payment_holds_released_when_released check ((status = 'released') = (released_at is not null))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_payment_holds');
        Schema::dropIfExists('checkouts');
    }
};
