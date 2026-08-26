<?php

declare(strict_types=1);

use App\Actions\Team\InviteUser;
use App\Mail\Lifecycle\FirstConnectionEstablished;
use App\Mail\Lifecycle\FirstOrderReceived;
use App\Mail\Lifecycle\TeamInvitation;
use App\Mail\Lifecycle\WelcomeEmail;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->user = User::factory()->create()->assignRole('Sahip');
});

it('can render WelcomeEmail mailable', function (): void {
    $mailable = new WelcomeEmail(
        userName: 'Ahmet Yılmaz',
        dashboardUrl: 'http://test.kobiconnect.test/dashboard',
    );

    $mailable->assertHasSubject('KobiConnect\'a hoş geldiniz!');
    $mailable->assertSeeInHtml('Ahmet Yılmaz');
    $mailable->assertSeeInHtml('Panele Git');
});

it('can render TeamInvitation mailable', function (): void {
    $mailable = new TeamInvitation(
        userName: 'Ayşe Kaya',
        roleName: 'Depo',
        tenantName: 'Acme Ltd',
        loginUrl: 'http://test.kobiconnect.test/login',
    );

    $mailable->assertHasSubject('Acme Ltd sizi KobiConnect ekibine davet etti');
    $mailable->assertSeeInHtml('Ayşe Kaya');
    $mailable->assertSeeInHtml('Depo');
    $mailable->assertSeeInHtml('Acme Ltd');
});

it('can render FirstConnectionEstablished mailable', function (): void {
    $mailable = new FirstConnectionEstablished(
        connectionName: 'Trendyol Mağazam',
        marketplace: 'Trendyol',
    );

    $mailable->assertHasSubject('Trendyol Mağazam bağlantınız başarıyla kuruldu!');
    $mailable->assertSeeInHtml('Trendyol Mağazam');
    $mailable->assertSeeInHtml('Trendyol');
});

it('can render FirstOrderReceived mailable', function (): void {
    $mailable = new FirstOrderReceived(
        data: [
            'orderNumber' => 'TY-12345',
            'channel' => 'Trendyol Mağazam',
            'total' => '1.250,00 ₺',
        ],
        orderUrl: 'http://test.kobiconnect.test/orders/1',
    );

    $mailable->assertHasSubject('İlk siparişiniz geldi! 🎉');
    $mailable->assertSeeInHtml('TY-12345');
    $mailable->assertSeeInHtml('Trendyol Mağazam');
    $mailable->assertSeeInHtml('1.250,00 ₺');
});

it('sends TeamInvitation when inviting a user', function (): void {
    Mail::fake();

    $invite = app(InviteUser::class);
    $invited = $invite('Mehmet Demir', 'mehmet@example.com', 'Depo');

    Mail::assertQueued(TeamInvitation::class, function (TeamInvitation $mail) {
        return $mail->hasTo('mehmet@example.com')
            && $mail->userName === 'Mehmet Demir'
            && $mail->roleName === 'Depo';
    });
});
