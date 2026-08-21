<?php

declare(strict_types=1);

use App\Enums\BillingPeriod;
use App\Enums\LicenseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lisans modeli — BACKEND-PLAN.md §3.1. Tum tablolar *central* semada yasar;
 * tenant kendi lisansina dokunamaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_period')->default(BillingPeriod::Monthly->value);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->jsonb('value');
            $table->timestamps();
            $table->unique(['plan_id', 'feature']);
        });

        Schema::create('licenses', function (Blueprint $table): void {
            $table->id();
            // Bir tenant = bir lisans. Unique kisiti bunu veritabani seviyesinde zorlar.
            $table->string('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status')->default(LicenseStatus::Active->value);
            $table->unsignedSmallInteger('seats')->default(1);
            $table->jsonb('limits')->default('{}');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_at']);
        });

        Schema::create('license_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->jsonb('payload')->default('{}');
            $table->timestamp('created_at');
            $table->index(['license_id', 'created_at']);
        });

        Schema::create('usage_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('metric');
            // 'YYYY-MM' (donemsel metrikler) veya 'total' (kumulatif).
            $table->string('period');
            $table->bigInteger('value')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('license_events');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
