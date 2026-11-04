<?php

namespace App\Ai\Tools\SiteEdit\Concerns;

use App\Ai\Support\SiteEditExecutorState;
use App\Models\Site;

trait UsesSiteEditExecutorState
{
    public function __construct(
        protected SiteEditExecutorState $state,
        protected Site $site,
    ) {}
}
