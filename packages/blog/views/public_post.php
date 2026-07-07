<?php

/** @var array<string, mixed> $post */
/** @var string $contentHtml */
/** @var array<string, mixed> $seo */
?>
<style>
.blog-article { max-width: 48rem; margin: 0 auto; }
.blog-article .blog-p { margin: 1rem 0; line-height: 1.7; }
.blog-article .blog-h1, .blog-article .blog-h2, .blog-article .blog-h3 { font-weight: 700; margin: 1.5rem 0 0.75rem; }
.blog-article .blog-h1 { font-size: 1.5rem; }
.blog-article .blog-h2 { font-size: 1.25rem; }
.blog-article .blog-h3 { font-size: 1.125rem; }
.blog-article .blog-img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 1rem 0; }
.blog-article .blog-ul { list-style: disc; padding-left: 1.5rem; margin: 1rem 0; }
.blog-article .blog-pre { overflow-x: auto; padding: 1rem; border-radius: 0.5rem; background: rgb(243 244 246); font-size: 0.875rem; }
.dark .blog-article .blog-pre { background: rgb(31 41 55); }
.blog-article .blog-code { font-family: ui-monospace, monospace; font-size: 0.875em; padding: 0.125rem 0.25rem; border-radius: 0.25rem; background: rgb(243 244 246); }
.dark .blog-article .blog-code { background: rgb(31 41 55); }
.blog-tag { display: inline-block; font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: 9999px; background: rgb(243 244 246); margin-right: 0.25rem; }
.dark .blog-tag { background: rgb(31 41 55); }
</style>

<article class="blog-article px-6 py-8">
    <p class="mb-4"><a href="/blog" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← All posts</a></p>

    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100"><?= htmlspecialchars((string) $post['title'], ENT_QUOTES) ?></h1>
        <?php if (!empty($post['excerpt'])) : ?>
            <p class="text-lg text-gray-600 dark:text-gray-400 mt-3"><?= htmlspecialchars((string) $post['excerpt'], ENT_QUOTES) ?></p>
        <?php endif; ?>
        <?php if (!empty($seo['tags'])) : ?>
            <div class="mt-4">
                <?php foreach ($seo['tags'] as $tag) : ?>
                    <span class="blog-tag text-gray-600 dark:text-gray-300"><?= htmlspecialchars((string) $tag, ENT_QUOTES) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="text-sm text-gray-500 mt-4">
            <?= htmlspecialchars((string) ($post['author_name'] ?? ''), ENT_QUOTES) ?>
            <?php if (!empty($post['published_at'])) : ?>
                · <?= htmlspecialchars(substr((string) $post['published_at'], 0, 10), ENT_QUOTES) ?>
            <?php endif; ?>
        </p>
    </header>

    <div class="prose-blog text-gray-800 dark:text-gray-200">
        <?= $contentHtml ?>
    </div>
</article>
