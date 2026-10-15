# Minn Legacy Pack

WordPress's own browser scripts and styles, for a site running [Minn](https://minn.run).

Minn runs a WordPress site without WordPress. Its engine carries no WordPress code, so the
scripts WordPress ships for its admin screens are not in it: the media library, the classic
editor (TinyMCE), jQuery UI, Backbone and the rest. Most sites never need them. The editors of
page builders such as Elementor do, and this plugin brings them back.

While the plugin is active on Minn:

- Minn serves these files at the addresses WordPress serves them from (`/wp-includes/js/...`,
  `/wp-admin/css/...`), straight from this plugin's folder, before any plugin loads.
- The plugin registers each script and style handle as WordPress registers it: its file, its
  dependencies, its version, its data and inline code. A handle Minn provides itself (its own
  jQuery, its builds of the small `wp-*` packages) stays Minn's.
- The media library window (`wp.media`) gets WordPress's words and templates, and its uploads
  are answered as WordPress answers them: a file sent to `/wp-admin/async-upload.php` (or
  admin-ajax.php) is checked, stored as an attachment and returned as the window reads it.

On WordPress the plugin does nothing; WordPress has all of this already.

## What is in it

| Folder | From WordPress |
|---|---|
| `wp-includes/js`, `wp-includes/css`, `wp-includes/images`, `wp-includes/fonts` | the minified scripts, styles, images and fonts |
| `wp-admin/js`, `wp-admin/css`, `wp-admin/images` | the same for the admin's own assets |
| `manifest.json` | every handle WordPress registers for those files, captured from the release |
| `media/strings.json`, `media/templates.html` | the media window's words and templates |

The version of this plugin is the WordPress release its files come from.

## Licence

The plugin's own code, `minn-legacy-pack.php` and `bin/`, is MIT licensed (`LICENSE`). It contains
none of WordPress's code: where it sets a script's inline code for the request (the signed-in user,
the site's date settings), it fills the request's values into the code captured from WordPress.

What it carries is WordPress's, under WordPress's licence, the GNU General Public License, version 2
or later (`license.txt`): the files under `wp-includes/` and `wp-admin/`, with the third-party
libraries WordPress bundles under their own licences, and what was captured from the release
(`manifest.json`, `media/`). Each minified file comes with the unminified original WordPress ships
beside it, so the source travels with the code; the few libraries WordPress ships only minified
(TinyMCE, jQuery and the like) are as WordPress ships them.

The package as a whole is therefore `MIT AND GPL-2.0-or-later`: the MIT parts may be taken on their
own under MIT, and the whole may be shared under the GPL. Minn Engine is MIT and contains none of
these files.

## Versions

The version is the WordPress release the files come from (7.1.3). A release of the plugin's own
code on the same files adds a fourth number (7.1.3.1). Tags are `v<version>`, and each release on
GitHub carries `minn-legacy-pack.zip` (`bin/release.sh`). The plugin's `Update URI` names this
repository, so Minn offers its updates through updates.minn.run, which follows its releases, and
WordPress never looks for it on wordpress.org.

## Installing it

Minn offers it: where a site runs an editor known to need it (Elementor's today) and the pack is
not active, Minn Admin's Plugins tab and the editor's links offer to install it from the Minn update
service. It can also be installed like any plugin from its release zip.

## Building it from a WordPress release

```bash
# 1. Capture the registry inside a WordPress install of that release, no plugins or theme loaded.
wp --path=/path/to/wordpress eval-file bin/capture.php --skip-plugins --skip-themes --user=1 > build/registry.json
# 2. Copy the files and write the manifest.
php bin/build.php /path/to/wordpress build/registry.json
```

Set the `Version:` header in `minn-legacy-pack.php` to the release, commit, tag `v<version>`, then
`bin/release.sh` and attach `dist/minn-legacy-pack.zip` to the GitHub release.
