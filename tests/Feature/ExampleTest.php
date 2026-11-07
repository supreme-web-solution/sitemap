<?php

test('the landing page sends visitors to login', function () {
    $this->get(route('home'))
        ->assertRedirect('/login');
});
