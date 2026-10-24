<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer payments (SPEC section 4). One checkout = one receipt ("kwitansi") with
        // one line per payment method used; an invoice can be paid over several checkouts.
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->unsignedBigInteger('amount'); // applied to the invoice, without surcharge
            $table->unsignedBigInteger('surcharge_total')->default(0);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'paid_on']);
            $table->index(['tenant_id', 'invoice_id']);
        });

        // Lines are never deleted: a wrong one is cancelled (with a reason) and its money
        // leaves the cash account again with a reversing movement.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained('payment_receipts')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete(); // copied from the method
            $table->unsignedBigInteger('amount');
            $table->decimal('surcharge_percent', 5, 2)->default(0); // copied from the method
            $table->unsignedBigInteger('surcharge_amount')->default(0);
            $table->unsignedBigInteger('cash_tendered')->nullable(); // cash only; change = tendered − amount − surcharge
            $table->string('reference', 80)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'invoice_id']);
            $table->index(['tenant_id', 'receipt_id']);
        });

        // Customer-debt bell reminders ("Piutang"), sent once each.
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('due_soon_notified_at')->nullable()->after('due_date');
            $table->timestamp('overdue_notified_at')->nullable()->after('due_soon_notified_at');
            $table->index(['tenant_id', 'branch_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'branch_id', 'status', 'due_date']);
            $table->dropColumn(['due_soon_notified_at', 'overdue_notified_at']);
        });
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_receipts');
    }
};
