<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('purchase_price')->default(0);
            $table->unsignedBigInteger('rental_price_per_day');
            $table->unsignedInteger('stock_total');
            $table->unsignedInteger('stock_damaged')->default(0);
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'is_active']);
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->text('address')->nullable();
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('rentals', function (Blueprint $table): void {
            $table->id();
            $table->string('trx_code')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('rent_date');
            $table->date('due_date');
            $table->timestamp('returned_at')->nullable();
            $table->unsignedInteger('duration_days');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('late_fee')->default(0);
            $table->string('guarantee_type');
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->unsignedBigInteger('deposit_returned')->default(0);
            $table->unsignedBigInteger('deposit_deducted')->default(0);
            $table->string('customer_photo_path')->nullable();
            $table->string('status')->default('rented');
            $table->text('notes')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'due_date']);
            $table->index('rent_date');
        });
        Schema::create('rental_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('item_name');
            $table->unsignedBigInteger('price_per_day');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('qty_returned_good')->default(0);
            $table->unsignedInteger('qty_damaged')->default(0);
            $table->unsignedInteger('qty_lost')->default(0);
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
            $table->index('item_id');
        });
        Schema::create('damage_records', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->foreignId('rental_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('charge_amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status')->default('unpaid');
            $table->string('resolution')->default('pending');
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->text('written_off_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('damage_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('damage_record_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method');
            $table->timestamp('paid_at');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->unsignedInteger('qty');
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('module');
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['module', 'created_at']);
        });
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('daily_sequences', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sequences');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('damage_payments');
        Schema::dropIfExists('damage_records');
        Schema::dropIfExists('rental_items');
        Schema::dropIfExists('rentals');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
