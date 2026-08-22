<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('auth tables live in the tenant schema, not the central one', function () {
    expect(Schema::connection('tenant')->hasTable('users'))->toBeTrue()
        ->and(Schema::connection('tenant')->hasTable('passkeys'))->toBeTrue()
        ->and(Schema::connection('tenant')->hasTable('sessions'))->toBeTrue()
        ->and(Schema::connection('tenant')->hasTable('roles'))->toBeTrue()
        ->and(Schema::connection('central')->hasTable('users'))->toBeFalse()
        ->and(Schema::connection('central')->hasTable('sessions'))->toBeFalse()
        ->and(Schema::connection('central')->hasTable('roles'))->toBeFalse();
});

test('auth routes are prefixed with the tenant path segment', function () {
    // Tenant id'si AYNI ZAMANDA URL slug'idir; PathTenantResolver
    // `tenancy()->find($id)` ile cozer, `domains` tablosuna bakmaz.
    expect(route('login'))->toBe('http://app.kobiconnect.test/test/login')
        ->and(route('register'))->toBe('http://app.kobiconnect.test/test/register')
        ->and(route('profile.edit'))->toBe('http://app.kobiconnect.test/test/settings/profile')
        ->and(route('dashboard'))->toBe('http://app.kobiconnect.test/test/dashboard');
});

test('the tenant path segment is resolved and dropped before the controller', function () {

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();

    // PathTenantResolver::resolved() parametreyi dusurur — controller
    // imzalarina sizmamali.
    expect(app('router')->getCurrentRoute()?->parameter('tenant'))->toBeNull()
        ->and(tenant()?->getTenantKey())->toBe('test');
});

test('an unknown tenant segment is rejected', function () {
    $this->get('http://app.kobiconnect.test/bilinmeyen-tenant/dashboard')
        ->assertNotFound();
});

test('users authenticate on the tenant path', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
