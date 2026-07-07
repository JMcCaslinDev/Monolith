<?php

declare(strict_types=1);

namespace Tests\Unit;

use Devtools\Catalog;
use PHPUnit\Framework\TestCase;

/** Dev Tools catalog: tool list, category access, and per-tool permissions. */
final class CatalogTest extends TestCase
{
    /** All 30 Dev Tools are registered — a missing tool would vanish from the UI silently. */
    public function test_all_thirty_tools_registered(): void
    {
        $this->assertCount(30, Catalog::tools());
    }

    /** Seven sidebar categories match the product grouping in the Dev Tools UI. */
    public function test_seven_categories_defined(): void
    {
        $this->assertCount(7, Catalog::categories());
        $this->assertArrayHasKey('converters', Catalog::categories());
        $this->assertArrayHasKey('encoders', Catalog::categories());
        $this->assertArrayHasKey('graphic', Catalog::categories());
    }

    /** Category-level permission grants access to every tool in that category. */
    public function test_category_permission_grants_tool_access(): void
    {
        $perms = ['devtools.converters.use'];
        $this->assertTrue(Catalog::canUse('cron-parser', $perms));
        $this->assertFalse(Catalog::canUse('json', $perms));
    }

    /** Per-tool permission grants access to one tool without opening the whole category. */
    public function test_tool_permission_grants_single_tool(): void
    {
        $perms = ['devtools.formatters.json.use'];
        $this->assertTrue(Catalog::canUse('json', $perms));
        $this->assertFalse(Catalog::canUse('sql', $perms));
    }

    /** Unknown tool slugs are denied so arbitrary paths cannot bypass permission checks. */
    public function test_can_use_denies_unknown_tool(): void
    {
        $this->assertFalse(Catalog::canUse('not-a-real-tool', ['devtools.converters.use']));
        $this->assertNull(Catalog::find('not-a-real-tool'));
    }

    /** find() returns metadata used by routes and the sidebar for a known tool. */
    public function test_find_returns_tool_metadata(): void
    {
        $tool = Catalog::find('jwt');
        $this->assertNotNull($tool);
        $this->assertSame('jwt', $tool['slug']);
        $this->assertSame('encoders', $tool['category']);
    }

    /** Permission helpers use stable devtools.{category}.{slug}.use names for grants. */
    public function test_permission_names_follow_convention(): void
    {
        $this->assertSame('devtools.converters.use', Catalog::categoryPermission('converters'));
        $this->assertSame('devtools.encoders.jwt.use', Catalog::toolPermission('encoders', 'jwt'));
    }

    /** Sidebar only lists tool categories the user is allowed to use. */
    public function test_accessible_categories_filters_by_permission(): void
    {
        $cats = Catalog::accessibleCategories(['devtools.text.use']);
        $this->assertArrayHasKey('text', $cats);
        $this->assertArrayNotHasKey('converters', $cats);
        $this->assertCount(5, $cats['text']['tools']);
    }

    /** accessibleTools returns a flat list of allowed tools for API permission checks. */
    public function test_accessible_tools_lists_permitted_slugs(): void
    {
        $tools = Catalog::accessibleTools(['devtools.generators.uuid.use']);
        $slugs = array_column($tools, 'slug');
        $this->assertSame(['uuid'], $slugs);
    }
}
