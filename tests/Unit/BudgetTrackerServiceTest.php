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

    /** Re-saving profile names must not wipe income/expenses tied to existing people. */
    public function test_setup_profile_preserves_income_when_names_unchanged(): void
    {
        $svc = new BudgetService($this->db);
        $userId = 1;
        $svc->setupProfile($userId, 'solo', ['Jonathan']);
        $personId = (int) $svc->loadState($userId)['people'][0]['id'];

        $svc->saveIncome($userId, $personId, 'Salary', 800000);
        $svc->setupProfile($userId, 'solo', ['Jonathan']);

        $state = $svc->loadState($userId);
        $this->assertSame($personId, (int) $state['people'][0]['id']);
        $this->assertCount(1, $state['income']);
        $this->assertSame(800000, $state['summary']['total_income_cents']);
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

    /** Partner mode keeps all accounts in one household list (no per-person split). */
    public function test_accounts_are_household_level_in_partner_mode(): void
    {
        $svc = new BudgetService($this->db);
        $userId = 1;
        $svc->setupProfile($userId, 'partner', ['Alex', 'Jordan']);
        $people = $svc->loadState($userId)['people'];
        $p0 = (int) $people[0]['id'];
        $p1 = (int) $people[1]['id'];

        $svc->saveIncome($userId, $p0, 'Salary', 600000);
        $svc->saveIncome($userId, $p1, 'Salary', 400000);
        $svc->saveAccount($userId, 'savings', 'Joint checking', 100000);
        $svc->saveAccount($userId, 'brokerage', 'Shared brokerage', 500000);

        $state = $svc->loadState($userId);
        $this->assertCount(2, $state['accounts']);
        foreach ($state['accounts'] as $account) {
            $this->assertNull($account['person_id']);
        }
        $this->assertSame(100000, $state['summary']['savings_cents']);
        $this->assertSame(500000, $state['summary']['assets_cents']);
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
        $this->assertCount(1, $result['included_people']);
        $this->assertNotEmpty($result['projections']);
        $this->assertGreaterThan(100000, $result['projections'][2]['horizons'][2]['value_cents']);
    }

    /** Purchase calculator can limit income to selected people in partner mode. */
    public function test_calculate_purchase_filters_by_person(): void
    {
        $svc = new BudgetService($this->db);
        $userId = 1;
        $svc->setupProfile($userId, 'partner', ['Alex', 'Jordan']);
        $people = $svc->loadState($userId)['people'];
        $p0 = (int) $people[0]['id'];
        $p1 = (int) $people[1]['id'];
        $svc->saveIncome($userId, $p0, 'Salary', 600000);
        $svc->saveIncome($userId, $p1, 'Salary', 400000);

        $all = $svc->calculatePurchase($userId, 100000);
        $this->assertSame(1000000, $all['share']['monthly_income_cents']);

        $one = $svc->calculatePurchase($userId, 100000, [$p0]);
        $this->assertSame(600000, $one['share']['monthly_income_cents']);
        $this->assertCount(1, $one['included_people']);
        $this->assertSame('Alex', $one['included_people'][0]['name']);
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
