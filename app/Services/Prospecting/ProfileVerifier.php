<?php

namespace App\Services\Prospecting;

use App\Models\Lead;

class ProfileVerifier
{
    /**
     * Sort a lead's profile into verified, inferred and missing information.
     *
     * Verified facts come from the listing or the owner. Inferred facts are
     * guesses the generator will make, and missing facts will be left out
     * (or kept neutral) rather than invented.
     *
     * @return array{items: list<array{key: string, label: string, status: string, detail: string}>, readiness: int}
     */
    public function verify(Lead $lead): array
    {
        $hasUploadedPhotos = ! empty($lead->photo_paths);

        $items = [
            $this->item('name', 'Business name', 'verified', 'From the Google listing.'),
            $this->item(
                'phone',
                'Phone number',
                filled($lead->phone) ? 'verified' : 'missing',
                filled($lead->phone) ? 'Will be shown as a tap-to-call button.' : 'No call button will be shown.',
            ),
            $this->item(
                'email',
                'Email address',
                filled($lead->email) ? 'verified' : 'missing',
                filled($lead->email) ? 'Used for the claim button.' : 'Add one so visitors can claim the site by email.',
            ),
            $this->item(
                'address',
                'Address',
                filled($lead->address) ? 'verified' : 'missing',
                filled($lead->address) ? 'Shown in the contact section.' : 'No address will be shown.',
            ),
            $this->item(
                'hours',
                'Opening hours',
                ! empty($lead->opening_hours) ? 'verified' : 'missing',
                ! empty($lead->opening_hours) ? 'Shown in the visit section.' : 'The hours section will be left out.',
            ),
            $this->item(
                'services',
                'Services offered',
                filled($lead->category) ? 'inferred' : 'missing',
                filled($lead->category)
                    ? "Typical {$lead->category} services will be suggested. Review them before sharing."
                    : 'Generic wording will be used.',
            ),
            $this->item(
                'description',
                'About the business',
                filled($lead->description) ? 'verified' : 'missing',
                filled($lead->description)
                    ? 'The copy is written from this description.'
                    : 'Copy stays neutral. Add a description to avoid generic wording.',
            ),
            $this->item(
                'logo',
                'Logo',
                $lead->logo_path ? 'verified' : 'missing',
                $lead->logo_path ? 'Uploaded.' : 'A lettermark will be used instead.',
            ),
            $this->item(
                'photos',
                'Photos',
                $hasUploadedPhotos ? 'verified' : ($lead->photo_url ? 'inferred' : 'missing'),
                match (true) {
                    $hasUploadedPhotos => 'Uploaded photos will be used.',
                    (bool) $lead->photo_url => 'Only the listing photo is available. Confirm you may use it.',
                    default => 'The gallery will be left out.',
                },
            ),
        ];

        $score = collect($items)->sum(fn (array $item) => match ($item['status']) {
            'verified' => 1.0,
            'inferred' => 0.5,
            default => 0.0,
        });

        return ['items' => $items, 'readiness' => (int) round($score / count($items) * 100)];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function item(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }
}
