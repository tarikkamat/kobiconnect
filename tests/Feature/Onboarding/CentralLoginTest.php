<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Ortak giris central host'ta yasar: musteri panel adresini bilmeden
 * e-posta + parola ile girer, tenant e-postadan cozulur.
 */
it('ortak giris ekranini gosterir', function (): void {
    $this->get(route('central.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('onboarding/login'));
});

it('dogru bilgilerle kullanicinin tenant paneline yonlendirir', function (): void {
    User::factory()->create(['email' => 'sahip@acme.test']);

    $this->post(route('central.login.store'), [
        'email' => 'sahip@acme.test',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', ['tenant' => 'test']));

    $this->assertAuthenticated();
});

it('yanlis parolayi reddeder', function (): void {
    User::factory()->create(['email' => 'sahip@acme.test']);

    $this->post(route('central.login.store'), [
        'email' => 'sahip@acme.test',
        'password' => 'yanlis-parola',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('hicbir tenant\'ta olmayan e-postayi reddeder', function (): void {
    $this->post(route('central.login.store'), [
        'email' => 'kimse@yok.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('iki adimli dogrulama acikken challenge ekranina gonderir', function (): void {
    $user = User::factory()->withTwoFactor()->create(['email' => 'sahip@acme.test']);

    $this->post(route('central.login.store'), [
        'email' => 'sahip@acme.test',
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login', ['tenant' => 'test']));

    // Merkezi giris 2FA'yi ATLAMAZ: oturum acilmaz, yalnizca Fortify'in
    // bekledigi challenge anahtarlari yazilir.
    $this->assertGuest();
    expect(session('login.id'))->toBe($user->getKey());
});

it('gecersiz e-postayi reddeder', function (): void {
    $this->post(route('central.login.store'), ['email' => 'gecersiz', 'password' => 'x'])
        ->assertSessionHasErrors('email');
});
