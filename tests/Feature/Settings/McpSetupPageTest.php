<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('mcp setup page shows the tenant specific endpoint', function (): void {
    $response = $this
        ->actingAs(User::factory()->create())
        ->get(route('mcp.setup'));

    $response->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('settings/mcp')
            ->where('endpoint', url(tenant('id').'/mcp'))
            ->where('actionCount', fn (int $count): bool => $count > 0)
    );
});
