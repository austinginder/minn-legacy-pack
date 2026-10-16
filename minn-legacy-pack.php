<?php
/**
 * Plugin Name: Minn Legacy Pack
 * Plugin URI: https://minn.run
 * Description: WordPress's own browser scripts and styles for a site running Minn, so the editors of page builders such as Elementor open without WordPress. On WordPress itself it does nothing.
 * Version: 7.1.3
 * Requires PHP: 8.1
 * Update URI: https://github.com/austinginder/minn-legacy-pack
 * Author: Austin Ginder
 * License: MIT AND GPL-2.0-or-later
 * License URI: https://github.com/austinginder/minn-legacy-pack#licence
 *
 * SPDX-License-Identifier: MIT
 *
 * This file and bin/ are the plugin's own code, under the MIT licence
 * (LICENSE). What it carries is WordPress's, under WordPress's licence,
 * GPL-2.0-or-later (license.txt): the files under wp-includes/ and
 * wp-admin/, from the release named in manifest.json, and what was captured
 * from that release (manifest.json, media/). Minn Engine serves those files
 * at the addresses WordPress serves them from while this plugin is active,
 * and the handles below are registered as WordPress registers them, except
 * where Minn registers its own build of a handle, which always wins.
 */

defined('ABSPATH') || exit;

// WordPress has these files and registers them itself.
if (!defined('MINN_ENGINE_VERSION')) {
    return;
}

/** The handles WordPress registers for its own files, captured from the release the files came from. */
function minn_legacy_pack_manifest(): array
{
    static $manifest = null;
    if ($manifest === null) {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/manifest.json'), true);
        $manifest = is_array($manifest) ? $manifest : [];
    }
    return $manifest;
}

/**
 * Registers the pack's handles of one kind that are not registered yet, each
 * with what WordPress gives it: its group and loading strategy, its
 * right-to-left stylesheet, its data and its inline code.
 */
function minn_legacy_pack_register(WP_Dependencies $registry, string $kind): void
{
    $site = untrailingslashit(site_url());
    foreach ((array) (minn_legacy_pack_manifest()[$kind] ?? []) as $handle => $row) {
        if (isset($registry->registered[$handle])) {
            continue;
        }
        $registry->add($handle, $row['src'], (array) $row['deps'], $row['ver']);
        foreach ((array) $row['extra'] as $key => $value) {
            if ($key === 'before' || $key === 'after') {
                foreach ((array) $value as $code) {
                    if (!is_string($code) || $code === '') {
                        continue;
                    }
                    $code = str_replace('{site}', $site, $code);
                    $kind === 'scripts' ? $registry->add_inline_script($handle, $code, $key) : $registry->add_inline_style($handle, $code);
                }
                continue;
            }
            if ($key === 'data') {
                $value = str_replace('{site}', $site, (string) $value);
            } elseif ($key === 'path') {
                $value = str_replace('{pack}', __DIR__, (string) $value);
            }
            $registry->add_data($handle, $key, $value);
        }
    }
}

/** A handle's data as WordPress writes it: one variable, its values as strings. */
function minn_legacy_pack_data(WP_Scripts $scripts, string $handle, string $name, array $values): void
{
    if (isset($scripts->registered[$handle])) {
        $scripts->add_data($handle, 'data', 'var ' . $name . ' = ' . wp_json_encode($values) . ';');
    }
}

/**
 * A handle's inline code after it as the release wrote it (manifest.json),
 * with the request's values put where the capture's were: each pattern's
 * match is replaced by the value given for it, taken as it is.
 *
 * @param array<string, string> $values pattern => what its match becomes
 */
function minn_legacy_pack_after(WP_Scripts $scripts, string $handle, array $values): void
{
    $code = (string) (minn_legacy_pack_manifest()['scripts'][$handle]['extra']['after'][1] ?? '');
    if ($code === '' || !isset($scripts->registered[$handle])) {
        return;
    }
    foreach ($values as $pattern => $value) {
        $code = (string) preg_replace_callback($pattern, static fn (): string => $value, $code, 1);
    }
    $scripts->add_data($handle, 'after', [false, $code]);
}

/**
 * The settings that belong to the site, the visitor and the moment rather
 * than to the release: who is signed in, the nonces, the site's date and
 * time formats and time zone, and the heartbeat's own filter.
 */
