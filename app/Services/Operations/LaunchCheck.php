<?php

namespace App\Services\Operations;

/**
 * One row in a launch-readiness report.
 */
readonly class LaunchCheck
{
    public function __construct(
        public string $id,
        public string $category,
        public string $status,
        public string $title,
        public string $message,
        public ?string $hint = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'status' => $this->status,
            'title' => $this->title,
            'message' => $this->message,
            'hint' => $this->hint,
        ];
    }
}
