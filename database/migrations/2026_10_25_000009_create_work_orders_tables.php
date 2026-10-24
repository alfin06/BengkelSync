<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Work orders ("SPK", SPEC section 4): a vehicle checked in for repair, with the
        // checklist, the mechanics, and the services and parts that end up on the invoice.
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // draft|queued|in_progress|waiting_parts|completed|invoiced|closed|cancelled
            $table->string('status', 16)->default('queued');
            $table->text('complaint');
            $table->text('diagnosis')->nullable();
            $table->unsignedInteger('odometer_in')->nullable();
            $table->dateTime('estimated_completion')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'customer_id']);
        });

        // The front-desk checklist of what to check or do; no price.
        Schema::create('work_order_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);

            $table->index(['tenant_id', 'work_order_id']);
        });

        Schema::create('work_order_mechanic', function (Blueprint $table) {
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mechanic_id')->constrained()->restrictOnDelete();

            $table->primary(['work_order_id', 'mechanic_id']);
            $table->index('mechanic_id');
        });

        // Prices are typed or prefilled when a line is added, and kept: later catalog or
        // part price changes do not change an open job.
        Schema::create('work_order_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->foreignId('mechanic_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);

            $table->index(['tenant_id', 'work_order_id']);
            $table->index(['tenant_id', 'service_item_id']);
        });

        // Parts leave the branch stock when added (StockService, work_order_use) and go back
        // when removed or when the work order is cancelled.
        Schema::create('work_order_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 2);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('unit_cost'); // average cost when added, for profit reports
            $table->unsignedBigInteger('line_total');
            $table->unsignedSmallInteger('sort')->default(0);

            $table->index(['tenant_id', 'work_order_id']);
            $table->index(['tenant_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_parts');
        Schema::dropIfExists('work_order_services');
        Schema::dropIfExists('work_order_mechanic');
        Schema::dropIfExists('work_order_tasks');
        Schema::dropIfExists('work_orders');
    }
};
