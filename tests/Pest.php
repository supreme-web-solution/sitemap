<?php

use App\Models\Lead;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param  array<string, mixed>  $content
 * @param  array<string, mixed>  $leadAttributes
 */
function multiPageSite(array $content = [], array $leadAttributes = []): Site
{
    $lead = Lead::factory()->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
        'photo_url' => null,
        'photo_paths' => null,
        ...$leadAttributes,
    ]);

    return Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'about' => "We fix pipes fast and keep the mess to a minimum.\n\nFamily run since day one, serving homes across Austin.",
            'services' => [
                [
                    'title' => 'Emergency Repairs',
                    'description' => 'Fast help when something goes wrong. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'emergency-repairs',
                    'body' => "We answer leaks at any hour.\n\nFast help when something goes wrong. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
                [
                    'title' => 'Drain Cleaning',
                    'description' => 'Clear drains, no mess. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'drain-cleaning',
                    'body' => "Clear drains, no mess.\n\nThe work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
                [
                    'title' => 'Water Heaters',
                    'description' => 'Repair and replacement for hot water. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'water-heaters',
                    'body' => "Repair and replacement for hot water.\n\nThe work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
                [
                    'title' => 'Installations',
                    'description' => 'New fixtures fitted properly. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'installations',
                    'body' => "New fixtures fitted properly.\n\nThe work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
            ],
            ...$content,
        ],
    ]);
}

function previewPage(Site $site, string $path = ''): string
{
    return route('sites.preview', array_filter(['site' => $site->slug, 'path' => $path ?: null, 'internal' => 1]));
}
