<?php

use App\Models\User;

test('a team member can open the tutorials', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tutorials.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tutorials/Index')
            ->has('tutorials', 4)
            ->where('tutorials.0.title', 'Intro, leads & website setup')
            ->where('tutorials.0.embedUrl', 'https://player.vimeo.com/video/1250739908')
            ->where('tutorials.1.embedUrl', 'https://player.vimeo.com/video/1250740403')
            ->where('tutorials.2.embedUrl', 'https://player.vimeo.com/video/1250743424')
            ->where('tutorials.3.embedUrl', 'https://player.vimeo.com/video/1250744003'));
});

test('a guest is asked to sign in before opening tutorials', function () {
    $this->get(route('tutorials.index', ['current_team' => 'demo-team']))
        ->assertRedirect(route('login'));
});
