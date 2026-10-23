<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPettyCashToExpensesTable extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('payment_source')->default('Main Drawer')->after('amount');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('petty_cash_balance', 15, 2)->default(0)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('payment_source');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('petty_cash_balance');
        });
    }
}