function minn_legacy_pack_settings(WP_Scripts $scripts): void
{
    $user = get_current_user_id();
    minn_legacy_pack_data($scripts, 'utils', 'userSettings', [
        'url' => (string) SITECOOKIEPATH,
        'uid' => (string) $user,
        'time' => (string) time(),
        'secure' => (string) ('https' === wp_parse_url(site_url(), PHP_URL_SCHEME)),
    ]);
    minn_legacy_pack_data($scripts, 'wp-api-request', 'wpApiSettings', [
        'root' => sanitize_url(get_rest_url()),
        'nonce' => $user ? wp_create_nonce('wp_rest') : '',
        'versionString' => 'wp/v2/',
    ]);
    if (isset($scripts->registered['heartbeat'])) {
        $heartbeat = ['ajaxurl' => admin_url('admin-ajax.php')];
        if ($user) {
            $heartbeat['nonce'] = wp_create_nonce('heartbeat-nonce');
        }
        minn_legacy_pack_data($scripts, 'heartbeat', 'heartbeatSettings', (array) apply_filters('heartbeat_settings', $heartbeat));
    }
    // The signed-in user's stores: their id, and the preferences kept for them.
    minn_legacy_pack_after($scripts, 'wp-data', ['/(?<=userId = )\d+/' => (string) $user]);
    $preferences = $user ? get_user_meta($user, $GLOBALS['wpdb']->get_blog_prefix() . 'persisted_preferences', true) : '';
    minn_legacy_pack_after($scripts, 'wp-preferences', [
        '/(?<=serverData = ).*?(?=;\s)/s' => (string) wp_json_encode($preferences ?: ''),
        '/(?<=userId = ")\d+(?=")/' => (string) $user,
    ]);

    global $wp_locale;
    if (!$wp_locale instanceof WP_Locale) {
        return;
    }
    $locale = get_user_locale();
    $months = array_values($wp_locale->month);
    $monthsShort = array_values($wp_locale->month_abbrev);
    $weekdays = array_values($wp_locale->weekday);
    $weekdaysShort = array_values($wp_locale->weekday_abbrev);
    $dateFormat = (string) get_option('date_format');
    $timeFormat = (string) get_option('time_format');
    $startOfWeek = (int) get_option('start_of_week', 0);
    $offset = (float) get_option('gmt_offset', 0);
    $zone = (string) get_option('timezone_string');
    $abbr = '';
    if ($zone !== '') {
        try {
            $abbr = (new DateTime('now', new DateTimeZone($zone)))->format('T');
        } catch (Exception $e) {
            $abbr = '';
        }
        if (preg_match('/^[+-]/', $abbr)) {
            $abbr = '';
        }
    }
    // The relative-time words are the release's own, as the captured settings wrote them.
    $relative = [];
    $captured = (string) (minn_legacy_pack_manifest()['scripts']['wp-date']['extra']['after'][1] ?? '');
    if (preg_match('/"relative":(\{[^}]*\})/', $captured, $m)) {
        $relative = (array) json_decode($m[1], true);
    }
    $date = [
        'l10n' => [
            'locale' => $locale,
            'months' => $months,
            'monthsShort' => $monthsShort,
            'weekdays' => $weekdays,
            'weekdaysShort' => $weekdaysShort,
            'meridiem' => ['am' => $wp_locale->get_meridiem('am'), 'pm' => $wp_locale->get_meridiem('pm'), 'AM' => $wp_locale->get_meridiem('AM'), 'PM' => $wp_locale->get_meridiem('PM')],
            'relative' => $relative,
            'startOfWeek' => $startOfWeek,
        ],
        'formats' => [
            'time' => $timeFormat,
            'date' => $dateFormat,
            /* translators: 1: Date, 2: Time. */
            'datetime' => sprintf(__('%1$s %2$s'), $dateFormat, $timeFormat),
            'datetimeAbbreviated' => __('M j, Y g:i a'),
        ],
        'timezone' => [
            'offset' => $offset == (int) $offset ? (int) $offset : $offset,
            'offsetFormatted' => str_replace(['.25', '.5', '.75'], [':15', ':30', ':45'], (string) $offset),
            'string' => $zone,
            'abbr' => $abbr,
        ],
    ];
    // The site's dates: the settings object handed to the date package, and the locale and its words handed to moment.
    minn_legacy_pack_after($scripts, 'wp-date', ['/(?<=\( )\{.*\}(?= \);)/s' => (string) wp_json_encode($date)]);
    minn_legacy_pack_after($scripts, 'moment', ['/(?<=\( )\'.*(?= \);)/s' => "'" . esc_js($locale) . "', " . wp_json_encode([
        'months' => $months,
        'monthsShort' => $monthsShort,
        'weekdays' => $weekdays,
        'weekdaysShort' => $weekdaysShort,
        'week' => ['dow' => $startOfWeek],
        'longDateFormat' => ['LT' => $timeFormat, 'LTS' => null, 'L' => null, 'LL' => $dateFormat, 'LLL' => sprintf(__('%1$s %2$s'), $dateFormat, $timeFormat), 'LLLL' => null],
    ])]);
}

