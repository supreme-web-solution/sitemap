<?php

namespace App\Services\Prospecting;

use App\Models\Lead;
use App\Services\Audit\SafeHttpFetcher;
use App\Services\Audit\UnsafeUrlException;
use App\Services\Audit\WebsiteAuditor;
use Illuminate\Http\Client\ConnectionException;

class WebsiteProfileEnricher
{
    public function __construct(
        protected WebsiteAuditor $auditor,
        protected SafeHttpFetcher $fetcher,
    ) {}

    /**
     * Pull public contact and description hints from the lead's website.
     *
     * @return array{applied: list<string>, skipped: list<string>}
     *
     * @throws UnsafeUrlException
     * @throws ConnectionException
     */
    public function enrich(Lead $lead): array
    {
        if (blank($lead->website)) {
            return ['applied' => [], 'skipped' => ['website']];
        }

        $url = $this->auditor->normalize($lead->website);
        $page = $this->fetcher->fetch($url);
        $html = $page['body'];

        $applied = [];
        $updates = [];

        if (blank($lead->email) && ($email = $this->firstMatch($html, '/mailto:([^\s"\'<>]+)/i'))) {
            $updates['email'] = strtolower($email);
            $applied[] = 'email';
        }

        if (blank($lead->description) && ($description = $this->metaContent($html, 'description'))) {
            $updates['description'] = $description;
            $applied[] = 'description';
        }

        if (blank($lead->phone) && ($phone = $this->firstMatch($html, '/tel:([+\d\s().-]{7,})/i'))) {
            $updates['phone'] = trim($phone);
            $applied[] = 'phone';
        }

        if ($updates !== []) {
            $lead->forceFill($updates)->save();
        }

        return [
            'applied' => $applied,
            'skipped' => array_values(array_diff(['email', 'description', 'phone'], $applied)),
        ];
    }

    protected function metaContent(string $html, string $name): ?string
    {
        $patterns = [
            '/<meta[^>]+name=["\']'.preg_quote($name, '/').'["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']'.preg_quote($name, '/').'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (($value = $this->firstMatch($html, $pattern)) !== null) {
                return trim(html_entity_decode($value));
            }
        }

        return null;
    }

    protected function firstMatch(string $html, string $pattern): ?string
    {
        if (preg_match($pattern, $html, $match) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($match[1] ?? ''));

        return $value === '' ? null : $value;
    }
}
