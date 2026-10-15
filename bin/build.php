<?php
// SPDX-License-Identifier: MIT
/**
 * Builds the pack from a WordPress install and its captured registry:
 *
 *   php bin/build.php /path/to/wordpress build/registry.json
 *
 * Copies WordPress's browser files (wp-includes js, css, images and fonts;
 * wp-admin js, css and images) into the pack under the same paths, each
 * minified file with the unminified original WordPress ships beside it (the
 * source the GPL asks to travel with it), leaving out source maps, PHP and
 * the files Minn Engine serves itself (its own jQuery and its own builds of
 * the small wp-* packages), then writes manifest.json from the registry,
 * with a stylesheet's file named inside the pack ({pack}/...) and no nonce
 * of the capture's. Run it from the pack's folder; it replaces what a
 * previous build copied.
 */

declare(strict_types=1);

[$self, $wordpress, $registryFile] = $argv + [null, null, null];
if (!is_string($wordpress) || !is_dir("{$wordpress}/wp-includes/js") || !is_string($registryFile) || !is_file($registryFile)) {
    fwrite(STDERR, "usage: php bin/build.php /path/to/wordpress build/registry.json\n");
    exit(1);
}
$wordpress = rtrim(realpath($wordpress), '/');
$pack = dirname(__DIR__);
$registry = json_decode((string) file_get_contents($registryFile), true);
if (!is_array($registry) || !isset($registry['scripts'], $registry['styles'])) {
    fwrite(STDERR, "{$registryFile} is not a captured registry (bin/capture.php)\n");
    exit(1);
}

// What the engine serves itself: these handles stay the engine's, so their files are not carried.
const ENGINE_HANDLES = ['jquery-core', 'jquery-migrate', 'wp-polyfill', 'wp-hooks', 'wp-i18n', 'wp-dom-ready', 'wp-escape-html', 'wp-url', 'wp-html-entities', 'wp-a11y', 'wp-api-fetch', 'comment-reply', 'wp-embed'];
const TREES = ['wp-includes/js', 'wp-includes/css', 'wp-includes/images', 'wp-includes/fonts', 'wp-admin/js', 'wp-admin/css', 'wp-admin/images'];
$skip = ['wp-includes/js/wp-embed-template.min.js' => true];
// A file a handle names is always carried, minified twin or not.
$named = [];
foreach (['scripts', 'styles'] as $kind) {
    foreach ($registry[$kind] as $row) {
        if (is_string($row['src'])) {
            $named[ltrim($row['src'], '/')] = true;
        }
    }
}
$skip['wp-includes/js/wp-embed-template.js'] = true;
foreach (ENGINE_HANDLES as $handle) {
    $src = $registry['scripts'][$handle]['src'] ?? false;
    if (is_string($src)) {
        $skip[ltrim($src, '/')] = true;
        $skip[str_replace('.min.js', '.js', ltrim($src, '/'))] = true;
    }
}

foreach (['wp-includes', 'wp-admin'] as $top) {
    if (is_dir("{$pack}/{$top}")) {
        exec('rm -rf ' . escapeshellarg("{$pack}/{$top}"));
    }
}
$files = 0;
$bytes = 0;
foreach (TREES as $tree) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$wordpress}/{$tree}", FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $relative = substr($path, strlen($wordpress) + 1);
        $ext = strtolower($file->getExtension());
        if ($ext === 'map' || $ext === 'php' || isset($skip[$relative])) {
            continue;
        }
        $target = "{$pack}/{$relative}";
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        copy($path, $target);
        $files++;
        $bytes += $file->getSize();
    }
}

// The manifest: every handle with its file in the pack, or an alias; the engine's own are left to the engine.
$manifest = ['wordpress' => $registry['wordpress'], 'scripts' => [], 'styles' => []];
foreach (['scripts', 'styles'] as $kind) {
    foreach ($registry[$kind] as $handle => $row) {
        if ($kind === 'scripts' && in_array($handle, ENGINE_HANDLES, true)) {
            continue;
        }
        if (is_string($row['src']) && !is_file($pack . $row['src'])) {
            fwrite(STDERR, "  {$kind} {$handle}: {$row['src']} not in the pack, left out\n");
            continue;
        }
        // A stylesheet's file, which the engine may inline, is named inside the pack wherever it is installed.
        if (is_string($row['extra']['path'] ?? null) && str_starts_with($row['extra']['path'], $wordpress . '/')) {
            $row['extra']['path'] = '{pack}/' . ltrim(substr($row['extra']['path'], strlen($wordpress) + 1), '/');
        }
        // A nonce is made for each request; the capture's is not carried.
        if (is_string($row['extra']['data'] ?? null)) {
            $row['extra']['data'] = (string) preg_replace('/"nonce":"[0-9a-f]+"/', '"nonce":""', $row['extra']['data']);
        }
        $manifest[$kind][$handle] = $row;
    }
}
file_put_contents("{$pack}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
printf("WordPress %s: %d files (%.1f MB), %d scripts and %d styles in manifest.json\n", $registry['wordpress'], $files, $bytes / 1e6, count($manifest['scripts']), count($manifest['styles']));
