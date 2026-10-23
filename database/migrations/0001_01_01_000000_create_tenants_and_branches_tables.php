<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active');
            $table->string('default_locale', 5)->default('id');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('logo_path')->nullable();
            $table->string('npwp', 32)->nullable();
            $table->boolean('tax_enabled')->default(false);
            $table->string('tax_name', 20)->default('PPN');
            $table->decimal('tax_rate', 5, 2)->default(11);
            $table->boolean('tax_inclusive')->default(false);
            $table->text('invoice_footer')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }
};
