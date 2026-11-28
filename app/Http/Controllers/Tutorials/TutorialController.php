<?php

namespace App\Http\Controllers\Tutorials;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class TutorialController extends Controller
{
    /**
     * Show the setup videos for this workspace.
     */
    public function __invoke(Team $currentTeam): Response
    {
        return Inertia::render('tutorials/Index', [
            'tutorials' => [
                [
                    'id' => 'intro',
                    'title' => 'Intro, leads & website setup',
                    'description' => 'Search for leads, open a business, and build the website.',
                    'embedUrl' => 'https://player.vimeo.com/video/1250739908',
                ],
                [
                    'id' => 'proposal',
                    'title' => 'Proposal, outreach and payment',
                    'description' => 'Send a proposal, reach out, and take payment.',
                    'embedUrl' => 'https://player.vimeo.com/video/1250740403',
                ],
                [
                    'id' => 'pipeline',
                    'title' => 'Pipeline, email and sequence',
                    'description' => 'Move a deal through the pipeline, send email, and run a sequence.',
                    'embedUrl' => 'https://player.vimeo.com/video/1250743424',
                ],
                [
                    'id' => 'branding',
                    'title' => 'Branding and white label',
                    'description' => 'Set your brand and show clients a white-labeled preview.',
                    'embedUrl' => 'https://player.vimeo.com/video/1250744003',
                ],
            ],
        ]);
    }
}
