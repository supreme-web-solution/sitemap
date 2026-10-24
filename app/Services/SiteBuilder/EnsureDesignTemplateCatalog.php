<?php

namespace App\Services\SiteBuilder;

use Database\Seeders\SiteDesignTemplateSeeder;

class EnsureDesignTemplateCatalog
{
    /**
     * Create starter catalog rows once per empty database.
     */
    public function ensure(): void
    {
        (new SiteDesignTemplateSeeder)->run();
    }
}
