<?php

namespace App\Http\Controllers\Proposals;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Proposals\ProposalCopyGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalDraftController extends Controller
{
    /**
     * Draft introduction and terms for a proposal with AI.
     */
    public function __invoke(Request $request, Team $currentTeam, ProposalCopyGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'items' => ['nullable', 'array', 'max:40'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.interval' => ['nullable', 'string', 'max:40'],
        ]);

        $lead = $currentTeam->leads()->whereKey($data['lead_id'])->firstOrFail();

        return response()->json($generator->generate(
            $currentTeam,
            $lead,
            $data['title'],
            $data['items'] ?? [],
        ));
    }
}
