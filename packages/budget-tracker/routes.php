<?php

declare(strict_types=1);

require_once __DIR__ . '/src/FinanceCalculator.php';
require_once __DIR__ . '/src/BudgetService.php';

$jsonResponse = function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
};

$dollarsToCents = function (string $raw): int {
    $clean = str_replace([',', '$', ' '], '', trim($raw));
    if ($clean === '' || !is_numeric($clean)) {
        return 0;
    }

    return (int) round((float) $clean * 100);
};

$publicState = function (array $state): array {
    return [
        'profile' => [
            'id' => (int) $state['profile']['id'],
            'mode' => $state['profile']['mode'],
            'onboarding_complete' => (bool) $state['profile']['onboarding_complete'],
        ],
        'people' => array_map(static fn (array $p): array => [
            'id' => (int) $p['id'],
            'name' => $p['name'],
            'sort_order' => (int) $p['sort_order'],
        ], $state['people']),
        'income' => array_map(static fn (array $i): array => [
            'id' => (int) $i['id'],
            'person_id' => (int) $i['person_id'],
            'label' => $i['label'],
            'amount_cents' => (int) $i['amount_cents'],
        ], $state['income']),
        'expenses' => array_map(static fn (array $e): array => [
            'id' => (int) $e['id'],
            'person_id' => (int) $e['person_id'],
            'category' => $e['category'],
            'label' => $e['label'],
            'amount_cents' => (int) $e['amount_cents'],
        ], $state['expenses']),
        'accounts' => array_map(static fn (array $a): array => [
            'id' => (int) $a['id'],
            'person_id' => $a['person_id'] !== null ? (int) $a['person_id'] : null,
            'kind' => $a['kind'],
            'name' => $a['name'],
            'balance_cents' => (int) $a['balance_cents'],
        ], $state['accounts']),
        'summary' => $state['summary'],
    ];
};

