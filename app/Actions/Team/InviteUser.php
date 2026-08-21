<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Actions\Licensing\CheckQuota;
use App\Models\License;
use App\Models\UsageCounter;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ekibe kullanici davet eder.
 *
 * Koltuk kotasi burada, Action seviyesinde kontrol edilir — middleware'de
 * degil (BACKEND-PLAN §3.2): anlamli hata mesajini ("Profesyonel planinizin
 * kullanici kotasi doldu (5/5)") ancak islemi yapan taraf uretebilir.
 *
 * ponytail: ayri bir davet/token tablosu YOK. Kullanici hemen yaratilir ve
 * Laravel'in kendi sifre sifirlama akisiyla ("sifrenizi belirleyin") davet
 * edilir; `password_reset_tokens` zaten tenant semasinda duruyor. Kabul
 * edilmemis davetlerin ayri bir yasam dongusu (yeniden gonder / iptal / suresi
 * doldu) gerekirse `invitations` tablosu buraya girer.
 */
final class InviteUser
{
    public const string SEAT_METRIC = 'seats.max';

    public function __construct(private readonly CheckQuota $checkQuota) {}

    public function __invoke(License $license, string $name, string $email, string $role): User
    {
        $this->reconcileSeatCounter($license->tenant_id);

        // Kontrol eder *ve* sayaci artirir.
        ($this->checkQuota)($license, self::SEAT_METRIC);

        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                // Kullanici bunu asla gormez: davet baglantisi kendi sifresini
                // belirletir. Bos birakmak yerine rastgele deger, cunku bos
                // hash her zaman dogrulanabilir bir sifreyi taklit eder.
                'password' => Str::password(32),
            ]);

            // ponytail: davet baglantisini acabilen kisi posta kutusuna
            // sahiptir; ayrica bir dogrulama duvari koymuyoruz — kayit
            // akisinda sahip kullanici icin de ayni karar verildi.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->assignRole($role);
        } catch (Throwable $exception) {
            // Kota sayaci artmis olurdu; sizan koltuk musteriyi kalici olarak
            // engeller, o yuzden geri aliyoruz.
            UsageCounter::record($license->tenant_id, self::SEAT_METRIC, -1);

            throw $exception;
        }

        Password::broker()->sendResetLink(['email' => $email]);

        return $user;
    }

    /**
     * Erisimi olan (en az bir rolu bulunan) kullanici sayisi.
     *
     * Koltugu tuketen sey rol sahibi olmaktir: erisimi kaldirilmis kullanici
     * satiri durur ama koltugu birakir.
     */
    public static function occupiedSeats(): int
    {
        return User::query()->whereHas('roles')->count();
    }

    /**
     * ponytail: `usage_counters` koltuk satiri kayit akisinda hic artirilmiyor
     * ve erisim kaldirmada hic azaltilmiyor; tek dogruluk kaynagi `users`
     * tablosudur. Kota kapisi sayaci okudugu icin, kapiya girmeden once sayaci
     * gercege esitliyoruz. RegisterTenant sahip kullanici icin sayaci artirir
     * hale gelirse bu tazeleme gereksizlesir ama zararsiz kalir.
     */
    private function reconcileSeatCounter(string $tenantId): void
    {
        $drift = self::occupiedSeats() - UsageCounter::valueFor($tenantId, self::SEAT_METRIC);

        if ($drift !== 0) {
            UsageCounter::record($tenantId, self::SEAT_METRIC, $drift);
        }
    }
}
