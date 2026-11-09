<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth:web')->group(function () {
    Route::redirect('definicoes', 'definicoes/perfil');

    Route::get('definicoes/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('definicoes/perfil', [ProfileController::class, 'update'])->name('profile.update');
    // A conta da equipa não se apaga a si própria: desactiva-se em Administração → Utilizadores.

    Route::get('definicoes/senha', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('definicoes/senha', [PasswordController::class, 'update'])->name('password.update');

    Route::get('definicoes/aparencia', fn () => Inertia::render('settings/appearance'))->name('appearance');
});
