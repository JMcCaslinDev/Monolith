<?php

declare(strict_types=1);

namespace Tests\Unit;

use CursorShare\ShareService;
use Tests\Support\TestCase;

/** CRUD, voting, and stats for the Cursor Share community package. */
final class ShareServiceTest extends TestCase
{
    private ShareService $share;

    protected function setUp(): void
    {
        parent::setUp();
        $this->share = new ShareService($this->db);
    }

    /** Users can publish a rule and find it in category browse lists. */
    public function test_create_and_list_post(): void
    {
        $userId = $this->insertMember();
        $post = $this->share->create(
            $userId,
            'rules',
            'Ponytail rule',
            'Keep it simple',
            'ponytail.mdc',
            '1.0',
            "---\ndescription: lazy dev\n---\n# Ponytail\nBe lazy.",
            ['lazy', 'rules'],
        );

        $this->assertSame('ponytail.mdc', $post['filename']);
        $listed = $this->share->list(['category' => 'rules', 'user_id' => $userId]);
        $this->assertCount(1, $listed);
        $this->assertSame('Ponytail rule', $listed[0]['title']);
        $this->assertArrayNotHasKey('content', $listed[0]);
    }

    /** Only the poster can update their post — others get an error. */
    public function test_only_owner_can_update(): void
    {
        $owner = $this->insertMember('owner2@test.com');
        $other = $this->insertMember('other@test.com');
        $post = $this->share->create($owner, 'commands', 'Test', '', 't.md', null, '# cmd', []);

        $this->expectException(\RuntimeException::class);
        $this->share->update($other, (int) $post['id'], 'Hijack', '', 'x.md', null, '# no', []);
    }

    /** Vote toggles and switches update denormalized counts used for top-10 ranking. */
    public function test_vote_toggle_and_switch(): void
    {
        $author = $this->insertMember('author@test.com');
        $voter = $this->insertMember('voter@test.com');
        $post = $this->share->create($author, 'rules', 'Vote me', '', 'v.mdc', null, '# v', []);

        $up = $this->share->vote($voter, (int) $post['id'], 1);
        $this->assertSame(1, $up['vote']);
        $this->assertSame(1, $up['upvotes']);

        $off = $this->share->vote($voter, (int) $post['id'], 1);
        $this->assertSame(0, $off['vote']);
        $this->assertSame(0, $off['upvotes']);

        $down = $this->share->vote($voter, (int) $post['id'], -1);
        $this->assertSame(-1, $down['vote']);
        $this->assertSame(1, $down['downvotes']);
    }

    /** View and download counters increment for popularity sorting and analytics. */
    public function test_view_and_download_counters(): void
    {
        $userId = $this->insertMember();
        $post = $this->share->create($userId, 'hooks', 'H', '', 'hooks.json', null, '{}', []);
        $id = (int) $post['id'];

        $this->share->recordView($id);
        $this->share->recordView($id);
        $this->share->recordDownload($id);

        $row = $this->share->find($id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['views']);
        $this->assertSame(1, $row['downloads']);
    }

    /** Top lists return at most ten posts per category ordered by score. */
    public function test_top_by_category_limits_ten(): void
    {
        $userId = $this->insertMember();
        for ($i = 0; $i < 12; $i++) {
            $this->share->create($userId, 'rules', "Rule $i", '', "r$i.mdc", null, "# $i", []);
        }
        $top = $this->share->topForCategory('rules', 10);
        $this->assertCount(10, $top);
    }
}
