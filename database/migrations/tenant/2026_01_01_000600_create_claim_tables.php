<?php

declare(strict_types=1);

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('remote_claim_id');

            // Talebin kendi basligi pazaryerinde statusuz gelebilir; gosterilen
            // statu kalemlerden turetilir (ClaimData::status()). Kanonik deger
            // burada saklanir, HAM deger yaninda durur — bilinmeyen bir statu
            // asla varsayilana katlanmaz (TRENDYOL.md 5).
            $table->string('status')->default(CanonicalClaimStatus::Created->value);
            $table->string('external_status')->default('');
            $table->string('reason')->nullable();
            $table->timestamp('opened_at');

            // KVKK: iade payload'i ad-soyad, adres ve kargo bilgisi tasir —
            // orders.raw ile birebir ayni yukumluluk (BACKEND-PLAN 13).
            // Sifreli metin jsonb'ye sigmaz, `text` olmak zorunda.
            $table->text('raw')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'remote_claim_id']);
            $table->index('status');
            $table->index('opened_at', 'claims_opened_at_brin', 'brin');
        });

        Schema::create('claim_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();

            // NULLABLE ve bilerek, order_lines.variant_id ile ayni gerekce:
            // pazaryeri bizde karsiligi olmayan bir kaleme talep acabilir.
            // Talep ASLA reddedilmez, satir eksik baglantiyla saklanir.
            $table->foreignId('order_line_id')->nullable()->constrained('order_lines')->nullOnDelete();

            // Cekme islemi tekrarlanabilir olmak zorunda; upsert'in dogal
            // anahtari bu (ClaimItemData::$remoteId).
            $table->string('remote_item_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status');
            $table->string('external_status')->default('');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['claim_id', 'remote_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_items');
        Schema::dropIfExists('claims');
    }
};
