<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use App\Services\Media\SiteImageStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a per-site favicon set and a 1200×630 Open Graph share card.
 *
 * Every generated website gets the same layout pattern: accent-tinted canvas,
 * centered logo (or letter mark), and the business name — so social previews
 * stay on-brand instead of falling back to a lead photo or the app favicon.
 */
class SiteBrandAssets
{
    public const OG_WIDTH = 1200;

    public const OG_HEIGHT = 630;

    /**
     * @var list<string>
     */
    public const KEYS = [
        'favicon_svg',
        'favicon_32',
        'favicon_16',
        'apple_touch_icon',
        'og_image',
    ];

    public function __construct(protected SiteImageStorage $images) {}

    /**
     * Whether the site content already has a complete brand asset set.
     *
     * @param  array<string, mixed>  $content
     */
    public function present(array $content): bool
    {
        $brand = $content['brand'] ?? null;

        if (! is_array($brand)) {
            return false;
        }

        foreach (self::KEYS as $key) {
            if (! is_string($brand[$key] ?? null) || $brand[$key] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Create brand assets when missing; leave an existing complete set alone.
     */
    public function ensure(Site $site): Site
    {
        if ($this->present($site->content ?? [])) {
            return $site;
        }

        return $this->refresh($site);
    }

    /**
     * Rebuild favicons and the OG card from the current logo, name, and accent.
     */
    public function refresh(Site $site): Site
    {
        $site->loadMissing('lead');
        $lead = $site->lead;

        if ($lead === null) {
            return $site;
        }

        $content = $site->content ?? [];
        $previous = is_array($content['brand'] ?? null) ? $content['brand'] : [];
        $accent = $this->normalizeHex((string) ($content['accent'] ?? '#2563eb'));
        $name = trim((string) $lead->name) ?: 'Business';
        $initials = $this->initials($name);
        $subtitle = $this->subtitle($lead->category, $lead->city);
        $logoBinary = $this->logoBinary($lead->logo_path);

        $brand = [
            'favicon_svg' => $this->store($this->svgFavicon($initials, $accent), $site, 'svg'),
            'favicon_32' => $this->store($this->pngMark(32, $initials, $accent, $logoBinary), $site, 'png'),
            'favicon_16' => $this->store($this->pngMark(16, $initials, $accent, $logoBinary), $site, 'png'),
            'apple_touch_icon' => $this->store($this->pngMark(180, $initials, $accent, $logoBinary), $site, 'png'),
            'og_image' => $this->store($this->ogCard($name, $subtitle, $initials, $accent, $logoBinary), $site, 'png'),
        ];

        foreach (self::KEYS as $key) {
            $old = is_string($previous[$key] ?? null) ? $previous[$key] : null;

            if ($old !== null && $old !== ($brand[$key] ?? null)) {
                $this->images->forgetReplaced($old);
            }
        }

        $content['brand'] = $brand;
        $site->content = $content;
        $site->save();

        return $site;
    }

    /**
     * Public URLs for the favicon / OG tags in the site layout.
     *
     * @param  array<string, mixed>  $content
     * @return array{
     *     favicon_svg: string|null,
     *     favicon_32: string|null,
     *     favicon_16: string|null,
     *     apple_touch_icon: string|null,
     *     og_image: string|null,
     * }
     */
    public function urls(array $content): array
    {
        $brand = is_array($content['brand'] ?? null) ? $content['brand'] : [];

        return [
            'favicon_svg' => $this->absoluteUrl($this->images->url($brand['favicon_svg'] ?? null)),
            'favicon_32' => $this->absoluteUrl($this->images->url($brand['favicon_32'] ?? null)),
            'favicon_16' => $this->absoluteUrl($this->images->url($brand['favicon_16'] ?? null)),
            'apple_touch_icon' => $this->absoluteUrl($this->images->url($brand['apple_touch_icon'] ?? null)),
            'og_image' => $this->absoluteUrl($this->images->url($brand['og_image'] ?? null)),
        ];
    }

    public function absoluteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    protected function store(string $binary, Site $site, string $extension): string
    {
        return $this->images->storeBinary($binary, (int) $site->team_id, (int) $site->lead_id, $extension)['path'];
    }

    protected function logoBinary(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $url = $this->images->url($path);

        if ($url === null) {
            return null;
        }

        try {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || $this->images->isCloudinary($path)) {
                $binary = @file_get_contents($url);

                return is_string($binary) && $binary !== '' ? $binary : null;
            }

            $absolute = $this->absoluteUrl($url);

            if ($absolute !== null && str_starts_with($absolute, 'http')) {
                $local = public_path(ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/'));

                if (is_file($local)) {
                    $binary = @file_get_contents($local);

                    return is_string($binary) && $binary !== '' ? $binary : null;
                }
            }

            $diskPath = str_starts_with($path, 'teams/') ? $path : null;

            if ($diskPath !== null && Storage::disk('public')->exists($diskPath)) {
                $binary = Storage::disk('public')->get($diskPath);

                return is_string($binary) && $binary !== '' ? $binary : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    protected function svgFavicon(string $initials, string $accent): string
    {
        $onAccent = $this->onAccent($accent);
        $label = htmlspecialchars($initials !== '' ? $initials : '•', ENT_QUOTES | ENT_XML1, 'UTF-8');
        $fontSize = strlen($initials) >= 3 ? 38 : 46;

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img">
          <rect width="64" height="64" rx="14" fill="{$accent}"/>
          <text x="32" y="34" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, Helvetica, sans-serif" font-size="{$fontSize}" font-weight="700" fill="{$onAccent}">{$label}</text>
        </svg>
        SVG;
    }

    protected function pngMark(int $size, string $initials, string $accent, ?string $logoBinary): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        [$r, $g, $b] = $this->hexToRgb($accent);
        $fill = imagecolorallocate($image, $r, $g, $b);
        $radius = (int) max(2, round($size * 0.22));
        $this->filledRoundedRect($image, 0, 0, $size - 1, $size - 1, $radius, $fill);

        if ($logoBinary !== null && $this->paintLogo($image, $logoBinary, (int) round($size * 0.18), (int) round($size * 0.18), (int) round($size * 0.64), (int) round($size * 0.64))) {
            return $this->encodePng($image);
        }

        [$tr, $tg, $tb] = $this->hexToRgb($this->onAccent($accent));
        $textColor = imagecolorallocate($image, $tr, $tg, $tb);
        $this->drawCenteredText($image, $initials !== '' ? $initials : '•', $textColor, (int) max(8, round($size * 0.42)));

        return $this->encodePng($image);
    }

    protected function ogCard(string $name, string $subtitle, string $initials, string $accent, ?string $logoBinary): string
    {
        $width = self::OG_WIDTH;
        $height = self::OG_HEIGHT;
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);

        [$ar, $ag, $ab] = $this->hexToRgb($accent);
        [$lr, $lg, $lb] = $this->mixRgb([248, 250, 252], [$ar, $ag, $ab], 0.12);
        [$dr, $dg, $db] = $this->mixRgb([15, 23, 42], [$ar, $ag, $ab], 0.35);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $image,
                (int) round($lr + ($dr - $lr) * $t * 0.22),
                (int) round($lg + ($dg - $lg) * $t * 0.22),
                (int) round($lb + ($db - $lb) * $t * 0.22),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        $panel = imagecolorallocatealpha($image, 255, 255, 255, 18);
        $this->filledRoundedRect($image, 72, 72, $width - 73, $height - 73, 36, $panel);

        $accentSoft = imagecolorallocatealpha($image, $ar, $ag, $ab, 96);
        imagefilledellipse($image, 180, 140, 280, 280, $accentSoft);
        imagefilledellipse($image, $width - 160, $height - 120, 340, 340, $accentSoft);

        $markSize = 168;
        $markX = (int) (($width - $markSize) / 2);
        $markY = 150;
        $mark = imagecreatetruecolor($markSize, $markSize);
        imagesavealpha($mark, true);
        $clear = imagecolorallocatealpha($mark, 0, 0, 0, 127);
        imagefill($mark, 0, 0, $clear);
        $markFill = imagecolorallocate($mark, $ar, $ag, $ab);
        $this->filledRoundedRect($mark, 0, 0, $markSize - 1, $markSize - 1, 36, $markFill);

        $usedLogo = $logoBinary !== null
            && $this->paintLogo($mark, $logoBinary, 28, 28, $markSize - 56, $markSize - 56);

        if (! $usedLogo) {
            [$tr, $tg, $tb] = $this->hexToRgb($this->onAccent($accent));
            $markText = imagecolorallocate($mark, $tr, $tg, $tb);
            $this->drawCenteredText($mark, $initials !== '' ? $initials : '•', $markText, 64);
        }

        imagecopy($image, $mark, $markX, $markY, 0, 0, $markSize, $markSize);
        imagedestroy($mark);

        $ink = imagecolorallocate($image, 15, 23, 42);
        $muted = imagecolorallocate($image, 71, 85, 105);
        $this->drawCenteredText($image, $name, $ink, 54, $markY + $markSize + 48);
        if ($subtitle !== '') {
            $this->drawCenteredText($image, $subtitle, $muted, 28, $markY + $markSize + 108);
        }

        $bar = imagecolorallocate($image, $ar, $ag, $ab);
        imagefilledrectangle($image, (int) (($width - 96) / 2), $height - 78, (int) (($width + 96) / 2), $height - 70, $bar);

        return $this->encodePng($image);
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function paintLogo($canvas, string $logoBinary, int $x, int $y, int $boxW, int $boxH): bool
    {
        $logo = @imagecreatefromstring($logoBinary);

        if ($logo === false) {
            return false;
        }

        $lw = imagesx($logo);
        $lh = imagesy($logo);

        if ($lw < 1 || $lh < 1) {
            imagedestroy($logo);

            return false;
        }

        $scale = min($boxW / $lw, $boxH / $lh);
        $dw = max(1, (int) round($lw * $scale));
        $dh = max(1, (int) round($lh * $scale));
        $dx = $x + (int) (($boxW - $dw) / 2);
        $dy = $y + (int) (($boxH - $dh) / 2);

        imagecopyresampled($canvas, $logo, $dx, $dy, 0, 0, $dw, $dh, $lw, $lh);
        imagedestroy($logo);

        return true;
    }

    /**
     * @param  \GdImage  $image
     */
    protected function drawCenteredText($image, string $text, int $color, int $size, ?int $baselineY = null): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $font = $this->fontPath($size >= 40);

        if ($font !== null) {
            $box = imagettfbbox($size, 0, $font, $text);
            $textWidth = abs($box[2] - $box[0]);
            $textHeight = abs($box[7] - $box[1]);
            $x = (int) (($width - $textWidth) / 2);
            $y = $baselineY ?? (int) (($height + $textHeight) / 2);
            imagettftext($image, $size, 0, $x, $y, $color, $font, $text);

            return;
        }

        $fontNumber = 5;
        $textWidth = imagefontwidth($fontNumber) * strlen($text);
        $textHeight = imagefontheight($fontNumber);
        $x = (int) (($width - $textWidth) / 2);
        $y = $baselineY !== null
            ? max(0, $baselineY - $textHeight)
            : (int) (($height - $textHeight) / 2);
        imagestring($image, $fontNumber, $x, $y, $text, $color);
    }

    protected function fontPath(bool $bold = false): ?string
    {
        $candidates = $bold
            ? [
                resource_path('fonts/DejaVuSans-Bold.ttf'),
                'C:/Windows/Fonts/arialbd.ttf',
                'C:/Windows/Fonts/segoeuib.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            ]
            : [
                resource_path('fonts/DejaVuSans.ttf'),
                'C:/Windows/Fonts/arial.ttf',
                'C:/Windows/Fonts/segoeui.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/TTF/DejaVuSans.ttf',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  \GdImage  $image
     */
    protected function filledRoundedRect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        $radius = max(0, min($radius, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * @param  \GdImage  $image
     */
    protected function encodePng($image): string
    {
        ob_start();
        imagepng($image, null, 6);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    protected function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = [];

        foreach ($words as $word) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', $word) ?? '';

            if ($clean === '') {
                continue;
            }

            $letters[] = Str::upper(Str::substr($clean, 0, 1));

            if (count($letters) >= 2) {
                break;
            }
        }

        if ($letters === []) {
            return 'B';
        }

        return implode('', $letters);
    }

    protected function subtitle(?string $category, ?string $city): string
    {
        $parts = array_values(array_filter([
            filled($category) ? trim((string) $category) : null,
            filled($city) ? trim((string) $city) : null,
        ]));

        return implode(' · ', $parts);
    }

    protected function normalizeHex(string $hex): string
    {
        $hex = strtoupper(ltrim(trim($hex), '#'));

        if (preg_match('/^[0-9A-F]{6}$/', $hex) !== 1) {
            return '#2563EB';
        }

        return '#'.$hex;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($this->normalizeHex($hex), '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected function onAccent(string $hex): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.62 ? '#0F172A' : '#FFFFFF';
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     * @return array{0: int, 1: int, 2: int}
     */
    protected function mixRgb(array $from, array $to, float $amount): array
    {
        $amount = max(0, min(1, $amount));

        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $amount),
            (int) round($from[1] + ($to[1] - $from[1]) * $amount),
            (int) round($from[2] + ($to[2] - $from[2]) * $amount),
        ];
    }
}
