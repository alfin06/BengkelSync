<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suppliers and the parts catalog are tenant-wide; stock is per branch (SPEC section 4).
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('sales_name')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('npwp', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50)->nullable();
            $table->string('name');
            $table->string('brand', 80)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('unit', 20)->default('pcs');
            // Weighted average purchase cost and selling price, integer Rupiah per unit.
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->unsignedBigInteger('sell_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'is_active', 'name']);
        });

        Schema::create('part_branch_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 2)->default(0);
            // Low-stock threshold: staff are notified when qty drops to or below it.
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->string('location', 50)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'part_id']);
            $table->index(['tenant_id', 'branch_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('qty', 12, 2); // signed: + in, − out
            $table->decimal('qty_after', 12, 2);
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->nullableMorphs('reference');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'branch_id', 'part_id', 'created_at']);
        });

        // Last line of defense for "a part that is out of stock cannot be used".
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE part_branch_stock ADD CONSTRAINT part_branch_stock_qty_not_negative CHECK (qty >= 0)');
            DB::statement('ALTER TABLE part_branch_stock ADD CONSTRAINT part_branch_stock_min_not_negative CHECK (min_stock >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('part_branch_stock');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('suppliers');
    }
};
