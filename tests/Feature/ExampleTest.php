<?php

test('the application redirects to recording page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('record.create'));
});
