<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\SiteEditRequest;
use App\Models\Site;
use App\Models\Team;
use App\Services\SiteBuilder\SiteContentSchema;
use App\Services\SiteBuilder\SiteEditApplier;
use App\Services\SiteBuilder\SiteQualityChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SiteEditController extends Controller
{
    /**
     * Apply a batch of visual-editor operations to the website.
     *
     * The client sends the revision it last saw. When the site has moved on since (another tab,
     * a teammate or an AI edit) nothing is applied and the editor is told to reload.
     */
    public function store(SiteEditRequest $request, Team $currentTeam, Site $site, SiteEditApplier $applier, SiteQualityChecker $quality): JsonResponse
    {
        $conflict = null;

        $updated = DB::transaction(function () use ($request, $site, $applier, &$conflict): ?Site {
            /** @var Site $locked */
            $locked = Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            if ($locked->revision !== (int) $request->validated('revision')) {
                $conflict = $locked;

                return null;
            }

            $before = SiteEditApplier::document($locked);
            $document = $applier->apply($before, $request->validated('ops'));
            $validator = Validator::make($document, SiteContentSchema::rules());
            SiteContentSchema::addCustomPageHooks($validator);
            $validated = $validator->validate();

            if ($document != $before) {
                $locked->snapshotForEditSession($request->user()->id, $this->editLabel($request->validated('ops')));
                $locked->update([
                    'template' => $validated['template'],
                    'content' => SiteContentSchema::toContent($validated),
                ]);
            }

            return $locked;
        });

        if ($updated === null) {
            return response()->json([
                'message' => __('This website changed somewhere else. Reload to keep editing.'),
                'revision' => $conflict->revision,
            ], 409);
        }

        return response()->json([
            'revision' => $updated->revision,
            'document' => SiteEditApplier::document($updated),
            'quality' => $quality->check($updated),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     */
    protected function editLabel(array $ops): string
    {
        $first = $ops[0] ?? [];
        $path = str_replace(['.', '_'], ' ', (string) ($first['path'] ?? 'the website'));

        if (count($ops) > 1) {
            return __('Edited the website');
        }

        return match ((string) ($first['op'] ?? 'set')) {
            'remove' => __('Removed :path', ['path' => $path]),
            'insert' => __('Added :path', ['path' => $path]),
            'move' => __('Moved :path', ['path' => $path]),
            default => __('Changed :path', ['path' => $path]),
        };
    }
}
