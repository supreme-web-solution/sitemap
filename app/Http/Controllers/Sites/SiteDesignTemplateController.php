<?php

namespace App\Http\Controllers\Sites;

use App\Enums\SiteDesignStyle;
use App\Http\Controllers\Controller;
use App\Models\SiteDesignTemplate;
use App\Models\Team;
use App\Services\SiteBuilder\EnsureDesignTemplateCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteDesignTemplateController extends Controller
{
    /**
     * Search the design catalog by industry, style, or accent palette.
     */
    public function index(Request $request, Team $currentTeam, EnsureDesignTemplateCatalog $catalog): JsonResponse
    {
        $catalog->ensure();

        $templates = SiteDesignTemplate::query()
            ->active()
            ->filtered(
                $request->string('industry')->toString() ?: null,
                $request->string('style')->toString() ?: null,
                $request->string('palette')->toString() ?: null,
            )
            ->ordered()
            ->get()
            ->map(fn (SiteDesignTemplate $template) => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'baseTemplate' => $template->base_template->value,
                'baseTemplateLabel' => $template->base_template->label(),
                'style' => $template->style->value,
                'styleLabel' => $template->style->label(),
                'industries' => $template->industries,
                'accentColor' => $template->accent_color,
                'description' => $template->description,
            ]);

        return response()->json([
            'templates' => $templates,
            'styles' => SiteDesignStyle::options(),
        ]);
    }
}
