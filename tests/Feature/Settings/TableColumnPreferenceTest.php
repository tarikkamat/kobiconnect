<?php

declare(strict_types=1);

use App\Models\User;

it('stores hidden columns per table without clobbering other tables', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('table-columns.update'), [
            'table' => 'orders.index',
            'hidden' => ['customer', 'externalStatus'],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('table-columns.update'), [
            'table' => 'stock.index',
            'hidden' => ['reserved'],
        ])
        ->assertRedirect();

    // jsonb anahtar sirasini korumaz; anahtar anahtar dogrulanir.
    $preferences = $user->fresh()->table_preferences;

    expect($preferences)->toHaveCount(2)
        ->and($preferences['orders.index'])->toBe(['hidden' => ['customer', 'externalStatus']])
        ->and($preferences['stock.index'])->toBe(['hidden' => ['reserved']]);
});

it('drops the table key when every column is visible again', function (): void {
    $user = User::factory()->create();
    $user->forceFill([
        'table_preferences' => ['orders.index' => ['hidden' => ['customer']]],
    ])->save();

    $this->actingAs($user)
        ->patch(route('table-columns.update'), [
            'table' => 'orders.index',
            'hidden' => [],
        ])
        ->assertRedirect();

    expect($user->fresh()->table_preferences)->toBe([]);
});

it('shares the preferences with every Inertia page', function (): void {
    $user = User::factory()->create();
    $user->forceFill([
        'table_preferences' => ['orders.index' => ['hidden' => ['customer']]],
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            // Tablo kimligi nokta icerir; dot-notation yerine deger kiyasi.
            fn ($page) => $page->where(
                'tablePreferences',
                ['orders.index' => ['hidden' => ['customer']]],
            ),
        );
});
