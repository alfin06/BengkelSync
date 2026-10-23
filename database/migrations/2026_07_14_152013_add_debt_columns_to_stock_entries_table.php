<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDebtColumnsToStockEntriesTable extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            // Place the new debt columns cleanly after your existing total_cost
            $table->decimal('amount_paid', 15, 2)->default(0)->after('total_cost');
            $table->enum('status', ['paid', 'partial', 'unpaid'])->default('paid')->after('amount_paid');
            $table->date('due_date')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'status', 'due_date']);
        });
    }
}