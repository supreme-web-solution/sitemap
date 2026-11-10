<?php

namespace App\Services\Assistant;

/**
 * OpenAI text-to-speech voices the website assistant can speak with.
 *
 * These are natural neural voices, not the visitor's device voices, so every visitor hears the same person.
 */
class AssistantVoices
{
    public const DEFAULT = 'nova';

    public const OFF = 'off';

    /**
     * @var list<array{id: string, name: string, gender: string|null, blurb: string}>
     */
    protected const CATALOG = [
        ['id' => 'nova', 'name' => 'Nova', 'gender' => 'female', 'blurb' => 'Warm and clear'],
        ['id' => 'alloy', 'name' => 'Alloy', 'gender' => 'female', 'blurb' => 'Neutral and steady'],
        ['id' => 'coral', 'name' => 'Coral', 'gender' => 'female', 'blurb' => 'Bright and friendly'],
        ['id' => 'sage', 'name' => 'Sage', 'gender' => 'female', 'blurb' => 'Calm and professional'],
        ['id' => 'shimmer', 'name' => 'Shimmer', 'gender' => 'female', 'blurb' => 'Soft and expressive'],
        ['id' => 'ash', 'name' => 'Ash', 'gender' => 'male', 'blurb' => 'Clear and natural'],
        ['id' => 'echo', 'name' => 'Echo', 'gender' => 'male', 'blurb' => 'Deep and confident'],
        ['id' => 'onyx', 'name' => 'Onyx', 'gender' => 'male', 'blurb' => 'Strong and warm'],
        ['id' => 'fable', 'name' => 'Fable', 'gender' => 'male', 'blurb' => 'Storyteller tone'],
        ['id' => self::OFF, 'name' => 'No voice', 'gender' => null, 'blurb' => 'Text only'],
    ];

    /**
     * @return list<array{id: string, name: string, gender: string|null, blurb: string}>
     */
    public function all(): array
    {
        return self::CATALOG;
    }

    public function exists(string $id): bool
    {
        return collect(self::CATALOG)->contains(fn (array $voice) => $voice['id'] === $id);
    }

    public function isSpoken(string $id): bool
    {
        return $id !== self::OFF && $this->exists($id);
    }

    /**
     * @return array{id: string, name: string, gender: string|null, blurb: string}|null
     */
    public function find(string $id): ?array
    {
        return collect(self::CATALOG)->firstWhere('id', $id);
    }

    /**
     * The voice that suits a presenter's gender.
     *
     * @return array{id: string, name: string, gender: string|null, blurb: string}
     */
    public function forGender(?string $gender): array
    {
        $preferred = $gender === 'male' ? 'ash' : self::DEFAULT;

        return $this->find($preferred) ?? $this->find(self::DEFAULT);
    }
}
