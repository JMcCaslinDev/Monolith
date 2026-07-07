<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use PHPUnit\Framework\TestCase;

/** Budget Tracker package wiring: manifest, registry merge, and HTTP route table. */
final class BudgetTrackerPackageTest extends TestCase
{
    /** Budget tracker routes are registered for SPA and API. */
    public function test_web_routes_include_budget_tracker_endpoints(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        $this->assertArrayHasKey('GET /projects/budget-tracker', $routes);
        $this->assertArrayHasKey('GET /projects/budget-tracker/api/state', $routes);
        $this->assertArrayHasKey('POST /projects/budget-tracker/purchase/calculate', $routes);
    }

    /** Budget tracker audit event types are documented in the merged registry. */
    public function test_registry_includes_budget_tracker_events(): void
    {
        $types = array_column(Registry::packageEvents(), 'type');
        $this->assertContains('budget-tracker.income.saved', $types);
        $this->assertContains('budget-tracker.purchase.calculated', $types);
        $this->assertContains('budget-tracker.onboarding.completed', $types);
    }

    /** Budget tracker project declares manage and purchase permissions. */
    public function test_manifest_declares_project_and_permissions(): void
    {
        $manifest = Registry::packages()['budget-tracker'];
        $this->assertSame('Budget Tracker', $manifest['project']['name']);
        $names = array_column($manifest['permissions'] ?? [], 'name');
        $this->assertContains('projects.budget-tracker.open', $names);
        $this->assertContains('budget-tracker.manage', $names);
        $this->assertContains('budget-tracker.purchase.use', $names);
    }

    /** UI exposes onboarding wizard, tabs, and purchase calculator. */
    public function test_view_includes_wizard_and_calculator_ui(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2) . '/packages/budget-tracker/views/app.php');
        $this->assertIsString($html);
        $this->assertStringContainsString('budgetTrackerApp', $html);
        $this->assertStringContainsString('Can I afford it?', $html);
        $this->assertStringContainsString('wizardStep', $html);
    }
}
