<?php

declare(strict_types=1);

namespace Tests\Unit;

use Stickies\StickyColors;
use Stickies\StickyService;
use Tests\Support\TestCase;

/** Per-user sticky notes: CRUD, search, categories, sections, and board moves. */
final class StickiesServiceTest extends TestCase
{
    /** New stickies default to board section with auto row placement. */
    public function test_create_note_assigns_section_and_position(): void
    {
        $svc = new StickyService($this->db);
        $note = $svc->saveNote(1, 'Buy milk', 'shopping', 'yellow', 'board');

        $this->assertSame('shopping', $note['category']);
        $this->assertSame('board', $note['section']);
        $this->assertSame('yellow', $note['color']);
        $this->assertSame(0, $note['pos_y']);
        $this->assertSame('Buy milk', $note['content']);
    }

    /** Second sticky in same section stacks on the next row. */
    public function test_create_second_note_increments_row_y(): void
    {
        $svc = new StickyService($this->db);
        $svc->saveNote(1, 'First', 'general', 'pink', 'ideas');
        $second = $svc->saveNote(1, 'Second', 'general', 'blue', 'ideas');

        $this->assertSame(1, $second['pos_y']);
    }

    /** Updates preserve position unless explicitly changed. */
    public function test_update_note_keeps_position_and_changes_content(): void
    {
        $svc = new StickyService($this->db);
        $created = $svc->saveNote(1, 'Draft', 'work', 'green', 'board', null, 40, 80);
        $updated = $svc->saveNote(1, 'Final draft', 'work', 'green', 'board', (int) $created['id']);

        $this->assertSame('Final draft', $updated['content']);
        $this->assertSame(40, $updated['pos_x']);
        $this->assertSame(80, $updated['pos_y']);
    }

    /** Category filter returns only matching stickies for the user. */
    public function test_list_notes_filters_by_category(): void
    {
        $svc = new StickyService($this->db);
        $svc->saveNote(1, 'Work task', 'work', 'yellow', 'board');
        $svc->saveNote(1, 'Personal errand', 'personal', 'pink', 'board');

        $workOnly = $svc->listNotes(1, null, 'work');
        $this->assertCount(1, $workOnly);
        $this->assertSame('work', $workOnly[0]['category']);
    }

    /** Search matches content, category, and section case-insensitively. */
    public function test_list_notes_search_matches_content_and_labels(): void
    {
        $svc = new StickyService($this->db);
        $svc->saveNote(1, 'Deploy Friday', 'work', 'yellow', 'sprint');
        $svc->saveNote(1, 'Groceries', 'personal', 'pink', 'errands');

        $byContent = $svc->listNotes(1, 'deploy');
        $this->assertCount(1, $byContent);

        $bySection = $svc->listNotes(1, 'ERRANDS');
        $this->assertCount(1, $bySection);
        $this->assertSame('Groceries', $bySection[0]['content']);
    }

    /** filterBySearch is reusable for client-side filtering parity. */
    public function test_filter_by_search_static_helper(): void
    {
        $rows = [
            ['content' => 'Hello world', 'category' => 'general', 'section' => 'board'],
            ['content' => 'Other', 'category' => 'work', 'section' => 'ideas'],
        ];
        $filtered = StickyService::filterBySearch($rows, 'WORLD');
        $this->assertCount(1, $filtered);
        $this->assertSame('Hello world', $filtered[0]['content']);
    }

    /** Move updates coordinates and optional section for board drag-drop. */
    public function test_move_note_updates_position_and_section(): void
    {
        $svc = new StickyService($this->db);
        $note = $svc->saveNote(1, 'Movable', 'general', 'orange', 'board');
        $moved = $svc->moveNote(1, (int) $note['id'], 120, 200, 'archive');

        $this->assertSame(120, $moved['pos_x']);
        $this->assertSame(200, $moved['pos_y']);
        $this->assertSame('archive', $moved['section']);
    }

    /** groupBySection clusters notes for scrollable board sections. */
    public function test_group_by_section_orders_sections_alphabetically(): void
    {
        $notes = [
            ['section' => 'z-last', 'id' => 1],
            ['section' => 'a-first', 'id' => 2],
            ['section' => 'a-first', 'id' => 3],
        ];
        $groups = StickyService::groupBySection($notes);
        $this->assertSame(['a-first', 'z-last'], array_keys($groups));
        $this->assertCount(2, $groups['a-first']);
    }

    /** Distinct categories and sections are listed for filter dropdowns. */
    public function test_categories_and_sections_for_user(): void
    {
        $svc = new StickyService($this->db);
        $svc->saveNote(1, 'A', 'alpha', 'yellow', 'one');
        $svc->saveNote(1, 'B', 'beta', 'pink', 'two');
        $svc->saveNote(1, 'C', 'alpha', 'blue', 'one');

        $this->assertSame(['alpha', 'beta'], $svc->categoriesForUser(1));
        $this->assertSame(['one', 'two'], $svc->sectionsForUser(1));
    }

    /** Invalid color falls back to yellow so board always renders. */
    public function test_invalid_color_defaults_to_yellow(): void
    {
        $svc = new StickyService($this->db);
        $note = $svc->saveNote(1, 'Test', 'general', 'not-a-color', 'board');
        $this->assertSame('yellow', $note['color']);
    }

    /** Users cannot delete another account's stickies. */
    public function test_cannot_delete_another_users_note(): void
    {
        $svc = new StickyService($this->db);
        $note = $svc->saveNote(1, 'Mine', 'general', 'yellow', 'board');
        $otherId = $this->insertMember();

        $this->expectException(\RuntimeException::class);
        $svc->deleteNote($otherId, (int) $note['id']);
    }

    /** Users cannot move another account's stickies. */
    public function test_cannot_move_another_users_note(): void
    {
        $svc = new StickyService($this->db);
        $note = $svc->saveNote(1, 'Mine', 'general', 'yellow', 'board');
        $otherId = $this->insertMember();

        $this->expectException(\RuntimeException::class);
        $svc->moveNote($otherId, (int) $note['id'], 10, 10);
    }

    /** Deleting removes the row so list is empty. */
    public function test_delete_note_removes_from_list(): void
    {
        $svc = new StickyService($this->db);
        $note = $svc->saveNote(1, 'Temporary', 'general', 'yellow', 'board');
        $svc->deleteNote(1, (int) $note['id']);
        $this->assertCount(0, $svc->listNotes(1));
    }
}
