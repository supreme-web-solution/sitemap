<?php

namespace App\Services\Simli;

/**
 * Trinity-2 preset faces from Simli's public catalog.
 *
 * @see https://docs.simli.com/api-reference/preset-faces
 */
class SimliFaces
{
    public const DEFAULT = 'bf13113a-355a-4d97-8b90-4edccd1da7b2';

    /**
     * @var list<array{id: string, name: string, gender: string, style: string, image_url: string}>
     */
    protected const CATALOG = [
        ['id' => 'bf13113a-355a-4d97-8b90-4edccd1da7b2', 'name' => 'Anna', 'gender' => 'female', 'style' => 'standard'],
        ['id' => 'f735e62c-dce7-4c0b-8e85-1c7e21774c45', 'name' => 'Sara', 'gender' => 'female', 'style' => 'standard'],
        ['id' => '084479bf-de7c-4df0-8d9c-5484193ecd27', 'name' => 'Mei', 'gender' => 'female', 'style' => 'standard'],
        ['id' => '5a83913c-411b-4afd-8432-b7d685e2aee6', 'name' => 'Claire', 'gender' => 'female', 'style' => 'standard'],
        ['id' => 'a36d66df-a23d-4569-996b-d0e990019857', 'name' => 'Lukas', 'gender' => 'male', 'style' => 'standard'],
        ['id' => '99767950-9d68-4783-a305-9118006d3363', 'name' => 'Mateo', 'gender' => 'male', 'style' => 'standard'],
        ['id' => '3dcb9c78-8822-4e6e-913f-b629c602524a', 'name' => 'Jordan', 'gender' => 'male', 'style' => 'standard'],
        ['id' => 'c060fe44-9b14-43e8-b92c-83a0e230db11', 'name' => 'Kate', 'gender' => 'female', 'style' => 'standard'],
        ['id' => '80459b57-2d66-4ca7-8682-59e1c649bc53', 'name' => 'Elena', 'gender' => 'female', 'style' => 'standard'],
        ['id' => 'a6d08e06-038a-4b32-84ae-b02e0f0f1140', 'name' => 'Zahra', 'gender' => 'female', 'style' => 'standard'],
        ['id' => '9fcd5739-bf66-49a5-b525-d02d7f0bc2fc', 'name' => 'Hank', 'gender' => 'male', 'style' => 'standard'],
        ['id' => '3bad681f-1b4a-4507-bc54-fbc675c191b7', 'name' => 'Victor', 'gender' => 'male', 'style' => 'standard'],
        ['id' => '66d1f5af-9b9b-49e2-a97f-6f5f3d59a2d5', 'name' => 'Tina', 'gender' => 'female', 'style' => 'standard'],
        ['id' => '8b1a04c8-b548-452f-bf7f-04907ea9720f', 'name' => 'Lina', 'gender' => 'female', 'style' => 'standard'],
        ['id' => '03ce3ee8-db97-4150-a333-8f643d48fee9', 'name' => 'Daniel', 'gender' => 'male', 'style' => 'standard'],
        ['id' => '99126e69-c224-404c-9aed-80805c1c9a94', 'name' => 'Sophie', 'gender' => 'female', 'style' => 'standard'],
        ['id' => '9291a1e2-8290-43ab-b5e1-4a5cdf147402', 'name' => 'Doctor', 'gender' => 'male', 'style' => 'standard'],
        ['id' => 'c1cf7312-5b70-454b-a3c2-a0b0b7e32caf', 'name' => 'Fred', 'gender' => 'male', 'style' => 'standard'],
        ['id' => '1d032d8b-0cff-4156-9f0d-f001f0738d9c', 'name' => 'Margaret', 'gender' => 'female', 'style' => 'standard'],
    ];

    /**
     * @return list<array{id: string, name: string, gender: string, style: string, image_url: string}>
     */
    public function all(): array
    {
        return array_map(fn (array $face): array => [
            ...$face,
            'image_url' => $this->imageUrl($face['id']),
        ], self::CATALOG);
    }

    public function exists(string $id): bool
    {
        return collect(self::CATALOG)->contains(fn (array $face) => $face['id'] === $id);
    }

    /**
     * @return array{id: string, name: string, gender: string, style: string, image_url: string}|null
     */
    public function find(string $id): ?array
    {
        $face = collect(self::CATALOG)->firstWhere('id', $id);

        return $face === null ? null : [...$face, 'image_url' => $this->imageUrl($face['id'])];
    }

    /**
     * Prefer a face that matches the still presenter's gender.
     *
     * @return array{id: string, name: string, gender: string, style: string, image_url: string}
     */
    public function forGender(?string $gender): array
    {
        $match = collect($this->all())->first(
            fn (array $face) => $gender === null || $face['gender'] === $gender,
        );

        return $match ?? $this->find(self::DEFAULT);
    }

    protected function imageUrl(string $id): string
    {
        return 'https://mintcdn.com/simli/C8pXSe7OQG-XmRnX/images/trinity2/'.$id.'.jpg';
    }
}
