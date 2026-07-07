<?php

/** @var string $csrf */
/** @var bool $canManage */
/** @var array<string, string> $palette */
$init = ['csrf' => $csrf, 'canManage' => $canManage, 'palette' => $palette];
?>
<style>
.stickies-shell { min-height: calc(100vh - 3.5rem); }
.stickies-board-bg {
  background: linear-gradient(160deg, #f8fafc 0%, #e2e8f0 40%, #f1f5f9 100%);
}
.dark .stickies-board-bg {
  background: linear-gradient(160deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}
.sticky-card {
  position: absolute;
  border-radius: 4px;
  box-shadow: 2px 4px 12px rgba(0,0,0,0.15), 0 1px 2px rgba(0,0,0,0.08);
  cursor: grab;
  transition: box-shadow 0.15s ease;
  padding: 0.65rem 0.75rem;
}
.sticky-card:active { cursor: grabbing; }
.sticky-card:hover { box-shadow: 4px 8px 20px rgba(0,0,0,0.2); z-index: 10; }
.sticky-icon-btn {
  width: 1.75rem;
  height: 1.75rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  font-size: 1rem;
  line-height: 1;
  background: rgba(0,0,0,0.1);
  flex-shrink: 0;
}
.sticky-icon-btn:hover { background: rgba(0,0,0,0.16); }
.sticky-section-canvas {
  position: relative;
  min-height: 880px;
  border-radius: 0.75rem;
  border: 1px dashed rgba(100,116,139,0.35);
}
.sticky-section-canvas.has-notch { padding-top: 2.5rem; }
.sticky-section-label {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  font-size: 0.65rem;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: rgba(100, 116, 139, 0.5);
  pointer-events: none;
  user-select: none;
  z-index: 0;
}
.dark .sticky-section-label { color: rgba(100, 116, 139, 0.45); }
.sticky-board-notch {
  position: absolute;
  top: 0.75rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.sticky-board-notch input,
.sticky-board-notch select {
  font-size: 0.7rem;
  line-height: 1.2rem;
  padding: 0.15rem 0.4rem;
  border-radius: 0.375rem;
  border: 1px solid rgba(100, 116, 139, 0.35);
  background: rgba(255, 255, 255, 0.55);
  color: inherit;
}
.dark .sticky-board-notch input,
.dark .sticky-board-notch select {
  background: rgba(15, 23, 42, 0.45);
  border-color: rgba(100, 116, 139, 0.4);
  color: #e2e8f0;
}
.sticky-expanded {
  animation: stickyZoomIn 0.28s ease-out forwards;
}
@keyframes stickyZoomIn {
  from { opacity: 0; transform: scale(0.92); }
  to { opacity: 1; transform: scale(1); }
}
.sticky-preview {
  display: -webkit-box;
  -webkit-line-clamp: 6;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
  font-size: 0.8rem;
  line-height: 1.35;
}
</style>

<div
  class="stickies-shell stickies-board-bg p-4 md:p-6"
  x-data="stickiesApp"
  data-stickies-init="<?= htmlspecialchars(json_encode($init, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>"
  x-cloak
>
  <div class="max-w-6xl mx-auto space-y-5">
    <header class="flex flex-wrap items-center gap-x-4 gap-y-2">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white shrink-0">📝 Stickies</h1>
      <p class="text-sm text-gray-600 dark:text-gray-300 flex-1 min-w-0">Quick notes on a draggable board — tap to edit full screen.</p>
      <?php if ($canManage) : ?>
      <button type="button" @click="createNote()" :disabled="saving"
        class="shrink-0 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 shadow-md disabled:opacity-50">
        + New sticky
      </button>
      <?php endif; ?>
    </header>

    <div class="space-y-2">
    <template x-if="error">
      <p class="text-sm text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 rounded-xl px-4 py-2" x-text="error"></p>
    </template>
    <template x-if="loading">
      <p class="text-sm text-gray-500">Loading your stickies…</p>
    </template>

    <?php if (!$canManage) : ?>
    <p class="text-sm text-gray-600 dark:text-gray-400">You can view this project but need <code class="text-xs">stickies.manage</code> to edit notes.</p>
    <?php endif; ?>

    <template x-if="!loading && canManage">
      <div class="space-y-8 pb-16">
        <template x-for="(group, index) in sectionGroups()" :key="group.name">
          <div class="sticky-section-canvas bg-white/30 dark:bg-gray-900/20 p-2"
            :class="index === 0 && 'has-notch'"
            :style="'min-height:' + Math.max(880, ...group.notes.map(n => n.pos_y + 200)) + 'px'">
            <div x-show="index === 0" class="sticky-board-notch">
              <label class="sr-only" for="stickies-search">Search stickies</label>
              <input id="stickies-search" type="search" x-model="search" @input="onSearchInput()"
                placeholder="Search…"
                class="w-28 rounded-md border border-gray-400/40 bg-white/60 text-gray-700 dark:border-gray-500/50 dark:bg-gray-900/50 dark:text-gray-200 focus:outline-none">
              <label class="sr-only" for="stickies-category">Filter category</label>
              <select id="stickies-category" x-model="filterCategory" @change="refresh(true)"
                class="w-24 rounded-md border border-gray-400/40 bg-white/60 text-gray-700 dark:border-gray-500/50 dark:bg-gray-900/50 dark:text-gray-200 focus:outline-none">
                <option value="all">All</option>
                <template x-for="cat in categoryOptions()" :key="cat">
                  <option :value="cat" x-text="sectionLabel(cat)"></option>
                </template>
              </select>
            </div>
            <p class="sticky-section-label" x-text="sectionLabel(group.name)"></p>
            <template x-for="note in group.notes" :key="note.id">
                <div class="sticky-card text-gray-900"
                  :style="noteStyle(note)"
                  @pointerdown="startDrag(note, $event, group.name)"
                  @click="openNote(note)">
                  <p class="sticky-preview whitespace-pre-wrap" x-text="previewText(note.content)"></p>
                </div>
            </template>
          </div>
        </template>

        <p x-show="notes.length === 0" class="text-center text-gray-500 py-12">
          No stickies yet — hit <strong>New sticky</strong> to start.
        </p>
      </div>
    </template>
    </div>
  </div>

  <!-- Fullscreen editor -->
  <template x-teleport="body">
    <div x-show="expandedId" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      @keydown.escape.window="closeExpanded()">
      <template x-for="note in notes" :key="'exp-' + note.id">
        <div x-show="isExpanded(note.id)" class="sticky-expanded relative h-[50vh] w-full max-w-2xl rounded-lg shadow-2xl p-6"
          :style="'background:' + (palette[note.color] || '#fef08a')"
          @click.outside="closeExpanded()">
          <div
            class="absolute top-6 z-10 flex justify-end transition-all duration-200"
            :class="isConfigOpen(note.id) ? 'left-6 right-6' : 'right-6'"
          >
            <div
              class="inline-flex items-center overflow-hidden rounded-full bg-white/40"
              :class="isConfigOpen(note.id) && 'w-full'"
            >
              <div
                x-show="isConfigOpen(note.id)"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-x-0"
                x-transition:enter-end="opacity-100 scale-x-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-x-100"
                x-transition:leave-end="opacity-0 scale-x-0"
                class="flex min-w-0 flex-1 origin-right items-center gap-3 overflow-hidden py-1 pl-3 text-xs text-gray-900"
                @click.stop
              >
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1">
                  <label class="inline-flex items-center gap-1">
                    <span class="text-gray-700 shrink-0">Category</span>
                    <input type="text" x-model="note.category" @change="saveNote(note)"
                      class="w-20 min-w-0 rounded border border-gray-400/40 bg-white/70 px-1.5 py-0.5">
                  </label>
                  <label class="inline-flex items-center gap-1">
                    <span class="text-gray-700 shrink-0">Section</span>
                    <input type="text" x-model="note.section" @change="saveNote(note)"
                      class="w-20 min-w-0 rounded border border-gray-400/40 bg-white/70 px-1.5 py-0.5">
                  </label>
                  <div class="inline-flex items-center gap-1">
                    <span class="text-gray-700 shrink-0">Color</span>
                    <div class="flex gap-1">
                      <template x-for="c in colors" :key="c">
                        <button type="button" @click="note.color = c; saveNote(note)"
                          class="h-4 w-4 rounded-full border-2"
                          :class="note.color === c ? 'border-gray-800' : 'border-transparent'"
                          :style="'background:' + (palette[c] || '#fef08a')"
                          :title="c"></button>
                      </template>
                    </div>
                  </div>
                </div>
                <button type="button" @click="deleteNote(note)" class="shrink-0 border-r border-gray-400/30 pr-3 text-rose-700 hover:underline">Delete</button>
              </div>
              <button type="button" @click.stop="toggleConfig(note.id)" class="sticky-icon-btn m-0.5 text-gray-800" title="Configure" aria-label="Configure" :aria-expanded="isConfigOpen(note.id)">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/>
                <line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/>
                <line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/>
                <line x1="1" x2="7" y1="14" y2="14"/><line x1="9" x2="15" y1="8" y2="8"/><line x1="17" x2="23" y1="16" y2="16"/>
              </svg>
            </button>
            </div>
          </div>
          <textarea
            :x-ref="'editor-' + note.id"
            x-model="note.content"
            @blur="saveNote(note)"
            class="absolute bottom-6 left-6 right-6 resize-none bg-transparent border-0 text-gray-900 text-lg leading-relaxed focus:ring-0 focus:outline-none transition-[top,padding] duration-200"
            :class="isConfigOpen(note.id) ? 'top-16 pr-0' : 'top-6 pr-11'"
            placeholder="Write your note…"></textarea>
        </div>
      </template>
    </div>
  </template>
</div>
