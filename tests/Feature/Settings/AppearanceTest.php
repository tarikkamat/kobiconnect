<?php

use App\Models\User;

test('appearance page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('appearance.edit'));

    $response->assertOk();
});
