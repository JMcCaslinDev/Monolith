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
  transition: box-shadow 0.15s ease, transform 0.15s ease;
  transform-style: preserve-3d;
}
.sticky-card:active { cursor: grabbing; }
.sticky-card:hover { box-shadow: 4px 8px 20px rgba(0,0,0,0.2); z-index: 10; }
.sticky-flip { perspective: 800px; }
.sticky-flip-inner {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 168px;
  transition: transform 0.45s ease;
  transform-style: preserve-3d;
}
.sticky-flip-inner.is-flipped { transform: rotateY(180deg); }
.sticky-face {
  position: absolute;
  inset: 0;
  backface-visibility: hidden;
  border-radius: 4px;
  padding: 0.65rem 0.75rem;
}
.sticky-face-back { transform: rotateY(180deg); }
.sticky-section-canvas {
  position: relative;
  min-height: 220px;
  border-radius: 0.75rem;
  border: 1px dashed rgba(100,116,139,0.35);
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
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <a href="/" class="text-xs text-amber-700/70 dark:text-amber-300/70 hover:underline">← Dashboard</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">📝 Stickies</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Quick notes on a draggable board — flip for settings, tap to edit full screen.</p>
      </div>
      <?php if ($canManage) : ?>
      <button type="button" @click="createNote()" :disabled="saving"
        class="rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 shadow-md disabled:opacity-50">
        + New sticky
      </button>
      <?php endif; ?>
    </header>

    <?php if ($canManage) : ?>
    <div class="flex flex-wrap gap-3 items-center bg-white/70 dark:bg-gray-900/70 backdrop-blur rounded-2xl p-3 border border-gray-200/60 dark:border-gray-700/60">
      <div class="flex-1 min-w-[200px]">
        <label class="sr-only" for="stickies-search">Search stickies</label>
        <input id="stickies-search" type="search" x-model="search" @input="onSearchInput()"
          placeholder="Search content, category, section…"
          class="w-full rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="sr-only" for="stickies-category">Filter category</label>
        <select id="stickies-category" x-model="filterCategory" @change="refresh()"
          class="rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
          <option value="all">All categories</option>
          <template x-for="cat in categories" :key="cat">
            <option :value="cat" x-text="sectionLabel(cat)"></option>
          </template>
        </select>
      </div>
    </div>
    <?php endif; ?>

    <p x-show="error" class="text-sm text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 rounded-xl px-4 py-2" x-text="error"></p>
    <p x-show="loading" class="text-sm text-gray-500">Loading your stickies…</p>

    <?php if (!$canManage) : ?>
    <p class="text-sm text-gray-600 dark:text-gray-400">You can view this project but need <code class="text-xs">stickies.manage</code> to edit notes.</p>
    <?php endif; ?>

    <template x-if="!loading && canManage">
      <div class="space-y-8 pb-16">
        <template x-for="group in sectionGroups()" :key="group.name">
          <section class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
              x-text="sectionLabel(group.name)"></h2>
            <div class="sticky-section-canvas bg-white/30 dark:bg-gray-900/20 p-2"
              :style="'min-height:' + Math.max(220, ...group.notes.map(n => n.pos_y + 180)) + 'px'">
              <template x-for="note in group.notes" :key="note.id">
                <div class="sticky-card sticky-flip"
                  :style="noteStyle(note)"
                  @pointerdown="startDrag(note, $event, group.name)">
                  <div class="sticky-flip-inner" :class="isFlipped(note.id) ? 'is-flipped' : ''">
                    <!-- Front -->
                    <div class="sticky-face text-gray-900" @click="openNote(note)">
                      <button type="button" class="absolute top-1 right-1 w-6 h-6 text-xs rounded-full bg-black/10 hover:bg-black/20"
                        @click="toggleFlip(note.id, $event)" title="Settings">⚙</button>
                      <p class="sticky-preview mt-4 whitespace-pre-wrap" x-text="previewText(note.content)"></p>
                      <p class="text-[10px] text-gray-600/80 mt-2" x-text="note.category"></p>
                    </div>
                    <!-- Back (settings) -->
                    <div class="sticky-face sticky-face-back text-gray-900 text-xs space-y-2">
                      <p class="font-semibold">Settings</p>
                      <label class="block">
                        <span class="text-gray-600">Category</span>
                        <input type="text" x-model="note.category" @change="saveNote(note)"
                          class="w-full mt-0.5 rounded border border-gray-300/60 bg-white/80 px-2 py-1 text-xs">
                      </label>
                      <label class="block">
                        <span class="text-gray-600">Section</span>
                        <input type="text" x-model="note.section" @change="saveNote(note)"
                          class="w-full mt-0.5 rounded border border-gray-300/60 bg-white/80 px-2 py-1 text-xs">
                      </label>
                      <div>
                        <span class="text-gray-600">Color</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                          <template x-for="c in colors" :key="c">
                            <button type="button" @click="note.color = c; saveNote(note)"
                              class="w-5 h-5 rounded-full border-2"
                              :class="note.color === c ? 'border-gray-800' : 'border-transparent'"
                              :style="'background:' + (palette[c] || '#fef08a')"></button>
                          </template>
                        </div>
                      </div>
                      <p class="text-[10px] text-gray-600" x-text="'Edited ' + formatDate(note.updated_at)"></p>
                      <button type="button" @click="deleteNote(note)" class="text-rose-700 hover:underline">Delete</button>
                      <button type="button" @click="toggleFlip(note.id, $event)" class="block text-gray-600 hover:underline">← Back</button>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </section>
        </template>

        <p x-show="notes.length === 0" class="text-center text-gray-500 py-12">
          No stickies yet — hit <strong>New sticky</strong> to start.
        </p>
      </div>
    </template>
  </div>

  <!-- Fullscreen editor -->
  <template x-teleport="body">
    <div x-show="expandedId" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      @keydown.escape.window="closeExpanded()">
      <template x-for="note in notes" :key="'exp-' + note.id">
        <div x-show="isExpanded(note.id)" class="sticky-expanded w-full max-w-2xl rounded-lg shadow-2xl p-6 min-h-[50vh] flex flex-col"
          :style="'background:' + (palette[note.color] || '#fef08a')"
          @click.outside="closeExpanded()">
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="text-xs text-gray-700" x-text="'Created ' + formatDate(note.created_at)"></span>
            <button type="button" @click="closeExpanded()" class="text-sm text-gray-700 hover:underline">Close</button>
          </div>
          <textarea
            :x-ref="'editor-' + note.id"
            x-model="note.content"
            @blur="saveNote(note)"
            class="flex-1 w-full bg-transparent border-0 resize-none text-gray-900 text-lg leading-relaxed focus:ring-0 focus:outline-none"
            placeholder="Write your note…"></textarea>
          <div class="flex flex-wrap gap-3 mt-4 text-xs text-gray-700 items-center">
            <label>Category <input type="text" x-model="note.category" @change="saveNote(note)" class="ml-1 rounded px-2 py-0.5 border border-gray-400/40 bg-white/50"></label>
            <label>Section <input type="text" x-model="note.section" @change="saveNote(note)" class="ml-1 rounded px-2 py-0.5 border border-gray-400/40 bg-white/50"></label>
            <span x-text="'Updated ' + formatDate(note.updated_at)"></span>
          </div>
        </div>
      </template>
    </div>
  </template>
</div>
