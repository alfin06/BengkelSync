<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shows a friendly page for unknown URLs', function () {
    $this->get('/does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
});

it('answers JSON requests with JSON errors', function () {
    $this->getJson('/does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
