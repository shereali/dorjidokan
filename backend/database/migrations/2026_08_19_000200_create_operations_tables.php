<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->ulid('public_id')->unique();
            $t->string('sku', 80);
            $t->string('name', 160);
            $t->string('unit', 20);
            $t->decimal('reorder_level', 12, 3)->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'sku']);
            $t->index(['tenant_id', 'name']);
        });
        Schema::create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $t->string('type', 24);
            $t->decimal('quantity', 12, 3);
            $t->unsignedBigInteger('unit_cost_minor')->default(0);
            $t->nullableMorphs('reference');
            $t->string('reason', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['tenant_id', 'inventory_item_id', 'occurred_at', 'id'], 'inv_mv_tenant_item_occ_id_idx');
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->ulid('public_id')->unique();
            $t->string('name', 160);
            $t->string('mobile_number', 20)->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'mobile_number']);
        });
        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->ulid('public_id')->unique();
            $t->string('purchase_number', 40);
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 24)->default('draft');
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'purchase_number']);
        });
        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 12, 3);
            $t->unsignedBigInteger('unit_cost_minor');
            $t->unsignedBigInteger('total_minor');
            $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->ulid('public_id')->unique();
            $t->string('sale_number', 40);
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sale_type', 24)->default('direct');
            $t->string('status', 24)->default('completed');
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->unsignedBigInteger('paid_minor')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'sale_number']);
            $t->index(['tenant_id', 'created_at', 'id']);
        });
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 12, 3);
            $t->unsignedBigInteger('unit_price_minor');
            $t->unsignedBigInteger('total_minor');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->nullableMorphs('payable');
            $t->unsignedBigInteger('amount_minor');
            $t->char('currency', 3)->default('BDT');
            $t->string('method', 24);
            $t->string('reference', 120)->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at');
            $t->timestamps();
            $t->index(['tenant_id', 'received_at', 'id']);
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->timestamps();
            $t->unique(['tenant_id', 'name']);
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->ulid('public_id')->unique();
            $t->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->text('note')->nullable();
            $t->date('expense_date');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'expense_date', 'id']);
        });
        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->date('work_date');
            $t->string('status', 20);
            $t->time('checked_in_at')->nullable();
            $t->time('checked_out_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });
        Schema::create('work_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('work_type', 80);
            $t->decimal('quantity', 10, 2)->default(1);
            $t->unsignedBigInteger('rate_minor');
            $t->unsignedBigInteger('amount_minor');
            $t->string('status', 20)->default('unpaid');
            $t->timestamp('completed_at');
            $t->timestamps();
            $t->index(['tenant_id', 'employee_id', 'status']);
        });
        Schema::create('rentals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->ulid('public_id')->unique();
            $t->string('rental_number', 40);
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('status', 24)->default('reserved');
            $t->date('starts_on');
            $t->date('due_on');
            $t->date('returned_on')->nullable();
            $t->unsignedBigInteger('rent_minor');
            $t->unsignedBigInteger('deposit_minor')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'rental_number']);
            $t->index(['tenant_id', 'status', 'due_on']);
        });
        Schema::create('rental_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 10, 2)->default(1);
            $t->string('condition_out', 255)->nullable();
            $t->string('condition_in', 255)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['rental_items', 'rentals', 'work_entries', 'attendance_records', 'expenses', 'expense_categories', 'payments', 'sale_items', 'sales', 'purchase_items', 'purchases', 'suppliers', 'inventory_movements', 'inventory_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
