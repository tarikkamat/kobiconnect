<?php

declare(strict_types=1);

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();

            // remote_id, Trendyol'da shipmentPackageId'dir: satici bir PAKETI
            // hazirlar, faturalar ve gonderir. Kismi iptal/bolme orderNumber'i
            // korur ama YENI bir shipmentPackageId uretir (TRENDYOL.md 9.1),
            // bu yuzden dedupe remote_id ile, mutabakat remote_order_number ile.
            $table->string('remote_id');
            $table->string('remote_order_number');

            $table->string('status')->default(CanonicalOrderStatus::Created->value);
            // Kanonik enum + HAM pazaryeri degeri birlikte saklanir; bilinmeyen
            // bir deger asla varsayilana katlanmaz (TRENDYOL.md 5).
            $table->string('external_status')->default('');
            $table->char('currency', 3)->default('TRY');
            $table->timestamp('placed_at');
            // Artimli senkronun ve monoton statu korumasinin dayanagi.
            $table->timestamp('remote_last_modified_at')->nullable();
            $table->jsonb('totals')->default('{}');

            // KVKK: ad-soyad, e-posta, telefon, tam adres, koordinat ve TCKN.
            // AsEncryptedArrayObject cast'i ile sifreli — sifreli metin jsonb'ye
            // sigmaz, text olmak zorunda (BACKEND-PLAN 13).
            $table->text('customer')->nullable();
            $table->text('raw')->nullable();

            $table->timestamps();

            $table->unique(['connection_id', 'remote_id']);
            $table->index(['connection_id', 'remote_order_number']);
            $table->index('status');
            $table->index('placed_at', 'orders_placed_at_brin', 'brin');
        });

        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // NULLABLE ve bilerek: pazaryerinden katalogda karsiligi olmayan bir
            // barkod gelebilir. O satirlar "eslesmemis" kuyruguna duser, siparis
            // ASLA reddedilmez (BACKEND-PLAN 5.3).
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('remote_line_id');
            $table->string('sku')->default('');
            $table->string('barcode', 40)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->jsonb('discounts')->default('{}');
            // commission bir ORAN, tutar degil (TRENDYOL.md 4.4.1).
            $table->decimal('commission', 8, 4)->nullable();
            $table->decimal('vat_rate', 8, 4)->nullable();
            // Satir statusu paket statusunden ayrisabilir (TRENDYOL.md 5.3).
            $table->string('status');
            $table->string('external_status')->default('');
            $table->timestamps();

            $table->unique(['order_id', 'remote_line_id']);
            $table->index('barcode');
        });

        // Eslesmemis satir kuyrugu kismi index ister; Blueprint ifade edemez.
        DB::statement('CREATE INDEX order_lines_unmatched ON order_lines (order_id) WHERE variant_id IS NULL');

        Schema::create('shipment_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('remote_package_id');
            $table->string('cargo_provider')->nullable();
            // CODE128 barkodu int64'u ve JS MAX_SAFE_INTEGER'i asar: string
            // (TRENDYOL.md 9.9).
            $table->string('tracking_number')->nullable();
            $table->string('tracking_link')->nullable();
            $table->string('status');
            $table->string('external_status')->default('');
            $table->decimal('deci', 8, 2)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'remote_package_id']);
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('shipment_packages')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('occurred_at');
            $table->string('source')->default('pull');
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'occurred_at']);
        });

        // packageHistories tekrar tekrar cekilir; insertOrIgnore'un calismasi
        // icin tekillik gerek. package_id NULL olabildigi ve PG'de NULL'lar
        // varsayilan olarak ayri sayildigi icin NULLS NOT DISTINCT sart (PG 15+).
        DB::statement(
            'ALTER TABLE order_status_history ADD CONSTRAINT order_status_history_event_uniq '
            .'UNIQUE NULLS NOT DISTINCT (order_id, package_id, to_status, occurred_at)'
        );

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            // Fatura LINKI 10 yil erisilebilir kalmali; bu bir link, PII kopyasi
            // degil (BACKEND-PLAN 13).
            $table->string('link')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('shipment_packages');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
