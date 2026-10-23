<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Team;
use App\Services\SiteBuilder\SitePages;
use App\Services\SiteBuilder\SiteRenderer;
use App\Services\SiteBuilder\SiteUrls;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SiteExportController extends Controller
{
    /**
     * Download the website as a ZIP archive of static files, one folder per page.
     */
    public function __invoke(Team $currentTeam, Site $site, SiteRenderer $renderer, SitePages $pages): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'site-export-');

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the site export archive.');
        }

        $urls = SiteUrls::root(trailingSlash: true);

        foreach ($pages->paths($site) as $pagePath) {
            $zip->addFromString(
                ltrim($pagePath.'/index.html', '/'),
                $renderer->render($site, path: $pagePath, urls: $urls),
            );
        }

        $zip->addFromString('robots.txt', "User-agent: *\nAllow: /\n");
        $zip->close();

        return response()
            ->download($path, Str::slug($site->lead->name).'-website.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
