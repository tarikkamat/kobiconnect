<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bildirim katmani — BACKEND-PLAN.md §11.3.
 *
 * Her iki tablo da TENANT semasindadir: bildirim tenant'in kullanicisina
 * aittir ve central'da kullanici yoktur (§4.1). Lisans olaylari central'da
 * dogsa bile bildirim tenant baglaminda yazilir (App\Listeners\Notifications).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Laravel'in kendi `notifications` semasi. `notifiable` morph'u
        // bigint'tir cunku tenant `users.id` bigint'tir.
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            // Laravel'in stub'i `text` kullanir; PG'de `jsonb` sectik ki
            // bildirim sayfasindaki olay filtresi `data->event` uzerinden
            // sorgulanabilsin. Eloquent tarafinda fark yok (`array` cast).
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Zil sorgusu her sayfa yuklemesinde kosar: okunmamislar once.
            $table->index(['notifiable_id', 'read_at', 'created_at'], 'notifications_bell');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // App\Notifications\NotificationEvent degeri. Enum'a cast EDILMEZ:
            // kaldirilan bir olayin eski satiri okunamaz hale gelmemeli.
            $table->string('event');
            // list<App\Notifications\NotificationChannel::value>. Bos dizi
            // "bu olaydan hic haber verme" demektir ve varsayilandan FARKLIDIR
            // (satir yoksa varsayilan kanallar gecerlidir).
            $table->jsonb('channels')->default('[]');
            $table->timestamps();
            $table->unique(['user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
