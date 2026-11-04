<?php

namespace App\Data;

use App\Services\SiteBuilder\SiteEditOpCatalog;

/**
 * LLM-authored plan for one site chat edit.
 */
final readonly class SiteEditPlan
{
    /**
     * @param  list<string>  $designCssTargets
     * @param  list<string>  $sectionsAdd
     * @param  list<string>  $sectionsRemove
     * @param  array<string, string>  $layoutVariants
     * @param  list<array<string, mixed>>  $documentOps
     */
    public function __construct(
        public string $reasoning,
        public ?string $copyInstruction,
        public array $designCssTargets,
        public string $designInstruction,
        public array $sectionsAdd,
        public array $sectionsRemove,
        public array $layoutVariants,
        public array $documentOps = [],
        public bool $fromLlm = true,
        public bool $aiProviderUnavailable = false,
        public ?string $aiProviderMessage = null,
    ) {}

    public function withAiBlock(string $message): self
    {
        return new self(
            reasoning: $this->reasoning,
            copyInstruction: $this->copyInstruction,
            designCssTargets: $this->designCssTargets,
            designInstruction: $this->designInstruction,
            sectionsAdd: $this->sectionsAdd,
            sectionsRemove: $this->sectionsRemove,
            layoutVariants: $this->layoutVariants,
            documentOps: $this->documentOps,
            fromLlm: $this->fromLlm,
            aiProviderUnavailable: true,
            aiProviderMessage: $message,
        );
    }

    public function wantsDesignCss(): bool
    {
        return $this->designCssTargets !== [];
    }

    public function wantsCopyRevise(): bool
    {
        return filled($this->copyInstruction);
    }

    public function wantsDocumentOps(): bool
    {
        return $this->documentOps !== [];
    }

    /**
     * Skip redundant CSS / layout steps when document_ops already set those fields.
     */
    public function withoutRedundantTargetsAfterOps(): self
    {
        $cssCovered = SiteEditOpCatalog::sectionCssTargetsFromOps($this->documentOps);
        $variantCovered = SiteEditOpCatalog::variantSectionsFromOps($this->documentOps);

        if ($cssCovered === [] && $variantCovered === []) {
            return $this;
        }

        $variants = $this->layoutVariants;

        foreach ($variantCovered as $section) {
            unset($variants[$section]);
        }

        return new self(
            reasoning: $this->reasoning,
            copyInstruction: $this->copyInstruction,
            designCssTargets: array_values(array_diff($this->designCssTargets, $cssCovered)),
            designInstruction: $this->designInstruction,
            sectionsAdd: $this->sectionsAdd,
            sectionsRemove: $this->sectionsRemove,
            layoutVariants: $variants,
            documentOps: $this->documentOps,
            fromLlm: $this->fromLlm,
            aiProviderUnavailable: $this->aiProviderUnavailable,
            aiProviderMessage: $this->aiProviderMessage,
        );
    }
}
