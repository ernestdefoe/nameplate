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

    /** @var array<int, true> ids asked for this request, not yet counted */
    private array $pending = [];

    /** @var array<int, int> counts already known this request */
    private array $known = [];

    public function for(int $userId): int
    {
        $this->pending[$userId] = true;
        $this->resolve();

        return $this->known[$userId] ?? 0;
    }

    /**
     * The count, deferred until every member on the page has asked.
     *
     * 🚨 The API serializer resolves a field value that is a Closure only
     * after it has been through every model, so each author's field only
     * notes the id here, and the first one resolved counts them all: one
     * cache read for the page, and on a cold cache ONE grouped query instead
     * of a join-and-count per author.
     */
    public function defer(int $userId): \Closure
    {
        $this->pending[$userId] = true;

        return function () use ($userId): int {
            $this->resolve();

            return $this->known[$userId] ?? 0;
        };
    }

    private function resolve(): void
    {
        $ids = array_keys(array_diff_key($this->pending, $this->known));
        $this->pending = [];

        if ($ids === []) {
            return;
        }

        $keys = array_map(fn (int $id) => $this->key($id), $ids);
        $cached = $this->cache->many($keys);
        $missing = [];

        foreach ($ids as $i => $id) {
            $value = $cached[$keys[$i]] ?? null;

            if ($value === null) {
                $missing[] = $id;
            } else {
                $this->known[$id] = (int) $value;
            }
        }

        if ($missing === []) {
            return;
        }

        $counts = $this->db->table('post_likes')
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->whereIn('posts.user_id', $missing)
            ->whereNull('posts.hidden_at')
            ->where('posts.type', 'comment')
            ->groupBy('posts.user_id')
            ->select('posts.user_id')
            ->selectRaw('count(*) as aggregate')
            ->pluck('aggregate', 'user_id')
            ->all();

        $fresh = [];

        foreach ($missing as $id) {
            $this->known[$id] = (int) ($counts[$id] ?? 0);
            $fresh[$this->key($id)] = $this->known[$id];
        }

        $this->cache->putMany($fresh, self::TTL);
    }

    public function forget(?int $userId): void
    {
        if ($userId) {
            unset($this->known[$userId]);
            $this->cache->forget($this->key($userId));
        }
    }

    private function key(int $userId): string
    {
        return 'ernestdefoe-nameplate.likes.' . $userId;
    }
}
