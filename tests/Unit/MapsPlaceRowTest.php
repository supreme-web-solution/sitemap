<?php

use App\Services\LeadFinder\MapsPlaceRow;

test('maps place rows accept emails as a list, string, or export-style keys', function () {
    $fromArray = MapsPlaceRow::from([
        'placeId' => 'ChIJ-test',
        'businessName' => 'Aloha Dental',
        'emails' => ['riverside@aloha-dental.com'],
    ])?->toListing();

    expect($fromArray?->email)->toBe('riverside@aloha-dental.com');

    $fromString = MapsPlaceRow::from([
        'Place ID' => 'ChIJ-export',
        'Business Name' => 'Galaxia Dental',
        'Emails' => 'info@galaxiadentalaustin.com',
    ])?->toListing();

    expect($fromString?->email)->toBe('info@galaxiadentalaustin.com');
});

test('maps place rows capture extra phones and social links', function () {
    $listing = MapsPlaceRow::from([
        'placeId' => 'ChIJ-swish',
        'businessName' => 'Swish Dental',
        'Additional Phones' => '5126476045; 5122962841',
        'Socials' => '{"instagram":["https://www.instagram.com/swishdental"],"facebook":["https://www.facebook.com/SwishDentalTX"],"youtube":null}',
    ])?->toListing();

    expect($listing?->additionalPhones)->toBe(['5126476045', '5122962841'])
        ->and($listing?->socialLinks)->toMatchArray([
            'instagram' => 'https://www.instagram.com/swishdental',
            'facebook' => 'https://www.facebook.com/SwishDentalTX',
        ]);
});

test('maps place rows pull offerings, owner posts, photos, and location extras from export json', function () {
    $listing = MapsPlaceRow::from([
        'Place ID' => 'ChIJMbq0xFG0RIYRh21ETz1YA1E',
        'Business Name' => 'Aloha Dental',
        'Category' => 'Dental clinic',
        'All Categories' => 'Dental clinic; Cosmetic dentist; Emergency dental service',
        'Neighborhood' => 'Riverside',
        'Located In' => 'AMLI South Shore',
        'Cover Photo' => 'https://example.test/cover.jpg',
        'Photos' => '[{"url":"https://example.test/gallery.jpg","title":"Office"}]',
        'Attributes' => '{"Offerings":["Cosmetic dentistry","Emergency services","Pediatric care"]}',
        'Owner Posts' => '[{"text":"We offer gentle family dentistry with same-day emergency visits when possible.","date":"2026-08-25T18:48:03.000Z","link":null}]',
    ])?->toListing();

    expect($listing?->services)->toBe([
        'Cosmetic dentistry',
        'Emergency services',
        'Pediatric care',
        'Cosmetic dentist',
        'Emergency dental service',
    ])
        ->and($listing?->description)->toContain('gentle family dentistry')
        ->and($listing?->neighborhood)->toBe('Riverside')
        ->and($listing?->locatedIn)->toBe('AMLI South Shore')
        ->and($listing?->photoUrls)->toBe([
            'https://example.test/cover.jpg',
            'https://example.test/gallery.jpg',
        ]);
});
