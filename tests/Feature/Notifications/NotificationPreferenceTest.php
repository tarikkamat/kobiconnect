<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\NotificationEvent;
use App\Notifications\NotificationPreferences;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->user = User::factory()->create()->assignRole('Yönetici');
});

it('matrisi olay gruplariyla ve kanal kullanilabilirligiyle verir', function (): void {
    $this->actingAs($this->user)
        ->get(route('notification-preferences.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/notifications')
            ->has('events', count(NotificationEvent::cases()))
            ->where('events.0.group', NotificationEvent::cases()[0]->group())
            // Yapilandirilmamis kanal (broadcast) matriste GORUNUR ama
            // secilemez; gizlemek "neden bildirim gelmiyor"u aciklanamaz yapar.
            ->where('channels.2.value', 'broadcast')
            ->where('channels.2.available', false)
            ->where('preferences.order_received', ['database'])
        );
});

it('isaretlenmemis olay sessizlige alinir, varsayilana DONMEZ', function (): void {
    $this->actingAs($this->user)
        ->put(route('notification-preferences.update'), [
            'preferences' => [
                NotificationEvent::SyncFailed->value => ['database' => 'on'],
            ],
        ])
        ->assertRedirect();

    $matrix = NotificationPreferences::matrixFor($this->user->refresh());

    expect($matrix[NotificationEvent::SyncFailed->value])->toBe(['database'])
        // Formda hic gorunmeyen olay "hicbir kanal" demektir; varsayilan
        // e-posta geri gelseydi kullanici kapatti sandigi bildirimi almaya
        // devam ederdi.
        ->and($matrix[NotificationEvent::OrderReceived->value])->toBe([]);
});

it('yapilandirilmamis kanal tercihe yazilamaz', function (): void {
    $this->actingAs($this->user)
        ->put(route('notification-preferences.update'), [
            'preferences' => [
                NotificationEvent::SyncFailed->value => ['broadcast' => 'on'],
            ],
        ])
        ->assertRedirect();

    expect(NotificationPreferences::channelsFor($this->user->refresh(), NotificationEvent::SyncFailed))
        ->toBe([]);
});

it('tercih KISIYE ozeldir', function (): void {
    $other = User::factory()->create()->assignRole('Yönetici');

    $this->actingAs($this->user)
        ->put(route('notification-preferences.update'), [
            'preferences' => [
                NotificationEvent::OrderReceived->value => ['database' => 'on'],
            ],
        ])
        ->assertRedirect();

    expect(NotificationPreferences::matrixFor($other)[NotificationEvent::SyncFailed->value])
        ->toBe(['database', 'mail']);
});
