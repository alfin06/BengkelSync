<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invoices (SPEC section 4): from a finished work order, or a walk-in sale without
        // one. Tax settings are copied from the branch when the invoice is made (section 5).
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            // Null: "Pelanggan umum", a walk-in sale without a customer record.
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->boolean('tax_enabled')->default(false);
            $table->string('tax_name', 20)->default('PPN');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('status', 10)->default('unpaid'); // unpaid|partial|paid|void
            $table->text('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'status', 'issue_date']);
            $table->index(['tenant_id', 'work_order_id']);
            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // service|part|other
            $table->foreignId('part_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('qty', 12, 2);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('unit_cost')->nullable(); // parts: average cost, for profit reports
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('line_total');
            // Kept from the work order, for workload (and possibly commission) reports.
            $table->foreignId('mechanic_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'part_id']);
            $table->index(['tenant_id', 'service_item_id']);
        });

        // Filled by invoice editing (InvoiceService::revise(), SPEC section 6).
        Schema::create('invoice_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_no');
            $table->json('snapshot');
            $table->text('reason');
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['invoice_id', 'revision_no']);
            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_revisions');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
