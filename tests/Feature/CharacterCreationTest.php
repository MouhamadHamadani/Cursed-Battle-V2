<?php

use App\Models\Character;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('registering a user creates a character with default stats', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'faction' => 'faction_2',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $character = $user->character;

    expect($character)->not->toBeNull();
    expect($character->level)->toEqual(1);
    expect($character->gold)->toEqual(100);
    expect($character->energy)->toEqual(10);
    expect($character->max_energy)->toEqual(10);
    expect($character->health)->toEqual(100);
    expect($character->max_health)->toEqual(100);
    expect($character->strength)->toEqual(5);
    expect($character->defense)->toEqual(5);
    expect($character->agility)->toEqual(5);
    expect($character->faction)->toEqual('faction_2');
    expect($character->hospitalized_until)->toBeNull();
});

test('deleting a user cascades to their character', function () {
    $user = User::factory()->create();
    $character = $user->character()->create([]);

    DB::table('users')->where('id', $user->id)->delete();

    expect(Character::find($character->id))->toBeNull();
});
