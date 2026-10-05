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
        $this->container->singleton(LikesReceived::class);
    }
}
