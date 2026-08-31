<?php

use App\Models\CombatLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('landing page pitches the core loop and both calls to action to a guest', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Two banners. One field. No quarter.');
    $response->assertSee(['Work', 'Train', 'Battle', 'Level Up']);
    $response->assertSee(route('login'));
    $response->assertSee(route('register'));

    // Hero + closing CTA are the same button twice, deliberately.
    expect(substr_count($response->getContent(), 'Start Thy Legend'))->toBe(2);
});

test('landing page swaps both calls to action for continue once signed in', function () {
    $response = $this->actingAs(User::factory()->create())->get('/');

    $response->assertStatus(200);
    $response->assertDontSee('Start Thy Legend');
    expect(substr_count($response->getContent(), 'Continue'))->toBe(2);
});

test('landing stats are real counts, not decoration', function () {
    // Deliberately three distinct numbers so a mis-wired key can't pass.
    $attacker = User::factory()->create();
    $defender = User::factory()->create();
    User::factory()->create();
    CombatLog::create([
        'attacker_id' => $attacker->character()->create([])->id,
        'defender_id' => $defender->character()->create([])->id,
        'attacker_level' => 1,
        'defender_level' => 1,
        'attacker_stats' => [],
        'defender_stats' => [],
        'events' => [],
        'gold_change' => 0,
        'xp_change' => 0,
    ]);

    $this->get('/')->assertSeeTextInOrder([
        '3', 'Souls enlisted',
        '2', 'Warriors afield',
        '1', 'Battles fought',
    ]);
});

test('landing stats strip disappears when the config switch is off', function () {
    // The service caches for five minutes; the array store survives between
    // requests inside one test process.
    Cache::flush();
    config(['game.landing_stats' => false]);

    $this->get('/')->assertDontSee('Battles fought');
});
