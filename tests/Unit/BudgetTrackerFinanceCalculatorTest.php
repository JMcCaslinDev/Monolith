<?php

declare(strict_types=1);

namespace Tests\Unit;

use BudgetTracker\FinanceCalculator;
use PHPUnit\Framework\TestCase;

/** Budget math: totals, income share, and compound-growth projections. */
final class BudgetTrackerFinanceCalculatorTest extends TestCase
{
    /** Monthly line items sum to total cents without dropping negative guards. */
    public function test_sum_cents_totals_income_lines(): void
    {
        $lines = [
            ['amount_cents' => 500000],
            ['amount_cents' => 250000],
        ];
        $this->assertSame(750000, FinanceCalculator::sumCents($lines));
    }

    /** Purchase share uses 40 hr/week model: monthly ÷ 160 hourly, 8-hr work days. */
    public function test_purchase_income_share_converts_to_time_units(): void
    {
        // $5,000/mo → $31.25/hr assumed; $1,000 purchase = 32 work hours
        $share = FinanceCalculator::purchaseIncomeShare(100000, 500000);
        $this->assertSame(6000000, $share['annual_income_cents']);
        $this->assertSame(500000, $share['monthly_income_cents']);
        $this->assertSame(3125, $share['assumed_hourly_cents']);
        $this->assertSame(1.67, $share['percent_of_annual']);
        $this->assertSame(20.0, $share['percent_of_monthly']);
        $this->assertSame(4.0, $share['days_of_income']);
        $this->assertSame(32.0, $share['hours_of_income']);
    }

    /** Zero income returns safe zeros so the UI never divides by null. */
    public function test_purchase_income_share_handles_zero_income(): void
    {
        $share = FinanceCalculator::purchaseIncomeShare(50000, 0);
        $this->assertSame(0.0, $share['percent_of_annual']);
        $this->assertSame(0.0, $share['percent_of_monthly']);
    }

    /** Compound growth matches standard FV formula for S&P-style rates. */
    public function test_future_value_compounds_at_annual_rate(): void
    {
        // $1,000 at 8% for 10 years ≈ $2,158.92
        $fv = FinanceCalculator::futureValueCents(100000, 8.0, 10);
        $this->assertSame(215892, $fv);
    }

    /** HYSA and S&P bands produce 1y / 10y / 30y horizon rows. */
    public function test_investment_projections_cover_savings_and_equity_rates(): void
    {
        $projections = FinanceCalculator::investmentProjections(100000);
        $rates = array_column($projections, 'rate');
        $this->assertSame([3.5, 7.0, 10.0], $rates);
        $this->assertSame([1, 10, 30], array_column($projections[0]['horizons'], 'years'));
        $this->assertGreaterThan(100000, $projections[2]['horizons'][2]['value_cents']);
    }

    /** Live summary rolls up per-person totals and net worth from accounts. */
    public function test_summarize_aggregates_people_and_accounts(): void
    {
        $people = [
            ['id' => 1, 'name' => 'Alex'],
            ['id' => 2, 'name' => 'Jordan'],
        ];
        $income = [
            ['person_id' => 1, 'amount_cents' => 500000],
            ['person_id' => 2, 'amount_cents' => 300000],
        ];
        $expenses = [
            ['person_id' => 1, 'amount_cents' => 200000],
            ['person_id' => 2, 'amount_cents' => 150000],
        ];
        $accounts = [
            ['kind' => 'savings', 'balance_cents' => 1000000],
            ['kind' => 'brokerage', 'balance_cents' => 5000000],
        ];

        $summary = FinanceCalculator::summarize($people, $income, $expenses, $accounts);

        $this->assertSame(800000, $summary['total_income_cents']);
        $this->assertSame(350000, $summary['total_expense_cents']);
        $this->assertSame(450000, $summary['net_monthly_cents']);
        $this->assertSame(1000000, $summary['savings_cents']);
        $this->assertSame(5000000, $summary['assets_cents']);
        $this->assertSame(6000000, $summary['net_worth_cents']);
        $this->assertSame(300000, $summary['people'][0]['net_cents']);
    }
}
