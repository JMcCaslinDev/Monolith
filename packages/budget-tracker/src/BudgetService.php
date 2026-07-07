<?php

declare(strict_types=1);

namespace BudgetTracker;

use PDO;
use RuntimeException;

/** Per-user budget profile, income, expenses, savings, and assets. */
final class BudgetService
{
    public function __construct(private readonly PDO $db)
    {
    }

  /** @return array<string, mixed>|null */
    public function findProfile(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM budget_profiles WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

  /** @return array<string, mixed> */
    public function getOrCreateProfile(int $userId): array
    {
        $existing = $this->findProfile($userId);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO budget_profiles (user_id, mode, onboarding_complete) VALUES (:uid, :mode, 0)',
        );
        $stmt->execute(['uid' => $userId, 'mode' => 'solo']);
        $id = (int) $this->db->lastInsertId();
        $row = $this->findProfile($userId);
        if ($row === null) {
            throw new RuntimeException('Failed to create budget profile');
        }

        return $row;
    }

  /** @return array{profile: array<string, mixed>, people: list<array<string, mixed>>, income: list<array<string, mixed>>, expenses: list<array<string, mixed>>, accounts: list<array<string, mixed>>, summary: array<string, mixed>} */
    public function loadState(int $userId): array
    {
        $profile = $this->getOrCreateProfile($userId);
        $profileId = (int) $profile['id'];
        $people = $this->peopleForProfile($profileId);
        $income = $this->incomeForProfile($profileId);
        $expenses = $this->expensesForProfile($profileId);
        $accounts = $this->accountsForProfile($profileId);

        return [
        'profile' => $profile,
        'people' => $people,
        'income' => $income,
        'expenses' => $expenses,
        'accounts' => $accounts,
        'summary' => FinanceCalculator::summarize($people, $income, $expenses, $accounts),
        ];
    }

