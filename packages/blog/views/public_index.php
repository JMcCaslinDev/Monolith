<?php

/** @var list<array<string, mixed>> $posts */
?>
<style>
.blog-public { max-width: 48rem; margin: 0 auto; }
.blog-card { border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1.25rem; margin-bottom: 1rem; }
.dark .blog-card { border-color: rgb(55 65 81); }
.blog-tag { display: inline-block; font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: 9999px; background: rgb(243 244 246); margin-right: 0.25rem; }
.dark .blog-tag { background: rgb(31 41 55); }
</style>

<div class="blog-public px-6 py-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Blog</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Articles and updates.</p>
    </header>

    <?php if ($posts === []) : ?>
        <p class="text-gray-500">No published posts yet.</p>
    <?php else : ?>
        <?php foreach ($posts as $post) : ?>
            <article class="blog-card bg-white/50 dark:bg-gray-900/50">
                <h2 class="text-xl font-semibold">
                    <a href="/blog/<?= htmlspecialchars((string) $post['slug'], ENT_QUOTES) ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                        <?= htmlspecialchars((string) $post['title'], ENT_QUOTES) ?>
                    </a>
                </h2>
                <?php if (!empty($post['excerpt'])) : ?>
                    <p class="text-gray-600 dark:text-gray-400 mt-2"><?= htmlspecialchars((string) $post['excerpt'], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <?php if (!empty($post['tags'])) : ?>
                    <div class="mt-3">
                        <?php foreach ($post['tags'] as $tag) : ?>
                            <span class="blog-tag text-gray-600 dark:text-gray-300"><?= htmlspecialchars((string) $tag, ENT_QUOTES) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="text-xs text-gray-500 mt-3">
                    <?= htmlspecialchars((string) ($post['author_name'] ?? ''), ENT_QUOTES) ?>
                    <?php if (!empty($post['published_at'])) : ?>
                        · <?= htmlspecialchars(substr((string) $post['published_at'], 0, 10), ENT_QUOTES) ?>
                    <?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
