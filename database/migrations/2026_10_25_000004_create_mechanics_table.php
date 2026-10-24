<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mechanics are records without a login (SPEC section 11), each working in one branch.
        Schema::create('mechanics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            // Encrypted with the app key (Laravel "encrypted" cast), so it is text, not char(16).
            $table->text('nik')->nullable();
            $table->string('ktp_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id', 'is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanics');
    }
};
