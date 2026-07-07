<?php

declare(strict_types=1);

return [
    'GET /projects/tools' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        events()->record('project.opened', (int) $user['id'], 'project', 'tools', [
            'project' => 'tools',
        ]);
        view('projects/tools-index', ['title' => 'Tools']);
    }, ['auth', 'perm:projects.tools.open']),

    'GET /projects/tools/json-converter' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        events()->record('tool.json_converter.used', (int) $user['id'], 'tool', 'json-converter', [
            'tool' => 'json-converter',
            'action' => 'open',
            'project' => 'tools',
        ]);
        package_view('tools', 'json-converter', ['title' => 'JSON Converter']);
    }, ['auth', 'perm:tools.json-converter.use']),

    // ponytail: keep old URL working
    'GET /tools/json-converter' => fn () => dispatch(function (): void {
        header('Location: /projects/tools/json-converter', true, 301);
        exit;
    }, ['auth', 'perm:tools.json-converter.use'], recordPageView: false),
];
