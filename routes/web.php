<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\Train;
use App\Livewire\Work;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/work', Work::class)->middleware(['auth', 'verified'])->name('work');
Route::get('/train', Train::class)->middleware(['auth', 'verified'])->name('train');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
