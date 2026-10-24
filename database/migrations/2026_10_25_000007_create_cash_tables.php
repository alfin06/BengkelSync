<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cash and petty cash (SPEC section 4). All per branch.
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->index(['tenant_id', 'branch_id', 'is_active', 'sort']);
        });

        // Append-only ledger. The balance of an account is the balance_after of its latest
        // movement; there is no editable balance column anywhere.
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount'); // signed Rupiah: + in, − out
            $table->bigInteger('balance_after');
            $table->nullableMorphs('reference');
            $table->uuid('transfer_group')->nullable(); // both sides of a transfer
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'branch_id', 'cash_account_id', 'id']);
            $table->index(['tenant_id', 'cash_account_id', 'created_at']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->decimal('surcharge_percent', 5, 2)->default(0);
            $table->boolean('needs_reference')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->index(['tenant_id', 'branch_id', 'is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_accounts');
    }
};
