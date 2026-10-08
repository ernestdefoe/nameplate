<?php

use Ernestdefoe\Nameplate\Likes\ForgetOnLike;
use Ernestdefoe\Nameplate\Likes\LikesReceived;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema\Attribute;
use Flarum\Extend;

$extenders = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    // A member's own choice: hide everyone's signatures (FoF Signature's) on
    // the posts they read.
    (new Extend\User())
        ->registerPreference('nameplateHideSignatures', 'boolval', false),

    (new Extend\Settings())
        ->default('ernestdefoe-nameplate.layout', 'side')
        ->default('ernestdefoe-nameplate.show_rank', true)
        ->default('ernestdefoe-nameplate.show_posts', true)
        ->default('ernestdefoe-nameplate.show_discussions', false)
        ->default('ernestdefoe-nameplate.show_joined', true)
        ->default('ernestdefoe-nameplate.show_best_answers', true)
        ->default('ernestdefoe-nameplate.show_likes', true)
        ->default('ernestdefoe-nameplate.signature_once', false)
        ->serializeToForum('nameplateLayout', 'ernestdefoe-nameplate.layout')
        ->serializeToForum('nameplateRank', 'ernestdefoe-nameplate.show_rank', 'boolval')
        ->serializeToForum('nameplatePosts', 'ernestdefoe-nameplate.show_posts', 'boolval')
        ->serializeToForum('nameplateDiscussions', 'ernestdefoe-nameplate.show_discussions', 'boolval')
        ->serializeToForum('nameplateJoined', 'ernestdefoe-nameplate.show_joined', 'boolval')
        ->serializeToForum('nameplateBestAnswers', 'ernestdefoe-nameplate.show_best_answers', 'boolval')
        ->serializeToForum('nameplateLikes', 'ernestdefoe-nameplate.show_likes', 'boolval')
        ->serializeToForum('nameplateSignatureOnce', 'ernestdefoe-nameplate.signature_once', 'boolval'),
];

/*
 * Likes received — only where flarum/likes is ENABLED.
 *
 * 🚨 Not merely installed: it ships with Flarum, so its classes load on forums
 * that have it switched off, and on one that never enabled it there is no
 * post_likes table — counting from it failed every response with a member in
 * it.
 */
$extenders[] = (new Extend\Conditional())
    ->whenExtensionEnabled('flarum-likes', fn () => [
        (new Extend\ServiceProvider())
            ->register(\Ernestdefoe\Nameplate\NameplateServiceProvider::class),

        (new Extend\ApiResource(UserResource::class))
            ->fields(fn () => [
                Attribute::make('nameplateLikesReceived')
                    ->get(fn ($user) => resolve('flarum.settings')->get('ernestdefoe-nameplate.show_likes')
                        ? resolve(LikesReceived::class)->defer((int) $user->id)
                        : null),
            ]),

        (new Extend\Event())
            ->listen(\Flarum\Likes\Event\PostWasLiked::class, ForgetOnLike::class)
            ->listen(\Flarum\Likes\Event\PostWasUnliked::class, ForgetOnLike::class),
    ]);

return $extenders;
