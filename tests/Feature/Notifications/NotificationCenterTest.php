<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\EventNotification;
use App\Notifications\NotificationEvent;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->owner = User::factory()->create()->assignRole('Yönetici');
    $this->colleague = User::factory()->create()->assignRole('Yönetici');
});

/**
 * `OrderReceived` bilerek secildi: varsayilan kanali yalnizca `database`,
 * dolayisiyla test bir posta sunucusuna dokunmaz.
 */
function notifyOrder(User $user, string $orderNumber): void
{
    $user->notify(new EventNotification(NotificationEvent::OrderReceived, [
        'order_number' => $orderNumber,
        'order_id' => 41,
    ]));
}

it('bildirimi yalnizca sahibine gosterir', function (): void {
    notifyOrder($this->owner, 'SIP-1');
    notifyOrder($this->colleague, 'SIP-2');

    $this->actingAs($this->owner)
        ->get(route('notifications.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('notifications/index')
            ->where('unreadCount', 1)
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Yeni sipariş: SIP-1')
            ->where('notifications.data.0.eventLabel', 'Yeni sipariş')
            ->where('notifications.data.0.read', false)
            // Bildirim bir ekrana goturmelidir; goturmuyorsa haber degil gurultu.
            ->where('notifications.data.0.url', route('orders.show', ['order' => 41], absolute: false))
        );
});

it('okunmamis filtresi okunmuslari duser', function (): void {
    notifyOrder($this->owner, 'SIP-1');
    notifyOrder($this->owner, 'SIP-2');
    $this->owner->notifications()->latest()->first()?->markAsRead();

    $this->actingAs($this->owner)
        ->get(route('notifications.index', ['unread' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('notifications.data', 1)
            ->where('filters.unread', true)
        );
});

it('zil ucu sayaci ve son bildirimleri JSON olarak verir', function (): void {
    foreach (range(1, 10) as $index) {
        notifyOrder($this->owner, "SIP-{$index}");
    }

    $response = $this->actingAs($this->owner)->getJson(route('notifications.feed'));

    $response->assertOk()
        ->assertJsonPath('unreadCount', 10)
        // Zil bir defter degil bir ozet: acilir liste sekiz kayitla sinirli.
        ->assertJsonCount(8, 'items');
});

it('zil verisi paylasilan proplara BINMEZ', function (): void {
    notifyOrder($this->owner, 'SIP-1');

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('notifications')
            ->missing('unreadCount')
        );
});

it('tekil ve toplu okundu isareti ayni ucu kullanir', function (): void {
    notifyOrder($this->owner, 'SIP-1');
    notifyOrder($this->owner, 'SIP-2');

    $first = $this->owner->notifications()->latest()->first();

    // Zil (useHttp) JSON ister, sayfadaki <Form> Inertia gezinmesi yapar; tek
    // uc iki cagriyi da karsilar.
    $this->actingAs($this->owner)
        ->post(route('notifications.read'), ['id' => $first?->getKey()])
        ->assertOk()
        ->assertJsonPath('unreadCount', 1);

    $this->actingAs($this->owner)
        ->post(route('notifications.read'), [], ['X-Inertia' => 'true'])
        ->assertRedirect();

    expect($this->owner->unreadNotifications()->count())->toBe(0);
});

it('baskasinin bildirimini okundu isaretleyemez', function (): void {
    notifyOrder($this->colleague, 'SIP-2');
    $foreign = $this->colleague->notifications()->latest()->first();

    $this->actingAs($this->owner)
        ->post(route('notifications.read'), ['id' => $foreign?->getKey()])
        ->assertOk();

    expect($this->colleague->unreadNotifications()->count())->toBe(1);
});
