<?php

use App\Models\Tenant;
use App\Models\User;
use Laravel\Fortify\Features;

/**
 * BACKEND-PLAN.md §4.2 — path tanimlamada uygulama TEK host'ta yasar, bu yuzden
 * Relying Party ID tum tenant'lar icin AYNIDIR. Subdomain modelindeki dogal
 * tenant kapanmasi burada YOKTUR; korumayi `User::getPasskeyUserHandle()`
 * icindeki tenant nitelemesi saglar.
 */
test('the relying party is the single app host', function () {
    expect(config('passkeys.relying_party_id'))->toBe('app.kobiconnect.test')
        ->and(config('passkeys.allowed_origins'))->toBe(['http://app.kobiconnect.test']);
});

test('every tenant shares the same relying party', function () {
    $other = Tenant::firstOrCreate(['id' => 'other']);

    try {
        tenancy()->initialize($other);

        expect(config('passkeys.relying_party_id'))->toBe('app.kobiconnect.test');
    } finally {
        tenancy()->initialize($this->tenant);
        $other->delete();
    }
});

/**
 * Asil koruma budur. RP ID ortak oldugu ve her tenant semasinda `users.id = 1`
 * bulundugu icin, paket varsayilani handle iki tenant'ta CAKISIRDI; authenticator
 * bunlari tek hesap sanip birbirinin resident credential'ini ezebilirdi.
 */
test('the user handle differs across tenants for the same user id', function () {
    // Handle yalnizca tablo adi + tenant anahtari + primary key'den turer;
    // kaydetmeye gerek yok. Ayni id'yi iki tenant'ta zorlayarak cakismanin
    // gercekten onlendigini olcuyoruz.
    $sameId = 1;

    $here = new User;
    $here->forceFill(['id' => $sameId]);
    $handleHere = $here->getPasskeyUserHandle();

    $other = Tenant::firstOrCreate(['id' => 'other']);

    try {
        tenancy()->initialize($other);

        $there = new User;
        $there->forceFill(['id' => $sameId]);

        expect($there->getPasskeyUserHandle())->not->toBe($handleHere);
    } finally {
        tenancy()->initialize($this->tenant);
        $other->delete();
    }
});

test('the passkey endpoints document is served from the tenant path', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());

    $this->get(route('well-known.passkeys'))
        ->assertOk()
        ->assertExactJson([
            'enroll' => 'http://app.kobiconnect.test/test/settings/security',
            'manage' => 'http://app.kobiconnect.test/test/settings/security',
        ]);
});
