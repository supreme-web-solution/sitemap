<?php

namespace App\Services\Assistant;

/**
 * The illustrated presenters a website assistant can use.
 *
 * Each avatar is a small SVG whose mouth (`#sm-mouth`) the widget animates while the assistant speaks.
 */
class AssistantAvatars
{
    public const DEFAULT = 'maya';

    /**
     * @var array<string, array{name: string, gender: string, skin: string, hair: string, style: string, shirt: string, background: string}>
     */
    protected const CATALOG = [
        'maya' => ['name' => 'Maya', 'gender' => 'female', 'skin' => '#f1c7a5', 'hair' => '#3b2417', 'style' => 'long', 'shirt' => '#2f6fed', 'background' => '#e0ecff'],
        'priya' => ['name' => 'Priya', 'gender' => 'female', 'skin' => '#b9794f', 'hair' => '#1b1210', 'style' => 'bun', 'shirt' => '#d9467a', 'background' => '#fde4ee'],
        'sofia' => ['name' => 'Sofia', 'gender' => 'female', 'skin' => '#e8b68d', 'hair' => '#b5651d', 'style' => 'bob', 'shirt' => '#1f9d78', 'background' => '#dcf5ec'],
        'alex' => ['name' => 'Alex', 'gender' => 'male', 'skin' => '#f0c4a0', 'hair' => '#2a2a2e', 'style' => 'short', 'shirt' => '#334155', 'background' => '#e6e9ef'],
        'marcus' => ['name' => 'Marcus', 'gender' => 'male', 'skin' => '#7b4a2d', 'hair' => '#0f0f10', 'style' => 'fade', 'shirt' => '#f59e0b', 'background' => '#fdf0d4'],
        'daniel' => ['name' => 'Daniel', 'gender' => 'male', 'skin' => '#d9a77f', 'hair' => '#6b3f1d', 'style' => 'wavy', 'shirt' => '#7c3aed', 'background' => '#ece4fd'],
    ];

    /**
     * Get the avatars to choose from.
     *
     * @return list<array{key: string, name: string, gender: string}>
     */
    public function all(): array
    {
        $avatars = [];

        foreach (self::CATALOG as $key => $avatar) {
            $avatars[] = ['key' => $key, 'name' => $avatar['name'], 'gender' => $avatar['gender']];
        }

        return $avatars;
    }

    public function exists(string $key): bool
    {
        return isset(self::CATALOG[$key]);
    }

    /**
     * The voice that suits the avatar: female or male.
     */
    public function genderOf(string $key): string
    {
        return self::CATALOG[$key]['gender'] ?? 'female';
    }

    public function nameOf(string $key): string
    {
        return self::CATALOG[$key]['name'] ?? self::CATALOG[self::DEFAULT]['name'];
    }

    /**
     * Render the avatar as an SVG document, or null when the key is unknown.
     */
    public function svg(string $key): ?string
    {
        $avatar = self::CATALOG[$key] ?? null;

        if ($avatar === null) {
            return null;
        }

        [$behind, $front] = $this->hair($avatar['style'], $avatar['hair']);
        $skinShade = '#00000012';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img" aria-label="{$avatar['name']}">
<rect width="200" height="200" fill="{$avatar['background']}"/>
{$behind}
<path d="M28 200 C28 162 68 150 100 150 C132 150 172 162 172 200 Z" fill="{$avatar['shirt']}"/>
<rect x="87" y="120" width="26" height="36" rx="10" fill="{$avatar['skin']}"/>
<rect x="87" y="120" width="26" height="14" rx="6" fill="{$skinShade}"/>
<ellipse cx="62" cy="92" rx="6" ry="9" fill="{$avatar['skin']}"/>
<ellipse cx="138" cy="92" rx="6" ry="9" fill="{$avatar['skin']}"/>
<ellipse cx="100" cy="90" rx="38" ry="44" fill="{$avatar['skin']}"/>
{$front}
<g class="sm-eyes" fill="#1f2937">
<ellipse class="sm-eye" cx="85" cy="88" rx="4" ry="5"/>
<ellipse class="sm-eye" cx="115" cy="88" rx="4" ry="5"/>
</g>
<path d="M77 78 Q85 73 93 77 M107 77 Q115 73 123 78" stroke="{$avatar['hair']}" stroke-width="3" stroke-linecap="round" fill="none"/>
<path d="M100 92 Q97 102 101 104" stroke="#00000030" stroke-width="2" stroke-linecap="round" fill="none"/>
<ellipse cx="76" cy="104" rx="7" ry="4" fill="#ff7a7a" opacity=".18"/>
<ellipse cx="124" cy="104" rx="7" ry="4" fill="#ff7a7a" opacity=".18"/>
<ellipse id="sm-mouth" cx="100" cy="116" rx="10" ry="2.5" fill="#7a2e2e"/>
</svg>
SVG;
    }

    /**
     * Hair drawn behind the head and in front of it.
     *
     * @return array{0: string, 1: string}
     */
    protected function hair(string $style, string $color): array
    {
        $fringe = "<path d=\"M60 80 C62 48 138 48 140 80 C128 62 84 58 60 80Z\" fill=\"{$color}\"/>";

        return match ($style) {
            'long' => [
                "<path d=\"M52 92 C52 38 148 38 148 92 L154 164 L46 164 Z\" fill=\"{$color}\"/>",
                $fringe,
            ],
            'bob' => [
                "<path d=\"M54 92 C54 40 146 40 146 92 L148 122 L52 122 Z\" fill=\"{$color}\"/>",
                $fringe,
            ],
            'bun' => [
                "<circle cx=\"100\" cy=\"38\" r=\"15\" fill=\"{$color}\"/>",
                "<path d=\"M58 84 C58 44 142 44 142 84 C128 62 72 62 58 84Z\" fill=\"{$color}\"/>",
            ],
            'fade' => [
                '',
                "<path d=\"M60 76 C62 46 138 46 140 76 C128 58 72 58 60 76Z\" fill=\"{$color}\"/>",
            ],
            'wavy' => [
                '',
                "<path d=\"M56 90 C46 42 154 42 144 90 C138 62 62 62 56 90Z\" fill=\"{$color}\"/>",
            ],
            default => [
                '',
                "<path d=\"M58 82 C56 40 144 40 142 82 C130 60 70 58 58 82Z\" fill=\"{$color}\"/>",
            ],
        };
    }
}
