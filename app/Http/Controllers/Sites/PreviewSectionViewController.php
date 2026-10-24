<?php

namespace App\Http\Controllers\Sites;

use App\Enums\SiteSection;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PreviewSectionViewController extends Controller
{
    /**
     * Section ids that can be recorded from the public preview.
     *
     * @var list<string>
     */
    public const KEYS = ['hero', 'services', 'about', 'reviews', 'gallery', 'visit', 'faq', 'contact'];

    /**
     * Count a public preview visitor looking at one section. Editor loads are ignored.
     */
    public function __invoke(Request $request, Site $site, string $section): Response
    {
        abort_if($request->boolean('internal'), 204);
        abort_unless(in_array($section, self::KEYS, true), 404);

        if ($site->preview_expires_at?->isPast()) {
            abort(410);
        }

        DB::transaction(function () use ($site, $section) {
            $locked = Site::query()->whereKey($site->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $views = $locked->preview_section_views ?? [];
            $views[$section] = (int) ($views[$section] ?? 0) + 1;
            $locked->forceFill(['preview_section_views' => $views])->save();
        });

        return response()->noContent();
    }

    /**
     * Labels used on the agency preview-access panel.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'hero' => 'Hero',
            'services' => SiteSection::Services->label(),
            'about' => SiteSection::About->label(),
            'reviews' => SiteSection::Reviews->label(),
            'gallery' => SiteSection::Gallery->label(),
            'visit' => SiteSection::Hours->label(),
            'faq' => SiteSection::Faq->label(),
            'contact' => SiteSection::Contact->label(),
        ];
    }
}
