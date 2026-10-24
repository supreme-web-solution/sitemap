<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\SiteBuilder\SiteCache;
use App\Services\SiteBuilder\SiteLocales;
use App\Services\SiteBuilder\SiteLocaleTranslator;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranslateSiteLocale implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public function __construct(public Site $site, public string $locale)
    {
        $this->onQueue(Queues::AI);
    }

    public function handle(SiteLocaleTranslator $translator): void
    {
        if (! SiteLocales::isSupported($this->locale) || $this->locale === SiteLocales::default($this->site->content)) {
            return;
        }

        $content = $this->site->content;
        $locales = SiteLocales::extra($content);

        if (! in_array($this->locale, $locales, true)) {
            $locales[] = $this->locale;
            $content['locales'] = $locales;
        }

        $content['translations'][$this->locale] = $translator->translate($this->site, $this->locale);

        $this->site->forceFill(['content' => $content])->save();
        SiteCache::flush($this->site->id);
    }
}