/** A file of the pack's media folder, or '' when it is not there. */
function minn_legacy_pack_media(string $file): string
{
    $path = __DIR__ . '/media/' . $file;
    return is_file($path) ? (string) file_get_contents($path) : '';
}

// The media modal's words, as WordPress gives them, ahead of any plugin's changes to them.
add_filter('media_view_strings', static function ($strings) {
    $own = json_decode(minn_legacy_pack_media('strings.json'), true);
    return array_merge(is_array($own) ? $own : [], (array) $strings);
}, 0);

if (!function_exists('wp_print_media_templates')) {
    /**
     * The media modal's templates, as WordPress prints them, then the ones
     * plugins add (print_media_templates).
     */
    function wp_print_media_templates()
    {
        echo str_replace(
            ['{site}', '{max_upload}'],
            [untrailingslashit(site_url()), esc_html(size_format(wp_max_upload_size()))],
            minn_legacy_pack_media('templates.html')
        );
        do_action('print_media_templates');
    }
}

/**
 * The media modal's upload (upload-attachment), posted to async-upload.php
 * or admin-ajax.php: the file is checked and stored as an attachment, and
 * the modal is sent the attachment as its scripts read it, or the reason it
 * was refused, as WordPress answers both.
 */
function minn_legacy_pack_upload_attachment(): void
{
    check_ajax_referer('media-form');
    $filename = isset($_FILES['async-upload']['name']) ? esc_html(wp_unslash((string) $_FILES['async-upload']['name'])) : '';
    $refuse = static function (string $message) use ($filename): void {
        echo wp_json_encode(['success' => false, 'data' => ['message' => $message, 'filename' => $filename]]);
        wp_die();
    };
    if (!current_user_can('upload_files')) {
        $refuse(__('Sorry, you are not allowed to upload files.'));
    }
    $postId = isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
    if ($postId > 0 && !current_user_can('edit_post', $postId)) {
        $refuse(__('Sorry, you are not allowed to attach files to this post.'));
    }
    // The uploader asks for the attachment's ID as soon as it exists, to
    // finish an image's sizes itself should the server fail making them.
    $announce = static function ($id): void {
        if (!headers_sent()) {
            header('X-WP-Upload-Attachment-ID: ' . (int) $id);
        }
    };
    add_action('add_attachment', $announce);
    $id = media_handle_upload('async-upload', $postId);
    remove_action('add_attachment', $announce);
    if (is_wp_error($id)) {
        $refuse($id->get_error_message());
    }
    $attachment = wp_prepare_attachment_for_js($id);
    if (!$attachment) {
        wp_die();
    }
    echo wp_json_encode(['success' => true, 'data' => $attachment]);
    wp_die();
}
add_action('wp_ajax_upload-attachment', 'minn_legacy_pack_upload_attachment', 1);

// Late, so the engine's own handles are in place first and keep their builds.
add_action('wp_default_scripts', static function ($scripts): void {
    if ($scripts instanceof WP_Scripts) {
        minn_legacy_pack_register($scripts, 'scripts');
        minn_legacy_pack_settings($scripts);
    }
}, 20);
add_action('wp_default_styles', static function ($styles): void {
    if ($styles instanceof WP_Styles) {
        minn_legacy_pack_register($styles, 'styles');
    }
}, 20);
