<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_stages_every_row_and_normalizes_retained_operations_idempotently(): void
    {
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        DB::purge('legacy');
        $schema = Schema::connection('legacy');
        $schema->create('customer', function (Blueprint $table) {
            $table->id();
            $table->string('cus_name');
            $table->string('cus_mobile');
            $table->string('cus_address')->nullable();
        });
        $schema->create('company', function (Blueprint $table) {
            $table->id();
            $table->string('com_name');
            $table->integer('is_stock')->default(0);
            $table->integer('order_sms')->default(0);
            $table->integer('item_receive_sms')->default(0);
            $table->integer('emp_payment_sms')->default(0);
        });
        $schema->create('set_text', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('com_id');
            $table->text('sms_text');
            $table->integer('is_active')->default(1);
        });
        $schema->create('product', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('item_code')->nullable();
            $table->string('uom')->nullable();
            $table->integer('ptype')->nullable();
            $table->integer('group_id')->nullable();
            $table->integer('is_active')->default(1);
        });
        $schema->create('measurement_head', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('mtype');
            $table->integer('ptype');
            $table->integer('group_id');
            $table->integer('steps');
        });
        $schema->create('dsms', function (Blueprint $table) {
            $table->id();
            $table->string('cus_name');
            $table->string('cus_mobile');
            $table->string('phy_ord_num');
            $table->decimal('amount');
            $table->dateTime('insert_date');
            $table->dateTime('phy_dv_date')->nullable();
        });
        $schema->create('order_record', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('order_number');
            $table->decimal('item_qty');
            $table->decimal('amount_cost')->default(0);
            $table->decimal('design_cost')->default(0);
            $table->string('group_name')->nullable();
        });
        $schema->create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_number');
            $table->integer('mtype');
            $table->unsignedBigInteger('measure_id');
            $table->string('measurement')->nullable();
        });
        $schema->create('accounting', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dsms_id');
            $table->decimal('amount');
            $table->string('payment_type')->nullable();
            $table->integer('is_active')->default(1);
            $table->dateTime('insert_date');
        });
        $schema->create('user', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('mobile')->nullable();
            $table->integer('emp_type')->nullable();
            $table->integer('is_active')->default(1);
        });
        $schema->create('direct_sale', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dstid')->nullable();
            $table->unsignedBigInteger('item_id');
            $table->decimal('item_qty');
            $table->decimal('price');
            $table->integer('in_out_status')->default(0);
            $table->dateTime('insert_date');
        });
        $schema->create('direct_sale_head', function (Blueprint $table) {
            $table->id();
            $table->string('cus_name')->nullable();
            $table->string('cus_mobile')->nullable();
            $table->string('cus_address')->nullable();
            $table->decimal('f_total')->default(0);
            $table->decimal('others_tk')->default(0);
            $table->decimal('discount_tk')->default(0);
            $table->decimal('total_receive')->default(0);
            $table->integer('in_out_status')->nullable();
            $table->integer('is_active')->default(1);
            $table->string('payment_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->dateTime('submission_date')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->dateTime('insert_date');
        });
        $schema->create('expense_type', function (Blueprint $table) {
            $table->id();
            $table->string('expense');
        });
        $schema->create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_type');
            $table->decimal('expense');
            $table->string('remarks')->nullable();
            $table->dateTime('insert_date');
        });
        $schema->create('daily_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emp_id');
            $table->date('attendence_date');
            $table->integer('pastatus');
        });
        $schema->create('wages_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emp_id');
            $table->unsignedBigInteger('ord_record_tblid')->nullable();
            $table->string('item_name')->nullable();
            $table->decimal('item_qty')->default(1);
            $table->decimal('taka');
            $table->integer('status')->default(0);
            $table->integer('is_active')->default(1);
            $table->string('remarks')->nullable();
            $table->dateTime('insert_date');
        });
        DB::connection('legacy')->table('customer')->insert(['id' => 1, 'cus_name' => 'Legacy Client', 'cus_mobile' => '01700000000']);
        DB::connection('legacy')->table('company')->insert(['id' => 1, 'com_name' => 'Imported Legacy Shop', 'is_stock' => 1, 'order_sms' => 1, 'item_receive_sms' => 1, 'emp_payment_sms' => 0]);
        DB::connection('legacy')->table('set_text')->insert(['id' => 1, 'com_id' => 1, 'sms_text' => 'Your dress is ready.', 'is_active' => 1]);
        DB::connection('legacy')->table('product')->insert(['id' => 1, 'product_name' => 'Panjabi', 'item_code' => 'P-1', 'uom' => 'piece', 'ptype' => 1, 'group_id' => 1]);
        DB::connection('legacy')->table('measurement_head')->insert(['id' => 1, 'title' => 'Chest', 'mtype' => 1, 'ptype' => 1, 'group_id' => 1, 'steps' => 1]);
        DB::connection('legacy')->table('dsms')->insert(['id' => 1, 'cus_name' => 'Legacy Client', 'cus_mobile' => '01700000000', 'phy_ord_num' => 'A100', 'amount' => 1200, 'insert_date' => now()]);
        DB::connection('legacy')->table('order_record')->insert(['id' => 1, 'item_id' => 1, 'order_number' => 'A100', 'item_qty' => 2, 'amount_cost' => 500, 'design_cost' => 100, 'group_name' => 'Pair']);
        DB::connection('legacy')->table('order_details')->insert(['id' => 1, 'order_number' => 1, 'mtype' => 1, 'measure_id' => 1, 'measurement' => '40']);
        DB::connection('legacy')->table('accounting')->insert(['id' => 1, 'dsms_id' => 1, 'amount' => 400, 'payment_type' => 'Cash', 'insert_date' => now()]);
        DB::connection('legacy')->table('user')->insert(['id' => 1, 'user_name' => 'Karigar One', 'mobile' => '01800000000', 'emp_type' => 2]);
        DB::connection('legacy')->table('direct_sale_head')->insert(['id' => 1, 'cus_name' => 'Counter buyer', 'cus_mobile' => '01900000001', 'f_total' => 200, 'total_receive' => 200, 'in_out_status' => 0, 'is_active' => 1, 'payment_status' => 'Paid', 'payment_type' => 'Cash', 'insert_date' => now()]);
        DB::connection('legacy')->table('direct_sale_head')->insert(['id' => 2, 'cus_name' => 'Fabric supplier', 'cus_mobile' => '01900000002', 'f_total' => 500, 'total_receive' => 500, 'in_out_status' => 1, 'is_active' => 1, 'payment_status' => 'Paid', 'payment_type' => 'Cash', 'insert_date' => now()]);
        DB::connection('legacy')->table('direct_sale_head')->insert(['id' => 3, 'cus_name' => 'Rental client', 'cus_mobile' => '01900000003', 'f_total' => 300, 'total_receive' => 100, 'in_out_status' => 0, 'is_active' => 2, 'payment_status' => 'Due', 'submission_date' => now(), 'payment_date' => now()->addDays(3), 'insert_date' => now()]);
        DB::connection('legacy')->table('direct_sale')->insert([
            ['id' => 1, 'dstid' => 1, 'item_id' => 1, 'item_qty' => 2, 'price' => 100, 'in_out_status' => 0, 'insert_date' => now()],
            ['id' => 2, 'dstid' => 2, 'item_id' => 1, 'item_qty' => 5, 'price' => 100, 'in_out_status' => 1, 'insert_date' => now()],
            ['id' => 3, 'dstid' => 3, 'item_id' => 1, 'item_qty' => 1, 'price' => 300, 'in_out_status' => 0, 'insert_date' => now()],
        ]);
        DB::connection('legacy')->table('expense_type')->insert(['id' => 1, 'expense' => 'Electricity']);
        DB::connection('legacy')->table('expenses')->insert(['id' => 1, 'expense_type' => 1, 'expense' => 500, 'remarks' => 'Legacy bill', 'insert_date' => now()]);
        DB::connection('legacy')->table('daily_attendance')->insert(['id' => 1, 'emp_id' => 1, 'attendence_date' => now()->toDateString(), 'pastatus' => 1]);
        DB::connection('legacy')->table('wages_details')->insert(['id' => 1, 'emp_id' => 1, 'ord_record_tblid' => 1, 'item_name' => 'Stitching', 'item_qty' => 2, 'taka' => 300, 'status' => 1, 'remarks' => 'Paid before cutover', 'insert_date' => now()]);
        Tenant::create(['name' => 'Imported Shop', 'slug' => 'imported', 'status' => 'active']);

        $this->artisan('legacy:import imported')->assertSuccessful();
        $this->artisan('legacy:import imported')->assertSuccessful();
        $this->artisan('legacy:validate imported')->assertSuccessful();

        $this->assertDatabaseCount('legacy_staging_records', 20);
        $this->assertDatabaseHas('tenants', ['slug' => 'imported', 'name' => 'Imported Legacy Shop']);
        $this->assertDatabaseHas('notification_templates', ['event' => 'order.ready', 'channel' => 'sms', 'body' => 'Your dress is ready.']);
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('garment_parts', 1);
        $this->assertDatabaseCount('order_measurements', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('journal_entries', 4);
        $this->assertSame(0, (int) DB::table('journal_lines')->sum(DB::raw('debit_minor - credit_minor')));
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('inventory_movements', 3);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_items', 1);
        $this->assertDatabaseCount('rentals', 1);
        $this->assertDatabaseCount('rental_items', 1);
        $this->assertDatabaseCount('work_entries', 1);
        $this->assertDatabaseCount('payout_batches', 1);
        $this->assertDatabaseCount('payout_items', 1);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('attendance_records', 1);
    }
}
