<?php

it('returns a successful response for the welcome page', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('returns an inertia response with Welcome component', function () {
    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('has the health endpoint', function () {
    $response = $this->get('/up');

    $response->assertStatus(200);
});
