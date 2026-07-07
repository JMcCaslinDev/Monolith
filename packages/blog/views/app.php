<?php

/** @var string $csrf */
/** @var bool $canManage */
/** @var bool $canAnalytics */
$init = [
    'csrf' => $csrf,
    'canManage' => $canManage,
    'canAnalytics' => $canAnalytics,
];
?>
<style>
.blog-shell { display: flex; height: calc(100vh - 3.5rem); overflow: hidden; }
.blog-sidebar { width: 16rem; flex-shrink: 0; border-right: 1px solid rgb(229 231 235); overflow-y: auto; }
.dark .blog-sidebar { border-color: rgb(55 65 81); }
.blog-main { flex: 1; overflow-y: auto; min-width: 0; }
.blog-editor { font-family: ui-monospace, monospace; font-size: 0.875rem; min-height: 16rem; }
.blog-bar { background: rgb(99 102 241); border-radius: 0.25rem 0.25rem 0 0; min-width: 4px; }
.blog-bar-wrap { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 0; }
.blog-bar-label { font-size: 10px; color: rgb(107 114 128); margin-top: 0.25rem; writing-mode: vertical-rl; transform: rotate(180deg); }
</style>

<div
    class="blog-shell page-bg"
    x-data="blogApp"
    data-blog-init="<?= htmlspecialchars(json_encode($init, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>"
    x-cloak
