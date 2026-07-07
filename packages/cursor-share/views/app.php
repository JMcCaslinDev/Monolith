<?php

/** @var array<string, array{label: string, icon: string, extension: string, filename_hint: string}> $categories */
/** @var string $csrf */
$init = [
    'categories' => $categories,
    'csrf' => $csrf,
];
?>
<style>
.cs-shell { display: flex; height: calc(100vh - 3.5rem); overflow: hidden; }
.cs-sidebar { width: 18rem; flex-shrink: 0; border-right: 1px solid rgb(229 231 235); overflow-y: auto; }
.dark .cs-sidebar { border-color: rgb(55 65 81); }
.cs-main { flex: 1; overflow-y: auto; min-width: 0; }
.cs-code { font-family: ui-monospace, monospace; font-size: 0.75rem; white-space: pre-wrap; word-break: break-word; }
</style>

<div
    class="cs-shell page-bg"
    x-data="cursorShareApp"
    data-cursor-share-init="<?= htmlspecialchars(json_encode($init, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>"
    x-cloak
>
    <aside class="cs-sidebar p-4 space-y-4">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Cursor Share</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Community rules, skills, commands, and hooks.</p>
        </div>

        <nav class="flex flex-col gap-1 text-sm">
            <button type="button" @click="setTab('browse')" class="text-left rounded px-2 py-1.5"
                :class="tab === 'browse' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800'">
                Browse
            </button>
            <button type="button" @click="setTab('mine')" class="text-left rounded px-2 py-1.5"
                :class="tab === 'mine' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800'">
                My posts
            </button>
            <button type="button" x-show="canPost" @click="startCreate()" class="text-left rounded px-2 py-1.5"
                :class="tab === 'create' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800'">
                New post
            </button>
        </nav>

        <div class="space-y-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Top 10</p>
            <template x-for="(meta, cat) in categories" :key="cat">
                <div>
                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300" x-text="meta.icon + ' ' + meta.label"></p>
                    <template x-if="!(top[cat] || []).length">
                        <p class="text-[11px] text-gray-400 py-1">None yet</p>
                    </template>
                    <template x-for="p in (top[cat] || []).slice(0, 10)" :key="p.id">
                        <button type="button" @click="openPost(p.id)" class="block w-full text-left text-[11px] truncate py-0.5 hover:underline text-gray-600 dark:text-gray-400">
                            <span x-text="p.title"></span>
                            <span class="text-gray-400" x-text="' (' + p.score + ')'"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </aside>

    <section class="cs-main p-4 space-y-4">
        <template x-if="tab === 'browse' || tab === 'mine'">
            <div class="space-y-3">
                <div class="flex flex-wrap gap-2 items-end">
                    <input type="search" x-model="filters.q" @keydown.enter="refresh()" placeholder="Search…" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm min-w-[12rem]">
                    <select x-model="filters.category" @change="refresh()" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                        <option value="">All categories</option>
                        <template x-for="(meta, cat) in categories" :key="cat">
                            <option :value="cat" x-text="meta.label"></option>
                        </template>
                    </select>
                    <select x-model="filters.sort" @change="refresh()" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                        <option value="popular">Most popular</option>
                        <option value="newest">Newest</option>
                        <option value="views">Most viewed</option>
                        <option value="downloads">Most downloaded</option>
                    </select>
                    <template x-if="tab === 'mine'">
                        <div class="flex gap-2">
                            <input type="date" x-model="filters.date_from" @change="refresh()" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                            <input type="date" x-model="filters.date_to" @change="refresh()" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                        </div>
                    </template>
                    <button type="button" @click="refresh()" class="rounded bg-gray-200 dark:bg-gray-700 px-3 py-1.5 text-sm">Apply</button>
                </div>

                <template x-if="!selected">
                    <div class="space-y-2">
                        <template x-if="!posts.length">
                            <p class="text-sm text-gray-500">No posts match your filters.</p>
                        </template>
                        <template x-for="p in posts" :key="p.id">
                            <article class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 bg-white/50 dark:bg-gray-900/50">
                                <div class="flex justify-between gap-2">
                                    <button type="button" @click="openPost(p.id)" class="text-left flex-1 min-w-0">
                                        <h2 class="font-medium text-gray-900 dark:text-gray-100 truncate" x-text="p.title"></h2>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            <span x-text="categoryLabel(p.category)"></span>
                                            · <span x-text="p.filename"></span>
                                            <template x-if="p.version"><span x-text="' · v' + p.version"></span></template>
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2" x-text="p.description || ''"></p>
                                    </button>
                                    <div class="text-right text-xs text-gray-500 shrink-0">
                                        <div x-text="p.score + ' score'"></div>
                                        <div x-text="p.views + ' views'"></div>
                                        <div x-text="p.downloads + ' downloads'"></div>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                </template>

                <template x-if="selected">
                    <div class="space-y-3">
                        <button type="button" @click="closePost()" class="text-sm text-indigo-600 dark:text-indigo-400">← Back to list</button>
                        <header>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="selected.title"></h2>
                            <p class="text-sm text-gray-500 mt-1">
                                <span x-text="categoryLabel(selected.category)"></span>
                                · <span x-text="selected.filename"></span>
                                <template x-if="selected.version"><span x-text="' · v' + selected.version"></span></template>
                                · by <span x-text="selected.author_name || selected.author_email"></span>
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2" x-text="selected.description || ''"></p>
                        </header>

                        <div class="flex flex-wrap gap-2 items-center text-sm">
                            <template x-if="canVote">
                                <div class="flex gap-1">
                                    <button type="button" @click="vote(1)" class="rounded px-2 py-1 border"
                                        :class="selected.user_vote === 1 ? 'border-green-500 bg-green-50 dark:bg-green-950/30' : 'border-gray-300 dark:border-gray-600'">▲ <span x-text="selected.upvotes"></span></button>
                                    <button type="button" @click="vote(-1)" class="rounded px-2 py-1 border"
                                        :class="selected.user_vote === -1 ? 'border-red-500 bg-red-50 dark:bg-red-950/30' : 'border-gray-300 dark:border-gray-600'">▼ <span x-text="selected.downvotes"></span></button>
                                </div>
                            </template>
                            <span class="text-gray-500" x-text="selected.views + ' views · ' + selected.downloads + ' downloads'"></span>
                            <template x-if="canDownload">
                                <a :href="'/projects/cursor-share/download?post_id=' + selected.id" class="rounded bg-indigo-600 text-white px-3 py-1 hover:bg-indigo-500">Download</a>
                            </template>
                            <template x-if="canPost && selected.user_id === currentUserId">
                                <button type="button" @click="startEdit(selected)" class="rounded border border-gray-300 dark:border-gray-600 px-3 py-1">Edit</button>
                            </template>
                        </div>

                        <pre class="cs-code rounded-lg border border-gray-200 dark:border-gray-700 p-3 bg-gray-50 dark:bg-gray-900 overflow-x-auto max-h-96" x-text="selected.content || ''"></pre>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="tab === 'create'">
            <form @submit.prevent="submitPost" class="max-w-2xl space-y-3">
                <h2 class="text-lg font-semibold" x-text="form.post_id ? 'Edit post' : 'New post'"></h2>

                <template x-if="!form.post_id">
                    <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Category</label>
                        <select x-model="form.category" required class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                            <template x-for="(meta, cat) in categories" :key="cat">
                                <option :value="cat" x-text="meta.label"></option>
                            </template>
                        </select>
                    </div>
                </template>

                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Title</label>
                    <input type="text" x-model="form.title" required maxlength="200" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Description</label>
                    <textarea x-model="form.description" maxlength="1000" rows="2" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm"></textarea>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Filename</label>
                        <input type="text" x-model="form.filename" :placeholder="filenameHint(form.category)" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                    </div>
                    <div class="w-28">
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Version</label>
                        <input type="text" x-model="form.version" placeholder="1.0" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Tags (comma-separated)</label>
                    <input type="text" x-model="form.tags" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Content</label>
                    <textarea x-model="form.content" required rows="12" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm font-mono text-xs"></textarea>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Or upload file</label>
                    <input type="file" @change="loadFile($event)" class="mt-1 block text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" :disabled="saving" class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-500 disabled:opacity-50">
                        <span x-text="saving ? 'Saving…' : (form.post_id ? 'Save changes' : 'Publish')"></span>
                    </button>
                    <button type="button" @click="setTab('browse')" class="rounded border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm">Cancel</button>
                </div>
                <p x-show="formError" class="text-sm text-red-600 dark:text-red-400" x-text="formError"></p>
            </form>
        </template>
    </section>
</div>
