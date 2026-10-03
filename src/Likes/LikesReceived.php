<?php

namespace Ernestdefoe\Nameplate\Likes;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

/**
 * How many likes a member's posts have received, for the panel beside their
 * posts.
 *
 * 🚨 Cached per member, and dropped the moment one of their posts is liked or
 * unliked. A discussion page shows up to twenty authors, and counting every
 * like on every one of their posts for every page view is a join over the
 * largest table on a busy forum. With the cache, a count is worked out once
 * and stays right without being worked out again.
 *
 * Through the query builder only — never raw SQL — so a forum with a table
 * prefix gets the right tables.
 */
class LikesReceived
{
    private const TTL = 86400;

    public function __construct(
        protected ConnectionInterface $db,
        protected Cache $cache,
    ) {
    }

    public function for(int $userId): int
    {
        return (int) $this->cache->remember($this->key($userId), self::TTL, function () use ($userId) {
            return $this->db->table('post_likes')
                ->join('posts', 'posts.id', '=', 'post_likes.post_id')
                ->where('posts.user_id', $userId)
                ->whereNull('posts.hidden_at')
                ->where('posts.type', 'comment')
                ->count();
        });
    }

    public function forget(?int $userId): void
    {
        if ($userId) {
            $this->cache->forget($this->key($userId));
        }
    }

    private function key(int $userId): string
    {
        return 'ernestdefoe-nameplate.likes.' . $userId;
    }
}
