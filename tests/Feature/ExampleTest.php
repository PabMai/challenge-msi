<?php

test('returns a successful response', function () {
    $this->get('/')->assertStatus(200);
});
