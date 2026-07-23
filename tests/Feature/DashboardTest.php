<?php

use App\Models\User;

test('dashboard displays the authenticated user character stats', function () {
    $user = User::factory()->create();
    $user->character()->create([]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertSee(['Level', 'Gold', '100']);
});
