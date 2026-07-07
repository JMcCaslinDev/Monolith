<?php

declare(strict_types=1);

namespace Tests\Unit;

use BudgetTracker\BudgetService;
use Tests\Support\TestCase;

/** Per-user budget persistence: profile, income, expenses, and accounts. */
final class BudgetTrackerServiceTest extends TestCase
{
    /** Each user gets an isolated profile; partner mode stores two people. */
    public function test_setup_profile_creates_people_for_solo_and_partner(): void
    {
        $svc = new BudgetService($this->db);
        $userId = 1;

        $svc->setupProfile($userId, 'solo', ['Alex']);
        $state = $svc->loadState($userId);
        $this->assertSame('solo', $state['profile']['mode']);
        $this->assertCount(1, $state['people']);
        $this->assertSame('Alex', $state['people'][0]['name']);

        $svc->setupProfile($userId, 'partner', ['Alex', 'Jordan']);
        $state = $svc->loadState($userId);
        $this->assertSame('partner', $state['profile']['mode']);
        $this->assertCount(2, $state['people']);
    }

    /** Income and expenses persist per person and roll into summary totals. */
    public function test_income_and_expenses_update_live_summary(): void
    {
        $svc = new BudgetService($this->db);
        $userId = 1;
        $svc->setupProfile($userId, 'solo', ['Alex']);
        $personId = (int) $svc->loadState($userId)['people'][0]['id'];

        $svc->saveIncome($userId, $personId, 'Salary', 600000);
        $svc->saveExpense($userId, $personId, 'Rent', 'Apartment', 200000);

        $summary = $svc->loadState($userId)['summary'];
        $this->assertSame(600000, $summary['total_income_cents']);
        $this->assertSame(200000, $summary['total_expense_cents']);
        $this->assertSame(400000, $summary['net_monthly_cents']);
    }

    /** Savings and brokerage balances split into net-worth components. */
    public function test_accounts_split_savings_and_assets(): void
    {
        $svc = new BudgetService($this->db);
        $userId = 1;
        $svc->setupProfile($userId, 'solo', ['Alex']);

        $svc->saveAccount($userId, 'savings', 'Emergency fund', 500000);
        $svc->saveAccount($userId, 'brokerage', 'Vanguard', 2000000);

        $summary = $svc->loadState($userId)['summary'];
        $this->assertSame(500000, $summary['savings_cents']);
        $this->assertSame(2000000, $summary['assets_cents']);
        $this->assertSame(2500000, $summary['net_worth_cents']);
    }

    /** Purchase calculator uses stored income for share and growth projections. */
    public function test_calculate_purchase_returns_share_and_projections(): void
    {
        $svc = new BudgetService($this->db);
        $userId = 1;
        $svc->setupProfile($userId, 'solo', ['Alex']);
        $personId = (int) $svc->loadState($userId)['people'][0]['id'];
        $svc->saveIncome($userId, $personId, 'Salary', 500000);

        $result = $svc->calculatePurchase($userId, 100000);
        $this->assertGreaterThan(0, $result['share']['percent_of_annual']);
        $this->assertNotEmpty($result['projections']);
        $this->assertGreaterThan(100000, $result['projections'][4]['horizons'][2]['value_cents']);
    }

    /** Users cannot mutate another account's income rows. */
    public function test_cannot_modify_another_users_income(): void
    {
        $svc = new BudgetService($this->db);
        $svc->setupProfile(1, 'solo', ['Owner']);
        $personId = (int) $svc->loadState(1)['people'][0]['id'];
        $income = $svc->saveIncome(1, $personId, 'Salary', 100000);

        $memberId = $this->insertMember();
        $this->expectException(\RuntimeException::class);
        $svc->deleteIncome($memberId, (int) $income['id']);
    }
}
