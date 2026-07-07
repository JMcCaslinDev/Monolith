<?php

declare(strict_types=1);

namespace BudgetTracker;

/** Pure budget math: totals, income share, and compound-growth projections. */
final class FinanceCalculator
{
    public const HOURS_PER_YEAR = 8760;

    public const DAYS_PER_YEAR = 365;

  /** @param list<array{amount_cents: int}> $lines */
    public static function sumCents(array $lines): int
    {
        $total = 0;
        foreach ($lines as $line) {
            $total += (int) ($line['amount_cents'] ?? 0);
        }

        return max(0, $total);
    }

  /** @param list<array{amount_cents: int, person_id?: int}> $lines */
  /** @return array<int, int> person_id => cents */
    public static function totalsByPerson(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $pid = (int) ($line['person_id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $out[$pid] = ($out[$pid] ?? 0) + max(0, (int) ($line['amount_cents'] ?? 0));
        }

        return $out;
    }

  /** Share of income the purchase represents across calendar periods. */
  /** @return array{
   *   annual_income_cents: int,
   *   purchase_cents: int,
   *   percent_of_annual: float,
   *   months_of_income: float,
   *   days_of_income: float,
   *   hours_of_income: float
   * }
   */
    public static function purchaseIncomeShare(int $purchaseCents, int $monthlyIncomeCents): array
    {
        $purchase = max(0, $purchaseCents);
        $monthly = max(0, $monthlyIncomeCents);
        if ($monthly < 1) {
            return [
            'annual_income_cents' => 0,
            'purchase_cents' => $purchase,
            'percent_of_annual' => 0.0,
            'months_of_income' => 0.0,
            'days_of_income' => 0.0,
            'hours_of_income' => 0.0,
            ];
        }

        $annual = $monthly * 12;
        $daily = $annual / self::DAYS_PER_YEAR;
        $hourly = $annual / self::HOURS_PER_YEAR;

        return [
        'annual_income_cents' => $annual,
        'purchase_cents' => $purchase,
        'percent_of_annual' => round(($purchase / $annual) * 100, 2),
        'months_of_income' => round($purchase / $monthly, 2),
        'days_of_income' => round($purchase / $daily, 2),
        'hours_of_income' => round($purchase / $hourly, 2),
        ];
    }

  /** Compound growth: FV = principal × (1 + rate)^years. */
    public static function futureValueCents(int $principalCents, float $annualRatePercent, int $years): int
    {
        if ($principalCents < 1 || $years < 1 || $annualRatePercent <= 0) {
            return max(0, $principalCents);
        }

        $rate = $annualRatePercent / 100;
        $fv = $principalCents * (1 + $rate) ** $years;

        return (int) round($fv);
    }

  /**
   * Opportunity-cost projections if the purchase were invested instead.
   *
   * @param list<int> $horizonYears
   * @param list<float> $annualRates
   * @return list<array{rate: float, label: string, horizons: list<array{years: int, value_cents: int, gain_cents: int}>}>
   */
    public static function investmentProjections(
        int $principalCents,
        array $horizonYears = [1, 5, 10, 20, 30],
        array $annualRates = [
      ['rate' => 3.5, 'label' => 'High-yield savings (~3–4%)'],
      ['rate' => 4.0, 'label' => 'High-yield savings (4%)'],
      ['rate' => 6.0, 'label' => 'Premium savings / X account (~6%)'],
      ['rate' => 7.0, 'label' => 'S&P 500 conservative (7%)'],
      ['rate' => 8.0, 'label' => 'S&P 500 average (8%)'],
      ['rate' => 10.0, 'label' => 'Growth portfolio (10%)'],
      ['rate' => 12.0, 'label' => 'Aggressive growth (12%)'],
        ],
    ): array {
        $principal = max(0, $principalCents);
        $out = [];

        foreach ($annualRates as $entry) {
            $rate = (float) ($entry['rate'] ?? 0);
            $label = (string) ($entry['label'] ?? ($rate . '%'));
            $horizons = [];
            foreach ($horizonYears as $years) {
                $y = max(1, (int) $years);
                $value = self::futureValueCents($principal, $rate, $y);
                $horizons[] = [
                'years' => $y,
                'value_cents' => $value,
                'gain_cents' => max(0, $value - $principal),
                ];
            }
            $out[] = [
            'rate' => $rate,
            'label' => $label,
            'horizons' => $horizons,
            ];
        }

        return $out;
    }

  /**
   * Live budget summary from persisted rows.
   *
   * @param list<array<string, mixed>> $people
   * @param list<array<string, mixed>> $income
   * @param list<array<string, mixed>> $expenses
   * @param list<array<string, mixed>> $accounts
   * @return array<string, mixed>
   */
    public static function summarize(
        array $people,
        array $income,
        array $expenses,
        array $accounts,
    ): array {
        $incomeByPerson = self::totalsByPerson($income);
        $expenseByPerson = self::totalsByPerson($expenses);
        $personSummaries = [];

        foreach ($people as $person) {
            $pid = (int) $person['id'];
            $inc = $incomeByPerson[$pid] ?? 0;
            $exp = $expenseByPerson[$pid] ?? 0;
            $personSummaries[] = [
            'person_id' => $pid,
            'name' => (string) $person['name'],
            'income_cents' => $inc,
            'expense_cents' => $exp,
            'net_cents' => $inc - $exp,
            ];
        }

        $totalIncome = self::sumCents($income);
        $totalExpenses = self::sumCents($expenses);
        $savingsCents = 0;
        $assetCents = 0;
        foreach ($accounts as $account) {
            $balance = max(0, (int) ($account['balance_cents'] ?? 0));
            if (($account['kind'] ?? '') === 'savings') {
                $savingsCents += $balance;
            } else {
                $assetCents += $balance;
            }
        }

        return [
        'people' => $personSummaries,
        'total_income_cents' => $totalIncome,
        'total_expense_cents' => $totalExpenses,
        'net_monthly_cents' => $totalIncome - $totalExpenses,
        'savings_cents' => $savingsCents,
        'assets_cents' => $assetCents,
        'net_worth_cents' => $savingsCents + $assetCents,
        ];
    }
}
