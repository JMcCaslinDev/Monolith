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

    /** Audit payloads and subject ids must not carry money amounts; the log is visible to admins. */
    public function test_audit_events_carry_no_money_amounts(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/packages/budget-tracker/routes.php');
        preg_match_all('/events\(\)->record\((.*?)\);/s', $src, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $call) {
            $this->assertStringNotContainsString('cents', $call, 'audit call leaks an amount: ' . $call);
            $this->assertStringNotContainsString('Cents', $call, 'audit call leaks an amount: ' . $call);
        }
    }

    /** Budget audit summaries render without any dollar figures. */
    public function test_event_summaries_hide_amounts(): void
    {
        require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
        foreach (['income.saved', 'expense.saved', 'purchase.calculated'] as $suffix) {
            $summary = event_summary([
                'type' => 'budget-tracker.' . $suffix,
                'subject_id' => '1',
                'payload' => ['label' => 'Pay', 'category' => 'Rent', 'amount_cents' => 123456],
            ]);
            $this->assertStringNotContainsString('$', $summary);
            $this->assertStringNotContainsString('1,234', $summary);
        }
    }
}
