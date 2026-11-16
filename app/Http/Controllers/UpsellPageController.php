<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpsellPageController extends Controller
{
    public function show(Request $request): Response
    {
        $page = (string) $request->route('page', '');
        $content = config("addons.pages.{$page}");

        abort_unless(is_array($content), 404);

        return Inertia::render('upsells/Show', [
            'page' => [
                'title' => $content['title'],
                'paragraphs' => $content['paragraphs'],
                'supportUrl' => config('addons.support_url'),
                'supportEmail' => config('addons.support_email'),
            ],
        ]);
    }
}
