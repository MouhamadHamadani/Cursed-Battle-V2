<?php

use App\Models\User;

// Livewire 3 registers POST /livewire/update itself with only the 'web'
// middleware group (HandleRequests::boot()), before routes/web.php even
// loads — so the throttle has to be appended to that group in
// bootstrap/app.php, not declared on a route, or it would never see this
// endpoint. This guards that it actually landed there.
test('a burst of requests to the livewire update endpoint past the per-minute limit is throttled', function () {
    $this->actingAs(User::factory()->create());

    for ($i = 1; $i <= 120; $i++) {
        $status = $this->post('/livewire/update')->status();
        expect($status)->not->toBe(429);
    }

    $this->post('/livewire/update')->assertStatus(429);
});

test('a normal-rate request to the livewire update endpoint is not throttled', function () {
    $this->actingAs(User::factory()->create());

    expect($this->post('/livewire/update')->status())->not->toBe(429);
});
