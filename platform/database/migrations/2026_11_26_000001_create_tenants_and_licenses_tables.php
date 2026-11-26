<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 120)->unique();
            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            $table->char('default_currency', 3)->default('SAR');
            $table->string('default_timezone', 64)->default('Asia/Riyadh');
            $table->string('default_locale', 5)->default('ar');
            $table->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 120);
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('licenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('license_key_id', 100)->unique();
            $table->string('edition', 60);
            $table->json('payload_json');
            $table->text('signature');
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active')->index();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenants');
    }
};
