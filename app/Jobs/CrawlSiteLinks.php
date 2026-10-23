<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\SiteBrokenLink;
use App\Services\Audit\SafeHttpFetcher;
use App\Services\Audit\UnsafeUrlException;
use App\Services\SiteBuilder\SiteRenderer;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

class CrawlSiteLinks implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 1;

    public function __construct(public Site $site)
    {
        $this->onQueue(Queues::MONITORING);
    }

    public function uniqueId(): string
    {
        return (string) $this->site->id;
    }

    /**
     * Fetch pages of the published (or preview) site and record links that do not resolve.
     */
    public function handle(SiteRenderer $renderer, SafeHttpFetcher $fetcher): void
    {
        $html = $renderer->render($this->site, asPreview: ! $this->site->isPublished()) ?? '';
        $urls = $this->extractUrls($html);
        $broken = [];

        foreach (array_slice($urls, 0, 25) as $url) {
            try {
                $result = $fetcher->fetch($url);
                $status = $result['status'];

                if ($status >= 400) {
                    $broken[] = ['url' => $url, 'status' => $status, 'error' => null];
                }
            } catch (UnsafeUrlException|ConnectionException $exception) {
                $broken[] = ['url' => $url, 'status' => null, 'error' => Str::limit($exception->getMessage(), 180)];
            }
        }

        $this->site->brokenLinks()->delete();

        foreach ($broken as $link) {
            SiteBrokenLink::query()->create(['site_id' => $this->site->id, ...$link]);
        }

        $this->site->forceFill(['last_crawled_at' => now()])->save();
    }

    /**
     * @return list<string>
     */
    protected function extractUrls(string $html): array
    {
        preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
