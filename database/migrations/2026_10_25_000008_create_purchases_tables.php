<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchases and supplier debt (SPEC section 4). Receiving goods from a supplier is a
        // purchase; it can be paid at once, in parts, or later.
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_ref', 80)->nullable(); // the supplier's own invoice number
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('status', 10)->default('unpaid'); // unpaid|partial|paid|void
            $table->text('void_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Due-date bell notifications are sent once each.
            $table->timestamp('due_soon_notified_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'status', 'due_date']);
            $table->index(['tenant_id', 'supplier_id']);
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 2);
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('line_total');

            $table->index(['tenant_id', 'purchase_id']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->unsignedBigInteger('amount');
            $table->string('reference', 80)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            // Corrections cancel a payment (with a reversing cash movement); rows are never deleted.
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'purchase_id']);
        });

        // Highest amount Front Desk may pay a purchase with on the spot from the cash drawer
        // or petty cash. Null: no limit.
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_purchase_limit')->nullable()->after('invoice_footer');
        });
    }

    public function down(): void
    {
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('cash_purchase_limit'));
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
    }
};
