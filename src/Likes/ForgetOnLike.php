<?php

namespace Ernestdefoe\Nameplate\Likes;

/** A like or an unlike changes the author's total, so their cached count goes. */
class ForgetOnLike
{
    public function __construct(protected LikesReceived $likes)
    {
    }

    public function handle(object $event): void
    {
        $post = $event->post ?? null;

        $this->likes->forget($post ? (int) $post->user_id : null);
    }
}