  /** @param 'solo'|'partner' $mode */
  /** @param list<string> $personNames */
  /** @return array<string, mixed> */
    public function setupProfile(int $userId, string $mode, array $personNames): array
    {
        if (!in_array($mode, ['solo', 'partner'], true)) {
            throw new RuntimeException('Invalid tracking mode');
        }

        $names = array_values(array_filter(array_map('trim', $personNames)));
        $expected = $mode === 'partner' ? 2 : 1;
        if (count($names) < $expected) {
            throw new RuntimeException('Enter a name for each person');
        }
        if (count($names) > $expected) {
            $names = array_slice($names, 0, $expected);
        }

        $profile = $this->getOrCreateProfile($userId);
        $profileId = (int) $profile['id'];

        $stmt = $this->db->prepare(
            'UPDATE budget_profiles SET mode = :mode, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $stmt->execute(['mode' => $mode, 'id' => $profileId]);

        $this->db->prepare('DELETE FROM budget_people WHERE profile_id = :pid')->execute(['pid' => $profileId]);

        $insert = $this->db->prepare(
            'INSERT INTO budget_people (profile_id, name, sort_order) VALUES (:pid, :name, :ord)',
        );
        foreach ($names as $i => $name) {
            $insert->execute(['pid' => $profileId, 'name' => $name, 'ord' => $i]);
        }

        $updated = $this->findProfile($userId);
        if ($updated === null) {
            throw new RuntimeException('Profile missing after setup');
        }

        return $updated;
    }

    public function completeOnboarding(int $userId): void
    {
        $profile = $this->getOrCreateProfile($userId);
        $stmt = $this->db->prepare(
            'UPDATE budget_profiles SET onboarding_complete = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $stmt->execute(['id' => (int) $profile['id']]);
    }

  /** @return array<string, mixed> */
    public function saveIncome(int $userId, int $personId, string $label, int $amountCents, ?int $incomeId = null): array
    {
        $this->assertPersonOwned($userId, $personId);
        $label = trim($label);
        if ($label === '') {
            throw new RuntimeException('Income source label is required');
        }
        if ($amountCents < 0) {
            throw new RuntimeException('Amount cannot be negative');
        }

        if ($incomeId !== null && $incomeId > 0) {
            $stmt = $this->db->prepare(
                'UPDATE budget_income_sources SET label = :label, amount_cents = :amt
         WHERE id = :id AND person_id = :pid',
            );
            $stmt->execute(['label' => $label, 'amt' => $amountCents, 'id' => $incomeId, 'pid' => $personId]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Income source not found');
            }

            return $this->findIncome($incomeId) ?? throw new RuntimeException('Income source missing');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO budget_income_sources (person_id, label, amount_cents) VALUES (:pid, :label, :amt)',
        );
        $stmt->execute(['pid' => $personId, 'label' => $label, 'amt' => $amountCents]);
        $id = (int) $this->db->lastInsertId();

        return $this->findIncome($id) ?? throw new RuntimeException('Income source missing');
    }

    public function deleteIncome(int $userId, int $incomeId): void
    {
        $row = $this->findIncome($incomeId);
        if ($row === null) {
            throw new RuntimeException('Income source not found');
        }
        $this->assertPersonOwned($userId, (int) $row['person_id']);
        $this->db->prepare('DELETE FROM budget_income_sources WHERE id = :id')->execute(['id' => $incomeId]);
    }

  /** @return array<string, mixed> */
    public function saveExpense(
        int $userId,
        int $personId,
        string $category,
        string $label,
        int $amountCents,
        ?int $expenseId = null,
    ): array {
        $this->assertPersonOwned($userId, $personId);
        $category = trim($category);
        if ($category === '') {
            throw new RuntimeException('Expense category is required');
        }
        if ($amountCents < 0) {
            throw new RuntimeException('Amount cannot be negative');
        }

        $label = trim($label);

        if ($expenseId !== null && $expenseId > 0) {
            $stmt = $this->db->prepare(
                'UPDATE budget_expense_sources SET category = :cat, label = :label, amount_cents = :amt
         WHERE id = :id AND person_id = :pid',
            );
            $stmt->execute([
            'cat' => $category,
            'label' => $label !== '' ? $label : null,
            'amt' => $amountCents,
            'id' => $expenseId,
            'pid' => $personId,
            ]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Expense not found');
            }

            return $this->findExpense($expenseId) ?? throw new RuntimeException('Expense missing');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO budget_expense_sources (person_id, category, label, amount_cents)
       VALUES (:pid, :cat, :label, :amt)',
        );
        $stmt->execute([
        'pid' => $personId,
        'cat' => $category,
        'label' => $label !== '' ? $label : null,
        'amt' => $amountCents,
        ]);
        $id = (int) $this->db->lastInsertId();

        return $this->findExpense($id) ?? throw new RuntimeException('Expense missing');
    }

    public function deleteExpense(int $userId, int $expenseId): void
    {
        $row = $this->findExpense($expenseId);
        if ($row === null) {
            throw new RuntimeException('Expense not found');
        }
        $this->assertPersonOwned($userId, (int) $row['person_id']);
        $this->db->prepare('DELETE FROM budget_expense_sources WHERE id = :id')->execute(['id' => $expenseId]);
    }

  /** @param 'savings'|'brokerage'|'retirement'|'other' $kind */
  /** @return array<string, mixed> */
    public function saveAccount(
        int $userId,
        string $kind,
        string $name,
        int $balanceCents,
        ?int $personId = null,
        ?int $accountId = null,
    ): array {
        $allowed = ['savings', 'brokerage', 'retirement', 'other'];
        if (!in_array($kind, $allowed, true)) {
            throw new RuntimeException('Invalid account type');
        }
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Account name is required');
        }
        if ($balanceCents < 0) {
            throw new RuntimeException('Balance cannot be negative');
        }

        $profile = $this->getOrCreateProfile($userId);
        $profileId = (int) $profile['id'];
        if ($personId !== null && $personId > 0) {
            $this->assertPersonOwned($userId, $personId);
        } else {
            $personId = null;
        }

        if ($accountId !== null && $accountId > 0) {
            $stmt = $this->db->prepare(
                'UPDATE budget_accounts SET kind = :kind, name = :name, balance_cents = :bal, person_id = :person
         WHERE id = :id AND profile_id = :pid',
            );
            $stmt->execute([
            'kind' => $kind,
            'name' => $name,
            'bal' => $balanceCents,
            'person' => $personId,
            'id' => $accountId,
            'pid' => $profileId,
            ]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Account not found');
            }

            return $this->findAccount($accountId) ?? throw new RuntimeException('Account missing');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO budget_accounts (profile_id, person_id, kind, name, balance_cents)
       VALUES (:pid, :person, :kind, :name, :bal)',
        );
        $stmt->execute([
        'pid' => $profileId,
        'person' => $personId,
        'kind' => $kind,
        'name' => $name,
        'bal' => $balanceCents,
        ]);
        $id = (int) $this->db->lastInsertId();

        return $this->findAccount($id) ?? throw new RuntimeException('Account missing');
    }

    public function deleteAccount(int $userId, int $accountId): void
    {
        $row = $this->findAccount($accountId);
        if ($row === null) {
            throw new RuntimeException('Account not found');
        }
        $profile = $this->getOrCreateProfile($userId);
        if ((int) $row['profile_id'] !== (int) $profile['id']) {
            throw new RuntimeException('Account not found');
        }
        $this->db->prepare('DELETE FROM budget_accounts WHERE id = :id')->execute(['id' => $accountId]);
    }

  /** @return array{share: array<string, mixed>, projections: list<array<string, mixed>>} */
    public function calculatePurchase(int $userId, int $purchaseCents): array
    {
        $state = $this->loadState($userId);
        $monthlyIncome = (int) ($state['summary']['total_income_cents'] ?? 0);

        return [
        'share' => FinanceCalculator::purchaseIncomeShare($purchaseCents, $monthlyIncome),
        'projections' => FinanceCalculator::investmentProjections($purchaseCents),
        ];
    }

  /** @return list<array<string, mixed>> */
    private function peopleForProfile(int $profileId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM budget_people WHERE profile_id = :pid ORDER BY sort_order ASC, id ASC',
        );
        $stmt->execute(['pid' => $profileId]);

        return $stmt->fetchAll() ?: [];
    }