return [
    'GET /projects/budget-tracker' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);
        events()->record('project.opened', $uid, 'project', 'budget-tracker', ['project' => 'budget-tracker']);

        package_view('budget-tracker', 'app', [
            'title' => 'Budget Tracker',
            'fullWidth' => true,
            'csrf' => csrf_token(),
            'canManage' => in_array('budget-tracker.manage', $perms, true),
            'canPurchase' => in_array('budget-tracker.purchase.use', $perms, true),
        ]);
    }, ['auth', 'perm:projects.budget-tracker.open']),

    'GET /projects/budget-tracker/api/state' => fn () => dispatch(function () use ($jsonResponse, $publicState): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $state = budget_tracker()->loadState($uid);
        $jsonResponse($publicState($state));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/profile/setup' => fn () => dispatch(function () use ($jsonResponse, $publicState): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $mode = trim((string) ($_POST['mode'] ?? 'solo'));
        $names = [];
        if (isset($_POST['person_names']) && is_array($_POST['person_names'])) {
            $names = array_map('strval', $_POST['person_names']);
        } elseif (trim((string) ($_POST['person_names'] ?? '')) !== '') {
            $decoded = json_decode((string) $_POST['person_names'], true);
            if (is_array($decoded)) {
                $names = array_map('strval', $decoded);
            }
        }

        try {
            budget_tracker()->setupProfile($uid, $mode, $names);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.profile.updated', $uid, 'budget_profile', (string) $uid, [
            'mode' => $mode,
            'people' => count($names),
        ]);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/profile/complete' => fn () => dispatch(function () use ($jsonResponse, $publicState): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];

        try {
            budget_tracker()->completeOnboarding($uid);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.onboarding.completed', $uid, 'budget_profile', (string) $uid, []);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/income/save' => fn () => dispatch(function () use ($jsonResponse, $publicState, $dollarsToCents): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $personId = (int) ($_POST['person_id'] ?? 0);
        $incomeId = (int) ($_POST['income_id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $amountCents = $dollarsToCents((string) ($_POST['amount'] ?? $_POST['amount_cents'] ?? '0'));
        if (isset($_POST['amount_cents']) && is_numeric($_POST['amount_cents'])) {
            $amountCents = (int) $_POST['amount_cents'];
        }

        try {
            $row = budget_tracker()->saveIncome(
                $uid,
                $personId,
                $label,
                $amountCents,
                $incomeId > 0 ? $incomeId : null,
            );
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.income.saved', $uid, 'budget_income', (string) $row['id'], [
            'person_id' => $personId,
            'label' => $label,
            'amount_cents' => $amountCents,
        ]);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/income/delete' => fn () => dispatch(function () use ($jsonResponse, $publicState): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $incomeId = (int) ($_POST['income_id'] ?? 0);

        try {
            budget_tracker()->deleteIncome($uid, $incomeId);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.income.deleted', $uid, 'budget_income', (string) $incomeId, []);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/expense/save' => fn () => dispatch(function () use ($jsonResponse, $publicState, $dollarsToCents): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $personId = (int) ($_POST['person_id'] ?? 0);
        $expenseId = (int) ($_POST['expense_id'] ?? 0);
        $category = trim((string) ($_POST['category'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        $amountCents = $dollarsToCents((string) ($_POST['amount'] ?? '0'));
        if (isset($_POST['amount_cents']) && is_numeric($_POST['amount_cents'])) {
            $amountCents = (int) $_POST['amount_cents'];
        }

        try {
            $row = budget_tracker()->saveExpense(
                $uid,
                $personId,
                $category,
                $label,
                $amountCents,
                $expenseId > 0 ? $expenseId : null,
            );
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.expense.saved', $uid, 'budget_expense', (string) $row['id'], [
            'person_id' => $personId,
            'category' => $category,
            'amount_cents' => $amountCents,
        ]);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/expense/delete' => fn () => dispatch(function () use ($jsonResponse, $publicState): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $expenseId = (int) ($_POST['expense_id'] ?? 0);

        try {
            budget_tracker()->deleteExpense($uid, $expenseId);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.expense.deleted', $uid, 'budget_expense', (string) $expenseId, []);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/account/save' => fn () => dispatch(function () use ($jsonResponse, $publicState, $dollarsToCents): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $accountId = (int) ($_POST['account_id'] ?? 0);
        $kind = trim((string) ($_POST['kind'] ?? 'savings'));
        $name = trim((string) ($_POST['name'] ?? ''));
        $personId = (int) ($_POST['person_id'] ?? 0);
        $amountCents = $dollarsToCents((string) ($_POST['balance'] ?? '0'));
        if (isset($_POST['balance_cents']) && is_numeric($_POST['balance_cents'])) {
            $amountCents = (int) $_POST['balance_cents'];
        }

        try {
            $row = budget_tracker()->saveAccount(
                $uid,
                $kind,
                $name,
                $amountCents,
                $personId > 0 ? $personId : null,
                $accountId > 0 ? $accountId : null,
            );
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.account.saved', $uid, 'budget_account', (string) $row['id'], [
            'kind' => $kind,
            'name' => $name,
            'balance_cents' => $amountCents,
        ]);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/account/delete' => fn () => dispatch(function () use ($jsonResponse, $publicState): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $accountId = (int) ($_POST['account_id'] ?? 0);

        try {
            budget_tracker()->deleteAccount($uid, $accountId);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('budget-tracker.account.deleted', $uid, 'budget_account', (string) $accountId, []);

        $jsonResponse($publicState(budget_tracker()->loadState($uid)));
    }, ['auth', 'perm:budget-tracker.manage'], recordPageView: false),

    'POST /projects/budget-tracker/purchase/calculate' => fn () => dispatch(function () use ($jsonResponse, $dollarsToCents): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $purchaseCents = $dollarsToCents((string) ($_POST['amount'] ?? '0'));
        if (isset($_POST['amount_cents']) && is_numeric($_POST['amount_cents'])) {
            $purchaseCents = (int) $_POST['amount_cents'];
        }
        if ($purchaseCents < 1) {
            $jsonResponse(['error' => 'Enter a purchase amount'], 400);
        }

        $result = budget_tracker()->calculatePurchase($uid, $purchaseCents);

        events()->record('budget-tracker.purchase.calculated', $uid, 'budget_purchase', (string) $purchaseCents, [
            'amount_cents' => $purchaseCents,
            'percent_of_annual' => $result['share']['percent_of_annual'] ?? 0,
        ]);

        $jsonResponse($result);
    }, ['auth', 'perm:budget-tracker.purchase.use'], recordPageView: false),
];
