export function registerBudgetTracker(Alpine) {
  const fmt = (cents) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format((cents || 0) / 100);

  Alpine.data('budgetTrackerApp', () => ({
    csrf: '',
    canManage: false,
    canPurchase: false,
    loading: true,
    saving: false,
    error: '',
    tab: 'overview',
    section: 'budget',
    showWizard: false,
    configureMode: false,
    wizardStep: 'mode',
    wizardPersonIdx: 0,

    profile: { mode: 'solo', onboarding_complete: false },
    people: [],
    income: [],
    expenses: [],
    accounts: [],
    summary: null,

    form: {
      mode: 'solo',
      names: ['', ''],
      income: { person_id: 0, income_id: 0, label: '', amount: '' },
      expense: { person_id: 0, expense_id: 0, category: '', label: '', amount: '' },
      account: { account_id: 0, kind: 'savings', name: '', balance: '' },
      purchase: { amount: '', person_ids: [] },
    },

    purchaseResult: null,

  init() {
    const cfg = JSON.parse(this.$el.dataset.budgetInit || '{}');
    this.csrf = cfg.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    this.canManage = !!cfg.canManage;
    this.canPurchase = !!cfg.canPurchase;
    this.refresh();
  },

  fmt,

  async refresh(options = {}) {
    const silent = !!options.silent;
    if (!this.canManage) {
      this.loading = false;
      return;
    }
    if (!silent) {
      this.loading = true;
    }
    this.error = '';
    try {
      const res = await fetch('/projects/budget-tracker/api/state');
      if (!res.ok) throw new Error('Could not load budget');
      const data = await res.json();
      this.applyState(data);
    } catch (e) {
      this.error = e.message || 'Load failed';
    } finally {
      if (!silent) {
        this.loading = false;
      }
    }
  },

  applyState(data) {
    this.profile = data.profile || this.profile;
    this.people = data.people || [];
    this.income = data.income || [];
    this.expenses = data.expenses || [];
    this.accounts = data.accounts || [];
    this.summary = data.summary || null;
    if (!this.configureMode) {
      this.showWizard = !this.profile.onboarding_complete;
      if (this.showWizard) {
        this.wizardStep = this.people.length ? this.nextWizardStepAfterLoad() : 'mode';
      }
    }
    if (this.people[0]) this.form.names[0] = this.people[0].name;
    if (this.people[1]) this.form.names[1] = this.people[1].name;
    if (this.showWizard || this.configureMode) {
      this.form.mode = this.profile.mode || 'solo';
    }
    this.syncFormPersonIds();
    this.syncPurchasePersonIds();
  },

  syncPurchasePersonIds() {
    if (!this.people.length) {
      this.form.purchase.person_ids = [];
      return;
    }
    const valid = new Set(this.people.map((p) => Number(p.id)));
    const kept = this.form.purchase.person_ids
      .map(Number)
      .filter((id) => valid.has(id));
    this.form.purchase.person_ids = kept.length
      ? kept
      : this.people.map((p) => Number(p.id));
  },

  syncFormPersonIds() {
    if (!this.people.length) return;
    const valid = new Set(this.people.map((p) => Number(p.id)));
    if (!valid.has(Number(this.form.income.person_id))) {
      this.form.income.person_id = Number(this.people[0].id);
    }
    if (!valid.has(Number(this.form.expense.person_id))) {
      this.form.expense.person_id = Number(this.people[0].id);
    }
  },

  nextWizardStepAfterLoad() {
    if (!this.people.length) return 'mode';
    const p0 = this.people[0]?.id;
    const hasIncome0 = this.income.some((i) => i.person_id === p0);
    const hasExpense0 = this.expenses.some((e) => e.person_id === p0);
    if (!hasIncome0) return 'income-0';
    if (!hasExpense0) return 'expense-0';
    if (this.profile.mode === 'partner') {
      const p1 = this.people[1]?.id;
      if (!p1) return 'name-1';
      const hasIncome1 = this.income.some((i) => i.person_id === p1);
      const hasExpense1 = this.expenses.some((e) => e.person_id === p1);
      if (!hasIncome1) return 'income-1';
      if (!hasExpense1) return 'expense-1';
    }
    return 'savings';
  },

  openConfigure() {
    this.configureMode = true;
    this.section = 'budget';
    this.showWizard = true;
    this.error = '';
    this.refresh({ silent: true }).then(() => {
      this.wizardStep = this.people.length ? 'name-0' : 'mode';
      this.form.mode = this.profile.mode || 'solo';
      if (this.people[0]) this.form.names[0] = this.people[0].name;
      if (this.people[1]) this.form.names[1] = this.people[1].name;
    });
  },

  exitConfigure() {
    this.configureMode = false;
    this.showWizard = false;
    this.error = '';
  },

  openAfford() {
    this.section = 'afford';
    this.error = '';
    this.purchaseResult = null;
  },

  openBudget() {
    this.section = 'budget';
    this.error = '';
  },

  wizardBack() {
    const back = {
      'name-0': 'mode',
      'income-0': 'name-0',
      'expense-0': 'income-0',
      'income-1': 'expense-0',
      'expense-1': 'income-1',
      savings: this.profile.mode === 'partner' ? 'expense-1' : 'expense-0',
      assets: 'savings',
    };
    if (back[this.wizardStep]) {
      this.wizardStep = back[this.wizardStep];
      this.error = '';
    }
  },

  wizardTitle() {
    const map = {
      mode: 'Who are we tracking?',
      'name-0': 'What should we call you?',
      'name-1': "What is your partner's name?",
      'income-0': `Monthly income for ${this.personName(0)}`,
      'expense-0': `Monthly expenses for ${this.personName(0)}`,
      'income-1': `Monthly income for ${this.personName(1)}`,
      'expense-1': `Monthly expenses for ${this.personName(1)}`,
      savings: 'Savings accounts (optional)',
      assets: 'Investments & assets (optional)',
      done: 'All set!',
    };
    return map[this.wizardStep] || 'Budget setup';
  },

  personName(idx) {
    return this.people[idx]?.name || this.form.names[idx] || (idx === 0 ? 'You' : 'Partner');
  },

  currentPersonId(idx = null) {
    const i = idx ?? this.wizardPersonIdx;
    return this.people[i]?.id || 0;
  },

  incomeForPerson(personId) {
    return this.income.filter((i) => i.person_id === personId);
  },

  personIncomeCents(personId) {
    return this.incomeForPerson(personId).reduce((sum, row) => sum + row.amount_cents, 0);
  },

  purchaseIncludedLabel() {
    if (!this.purchaseResult?.included_people?.length) return '';
    return this.purchaseResult.included_people
      .map((p) => `${p.name} (${this.fmt(p.income_cents)})`)
      .join(', ');
  },

  expensesForPerson(personId) {
    return this.expenses.filter((e) => e.person_id === personId);
  },

  expenseCategories() {
    const cats = new Set();
    for (const row of this.expenses) {
      const cat = (row.category || '').trim();
      if (cat !== '') cats.add(cat);
    }
    return [...cats].sort((a, b) => a.localeCompare(b));
  },

  expensesGroupedBySection(personId) {
    const byCat = {};
    for (const row of this.expensesForPerson(personId)) {
      const cat = (row.category || '').trim() || 'Other';
      (byCat[cat] ||= []).push(row);
    }
    return Object.keys(byCat)
      .sort((a, b) => a.localeCompare(b))
      .map((category) => ({
        category,
        items: byCat[category].sort((a, b) =>
          this.expenseLineLabel(a).localeCompare(this.expenseLineLabel(b)),
        ),
      }));
  },

  expenseLineLabel(row) {
    const detail = (row.label || '').trim();
    return detail !== '' ? detail : row.category;
  },

  savingsAccounts() {
    return this.accounts.filter((a) => a.kind === 'savings');
  },

  assetAccounts() {
    return this.accounts.filter((a) => a.kind !== 'savings');
  },

  async post(url, fields) {
    const body = new URLSearchParams({ csrf: this.csrf, ...fields });
    const res = await fetch(url, { method: 'POST', body });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
  },

  async chooseMode(mode) {
    this.form.mode = mode;
    this.wizardStep = 'name-0';
  },

  async saveNamesAndContinue() {
    const names = this.form.mode === 'partner'
      ? [this.form.names[0], this.form.names[1]]
      : [this.form.names[0]];
    if (!names[0]?.trim()) {
      this.error = 'Please enter your name';
      return;
    }
    if (this.form.mode === 'partner' && !names[1]?.trim()) {
      this.error = "Please enter your partner's name";
      return;
    }
    const unchanged = this.configureMode
      && this.people.length > 0
      && this.form.mode === (this.profile.mode || 'solo')
      && names[0].trim() === (this.people[0]?.name || '').trim()
      && (this.form.mode !== 'partner' || names[1]?.trim() === (this.people[1]?.name || '').trim());
    if (unchanged) {
      this.error = '';
      this.wizardStep = 'income-0';
      this.resetIncomeForm(this.people[0]?.id);
      return;
    }
    this.saving = true;
    this.error = '';
    try {
      const data = await this.post('/projects/budget-tracker/profile/setup', {
        mode: this.form.mode,
        person_names: JSON.stringify(names),
      });
      this.applyState(data);
      this.wizardStep = 'income-0';
    } catch (e) {
      this.error = e.message;
    } finally {
      this.saving = false;
    }
  },

  resetIncomeForm(personId) {
    this.form.income = { person_id: personId, income_id: 0, label: '', amount: '' };
  },

  resetExpenseForm(personId, keepCategory = false) {
    this.form.expense = {
      person_id: personId,
      expense_id: 0,
      category: keepCategory ? (this.form.expense.category || '') : '',
      label: '',
      amount: '',
    };
  },

  async saveIncome(andContinue = false) {
    const f = this.form.income;
    const personId = f.person_id;
    const hasExisting = this.incomeForPerson(personId).length > 0;
    const hasNew = f.label.trim() && f.amount;

    if (!hasNew) {
      if (andContinue && hasExisting) {
        this.error = '';
        this.advanceFromIncome(personId);
        return;
      }
      this.error = 'Add a source name and amount';
      return;
    }
    this.saving = true;
    this.error = '';
    try {
      const data = await this.post('/projects/budget-tracker/income/save', {
        person_id: String(f.person_id),
        income_id: String(f.income_id || 0),
        label: f.label,
        amount: f.amount,
      });
      this.applyState(data);
      this.resetIncomeForm(f.person_id);
      if (andContinue && this.incomeForPerson(f.person_id).length > 0) {
        this.advanceFromIncome(f.person_id);
      }
    } catch (e) {
      this.error = e.message;
    } finally {
      this.saving = false;
    }
  },

  advanceFromIncome(personId) {
    const idx = this.people.findIndex((p) => p.id === personId);
    if (idx === 0) this.wizardStep = 'expense-0';
    else if (idx === 1) this.wizardStep = 'expense-1';
    this.resetExpenseForm(personId);
  },

  async saveExpense(andContinue = false) {
    const f = this.form.expense;
    const personId = f.person_id;
    const hasExisting = this.expensesForPerson(personId).length > 0;
    const hasNew = f.category.trim() && f.amount;

    if (!hasNew) {
      if (andContinue && hasExisting) {
        this.error = '';
        this.advanceFromExpense(personId);
        return;
      }
      this.error = 'Add a section and amount';
      return;
    }
    this.saving = true;
    this.error = '';
    try {
      const data = await this.post('/projects/budget-tracker/expense/save', {
        person_id: String(f.person_id),
        expense_id: String(f.expense_id || 0),
        category: f.category,
        label: f.label,
        amount: f.amount,
      });
      this.applyState(data);
      this.resetExpenseForm(f.person_id, !andContinue);
      if (andContinue && this.expensesForPerson(f.person_id).length > 0) {
        this.advanceFromExpense(f.person_id);
      }
    } catch (e) {
      this.error = e.message;
    } finally {
      this.saving = false;
    }
  },

  advanceFromExpense(personId) {
    const idx = this.people.findIndex((p) => p.id === personId);
    if (idx === 0 && this.profile.mode === 'partner') {
      this.wizardStep = 'income-1';
      this.resetIncomeForm(this.people[1]?.id);
    } else {
      this.wizardStep = 'savings';
    }
  },

  startWizardIncome(step) {
    const idx = step === 'income-1' ? 1 : 0;
    const pid = this.people[idx]?.id;
    if (pid) {
      this.form.income.person_id = pid;
      this.wizardStep = step;
    }
  },

  startWizardExpense(step) {
    const idx = step === 'expense-1' ? 1 : 0;
    const pid = this.people[idx]?.id;
    if (pid) {
      this.form.expense.person_id = pid;
      this.wizardStep = step;
    }
  },

  async saveAccount(kind = 'savings') {
    const f = this.form.account;
    f.kind = kind;
    if (!f.name.trim() || !f.balance) {
      this.error = 'Add an account name and balance';
      return;
    }
    this.saving = true;
    this.error = '';
    try {
      const data = await this.post('/projects/budget-tracker/account/save', {
        account_id: String(f.account_id || 0),
        kind: f.kind,
        name: f.name,
        balance: f.balance,
      });
      this.applyState(data);
      this.form.account = { account_id: 0, kind, name: '', balance: '' };
    } catch (e) {
      this.error = e.message;
    } finally {
      this.saving = false;
    }
  },

  async deleteIncome(id) {
    if (!confirm('Remove this income source?')) return;
    try {
      const data = await this.post('/projects/budget-tracker/income/delete', { income_id: String(id) });
      this.applyState(data);
      if (Number(this.form.income.income_id) === Number(id)) {
        this.cancelIncomeEdit();
      }
    } catch (e) {
      this.error = e.message;
    }
  },

  async deleteExpense(id) {
    if (!confirm('Remove this expense?')) return;
    try {
      const data = await this.post('/projects/budget-tracker/expense/delete', { expense_id: String(id) });
      this.applyState(data);
      if (Number(this.form.expense.expense_id) === Number(id)) {
        this.cancelExpenseEdit();
      }
    } catch (e) {
      this.error = e.message;
    }
  },

  async deleteAccount(id) {
    if (!confirm('Remove this account?')) return;
    try {
      const data = await this.post('/projects/budget-tracker/account/delete', { account_id: String(id) });
      this.applyState(data);
    } catch (e) {
      this.error = e.message;
    }
  },

  async finishOnboarding() {
    if (this.configureMode && this.profile.onboarding_complete) {
      this.exitConfigure();
      return;
    }
    this.saving = true;
    try {
      const data = await this.post('/projects/budget-tracker/profile/complete', {});
      this.applyState(data);
      this.configureMode = false;
      this.showWizard = false;
      this.section = 'budget';
      this.tab = 'overview';
    } catch (e) {
      this.error = e.message;
    } finally {
      this.saving = false;
    }
  },

  async calculatePurchase() {
    if (!this.canPurchase) return;
    if (!this.form.purchase.amount) {
      this.error = 'Enter a purchase amount';
      return;
    }
    if (!this.form.purchase.person_ids.length) {
      this.error = 'Select at least one person';
      return;
    }
    this.saving = true;
    this.error = '';
    this.purchaseResult = null;
    try {
      const data = await this.post('/projects/budget-tracker/purchase/calculate', {
        amount: this.form.purchase.amount,
        person_ids: JSON.stringify(this.form.purchase.person_ids.map(Number)),
      });
      this.purchaseResult = data;
    } catch (e) {
      this.error = e.message;
    } finally {
      this.saving = false;
    }
  },

  cancelIncomeEdit() {
    const personId = this.form.income.person_id || this.people[0]?.id;
    this.resetIncomeForm(personId);
    this.error = '';
  },

  cancelExpenseEdit() {
    const personId = this.form.expense.person_id || this.people[0]?.id;
    this.resetExpenseForm(personId);
    this.error = '';
  },

  editIncome(row) {
    this.section = 'budget';
    this.form.income = {
      person_id: row.person_id,
      income_id: row.id,
      label: row.label,
      amount: (row.amount_cents / 100).toFixed(2),
    };
    this.tab = 'income';
  },

  editExpense(row) {
    this.section = 'budget';
    this.form.expense = {
      person_id: row.person_id,
      expense_id: row.id,
      category: row.category,
      label: row.label || '',
      amount: (row.amount_cents / 100).toFixed(2),
    };
    this.tab = 'expenses';
  },

  netClass(cents) {
    return cents >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
  },
  }));
}
