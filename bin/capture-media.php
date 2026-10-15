<?php
// SPDX-License-Identifier: MIT
/**
 * The media modal's text and templates, as data: run inside a WordPress install
 * with no plugins or theme loaded, signed in as an administrator,
 *
 *   wp eval-file bin/capture-media.php --skip-plugins --skip-themes --user=1
 *
 * Writes media/strings.json (the modal's words, as wp_enqueue_media() hands
 * them to its script before the media_view_strings filter) and
 * media/templates.html (wp_print_media_templates() without any plugin's
 * additions), with the site's address written {site} and the upload limit
 * {max_upload}, which the pack puts back for each site.
 */
$pack = dirname(__DIR__);
$site = untrailingslashit(site_url());
$strings = null;
add_filter('media_view_strings', static function ($given) use (&$strings) {
    $strings = $given;
    return $given;
}, PHP_INT_MIN);
wp_enqueue_media();
if (!is_array($strings)) {
    fwrite(STDERR, "wp_enqueue_media() handed media_view_strings nothing\n");
    exit(1);
}
file_put_contents("{$pack}/media/strings.json", json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
if (!function_exists('wp_print_media_templates')) {
    require_once ABSPATH . WPINC . '/media-template.php';
}
remove_all_actions('print_media_templates');
ob_start();
wp_print_media_templates();
$html = str_replace($site, '{site}', (string) ob_get_clean());
$limit = esc_html(size_format(wp_max_upload_size()));
$html = str_replace(sprintf(__('Maximum upload file size: %s.'), $limit), sprintf(__('Maximum upload file size: %s.'), '{max_upload}'), $html);
file_put_contents("{$pack}/media/templates.html", $html);
fwrite(STDERR, count($strings) . " strings, " . substr_count($html, 'type="text/html"') . " templates\n");
