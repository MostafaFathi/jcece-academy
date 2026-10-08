<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->decimal('promotional_price', 12, 2)->nullable()->after('compare_price');
        });
        Schema::table('packages', function (Blueprint $table): void {
            $table->decimal('promotional_price', 12, 2)->nullable()->after('compare_price');
            $table->timestamp('discount_starts_at')->nullable()->after('promotional_price');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
        });
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->boolean('is_active')->default(false);
            $table->string('discount_type', 16);
            $table->decimal('discount_value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->decimal('minimum_order_amount', 12, 2)->nullable();
            $table->string('applies_to', 16)->default('all');
            $table->json('product_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('carts', function (Blueprint $table): void {
            $table->string('coupon_code', 64)->nullable();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code_snapshot', 64)->nullable();
            $table->string('coupon_type_snapshot', 16)->nullable();
            $table->decimal('coupon_value_snapshot', 12, 2)->nullable();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('promotional_discount_amount', 12, 2)->default(0);
            $table->decimal('coupon_discount_amount', 12, 2)->default(0);
        });
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['coupon_id', 'status']);
            $table->index(['coupon_id', 'user_id', 'status'], 'coupon_user_status_idx');
        });
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('reason', 64);
            $table->text('internal_note')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('access_effect', 16)->default('none');
            $table->json('order_item_ids')->nullable();
            $table->string('external_reference')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('coupon_redemptions');
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['promotional_discount_amount', 'coupon_discount_amount']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code_snapshot', 'coupon_type_snapshot', 'coupon_value_snapshot']);
        });
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropColumn('coupon_code');
        });
        Schema::dropIfExists('coupons');
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['promotional_price', 'discount_starts_at', 'discount_ends_at']);
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('promotional_price');
        });
    }
};
