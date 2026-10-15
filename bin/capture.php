<?php
// SPDX-License-Identifier: MIT
/**
 * The registry of WordPress's own scripts and styles, as data: run inside a
 * WordPress install with no plugins or theme loaded,
 *
 *   wp eval-file bin/capture.php --skip-plugins --skip-themes --user=1 > build/registry.json
 *
 * Every handle whose file ships with WordPress (under /wp-includes/ or
 * /wp-admin/), and the aliases with no file of their own: its source, its
 * dependencies, its version, and what it carries (its group, loading
 * strategy, right-to-left stylesheet, data and inline scripts). The site's
 * own address is written as {site} so the pack can put each site's back.
 */
$site = untrailingslashit(site_url());
$out = ['wordpress' => get_bloginfo('version'), 'scripts' => [], 'styles' => []];
foreach (['scripts' => wp_scripts(), 'styles' => wp_styles()] as $kind => $registry) {
    foreach ($registry->registered as $handle => $dep) {
        $src = is_string($dep->src) ? $dep->src : false;
        if ($src !== false && !preg_match('#^/wp-(includes|admin)/#', $src)) {
            continue;
        }
        $extra = (array) $dep->extra;
        foreach (['data', 'before', 'after'] as $key) {
            if (isset($extra[$key])) {
                $extra[$key] = json_decode(str_replace($site, '{site}', (string) json_encode($extra[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);
            }
        }
        unset($extra['module_dependencies']);
        $out[$kind][$handle] = ['src' => $src, 'deps' => array_values($dep->deps), 'ver' => $dep->ver, 'extra' => $extra];
    }
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
