<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The service catalog (SPEC section 4): tenant-wide, same price in every branch.
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->string('category', 50)->nullable();
            $table->unsignedBigInteger('default_price')->default(0);
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'category', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
