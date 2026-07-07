<?php
/** @var array<string, array<string, mixed>> $categories */
/** @var string $activeTool */
$categoryIds = array_keys($categories);
?>
<div
    class="devtools-shell"
    x-data="devtoolsApp"
    data-devtools-init='<?= json_encode(['tool' => $activeTool, 'categories' => $categoryIds], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>'
>
    <aside class="devtools-sidebar w-72 shrink-0 border-r border-slate-800 bg-slate-900/80">
        <div class="p-4 border-b border-slate-800">
            <a href="/" class="text-xs text-muted hover:text-slate-300">← Dashboard</a>
            <h1 class="mt-2 text-lg font-semibold">Dev Tools</h1>
        </div>
        <nav class="p-2">
            <?php foreach ($categories as $catId => $cat) : ?>
            <div class="mb-2">
                <button
                    type="button"
                    @click="toggleCategory('<?= htmlspecialchars($catId, ENT_QUOTES) ?>')"
                    class="flex w-full items-center justify-between rounded-md px-2 py-2.5 text-left text-sm font-semibold uppercase tracking-wide text-slate-400 hover:bg-slate-800 hover:text-slate-200"
                    :class="openCategories['<?= htmlspecialchars($catId, ENT_QUOTES) ?>'] ? 'text-slate-300' : 'text-slate-500'"
                >
                    <span class="flex items-center gap-2">
                        <span class="font-mono text-xs opacity-70"><?= htmlspecialchars($cat['icon'], ENT_QUOTES) ?></span>
                        <?= htmlspecialchars($cat['label'], ENT_QUOTES) ?>
                    </span>
                    <span class="text-base leading-none opacity-70" x-text="openCategories['<?= htmlspecialchars($catId, ENT_QUOTES) ?>'] ? '▾' : '▸'"></span>
                </button>
                <div
                    x-show="openCategories['<?= htmlspecialchars($catId, ENT_QUOTES) ?>']"
                    x-cloak
                    class="mt-0.5 space-y-0.5 border-l-2 border-slate-700/50 ml-3 pl-2"
                >
                    <?php foreach ($cat['tools'] as $tool) : ?>
                    <button
                        type="button"
                        data-tool-slug="<?= htmlspecialchars($tool['slug'], ENT_QUOTES) ?>"
                        data-tool-name="<?= htmlspecialchars($tool['name'], ENT_QUOTES) ?>"
                        @click="selectTool('<?= htmlspecialchars($tool['slug'], ENT_QUOTES) ?>')"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-slate-800/80"
                        :class="isActive('<?= htmlspecialchars($tool['slug'], ENT_QUOTES) ?>') ? 'bg-indigo-500/25 text-indigo-200 font-medium' : 'text-slate-400'"
                    >
                        <span class="w-5 shrink-0 text-center font-mono text-[10px] opacity-60"><?= htmlspecialchars($tool['icon'], ENT_QUOTES) ?></span>
                        <span class="truncate"><?= htmlspecialchars($tool['name'], ENT_QUOTES) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div x-ref="mainPanel" class="devtools-main min-w-0 flex-1 bg-slate-950/30 p-6">
        <template x-if="!activeTool">
            <p class="text-muted">Select a tool from the sidebar.</p>
        </template>

        <div x-show="activeTool">
            <h2 class="text-xl font-semibold" x-text="toolTitle()"></h2>
            <p class="mt-1 text-sm text-muted">Runs locally in your browser — nothing leaves your machine unless you use Certificate parse.</p>

            <p
                x-show="error || (!isActive('uuid') && status)"
                class="mt-3 min-h-[1.25rem] text-sm"
                :class="error ? 'text-red-400' : 'text-emerald-400'"
                x-text="error || status"
            ></p>

            <?php require __DIR__ . '/partials/tool-panels.php'; ?>
        </div>
    </div>
</div>

<style>
.devtools-shell { display: flex; height: calc(100vh - 3.5rem); width: 100%; overflow: hidden; }
.devtools-sidebar, .devtools-main { min-height: 0; overflow-y: auto; }
</style>
