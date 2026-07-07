<?php

declare(strict_types=1);

return [
    'id' => 'budget-tracker',
    'project' => [
        'name' => 'Budget Tracker',
        'description' => 'Track income, expenses, savings, and assets — plus purchase affordability.',
        'icon' => '💰',
        'path' => '/projects/budget-tracker',
        'permissions' => [
            'view' => 'projects.budget-tracker.view',
            'open' => 'projects.budget-tracker.open',
        ],
    ],
    'permissions' => [
        ['name' => 'projects.budget-tracker.view', 'description' => 'See Budget Tracker on dashboard', 'category' => 'projects'],
        ['name' => 'projects.budget-tracker.open', 'description' => 'Open Budget Tracker project', 'category' => 'projects'],
        ['name' => 'budget-tracker.manage', 'description' => 'Manage own budget, income, expenses, and assets', 'category' => 'budget-tracker'],
        ['name' => 'budget-tracker.purchase.use', 'description' => 'Use purchase affordability calculator', 'category' => 'budget-tracker'],
    ],
    'routes' => [
        ['method' => 'GET', 'path' => '/projects/budget-tracker', 'permission' => 'projects.budget-tracker.open', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/projects/budget-tracker/api/state', 'permission' => 'budget-tracker.manage', 'event' => 'page.viewed', 'note' => 'Load budget state'],
    ],
    'mutations' => [
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/profile/setup',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.profile.updated',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/profile/complete',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.onboarding.completed',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/income/save',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.income.saved',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/income/delete',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.income.deleted',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/expense/save',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.expense.saved',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/expense/delete',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.expense.deleted',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/account/save',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.account.saved',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/account/delete',
            'permission' => 'budget-tracker.manage',
            'event' => 'budget-tracker.account.deleted',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/budget-tracker/purchase/calculate',
            'permission' => 'budget-tracker.purchase.use',
            'event' => 'budget-tracker.purchase.calculated',
        ],
    ],
    'events' => [
        ['type' => 'budget-tracker.profile.updated', 'automatic' => false, 'note' => 'Budget profile mode or people updated'],
        ['type' => 'budget-tracker.onboarding.completed', 'automatic' => false, 'note' => 'User finished budget onboarding wizard'],
        ['type' => 'budget-tracker.income.saved', 'automatic' => false, 'note' => 'Income source created or updated'],
        ['type' => 'budget-tracker.income.deleted', 'automatic' => false, 'note' => 'Income source removed'],
        ['type' => 'budget-tracker.expense.saved', 'automatic' => false, 'note' => 'Expense created or updated'],
        ['type' => 'budget-tracker.expense.deleted', 'automatic' => false, 'note' => 'Expense removed'],
        ['type' => 'budget-tracker.account.saved', 'automatic' => false, 'note' => 'Savings or asset account saved'],
        ['type' => 'budget-tracker.account.deleted', 'automatic' => false, 'note' => 'Savings or asset account removed'],
        ['type' => 'budget-tracker.purchase.calculated', 'automatic' => false, 'note' => 'Purchase affordability calculated'],
    ],
];
