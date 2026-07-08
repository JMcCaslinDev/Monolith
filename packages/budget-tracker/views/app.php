<?php

/** @var string $csrf */
/** @var bool $canManage */
/** @var bool $canPurchase */
$init = ['csrf' => $csrf, 'canManage' => $canManage, 'canPurchase' => $canPurchase];
?>
<style>
.budget-shell { min-height: calc(100vh - 3.5rem); }
.budget-bubble {
  border-radius: 1.5rem;
  box-shadow: 0 8px 32px rgba(99, 102, 241, 0.12), 0 2px 8px rgba(0,0,0,0.04);
}
.dark .budget-bubble {
  box-shadow: 0 8px 32px rgba(99, 102, 241, 0.2), 0 2px 8px rgba(0,0,0,0.3);
}
</style>

<div
  class="budget-shell bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100 p-4 md:p-8"
  x-data="budgetTrackerApp"
  data-budget-init="<?= htmlspecialchars(json_encode($init, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>"
  x-cloak
>
  <div class="max-w-4xl mx-auto space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">💰 Budget & Asset Tracker</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Your live financial snapshot — income, expenses, and net worth.</p>
      </div>
      <template x-if="summary && !showWizard">
        <div class="flex flex-wrap items-center gap-3">
          <button type="button" @click="openConfigure()"
            class="rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">
            Configure
          </button>
          <div class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 backdrop-blur px-5 py-3 text-right">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Monthly net</p>
            <p class="text-2xl font-bold" :class="netClass(summary.net_monthly_cents)" x-text="fmt(summary.net_monthly_cents)"></p>
          </div>
        </div>
      </template>
    </header>

    <p x-show="error" class="text-sm text-rose-600 dark:text-rose-400 budget-bubble bg-rose-50 dark:bg-rose-950/40 px-4 py-2" x-text="error"></p>
    <p x-show="loading" class="text-sm text-gray-600 dark:text-gray-400">Loading your budget…</p>

    <datalist id="budget-expense-sections">
      <template x-for="cat in expenseCategories()" :key="cat">
        <option :value="cat"></option>
      </template>
    </datalist>

    <!-- Onboarding wizard -->
    <template x-if="showWizard && !loading">
      <div class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 backdrop-blur p-6 md:p-8 space-y-6">
        <div>
          <button type="button" x-show="configureMode && wizardStep === 'mode'" @click="exitConfigure()"
            class="mb-2 text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300">← Back to budget</button>
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white" x-text="wizardTitle()"></h2>
        </div>

        <!-- Step: mode -->
        <div x-show="wizardStep === 'mode'" class="grid sm:grid-cols-2 gap-4">
          <button type="button" @click="chooseMode('solo')"
            class="budget-bubble p-6 text-left border-2 border-transparent hover:border-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 transition">
            <span class="text-3xl">🙋</span>
            <p class="font-semibold mt-2 text-gray-900 dark:text-white">Just me</p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Track your own income and expenses.</p>
          </button>
          <button type="button" @click="chooseMode('partner')"
            class="budget-bubble p-6 text-left border-2 border-transparent hover:border-violet-400 bg-violet-50 dark:bg-violet-950/40 transition">
            <span class="text-3xl">👫</span>
            <p class="font-semibold mt-2 text-gray-900 dark:text-white">Me & partner</p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Separate incomes and expenses for each person. Savings and assets stay shared.</p>
          </button>
        </div>

        <!-- Step: names -->
        <div x-show="wizardStep === 'name-0' || wizardStep === 'name-1'" class="space-y-4 max-w-md">
          <template x-if="wizardStep === 'name-0'">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your name</label>
              <input type="text" x-model="form.names[0]" class="input-field rounded-2xl px-4 py-3 w-full" placeholder="Alex">
            </div>
          </template>
          <template x-if="wizardStep === 'name-0' && form.mode === 'partner'">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Partner's name</label>
              <input type="text" x-model="form.names[1]" class="input-field rounded-2xl px-4 py-3 w-full" placeholder="Jordan">
            </div>
          </template>
          <div class="flex flex-wrap gap-3">
            <button type="button" @click="wizardBack()"
              class="rounded-2xl border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 px-5 py-3 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">← Back</button>
            <button type="button" @click="saveNamesAndContinue()" :disabled="saving"
              class="rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 font-medium disabled:opacity-50">
              Continue →
            </button>
          </div>
        </div>

        <!-- Step: income -->
        <template x-if="wizardStep === 'income-0' || wizardStep === 'income-1'">
          <div class="space-y-4">
            <p x-show="!incomeForPerson(currentPersonId(wizardStep === 'income-1' ? 1 : 0)).length"
              class="text-sm text-gray-600 dark:text-gray-400">No income saved yet — add a source below.</p>
            <div class="flex flex-wrap gap-2" x-show="incomeForPerson(currentPersonId(wizardStep === 'income-1' ? 1 : 0)).length">
              <template x-for="row in incomeForPerson(currentPersonId(wizardStep === 'income-1' ? 1 : 0))" :key="row.id">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-3 py-1 text-sm">
                  <span x-text="row.label + ': ' + fmt(row.amount_cents)"></span>
                </span>
              </template>
            </div>
            <div class="grid sm:grid-cols-2 gap-3 max-w-lg" x-init="form.income.person_id = currentPersonId(wizardStep === 'income-1' ? 1 : 0)">
              <input type="text" x-model="form.income.label" placeholder="Source (e.g. Salary)" class="input-field rounded-2xl px-4 py-3 w-full">
              <input type="number" x-model="form.income.amount" min="0" step="0.01" placeholder="Monthly $" class="input-field rounded-2xl px-4 py-3 w-full">
            </div>
            <div class="flex flex-wrap gap-3">
              <button type="button" @click="wizardBack()"
                class="rounded-2xl border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 px-5 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">← Back</button>
              <button type="button" @click="saveIncome(false)" :disabled="saving" class="rounded-2xl border border-indigo-300 dark:border-indigo-600 px-5 py-2.5 text-sm font-medium text-indigo-700 dark:text-indigo-300">+ Add another source</button>
              <button type="button" @click="saveIncome(true)" :disabled="saving" class="rounded-2xl bg-indigo-600 text-white px-5 py-2.5 text-sm font-medium">Continue →</button>
            </div>
          </div>
        </template>

        <!-- Step: expenses -->
        <template x-if="wizardStep === 'expense-0' || wizardStep === 'expense-1'">
          <div class="space-y-4">
            <template x-for="group in expensesGroupedBySection(currentPersonId(wizardStep === 'expense-1' ? 1 : 0))" :key="group.category">
              <div x-show="group.items.length">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2" x-text="group.category"></p>
                <div class="flex flex-wrap gap-2">
                  <template x-for="row in group.items" :key="row.id">
                    <span class="inline-flex rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-200 px-3 py-1 text-sm"
                      x-text="expenseLineLabel(row) + ': ' + fmt(row.amount_cents)"></span>
                  </template>
                </div>
              </div>
            </template>
            <div class="grid sm:grid-cols-3 gap-3 max-w-2xl" x-init="form.expense.person_id = currentPersonId(wizardStep === 'expense-1' ? 1 : 0)">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Section</label>
                <input type="text" x-model="form.expense.category" list="budget-expense-sections" placeholder="e.g. Transportation" class="input-field rounded-2xl px-4 py-3 w-full">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expense name</label>
                <input type="text" x-model="form.expense.label" placeholder="e.g. Car payment" class="input-field rounded-2xl px-4 py-3 w-full">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monthly amount</label>
                <input type="number" x-model="form.expense.amount" min="0" step="0.01" placeholder="$" class="input-field rounded-2xl px-4 py-3 w-full">
              </div>
            </div>
            <div class="flex flex-wrap gap-3">
              <button type="button" @click="wizardBack()"
                class="rounded-2xl border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 px-5 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">← Back</button>
              <button type="button" @click="saveExpense(false)" :disabled="saving" class="rounded-2xl border border-rose-300 px-5 py-2.5 text-sm font-medium text-rose-700 dark:text-rose-300">+ Add another expense</button>
              <button type="button" @click="saveExpense(true)" :disabled="saving" class="rounded-2xl bg-indigo-600 text-white px-5 py-2.5 text-sm font-medium">Continue →</button>
            </div>
          </div>
        </template>

        <!-- Step: savings -->
        <div x-show="wizardStep === 'savings'" class="space-y-4">
          <p class="text-sm text-gray-600 dark:text-gray-400">List savings accounts with current balances. Skip if you prefer.</p>
          <div class="flex flex-wrap gap-2">
            <template x-for="a in savingsAccounts()" :key="a.id">
              <span class="rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200 px-3 py-1 text-sm" x-text="a.name + ': ' + fmt(a.balance_cents)"></span>
            </template>
          </div>
          <div class="grid sm:grid-cols-2 gap-3 max-w-lg" x-init="form.account.kind = 'savings'">
            <input type="text" x-model="form.account.name" placeholder="Account name" class="input-field rounded-2xl px-4 py-3 w-full">
            <input type="number" x-model="form.account.balance" min="0" step="0.01" placeholder="Balance $" class="input-field rounded-2xl px-4 py-3 w-full">
          </div>
          <div class="flex flex-wrap gap-3">
            <button type="button" @click="wizardBack()"
              class="rounded-2xl border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 px-5 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">← Back</button>
            <button type="button" @click="saveAccount('savings')" :disabled="saving" class="rounded-2xl border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 px-5 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">+ Add account</button>
            <button type="button" @click="wizardStep = 'assets'" class="rounded-2xl bg-indigo-600 text-white px-5 py-2.5 text-sm font-medium">Continue →</button>
          </div>
        </div>

        <!-- Step: assets -->
        <div x-show="wizardStep === 'assets'" class="space-y-4">
          <p class="text-sm text-gray-600 dark:text-gray-400">Brokerage, retirement, or other investment accounts.</p>
          <div class="flex flex-wrap gap-2">
            <template x-for="a in assetAccounts()" :key="a.id">
              <span class="rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 px-3 py-1 text-sm" x-text="a.name + ': ' + fmt(a.balance_cents)"></span>
            </template>
          </div>
          <div class="grid sm:grid-cols-3 gap-3 max-w-2xl">
            <select x-model="form.account.kind" class="input-field rounded-2xl px-4 py-3 w-full">
              <option value="brokerage">Brokerage</option>
              <option value="retirement">Retirement (401k/IRA)</option>
              <option value="other">Other asset</option>
            </select>
            <input type="text" x-model="form.account.name" placeholder="Account name" class="input-field rounded-2xl px-4 py-3 w-full">
            <input type="number" x-model="form.account.balance" min="0" step="0.01" placeholder="Value $" class="input-field rounded-2xl px-4 py-3 w-full">
          </div>
          <div class="flex flex-wrap gap-3">
            <button type="button" @click="wizardBack()"
              class="rounded-2xl border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 px-5 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">← Back</button>
            <button type="button" @click="saveAccount(form.account.kind)" :disabled="saving" class="rounded-2xl border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 px-5 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">+ Add asset</button>
            <button type="button" @click="finishOnboarding()" :disabled="saving"
              class="rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 font-semibold disabled:opacity-50"
              x-text="configureMode ? 'Save & return →' : 'See my budget →'"></button>
          </div>
        </div>
      </div>
    </template>

    <!-- Main dashboard -->
    <template x-if="!showWizard && !loading && summary">
      <div class="space-y-6">
        <div class="flex flex-wrap gap-3 border-b border-gray-200 dark:border-gray-700 pb-6">
          <button type="button" @click="openBudget()"
            class="rounded-2xl px-5 py-3 text-sm font-semibold transition"
            :class="section === 'budget' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800'">
            My Budget
          </button>
          <button type="button" x-show="canPurchase" @click="openAfford()"
            class="rounded-2xl px-5 py-3 text-sm font-semibold transition"
            :class="section === 'afford' ? 'bg-violet-600 text-white' : 'bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800'">
            Can I afford it?
          </button>
        </div>

        <template x-if="section === 'budget'">
        <div class="space-y-6">
        <nav class="flex flex-wrap items-center gap-2">
          <button type="button" x-show="tab !== 'overview'" @click="tab = 'overview'"
            class="text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300 mr-1">← Overview</button>
          <template x-for="t in ['overview','income','expenses','assets']" :key="t">
            <button type="button" @click="tab = t"
              class="rounded-full px-4 py-2 text-sm font-medium capitalize transition"
              :class="tab === t ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-600'"
              x-text="t"></button>
          </template>
        </nav>

        <!-- Overview -->
        <div x-show="tab === 'overview'" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-5">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Total income / mo</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" x-text="fmt(summary.total_income_cents)"></p>
          </div>
          <div class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-5">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Total expenses / mo</p>
            <p class="text-2xl font-bold text-rose-600 dark:text-rose-400" x-text="fmt(summary.total_expense_cents)"></p>
          </div>
          <div class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-5">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Net worth (saved)</p>
            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400" x-text="fmt(summary.net_worth_cents)"></p>
          </div>
          <template x-for="p in summary.people" :key="p.person_id">
            <div class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-5 sm:col-span-2 lg:col-span-3">
              <h3 class="font-semibold text-gray-900 dark:text-white" x-text="p.name"></h3>
              <div class="mt-2 grid sm:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-500 dark:text-gray-400">Income</span><p class="font-medium text-emerald-600 dark:text-emerald-400" x-text="fmt(p.income_cents)"></p></div>
                <div><span class="text-gray-500 dark:text-gray-400">Expenses</span><p class="font-medium text-rose-600 dark:text-rose-400" x-text="fmt(p.expense_cents)"></p></div>
                <div><span class="text-gray-500 dark:text-gray-400">Net</span><p class="font-medium" :class="netClass(p.net_cents)" x-text="fmt(p.net_cents)"></p></div>
              </div>
            </div>
          </template>
        </div>

        <!-- Income tab -->
        <div x-show="tab === 'income'" class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-6 space-y-6">
          <template x-for="(person, idx) in people" :key="person.id">
            <div>
              <h3 class="font-semibold text-gray-900 dark:text-white" x-text="person.name + ' — income sources'"></h3>
              <ul class="mt-2 space-y-2">
                <template x-for="row in incomeForPerson(person.id)" :key="row.id">
                  <li class="flex items-center justify-between rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3 text-gray-900 dark:text-emerald-100">
                    <span x-text="row.label + ' — ' + fmt(row.amount_cents) + '/mo'"></span>
                    <div class="flex gap-2">
                      <button type="button" @click="editIncome(row)" class="text-xs text-indigo-600 underline">Edit</button>
                      <button type="button" @click="deleteIncome(row.id)" class="text-xs text-rose-600 underline">Remove</button>
                    </div>
                  </li>
                </template>
              </ul>
            </div>
          </template>
          <form @submit.prevent="saveIncome(false)" class="grid sm:grid-cols-4 gap-3 border-t border-gray-200 dark:border-gray-700 pt-4">
            <select x-model.number="form.income.person_id" class="input-field rounded-2xl px-3 py-2 w-full">
              <template x-for="p in people" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
            </select>
            <input type="text" x-model="form.income.label" placeholder="Source" class="input-field rounded-2xl px-3 py-2 w-full" required>
            <input type="number" x-model="form.income.amount" step="0.01" min="0" placeholder="Monthly $" class="input-field rounded-2xl px-3 py-2 w-full" required>
            <div class="flex gap-2">
              <button type="submit" class="flex-1 rounded-2xl bg-emerald-600 text-white py-2 font-medium" x-text="form.income.income_id ? 'Update' : 'Add income'"></button>
              <button type="button" x-show="form.income.income_id" @click="cancelIncomeEdit()"
                class="rounded-2xl border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Cancel</button>
            </div>
          </form>
        </div>

        <!-- Expenses tab -->
        <div x-show="tab === 'expenses'" class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-6 space-y-6">
          <template x-for="person in people" :key="person.id">
            <div class="space-y-4">
              <h3 class="font-semibold text-gray-900 dark:text-white" x-text="person.name + ' — expenses'"></h3>
              <template x-for="group in expensesGroupedBySection(person.id)" :key="person.id + '-' + group.category">
                <div>
                  <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2" x-text="group.category"></h4>
                  <ul class="space-y-2">
                    <template x-for="row in group.items" :key="row.id">
                      <li class="flex items-center justify-between rounded-2xl bg-rose-50 dark:bg-rose-950/30 px-4 py-3 text-gray-900 dark:text-rose-100">
                        <span x-text="expenseLineLabel(row) + ' — ' + fmt(row.amount_cents) + '/mo'"></span>
                        <div class="flex gap-2">
                          <button type="button" @click="editExpense(row)" class="text-xs text-indigo-600 dark:text-indigo-400 underline">Edit</button>
                          <button type="button" @click="deleteExpense(row.id)" class="text-xs text-rose-600 dark:text-rose-400 underline">Remove</button>
                        </div>
                      </li>
                    </template>
                  </ul>
                </div>
              </template>
            </div>
          </template>
          <form @submit.prevent="saveExpense(false)" class="grid sm:grid-cols-5 gap-3 border-t border-gray-200 dark:border-gray-700 pt-4">
            <select x-model.number="form.expense.person_id" class="input-field rounded-2xl px-3 py-2 w-full">
              <template x-for="p in people" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
            </select>
            <input type="text" x-model="form.expense.category" list="budget-expense-sections" placeholder="Section" class="input-field rounded-2xl px-3 py-2 w-full" required>
            <input type="text" x-model="form.expense.label" placeholder="Expense name" class="input-field rounded-2xl px-3 py-2 w-full">
            <input type="number" x-model="form.expense.amount" step="0.01" min="0" placeholder="$" class="input-field rounded-2xl px-3 py-2 w-full" required>
            <div class="flex gap-2">
              <button type="submit" class="flex-1 rounded-2xl bg-rose-600 text-white py-2" x-text="form.expense.expense_id ? 'Update' : 'Add expense'"></button>
              <button type="button" x-show="form.expense.expense_id" @click="cancelExpenseEdit()"
                class="rounded-2xl border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Cancel</button>
            </div>
          </form>
        </div>

        <!-- Assets tab -->
        <div x-show="tab === 'assets'" class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-6 space-y-4">
          <p class="text-sm text-gray-600 dark:text-gray-400">Household savings and investments — one combined list, not split by person.</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <h3 class="font-semibold text-sky-700 dark:text-sky-300">Savings</h3>
              <p class="text-2xl font-bold text-gray-900 dark:text-white" x-text="fmt(summary.savings_cents)"></p>
              <ul class="mt-2 space-y-1 text-sm">
                <template x-for="a in savingsAccounts()" :key="a.id">
                  <li class="flex justify-between rounded-xl bg-sky-50 dark:bg-sky-950/30 px-3 py-2 text-gray-900 dark:text-sky-100">
                    <span x-text="a.name"></span>
                    <span class="flex gap-2"><span x-text="fmt(a.balance_cents)"></span>
                    <button type="button" @click="deleteAccount(a.id)" class="text-rose-600 text-xs">×</button></span>
                  </li>
                </template>
              </ul>
            </div>
            <div>
              <h3 class="font-semibold text-amber-700 dark:text-amber-300">Investments & assets</h3>
              <p class="text-2xl font-bold text-gray-900 dark:text-white" x-text="fmt(summary.assets_cents)"></p>
              <ul class="mt-2 space-y-1 text-sm">
                <template x-for="a in assetAccounts()" :key="a.id">
                  <li class="flex justify-between rounded-xl bg-amber-50 dark:bg-amber-950/30 px-3 py-2 text-gray-900 dark:text-amber-100">
                    <span x-text="a.kind + ': ' + a.name"></span>
                    <span class="flex gap-2"><span x-text="fmt(a.balance_cents)"></span>
                    <button type="button" @click="deleteAccount(a.id)" class="text-rose-600 text-xs">×</button></span>
                  </li>
                </template>
              </ul>
            </div>
          </div>
          <form @submit.prevent="saveAccount(form.account.kind)" class="grid sm:grid-cols-4 gap-3 border-t border-gray-200 dark:border-gray-700 pt-4">
            <select x-model="form.account.kind" class="input-field rounded-2xl px-3 py-2 w-full">
              <option value="savings">Savings</option>
              <option value="brokerage">Brokerage</option>
              <option value="retirement">Retirement</option>
              <option value="other">Other</option>
            </select>
            <input type="text" x-model="form.account.name" placeholder="Account name" class="input-field rounded-2xl px-3 py-2 w-full" required>
            <input type="number" x-model="form.account.balance" step="0.01" min="0" placeholder="Balance $" class="input-field rounded-2xl px-3 py-2 w-full" required>
            <button type="submit" class="rounded-2xl bg-indigo-600 text-white py-2">Add account</button>
          </form>
        </div>
        </div>
        </template>

        <!-- Purchase calculator (separate section) -->
        <template x-if="section === 'afford' && canPurchase">
        <div class="budget-bubble bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 p-6 space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Can I afford it?</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">See what a purchase costs relative to your income — and what that money could grow into if invested instead.</p>
          </div>
          <form @submit.prevent="calculatePurchase()" class="space-y-4">
            <div class="flex flex-wrap gap-3 items-end">
              <div>
                <label class="text-sm text-gray-600 dark:text-gray-400">Purchase amount</label>
                <input type="text" inputmode="decimal" autocomplete="off"
                  x-model="form.purchase.amount"
                  @focus="if (form.purchase.amount === '0' || form.purchase.amount === 0) form.purchase.amount = ''"
                  placeholder="$" class="input-field rounded-2xl px-4 py-3 w-40 mt-1">
              </div>
              <button type="submit" :disabled="saving" class="rounded-2xl bg-violet-600 hover:bg-violet-500 text-white px-6 py-3 font-medium disabled:opacity-50">Calculate</button>
            </div>
            <fieldset class="space-y-2" x-show="people.length">
              <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">Factor in income from</legend>
              <template x-for="p in people" :key="p.id">
                <label class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                  <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600"
                    :value="p.id" x-model="form.purchase.person_ids">
                  <span x-text="p.name"></span>
                  <span class="text-gray-500 dark:text-gray-400" x-text="fmt(personIncomeCents(p.id)) + '/mo'"></span>
                </label>
              </template>
            </fieldset>
          </form>

          <template x-if="purchaseResult">
            <div class="space-y-6 border-t border-gray-200 dark:border-gray-700 pt-6">
              <p class="text-sm text-gray-600 dark:text-gray-400"
                x-show="purchaseResult.share.monthly_income_cents > 0">
                Based on monthly income of
                <span class="font-medium text-gray-900 dark:text-gray-100" x-text="fmt(purchaseResult.share.monthly_income_cents)"></span>
                from
                <span class="font-medium text-gray-900 dark:text-gray-100" x-text="purchaseIncludedLabel()"></span>.
                Assumes 40 hr/week →
                <span class="font-medium text-gray-900 dark:text-gray-100" x-text="fmt(purchaseResult.share.assumed_hourly_cents)"></span>/hr
                (monthly ÷ 160) and 8-hour work days.
              </p>
              <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="rounded-2xl bg-violet-50 dark:bg-violet-950/40 p-4 text-gray-900 dark:text-violet-100">
                  <p class="text-xs text-gray-600 dark:text-violet-300">Assumed hourly rate</p>
                  <p class="text-xl font-bold" x-text="fmt(purchaseResult.share.assumed_hourly_cents)"></p>
                </div>
                <div class="rounded-2xl bg-violet-50 dark:bg-violet-950/40 p-4 text-gray-900 dark:text-violet-100">
                  <p class="text-xs text-gray-600 dark:text-violet-300">% of annual income</p>
                  <p class="text-xl font-bold" x-text="purchaseResult.share.percent_of_annual + '%'"></p>
                </div>
                <div class="rounded-2xl bg-violet-50 dark:bg-violet-950/40 p-4 text-gray-900 dark:text-violet-100">
                  <p class="text-xs text-gray-600 dark:text-violet-300">% of monthly income</p>
                  <p class="text-xl font-bold" x-text="purchaseResult.share.percent_of_monthly + '%'"></p>
                </div>
                <div class="rounded-2xl bg-violet-50 dark:bg-violet-950/40 p-4 text-gray-900 dark:text-violet-100">
                  <p class="text-xs text-gray-600 dark:text-violet-300">Work days (8 hr)</p>
                  <p class="text-xl font-bold" x-text="purchaseResult.share.days_of_income"></p>
                </div>
                <div class="rounded-2xl bg-violet-50 dark:bg-violet-950/40 p-4 text-gray-900 dark:text-violet-100">
                  <p class="text-xs text-gray-600 dark:text-violet-300">Work hours</p>
                  <p class="text-xl font-bold" x-text="purchaseResult.share.hours_of_income"></p>
                </div>
              </div>

              <div>
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">If invested instead (compound growth)</h3>
                <div class="grid gap-4 md:grid-cols-3">
                  <template x-for="proj in purchaseResult.projections" :key="proj.label">
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-5 space-y-4">
                      <p class="text-base font-semibold text-indigo-700 dark:text-indigo-300 leading-snug" x-text="proj.label"></p>
                      <div class="space-y-3">
                        <template x-for="h in proj.horizons" :key="h.years">
                          <div class="rounded-xl bg-gray-100 dark:bg-gray-800 p-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400" x-text="h.years + (h.years === 1 ? ' year' : ' years')"></p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="fmt(h.value_cents)"></p>
                            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400 mt-1" x-text="'+' + fmt(h.gain_cents) + ' growth'"></p>
                          </div>
                        </template>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </template>
        </div>
        </template>
      </div>
    </template>
  </div>
</div>
