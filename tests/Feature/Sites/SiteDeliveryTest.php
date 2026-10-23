<?php

use App\Jobs\BackupPublishedSite;
use App\Jobs\CheckSiteUptime;
use App\Jobs\CrawlSiteLinks;
use App\Jobs\TransferSiteOwnership;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

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

test('published sites are backed up on a schedule', function () {
    Queue::fake([BackupPublishedSite::class]);

    Site::factory()->create(['published_at' => now()]);
    Site::factory()->create(['published_at' => null]);

    $this->artisan('sites:backup')->assertSuccessful();

    Queue::assertPushed(BackupPublishedSite::class, 1);
});
