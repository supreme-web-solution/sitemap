<?php

namespace App\Ai\Support;

use App\Data\SiteEditPlan;

/**
 * Mutable edit plan built by {@see SiteEditExecutorAgent} tool calls.
 */
final class SiteEditExecutorState
{
    /** @var list<array<string, mixed>> */
    public array $documentOps = [];

    /** @var list<string> */
    public array $sectionsAdd = [];

    /** @var list<string> */
    public array $sectionsRemove = [];

    /** @var array<string, string> */
    public array $layoutVariants = [];

    public ?string $copyInstruction = null;

    /** @var list<string> */
    public array $designCssTargets = [];

    public string $designInstruction = '';

    public string $reasoning = '';

    public bool $finished = false;

    public function hasChanges(): bool
    {
        return $this->documentOps !== []
            || $this->sectionsAdd !== []
            || $this->sectionsRemove !== []
            || $this->layoutVariants !== []
            || filled($this->copyInstruction)
            || $this->designCssTargets !== [];
    }

    public function toPlan(bool $fromLlm = true): SiteEditPlan
    {
        $plan = new SiteEditPlan(
            reasoning: $this->reasoning,
            copyInstruction: $this->copyInstruction,
            designCssTargets: array_values(array_unique($this->designCssTargets)),
            designInstruction: $this->designInstruction,
            sectionsAdd: array_values(array_unique($this->sectionsAdd)),
            sectionsRemove: array_values(array_unique($this->sectionsRemove)),
            layoutVariants: $this->layoutVariants,
            documentOps: $this->documentOps,
            fromLlm: $fromLlm,
        );

        return $plan->withoutRedundantTargetsAfterOps();
    }
}
