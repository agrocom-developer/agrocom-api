<?php

it('responde con éxito en la página principal', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