>
    <aside class="blog-sidebar p-4 space-y-4">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Blog</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Drafts, publishing, SEO, and analytics.</p>
        </div>

        <nav class="flex flex-col gap-1 text-sm">
            <button type="button" @click="setTab('drafts')" class="text-left rounded px-2 py-1.5"
                :class="tab === 'drafts' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800'">
                Drafts
            </button>
            <button type="button" @click="setTab('published')" class="text-left rounded px-2 py-1.5"
                :class="tab === 'published' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800'">
                Published
            </button>
            <button type="button" x-show="canManage" @click="startCreate()" class="text-left rounded px-2 py-1.5"
                :class="tab === 'editor' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800'">
                Editor
            </button>
            <button type="button" x-show="canAnalytics" @click="setTab('analytics')" class="text-left rounded px-2 py-1.5"
                :class="tab === 'analytics' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800'">
                Analytics
            </button>
        </nav>

        <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            <p>Public index: <a href="/blog" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">/blog</a></p>
            <p>Sitemap: <a href="/blog/sitemap.xml" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">/blog/sitemap.xml</a></p>
        </div>
    </aside>

    <section class="blog-main p-4 space-y-4">
        <template x-if="tab === 'drafts' || tab === 'published'">
            <div class="space-y-3">
                <div class="flex flex-wrap gap-2 items-end">
                    <input type="search" x-model="filters.q" @keydown.enter="refresh()" placeholder="Search…" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm min-w-[12rem]">
                    <button type="button" @click="refresh()" class="rounded bg-gray-200 dark:bg-gray-700 px-3 py-1.5 text-sm">Search</button>
                </div>

                <template x-if="!posts.length">
                    <p class="text-sm text-gray-500">No posts in this list.</p>
                </template>

                <template x-for="p in posts" :key="p.id">
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 bg-white/50 dark:bg-gray-900/50">
                        <div class="flex justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h2 class="font-medium text-gray-900 dark:text-gray-100 truncate" x-text="p.title"></h2>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <span x-text="p.slug"></span>
                                    · <span x-text="p.views + ' views'"></span>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2" x-text="p.excerpt || ''"></p>
                                <template x-if="(p.tags || []).length">
                                    <p class="text-xs text-gray-500 mt-1" x-text="'Tags: ' + (p.tags || []).join(', ')"></p>
                                </template>
                                <template x-if="p.seo">
                                    <p class="text-xs text-gray-500 mt-1 truncate" x-text="'SEO title: ' + (p.seo.title || p.title)"></p>
                                </template>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <button type="button" x-show="canManage" @click="startEdit(p)" class="text-xs rounded bg-gray-200 dark:bg-gray-700 px-2 py-1">Edit</button>
                                <a x-show="p.status === 'published'" :href="p.public_url" target="_blank" class="text-xs text-indigo-600 dark:text-indigo-400 text-center">View</a>
                                <button type="button" x-show="canManage && p.status === 'draft'" @click="publishPost(p.id)" class="text-xs rounded bg-green-600 text-white px-2 py-1">Publish</button>
                                <button type="button" x-show="canManage && p.status === 'published'" @click="unpublishPost(p.id)" class="text-xs rounded bg-amber-600 text-white px-2 py-1">Unpublish</button>
                                <button type="button" x-show="canManage" @click="deletePost(p.id)" class="text-xs rounded bg-red-600 text-white px-2 py-1">Delete</button>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </template>

        <template x-if="tab === 'editor' && canManage">
            <div class="space-y-3 max-w-3xl">
                <p class="text-sm text-gray-500">Markdown supported. Use blank lines for paragraphs, <code>![alt](/url)</code> for images.</p>
                <template x-if="formError">
                    <p class="text-sm text-red-600" x-text="formError"></p>
                </template>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">Title
                        <input type="text" x-model="form.title" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5">
                    </label>
                    <label class="block text-sm">Slug
                        <input type="text" x-model="form.slug" placeholder="auto from title" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5">
                    </label>
                </div>

                <label class="block text-sm">Excerpt
                    <textarea x-model="form.excerpt" rows="2" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5"></textarea>
                </label>

                <div class="flex flex-wrap gap-2 items-center">
                    <label class="text-sm">Content</label>
                    <label class="text-xs rounded bg-gray-200 dark:bg-gray-700 px-2 py-1 cursor-pointer">
                        Upload image
                        <input type="file" accept="image/*" class="hidden" @change="uploadImage($event)">
                    </label>
                </div>
                <textarea x-model="form.content" class="blog-editor w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5"></textarea>

                <label class="block text-sm">Tags (comma-separated)
                    <input type="text" x-model="form.tags" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5">
                </label>

                <fieldset class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
                    <legend class="text-sm font-medium px-1">SEO</legend>
                    <label class="block text-sm">Meta title
                        <input type="text" x-model="form.meta_title" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5">
                    </label>
                    <label class="block text-sm">Meta description
                        <textarea x-model="form.meta_description" rows="2" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5"></textarea>
                    </label>
                    <label class="block text-sm">OG image URL
                        <input type="text" x-model="form.og_image_url" placeholder="/uploads/blog/… or https://…" class="mt-1 w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5">
                    </label>
                </fieldset>

                <div class="flex gap-2">
                    <button type="button" @click="submitPost()" :disabled="saving" class="rounded bg-indigo-600 text-white px-4 py-2 text-sm disabled:opacity-50">
                        <span x-text="saving ? 'Saving…' : (form.post_id ? 'Update draft' : 'Save draft')"></span>
                    </button>
                    <button type="button" x-show="form.post_id" @click="publishPost(form.post_id)" class="rounded bg-green-600 text-white px-4 py-2 text-sm">Publish</button>
                </div>
            </div>
        </template>

        <template x-if="tab === 'analytics' && canAnalytics">
            <div class="space-y-6">
                <template x-if="!analytics">
                    <p class="text-sm text-gray-500">Loading analytics…</p>
                </template>
                <template x-if="analytics">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-xs text-gray-500">Total posts</p>
                            <p class="text-2xl font-semibold" x-text="analytics.totals.posts"></p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-xs text-gray-500">Published</p>
                            <p class="text-2xl font-semibold" x-text="analytics.totals.published"></p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-xs text-gray-500">Drafts</p>
                            <p class="text-2xl font-semibold" x-text="analytics.totals.drafts"></p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-xs text-gray-500">Total views</p>
                            <p class="text-2xl font-semibold" x-text="analytics.totals.views"></p>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Views (last 30 days)</h2>
                        <div class="flex items-end gap-1 h-40 border-b border-gray-200 dark:border-gray-700 pb-1">
                            <template x-for="day in analytics.daily_views" :key="day.date">
                                <div class="blog-bar-wrap">
                                    <div class="blog-bar w-full" :style="'height:' + barHeight(day.views) + 'px'"></div>
                                    <span class="blog-bar-label" x-text="day.date.slice(5)"></span>
                                </div>
                            </template>
                            <template x-if="!(analytics.daily_views || []).length">
                                <p class="text-sm text-gray-500">No view data yet.</p>
                            </template>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Top posts by views</h2>
                        <template x-for="p in analytics.top_posts" :key="p.id">
                            <div class="flex justify-between text-sm py-1 border-b border-gray-100 dark:border-gray-800">
                                <span x-text="p.title"></span>
                                <span class="text-gray-500" x-text="p.views + ' views'"></span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </section>
</div>
