<?php

declare(strict_types=1);

it('requires the planner context before showing discovery results', function (): void {
    $response = $this->get('/ar/search?planner=1');

    $response
        ->assertRedirect()
        ->assertSessionHasErrors([
            'occasion',
            'governorate_public_id',
            'city_public_id',
            'event_starts_at',
            'event_ends_at',
        ]);
});
