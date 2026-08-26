<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('renders definitions hub page for authenticated manager', function (): void {
    $this->actingAs($this->manager)
        ->get(route('definitions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/definitions/index')
        );
});

it('redirects unauthenticated user to login', function (): void {
    $this->get(route('definitions.index'))
        ->assertRedirect(route('login'));
});
