<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Ekibe kullanici davet eder.
 *
 * ponytail: ayri bir davet/token tablosu YOK. Kullanici hemen yaratilir ve
 * Laravel'in kendi sifre sifirlama akisiyla ("sifrenizi belirleyin") davet
 * edilir; `password_reset_tokens` zaten tenant semasinda duruyor. Kabul
 * edilmemis davetlerin ayri bir yasam dongusu (yeniden gonder / iptal / suresi
 * doldu) gerekirse `invitations` tablosu buraya girer.
 */
final class InviteUser
{
    public function __invoke(string $name, string $email, string $role): User
    {
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
}
