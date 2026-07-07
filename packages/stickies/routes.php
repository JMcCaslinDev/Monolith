<?php

declare(strict_types=1);

require_once __DIR__ . '/src/StickyColors.php';
require_once __DIR__ . '/src/StickyService.php';

use Stickies\StickyColors;
use Stickies\StickyService;

$jsonResponse = function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
};

$publicPayload = function (array $notes, StickyService $svc, int $userId): array {
    return [
        'notes' => $notes,
        'categories' => $svc->categoriesForUser($userId),
        'sections' => $svc->sectionsForUser($userId),
        'colors' => StickyColors::ALL,
        'palette' => StickyColors::palette(),
    ];
};

return [
    'GET /projects/stickies' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);
        events()->record('project.opened', $uid, 'project', 'stickies', ['project' => 'stickies']);

        package_view('stickies', 'app', [
            'title' => 'Stickies',
            'fullWidth' => true,
            'csrf' => csrf_token(),
            'canManage' => in_array('stickies.manage', $perms, true),
            'palette' => StickyColors::palette(),
        ]);
    }, ['auth', 'perm:projects.stickies.open']),

    'GET /projects/stickies/api/notes' => fn () => dispatch(function () use ($jsonResponse, $publicPayload): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $search = isset($_GET['q']) ? (string) $_GET['q'] : null;
        $category = isset($_GET['category']) ? (string) $_GET['category'] : null;
        $svc = stickies();
        $notes = $svc->listNotes($uid, $search, $category);
        $jsonResponse($publicPayload($notes, $svc, $uid));
    }, ['auth', 'perm:stickies.manage'], recordPageView: false),

    'POST /projects/stickies/note/save' => fn () => dispatch(function () use ($jsonResponse, $publicPayload): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $content = (string) ($_POST['content'] ?? '');
        $category = (string) ($_POST['category'] ?? 'general');
        $color = (string) ($_POST['color'] ?? 'yellow');
        $section = (string) ($_POST['section'] ?? 'board');
        $posX = isset($_POST['pos_x']) && is_numeric($_POST['pos_x']) ? (int) $_POST['pos_x'] : null;
        $posY = isset($_POST['pos_y']) && is_numeric($_POST['pos_y']) ? (int) $_POST['pos_y'] : null;

        try {
            $row = stickies()->saveNote(
                $uid,
                $content,
                $category,
                $color,
                $section,
                $noteId > 0 ? $noteId : null,
                $posX,
                $posY,
            );
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('stickies.note.saved', $uid, 'sticky', (string) $row['id'], [
            'category' => $row['category'],
            'section' => $row['section'],
            'color' => $row['color'],
            'created' => $noteId < 1,
        ]);

        $svc = stickies();
        $jsonResponse($publicPayload($svc->listNotes($uid), $svc, $uid));
    }, ['auth', 'perm:stickies.manage'], recordPageView: false),

    'POST /projects/stickies/note/delete' => fn () => dispatch(function () use ($jsonResponse, $publicPayload): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $noteId = (int) ($_POST['note_id'] ?? 0);

        try {
            stickies()->deleteNote($uid, $noteId);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('stickies.note.deleted', $uid, 'sticky', (string) $noteId, []);

        $svc = stickies();
        $jsonResponse($publicPayload($svc->listNotes($uid), $svc, $uid));
    }, ['auth', 'perm:stickies.manage'], recordPageView: false),

    'POST /projects/stickies/note/move' => fn () => dispatch(function () use ($jsonResponse, $publicPayload): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $posX = (int) ($_POST['pos_x'] ?? 0);
        $posY = (int) ($_POST['pos_y'] ?? 0);
        $section = isset($_POST['section']) ? (string) $_POST['section'] : null;

        try {
            $row = stickies()->moveNote($uid, $noteId, $posX, $posY, $section);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('stickies.note.moved', $uid, 'sticky', (string) $noteId, [
            'pos_x' => $row['pos_x'],
            'pos_y' => $row['pos_y'],
            'section' => $row['section'],
        ]);

        $svc = stickies();
        $jsonResponse($publicPayload($svc->listNotes($uid), $svc, $uid));
    }, ['auth', 'perm:stickies.manage'], recordPageView: false),
];