  /** @return list<array<string, mixed>> */
    private function incomeForProfile(int $profileId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, i.person_id
       FROM budget_income_sources i
       JOIN budget_people p ON p.id = i.person_id
       WHERE p.profile_id = :pid
       ORDER BY p.sort_order ASC, i.id ASC',
        );
        $stmt->execute(['pid' => $profileId]);

        return $stmt->fetchAll() ?: [];
    }

  /** @return list<array<string, mixed>> */
    private function expensesForProfile(int $profileId): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, e.person_id
       FROM budget_expense_sources e
       JOIN budget_people p ON p.id = e.person_id
       WHERE p.profile_id = :pid
       ORDER BY p.sort_order ASC, e.id ASC',
        );
        $stmt->execute(['pid' => $profileId]);

        return $stmt->fetchAll() ?: [];
    }

  /** @return list<array<string, mixed>> */
    private function accountsForProfile(int $profileId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM budget_accounts WHERE profile_id = :pid ORDER BY kind ASC, id ASC',
        );
        $stmt->execute(['pid' => $profileId]);

        return $stmt->fetchAll() ?: [];
    }

  /** @return array<string, mixed>|null */
    private function findIncome(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM budget_income_sources WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

  /** @return array<string, mixed>|null */
    private function findExpense(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM budget_expense_sources WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

  /** @return array<string, mixed>|null */
    private function findAccount(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM budget_accounts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function assertPersonOwned(int $userId, int $personId): void
    {
        $profile = $this->getOrCreateProfile($userId);
        $stmt = $this->db->prepare(
            'SELECT id FROM budget_people WHERE id = :pid AND profile_id = :profile LIMIT 1',
        );
        $stmt->execute(['pid' => $personId, 'profile' => (int) $profile['id']]);
        if ($stmt->fetch() === false) {
            throw new RuntimeException('Person not found');
        }
    }
}
