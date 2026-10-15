<?php

declare(strict_types=1);

it('prevents a native vendor login form submission before Livewire handles it', function (): void {
    $this->get('/vendor-portal/login')
        ->assertOk()
        ->assertSee('wire:submit="authenticate"', false)
        ->assertSee('onsubmit="event.preventDefault()"', false);
});
