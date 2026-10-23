<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Normalized "B 1234 XYZ". Unique per tenant including soft-deleted vehicles,
            // so a deleted vehicle can be restored instead of duplicated.
            $table->string('plate_number', 20);
            $table->string('make', 50);
            $table->string('model', 80);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 30)->nullable();
            $table->string('vin', 30)->nullable();
            $table->string('engine_number', 30)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->unsignedInteger('last_odometer')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'plate_number']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'make', 'model']);
        });

        Schema::create('vehicle_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'ended_on']);
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_ownerships');
        Schema::dropIfExists('vehicles');
    }
};
