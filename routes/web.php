<?php

use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsureCharacterExists;
use App\Livewire\Battle;
use App\Livewire\FactionSelect;
use App\Livewire\Hospital;
use App\Livewire\Market;
use App\Livewire\Train;
use App\Livewire\Work;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Step two of character creation — reachable with no character, which is
// exactly the state EnsureCharacterExists bounces everything else out of.
Route::get('/faction', FactionSelect::class)->middleware(['auth', 'verified'])->name('faction.select');

// Everything past this point dereferences a character.
Route::middleware(['auth', 'verified', EnsureCharacterExists::class])->group(function () {
    Route::get('/home', function () {
        return view('home');
    })->name('home');

    Route::get('/work', Work::class)->name('work');
    Route::get('/train', Train::class)->name('train');
    Route::get('/market', Market::class)->name('market');
    Route::get('/battle', Battle::class)->name('battle');
    Route::get('/hospital', Hospital::class)->name('hospital');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
