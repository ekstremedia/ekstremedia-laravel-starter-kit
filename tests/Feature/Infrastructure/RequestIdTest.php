<?php

it('stamps a generated request id on the response when the client omits one', function () {
    $response = $this->get('/');

    $response->assertOk();
    $id = $response->headers->get('X-Request-Id');

    expect($id)->not->toBeNull();
    expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

it('accepts a well-formed client-supplied request id', function () {
    $response = $this->withHeaders(['X-Request-Id' => 'trace-abc-123'])->get('/');

    $response->assertHeader('X-Request-Id', 'trace-abc-123');
});

it('ignores a junk client-supplied request id', function () {
    // Control characters + newline = classic log-injection payload.
    $response = $this->withHeaders(['X-Request-Id' => "oh\nno"])->get('/');

    $echoed = $response->headers->get('X-Request-Id');
    expect($echoed)->toMatch('/^[0-9a-f-]{36}$/i');
});
