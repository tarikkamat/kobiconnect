<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Parola sifirlama central host'ta yasar: kullanici panel adresini bilmeden
 * baglantiyi alir ve yeni parolasini belirler. Token tenant semasindadir, o
 * yuzden her adim once e-postadan tenant'i cozer.
 */
it('sifirlama talebi ekranini gosterir', function (): void {
    $this->get(route('central.password.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('onboarding/forgot-password'));
});

it('kayitli e-postaya CENTRAL sifirlama baglantisi gonderir', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'sahip@acme.test']);

    $this->post(route('central.password.email'), ['email' => 'sahip@acme.test'])
        ->assertRedirect(route('central.password.request'))
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        // Baglanti tenant path'i tasimamali — bkz. ResetPassword::createUrlUsing.
        expect($notification->toMail($user)->actionUrl)->toBe(route('central.password.reset', [
            'token' => $notification->token,
            'email' => 'sahip@acme.test',
        ]));

        return true;
    });
});

it('bilinmeyen e-posta icin ayni yaniti verir ama posta gondermez', function (): void {
    Notification::fake();

    $this->post(route('central.password.email'), ['email' => 'kimse@yok.test'])
        ->assertRedirect(route('central.password.request'))
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('sifirlama ekranini token ile gosterir', function (): void {
    $this->get(route('central.password.reset', ['token' => 'ornek-token', 'email' => 'sahip@acme.test']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/reset-password')
            ->where('token', 'ornek-token')
            ->where('email', 'sahip@acme.test'));
});

it('gecerli token ile parolayi gunceller', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'sahip@acme.test']);

    $this->post(route('central.password.email'), ['email' => 'sahip@acme.test']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $this->post(route('central.password.update'), [
            'token' => $notification->token,
            'email' => 'sahip@acme.test',
            'password' => 'yeni-parola',
            'password_confirmation' => 'yeni-parola',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('central.login'));

        expect(Hash::check('yeni-parola', $user->fresh()->password))->toBeTrue();

        return true;
    });
});

it('gecersiz token ile parolayi guncellemez', function (): void {
    $user = User::factory()->create(['email' => 'sahip@acme.test']);

    $this->post(route('central.password.update'), [
        'token' => 'gecersiz-token',
        'email' => 'sahip@acme.test',
        'password' => 'yeni-parola',
        'password_confirmation' => 'yeni-parola',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('yeni-parola', $user->fresh()->password))->toBeFalse();
});
