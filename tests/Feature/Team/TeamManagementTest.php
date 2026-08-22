<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->owner = User::factory()->create(['name' => 'Sahibi'])->assignRole('Sahip');

    Notification::fake();
});

it('lists members with their role and whether they still have access', function (): void {
    User::factory()->create(['name' => 'Askıda'])->syncRoles([]);

    $this->actingAs($this->owner)
        ->get(route('team.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/team/index')
            ->has('members', 2)
            ->where('members.0.name', 'Askıda')
            ->where('members.0.active', false)
            ->where('members.1.active', true)
            ->where('members.1.isSelf', true)
            ->where('seats.used', 1));
});

it('creates the user and invites them to set their own password', function (): void {
    $this->actingAs($this->owner)
        ->post(route('team.store'), [
            'name' => 'Yeni Depocu',
            'email' => 'depo@example.com',
            'role' => 'Depo',
        ])
        ->assertRedirect();

    $invited = User::query()->where('email', 'depo@example.com')->sole();

    expect($invited->hasRole('Depo'))->toBeTrue();

    Notification::assertSentTo($invited, ResetPassword::class);
});

it('frees the seat when a user loses their role', function (): void {
    User::factory()->create()->assignRole('Depo');
    $revoked = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($this->owner)
        ->delete(route('team.destroy', $revoked))
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->post(route('team.store'), [
            'name' => 'Yerine Gelen',
            'email' => 'yerine@example.com',
            'role' => 'Depo',
        ])
        ->assertRedirect();

    expect(User::query()->where('email', 'yerine@example.com')->exists())->toBeTrue();
});

it('refuses to take the role of the last owner', function (): void {
    $this->actingAs($this->owner)
        ->patch(route('team.update', $this->owner), ['role' => 'Yönetici'])
        ->assertSessionHasErrors('role');

    expect($this->owner->refresh()->hasRole('Sahip'))->toBeTrue();
});

it('refuses to revoke the access of the last owner', function (): void {
    $other = User::factory()->create()->assignRole('Yönetici');

    $this->actingAs($other)
        ->delete(route('team.destroy', $this->owner))
        ->assertSessionHasErrors('role');

    expect($this->owner->refresh()->hasRole('Sahip'))->toBeTrue();
});

it('allows demoting an owner once a second owner exists', function (): void {
    $second = User::factory()->create()->assignRole('Sahip');

    $this->actingAs($this->owner)
        ->patch(route('team.update', $second), ['role' => 'Depo'])
        ->assertRedirect();

    expect($second->refresh()->hasRole('Depo'))->toBeTrue()
        ->and($second->hasRole('Sahip'))->toBeFalse();
});

it('stops anyone from revoking their own access', function (): void {
    $second = User::factory()->create()->assignRole('Sahip');

    $this->actingAs($second)
        ->delete(route('team.destroy', $second))
        ->assertForbidden();

    expect($second->refresh()->hasRole('Sahip'))->toBeTrue();
});

it('keeps the row when access is revoked so it can be handed back', function (): void {
    $member = User::factory()->create()->assignRole('Depo');

    $this->actingAs($this->owner)
        ->delete(route('team.destroy', $member))
        ->assertRedirect();

    expect($member->refresh()->roles)->toBeEmpty()
        ->and(User::query()->whereKey($member->getKey())->exists())->toBeTrue();

    $this->actingAs($this->owner)
        ->patch(route('team.update', $member), ['role' => 'Muhasebe'])
        ->assertRedirect();

    expect($member->refresh()->hasRole('Muhasebe'))->toBeTrue();
});

it('keeps team management away from roles without users.manage', function (): void {
    $keeper = User::factory()->create()->assignRole('Depo');

    $this->actingAs($keeper)->get(route('team.index'))->assertForbidden();

    $this->actingAs($keeper)
        ->post(route('team.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'Depo'])
        ->assertForbidden();
});
