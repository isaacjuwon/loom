<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::livewire('projects', 'pages::projects.index')->name('projects.index');
        Route::livewire('projects/create', 'pages::projects.create')->name('projects.create');
        Route::livewire('projects/{project}', 'pages::projects.show')->name('projects.show');
        Route::livewire('projects/{project}/edit', 'pages::projects.edit')->name('projects.edit');
    });
