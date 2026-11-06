<?php

use App\Services\Media\SiteImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('public');
    config([
        'services.cloudinary.url' => null,
        'services.cloudinary.cloud_name' => null,
        'services.cloudinary.key' => null,
        'services.cloudinary.secret' => null,
    ]);
});

test('without cloudinary credentials images stay on the public disk', function () {
    $storage = app(SiteImageStorage::class);
    $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

    $stored = $storage->storeUploadedFile($file, 7, 42);

    expect($storage->configured())->toBeFalse()
        ->and($stored['path'])->toStartWith('teams/7/leads/42/')
        ->and($storage->isCloudinary($stored['path']))->toBeFalse();

    Storage::disk('public')->assertExists($stored['path']);
    expect($storage->url($stored['path']))->toContain('/storage/teams/7/leads/42/');
});

test('cloudinary paths resolve to auto-format delivery urls', function () {
    config([
        'services.cloudinary.cloud_name' => 'demo-cloud',
        'services.cloudinary.key' => 'key',
        'services.cloudinary.secret' => 'secret',
    ]);

    $storage = app(SiteImageStorage::class);

    expect($storage->configured())->toBeTrue()
        ->and($storage->url('cloudinary:mapmatic/teams/1/leads/2/photo'))
        ->toBe('https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_2000/mapmatic/teams/1/leads/2/photo');
});

test('local binary storage still works as a fallback', function () {
    $storage = app(SiteImageStorage::class);

    $stored = $storage->storeBinary('fake-bytes', 3, 9, 'png');

    expect($stored['path'])->toStartWith('teams/3/leads/9/ai-')
        ->and($stored['path'])->toEndWith('.png');

    Storage::disk('public')->assertExists($stored['path']);
});
