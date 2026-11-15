<?php

namespace Ernestdefoe\Nameplate;

use Ernestdefoe\Nameplate\Likes\LikesReceived;
use Flarum\Foundation\AbstractServiceProvider;

class NameplateServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // One per request: it collects every author on the page and counts
        // their likes together. A fresh instance per field would count one each.
        // Scoped, not a singleton, so a long-lived worker starts each job fresh.
        $this->container->scoped(LikesReceived::class);
    }
}
