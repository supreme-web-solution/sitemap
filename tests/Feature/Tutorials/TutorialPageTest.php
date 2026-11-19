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
            ->where('tutorials.0.embedUrl', 'https://drive.google.com/file/d/1OchLCE_94cWfgpe_TB7vt3KMSQ0pHKhA/preview')
            ->where('tutorials.1.embedUrl', 'https://drive.google.com/file/d/1clYubv4sHd9YltEHkzPlsuSszFs-AyF4/preview')
            ->where('tutorials.2.embedUrl', 'https://drive.google.com/file/d/1-shMv2p6n3mELbIKx3CwIcD0ALYfjY4f/preview')
            ->where('tutorials.3.embedUrl', 'https://drive.google.com/file/d/10jgjd5G-kkp0vKu3ZcaS2AQJn9Rpg8--/preview'));
});

test('a guest is asked to sign in before opening tutorials', function () {
    $this->get(route('tutorials.index', ['current_team' => 'demo-team']))
        ->assertRedirect(route('login'));
});
