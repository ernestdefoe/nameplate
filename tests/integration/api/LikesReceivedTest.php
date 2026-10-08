<?php

namespace Ernestdefoe\Nameplate\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Likes received, with flarum/likes enabled: counted right, kept current when
 * a post is liked or unliked, and counted for a whole page at once.
 *
 * Users 2-11 each wrote two posts in discussion 1. User 2's post 1 has three
 * likes and their hidden post 2 one more; user 3's post 3 has one like.
 */
class LikesReceivedTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-likes', 'ernestdefoe-nameplate');

        $users = [$this->normalUser()];
        foreach (range(3, 11) as $id) {
            $users[] = ['id' => $id, 'username' => "user$id", 'email' => "user$id@machine.local", 'is_email_confirmed' => 1];
        }

        $posts = [];
        $number = 0;
        foreach (range(2, 11) as $author) {
            foreach ([0, 1] as $k) {
                $id = ($author - 2) * 2 + $k + 1;
                $posts[] = ['id' => $id, 'discussion_id' => 1, 'number' => ++$number, 'created_at' => Carbon::now(), 'user_id' => $author, 'type' => 'comment', 'content' => '<t><p>Post '.$id.'</p></t>'];
            }
        }
        $posts[1]['hidden_at'] = Carbon::now();

        $this->prepareDatabase([
            User::class => $users,
            Discussion::class => [
                ['id' => 1, 'title' => 'Busy', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 20],
            ],
            Post::class => $posts,
            'post_likes' => [
                ['post_id' => 1, 'user_id' => 3],
                ['post_id' => 1, 'user_id' => 4],
                ['post_id' => 1, 'user_id' => 5],
                ['post_id' => 2, 'user_id' => 3],
                ['post_id' => 3, 'user_id' => 2],
            ],
        ]);
    }

    /** @return array<int, mixed> likes received, by user id, as the discussion page includes them */
    private function likesOnThePage(?int $actor = null): array
    {
        $response = $this->send($this->request('GET', '/api/discussions/1', $actor ? ['authenticatedAs' => $actor] : []));
        $this->assertSame(200, $response->getStatusCode());

        $out = [];
        foreach (json_decode((string) $response->getBody(), true)['included'] as $item) {
            if ($item['type'] === 'users') {
                $out[(int) $item['id']] = $item['attributes']['nameplateLikesReceived'] ?? 'absent';
            }
        }

        return $out;
    }

    private function like(int $post, int $actor, bool $liked): void
    {
        $response = $this->send($this->request('PATCH', "/api/posts/$post", [
            'authenticatedAs' => $actor,
            'json' => ['data' => ['type' => 'posts', 'id' => (string) $post, 'attributes' => ['isLiked' => $liked]]],
        ]));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function likes_on_visible_comments_are_counted()
    {
        $likes = $this->likesOnThePage();

        $this->assertSame(3, $likes[2], 'The like on the hidden post does not count');
        $this->assertSame(1, $likes[3]);
        $this->assertSame(0, $likes[4]);
    }

    #[Test]
    public function every_author_on_the_page_is_counted_without_a_query_each()
    {
        // The posts as a discussion page loads them, each with its author.
        // flarum/testing fails the request when the same query repeats.
        $response = $this->send($this->request('GET', '/api/posts')->withQueryParams(['filter' => ['discussion' => '1'], 'include' => 'user']));
        $this->assertSame(200, $response->getStatusCode());

        $likes = [];
        foreach (json_decode((string) $response->getBody(), true)['included'] as $item) {
            if ($item['type'] === 'users') {
                $likes[(int) $item['id']] = $item['attributes']['nameplateLikesReceived'] ?? null;
            }
        }

        $this->assertCount(10, array_filter($likes, 'is_int'));
        $this->assertSame(3, $likes[2]);
    }

    #[Test]
    public function the_count_is_switched_off_with_the_setting()
    {
        $this->setting('ernestdefoe-nameplate.show_likes', '0');

        // The panel shows a count only when it is a number.
        $this->assertFalse(is_int($this->likesOnThePage()[2]));
    }

    #[Test]
    public function a_new_like_and_an_unlike_show_at_once()
    {
        $this->assertSame(1, $this->likesOnThePage()[3], 'Counted, and now cached');

        $this->like(4, 6, true);
        $this->assertSame(2, $this->likesOnThePage()[3]);

        $this->like(4, 6, false);
        $this->assertSame(1, $this->likesOnThePage()[3]);
    }
}
