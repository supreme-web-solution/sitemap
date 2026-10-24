<?php

namespace App\Http\Controllers\Sites;

use App\Enums\PlanFeature;
use App\Enums\SiteSection;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Jobs\ApplySiteEdit;
use App\Models\Site;
use App\Models\SiteChatMessage;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\SiteBuilder\SiteEditApplier;
use App\Services\SiteBuilder\SiteQualityChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SiteChatController extends Controller
{
    /**
     * Queue an AI edit of the website from a plain-English instruction.
     */
    public function store(Request $request, Team $currentTeam, Site $site, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::AiEditing);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
            'section' => ['nullable', 'string', Rule::enum(SiteSection::class)],
            'fix' => ['nullable', 'string', 'max:40'],
            'field' => ['nullable', 'string', 'max:80', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! SiteEditApplier::isEditablePath((string) $value)) {
                    $fail(__('That part of the website cannot be improved.'));
                }
            }],
        ]);

        $body = $this->instruction($data, $site);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('Describe a change, pick a section to improve, or fix a quality finding.'),
            ]);
        }

        $this->failStale($site);

        if ($site->chatMessages()->where('status', SiteChatMessage::STATUS_PENDING)->exists()) {
            throw ValidationException::withMessages([
                'body' => __('Wait for the current edit to finish.'),
            ]);
        }

        $usage->consume($currentTeam, UsageMetric::AiEdits);

        $site->chatMessages()->create([
            'user_id' => $request->user()->id,
            'role' => SiteChatMessage::ROLE_USER,
            'body' => $body,
            'status' => SiteChatMessage::STATUS_DONE,
        ]);

        $assistant = $site->chatMessages()->create([
            'user_id' => $request->user()->id,
            'role' => SiteChatMessage::ROLE_ASSISTANT,
            'body' => __('Editing the website…'),
            'status' => SiteChatMessage::STATUS_PENDING,
        ]);

        ApplySiteEdit::dispatch($assistant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Editing the website.')]);

        return back();
    }

    /**
     * Treat a stuck pending edit as failed so the chat never stays locked.
     */
    protected function failStale(Site $site): void
    {
        $site->chatMessages()
            ->where('status', SiteChatMessage::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(SiteChatMessage::STALE_AFTER_MINUTES))
            ->update([
                'status' => SiteChatMessage::STATUS_FAILED,
                'body' => __('The edit timed out. Try again.'),
            ]);
    }

    /**
     * @param  array{body?: string|null, section?: string|null, fix?: string|null, field?: string|null}  $data
     */
    protected function instruction(array $data, Site $site): string
    {
        if (filled($data['fix'] ?? null)) {
            return SiteQualityChecker::aiInstruction((string) $data['fix']) ?? '';
        }

        if (filled($data['field'] ?? null)) {
            $path = (string) $data['field'];
            $current = Arr::get(SiteEditApplier::document($site), $path);
            $request = trim((string) ($data['body'] ?? ''));

            return __('Change only this one piece of text on the website, at the content path ":path". Its current wording is: ":current". :request Keep facts already on the site. Do not invent licenses, years in business, or contact details. Leave everything else exactly as it is.', [
                'path' => $path,
                'current' => is_scalar($current) ? Str::limit((string) $current, 400) : '',
                'request' => $request !== '' ? __('What to do: :request.', ['request' => rtrim($request, '.')]) : __('Make it clearer and more persuasive.'),
            ]);
        }
        if (filled($data['section'] ?? null)) {
            $section = SiteSection::from((string) $data['section']);

            return __('Rewrite only the :section section of this website. Keep facts already on the site. Do not invent licenses, years in business, or contact details.', [
                'section' => $section->label(),
            ]);
        }

        return trim((string) ($data['body'] ?? ''));
    }
}
