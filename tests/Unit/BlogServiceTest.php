<?php

declare(strict_types=1);

namespace Tests\Unit;

use Blog\BlogService;
use Tests\Support\TestCase;

/** CRUD, publish workflow, views, and analytics for the Blog package. */
final class BlogServiceTest extends TestCase
{
    private BlogService $blog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blog = new BlogService($this->db);
    }

    /** Admins can save a draft and find it in draft listings. */
    public function test_create_draft_and_list(): void
    {
        $userId = $this->insertMember();
        $post = $this->blog->create(
            $userId,
            'Hello World',
            null,
            'A short intro',
            "First paragraph.\n\nSecond paragraph with **bold**.",
            ['php', 'news'],
        );

        $this->assertSame('draft', $post['status']);
        $this->assertSame('hello-world', $post['slug']);
        $listed = $this->blog->list(['status' => 'draft']);
        $this->assertCount(1, $listed);
        $this->assertSame('Hello World', $listed[0]['title']);
        $this->assertArrayNotHasKey('content', $listed[0]);
    }

    /** Publishing sets status and makes the post visible by public slug. */
    public function test_publish_makes_post_public_by_slug(): void
    {
        $userId = $this->insertMember();
        $post = $this->blog->create($userId, 'Launch', 'launch-day', '', '# Launch\n\nWe are live.', []);
        $published = $this->blog->publish((int) $post['id']);

        $this->assertSame('published', $published['status']);
        $this->assertNotNull($published['published_at']);
        $found = $this->blog->findPublishedBySlug('launch-day');
        $this->assertNotNull($found);
        $this->assertSame('Launch', $found['title']);
    }

    /** Duplicate slugs get a numeric suffix so URLs stay unique. */
    public function test_unique_slug_suffix(): void
    {
        $userId = $this->insertMember();
        $first = $this->blog->create($userId, 'Tips', 'tips', '', 'One', []);
        $second = $this->blog->create($userId, 'More Tips', 'tips', '', 'Two', []);

        $this->assertSame('tips', $first['slug']);
        $this->assertSame('tips-1', $second['slug']);
    }

    /** View counts and daily buckets power analytics charts. */
    public function test_record_view_increments_counters(): void
    {
        $userId = $this->insertMember();
        $post = $this->blog->create($userId, 'Stats', 'stats', '', 'Track me', []);
        $id = (int) $post['id'];

        $this->blog->recordView($id);
        $this->blog->recordView($id);

        $row = $this->blog->find($id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['views']);

        $analytics = $this->blog->analytics(30);
        $this->assertSame(2, $analytics['totals']['views']);
        $this->assertNotEmpty($analytics['daily_views']);
    }

    /** Unpublish hides a post from the public slug lookup. */
    public function test_unpublish_hides_from_public(): void
    {
        $userId = $this->insertMember();
        $post = $this->blog->create($userId, 'Temp', 'temp-post', '', 'Body', []);
        $id = (int) $post['id'];
        $this->blog->publish($id);
        $this->blog->unpublish($id);

        $this->assertNull($this->blog->findPublishedBySlug('temp-post'));
    }
}
