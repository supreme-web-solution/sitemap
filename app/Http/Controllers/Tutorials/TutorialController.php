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
                    'embedUrl' => 'https://drive.google.com/file/d/1OchLCE_94cWfgpe_TB7vt3KMSQ0pHKhA/preview',
                ],
                [
                    'id' => 'proposal',
                    'title' => 'Proposal, outreach and payment',
                    'description' => 'Send a proposal, reach out, and take payment.',
                    'embedUrl' => 'https://drive.google.com/file/d/1clYubv4sHd9YltEHkzPlsuSszFs-AyF4/preview',
                ],
                [
                    'id' => 'pipeline',
                    'title' => 'Pipeline, email and sequence',
                    'description' => 'Move a deal through the pipeline, send email, and run a sequence.',
                    'embedUrl' => 'https://drive.google.com/file/d/1-shMv2p6n3mELbIKx3CwIcD0ALYfjY4f/preview',
                ],
                [
                    'id' => 'branding',
                    'title' => 'Branding and white label',
                    'description' => 'Set your brand and show clients a white-labeled preview.',
                    'embedUrl' => 'https://drive.google.com/file/d/10jgjd5G-kkp0vKu3ZcaS2AQJn9Rpg8--/preview',
                ],
            ],
        ]);
    }
}
