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
 * Without flarum/likes enabled: the settings the panel reads and the member's
 * "hide signatures" preference, and no likes count at all.
 */
class NameplateTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-nameplate');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Discussion::class => [
                ['id' => 1, 'title' => 'Hello', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Hello</p></t>'],
            ],
        ]);
    }

    private function forum(): array
    {
        return json_decode((string) $this->send($this->request('GET', '/api'))->getBody(), true)['data']['attributes'];
    }

    #[Test]
    public function the_forum_payload_carries_the_defaults()
    {
        $forum = $this->forum();

        $this->assertSame('side', $forum['nameplateLayout']);
        $this->assertTrue($forum['nameplateRank']);
        $this->assertTrue($forum['nameplatePosts']);
        $this->assertFalse($forum['nameplateDiscussions']);
        $this->assertTrue($forum['nameplateJoined']);
        $this->assertTrue($forum['nameplateBestAnswers']);
        $this->assertTrue($forum['nameplateLikes']);
        $this->assertFalse($forum['nameplateSignatureOnce']);
    }

    #[Test]
    public function the_forum_payload_follows_the_settings()
    {
        $this->setting('ernestdefoe-nameplate.layout', 'top');
        $this->setting('ernestdefoe-nameplate.show_rank', '0');
        $this->setting('ernestdefoe-nameplate.show_discussions', '1');
        $this->setting('ernestdefoe-nameplate.signature_once', '1');

        $forum = $this->forum();

        $this->assertSame('top', $forum['nameplateLayout']);
        $this->assertFalse($forum['nameplateRank']);
        $this->assertTrue($forum['nameplateDiscussions']);
        $this->assertTrue($forum['nameplateSignatureOnce']);
    }

    #[Test]
    public function a_member_may_hide_signatures_for_themselves()
    {
        $show = fn () => json_decode((string) $this->send($this->request('GET', '/api/users/2', ['authenticatedAs' => 2]))->getBody(), true)['data']['attributes']['preferences']['nameplateHideSignatures'];

        $this->assertFalse($show());

        $response = $this->send($this->request('PATCH', '/api/users/2', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'users', 'id' => '2', 'attributes' => ['preferences' => ['nameplateHideSignatures' => true]]]],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($show());
    }

    #[Test]
    public function without_flarum_likes_enabled_there_is_no_likes_count_and_nothing_breaks()
    {
        $response = $this->send($this->request('GET', '/api/discussions/1', ['authenticatedAs' => 1]));
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());

        $users = array_filter($body['included'], fn ($i) => $i['type'] === 'users');
        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertArrayNotHasKey('nameplateLikesReceived', $user['attributes']);
        }
    }
}
