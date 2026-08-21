<?php

declare(strict_types=1);

use App\Models\License;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Paylasilan `license` prop'u HAM enum degerini tasimaz.
 *
 * Grace period'da `licenses.status` hala `active`'tir — sureyi belirleyen sey
 * `grace_until` sutunudur. Ham enum paylasilirsa arayuz grace durumunu HICBIR
 * ZAMAN goremez ve uyari banner'i tetiklenmez.
 */
it('grace period icinde turetilmis durumu ve kalan gunu paylasir', function (): void {
    $license = License::factory()->forTenant($this->tenant)->inGracePeriod()->create();

    // Kok neden: modelin kendi enum'u hala `active` diyor.
    expect($license->status->value)->toBe('active')
        ->and($license->inGracePeriod())->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('license.status', 'grace')
            ->where('license.readOnly', true)
            ->where('license.graceDaysLeft', fn (?int $days): bool => $days !== null && $days >= 0)
        );
});

it('aktif lisansta grace bilgisi tasimaz', function (): void {
    License::factory()->forTenant($this->tenant)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('license.status', 'active')
            ->where('license.readOnly', false)
            ->where('license.graceDaysLeft', null)
        );
});
