<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;
use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Deterministic homepage structure changes from plain-English chat (add/remove sections).
 *
 * Runs on every AI edit so structural intent is not lost when the copy planner succeeds but
 * returns an unchanged plan.
 */
class SiteStructuralIntent
{
    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function apply(Lead $lead, array $content, string $instruction): array
    {
        $before = array_values($content['sections'] ?? SiteSection::defaultOrder());
        $sections = $before;
        $mentioned = SiteChatInstruction::mentionedSections($instruction);

        SiteEditLog::info('structural intent', [
            'lead_id' => $lead->id,
            'instruction' => $instruction,
            'mentioned_sections' => $mentioned,
            'wants_add' => self::wantsAddSection($instruction),
            'wants_remove' => self::wantsRemoveSection($instruction),
            'sections_before' => $before,
        ]);

        if (self::wantsAddSection($instruction)) {
            foreach ($mentioned as $key) {
                $sections = self::insertSection($sections, $key);
                $content = SiteContentSchema::showSection($content, $key);
            }
        }

        if (self::wantsRemoveSection($instruction)) {
            foreach ($mentioned as $key) {
                $sections = array_values(array_filter($sections, fn (string $section) => $section !== $key));
                $content = SiteContentSchema::hideSection($content, $key);
            }
        }

        $content['sections'] = $sections;

        if ($sections !== $before) {
            SiteEditLog::info('structural sections updated', [
                'lead_id' => $lead->id,
                'sections_after' => $sections,
            ]);
        }

        return $content;
    }

    public static function wantsAddSection(string $instruction): bool
    {
        $lower = Str::lower($instruction);

        if (preg_match('/\b(add|include|show|enable|turn on|insert|put|create|build|need|want)\b/i', $instruction) !== 1) {
            return false;
        }

        if (preg_match('/\b(section|block|area|homepage|home page|landing page|site)\b/i', $instruction) !== 1
            && SiteChatInstruction::mentionedSections($instruction) === []) {
            return false;
        }

        if (preg_match('/\b(remove|hide|disable|delete|drop|without)\b/i', $lower) === 1) {
            return false;
        }

        return SiteChatInstruction::mentionedSections($instruction) !== [];
    }

    public static function wantsRemoveSection(string $instruction): bool
    {
        if (preg_match('/\b(remove|hide|disable|turn off|drop|delete|strip)\b/i', $instruction) !== 1) {
            return false;
        }

        return SiteChatInstruction::mentionedSections($instruction) !== [];
    }

    /**
     * @param  list<string>  $sections
     * @return list<string>
     */
    public static function insertSection(array $sections, string $key): array
    {
        if (in_array($key, $sections, true)) {
            return $sections;
        }

        if (CustomHomeSection::isKey($key)) {
            $contactIndex = array_search(SiteSection::Contact->value, $sections, true);
            $insertAt = $contactIndex === false ? count($sections) : $contactIndex;
            array_splice($sections, $insertAt, 0, [$key]);

            return array_values($sections);
        }

        $order = SiteSection::defaultOrder();
        $targetIndex = array_search($key, $order, true);

        if ($targetIndex === false) {
            $sections[] = $key;

            return array_values($sections);
        }

        $insertAt = count($sections);

        foreach ($sections as $index => $section) {
            $sectionIndex = array_search($section, $order, true);

            if ($sectionIndex !== false && $sectionIndex < $targetIndex) {
                $insertAt = $index + 1;
            }
        }

        array_splice($sections, $insertAt, 0, [$key]);

        return array_values($sections);
    }
}
