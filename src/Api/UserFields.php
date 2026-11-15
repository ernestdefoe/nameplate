<?php

namespace Ernestdefoe\Nameplate\Api;

use Ernestdefoe\Nameplate\Likes\LikesReceived;
use Flarum\Api\Schema\Attribute;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The fields Nameplate adds to a member: likes received, only where Flarum
 * Likes is enabled (extend.php registers this inside that condition).
 */
class UserFields
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected LikesReceived $likes,
    ) {
    }

    public function __invoke(): array
    {
        return [
            Attribute::make('nameplateLikesReceived')
                ->get(fn ($user) => $this->settings->get('ernestdefoe-nameplate.show_likes')
                    ? $this->likes->defer((int) $user->id)
                    : null),
        ];
    }
}
