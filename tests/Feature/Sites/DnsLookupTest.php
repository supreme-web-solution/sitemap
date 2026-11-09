<?php

use App\Services\Domains\DnsLookup;
use Illuminate\Support\Facades\Http;

test('dns lookup prefers cloudflare doh for cname checks', function () {
    Http::fake([
        'cloudflare-dns.com/*' => Http::response([
            'Answer' => [
                ['name' => 'test.client.com', 'type' => 5, 'data' => 'sites.mapmaticapp.com.'],
            ],
        ], 200),
    ]);

    expect(app(DnsLookup::class)->cname('test.client.com'))
        ->toBe(['sites.mapmaticapp.com']);
});

test('dns lookup returns empty cname list when doh has no answer', function () {
    Http::fake([
        'cloudflare-dns.com/*' => Http::response([
            'Status' => 0,
            'Answer' => null,
        ], 200),
    ]);

    expect(app(DnsLookup::class)->cname('missing.client.com'))->toBe([]);
});
