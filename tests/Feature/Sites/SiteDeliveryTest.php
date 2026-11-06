<?php

use App\Jobs\BackupPublishedSite;
use App\Jobs\CheckSiteUptime;
use App\Jobs\CrawlSiteLinks;
use App\Jobs\TransferSiteOwnership;
use App\Mail\SiteTransferEmail;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use ZipArchive;

test('health checks are queued for a site', function () {
    Queue::fake([CrawlSiteLinks::class, CheckSiteUptime::class]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.health.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertRedirect();

    Queue::assertPushed(CrawlSiteLinks::class);
    Queue::assertPushed(CheckSiteUptime::class);
});

test('ownership transfer is queued', function () {
    Queue::fake([TransferSiteOwnership::class]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.transfer.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'email' => 'client@example.com',
        ])
        ->assertRedirect();

    Queue::assertPushed(TransferSiteOwnership::class, fn (TransferSiteOwnership $job) => $job->email === 'client@example.com');
});

test('a transferred website emails a public download link', function () {
    Mail::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['name' => 'Blue Dragon Plumbing']);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'published_at' => now(),
    ]);

    (new TransferSiteOwnership($site, 'client@example.com'))->handle();

    $site->refresh();

    expect($site->published_at)->toBeNull()
        ->and($site->transferred_to_email)->toBe('client@example.com');

    $mail = null;

    Mail::assertQueued(SiteTransferEmail::class, function (SiteTransferEmail $queued) use (&$mail) {
        $mail = $queued;

        return $queued->hasTo('client@example.com');
    });

    expect($mail)->not->toBeNull()
        ->and($mail->downloadUrl)->toContain('signature=');

    $mail->assertSeeInHtml('Your website is ready')
        ->assertSeeInHtml('Blue Dragon Plumbing')
        ->assertSeeInHtml('Download your website')
        ->assertSeeInText('no login needed');

    $response = $this->get($mail->downloadUrl);

    $response->assertOk()->assertDownload('blue-dragon-plumbing-website.zip');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $html = $zip->getFromName('index.html');
    $zip->close();

    expect($html)->toContain('Blue Dragon Plumbing');

    $this->get(route('sites.handoff', $site))->assertForbidden();

    $expired = URL::temporarySignedRoute('sites.handoff', now()->subMinute(), ['site' => $site]);

    $this->get($expired)->assertForbidden();
});

test('published sites are backed up on a schedule', function () {
    Queue::fake([BackupPublishedSite::class]);

    Site::factory()->create(['published_at' => now()]);
    Site::factory()->create(['published_at' => null]);

    $this->artisan('sites:backup')->assertSuccessful();

    Queue::assertPushed(BackupPublishedSite::class, 1);
});
