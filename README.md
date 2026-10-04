# Tufte Formats

Post formats as notes for [Tufte Blocks](https://github.com/dhanson-wp/tufte-blocks) sites.

I wanted short posts (a thought, a photo, a link, a quote) to live next to my essays without crowding them. This plugin turns any post that isn't a Standard post into a note, keeps notes out of the blog loop, search, and the main feed, and gives them their own archive views.

It's a companion for the theme and it knows nothing about my site. Site-specific work (migrations, redirects) lives in the site repo.

## What it does

- **Notes model.** Any non-standard post format is a note (aside, gallery, link, image, quote, status, video, audio). Essays stay Standard.
- **Archive views.** `/archive/`, `/archive/essays/`, and `/archive/notes/` filter one stream, with a template hierarchy hook for the page templates.
- **Note meta.** A reply count in the note's margin ("N replies", or "Reply" with none), fediverse handles and webmention sources under comments, and thread depth for notes.
- **Link notes.** The first external link in a link-format note is marked as external, with a source name that links to the same address.
- **Creating notes.** Untitled posts made through the REST API or code get a format from their first block, and Posts > New note starts a status note. A titled post stays an essay.
- **In-place navigation.** A small ES module swaps the archive stream without a full page load, with the real URL kept.
- **Parent compatibility.** Tufte Blocks 1.9.0 registers its own Status-only notes hooks; this plugin removes them so one model runs.

The block theme side (templates, patterns, styles) belongs to the theme. The child theme at [derekhanson.blog](https://derekhanson.blog) is the reference.

## Requirements

A block theme (Tufte Blocks or a child of it). I build and test it on WordPress 7.1 with PHP 8.3; the in-place navigation uses script modules and the Interactivity API, so it needs a WordPress release that has them. I haven't set a minimum version yet.

## Install

Copy this folder into `wp-content/plugins/tufte-formats` and activate it.

## Tests

The tests are plain PHP and run inside WordPress:

```
wp eval-file wp-content/plugins/tufte-formats/tests/run.php
```

The runner prints one PASS or FAIL line per case and exits non-zero on a failure.

## Where this lives

This repo is the plugin's home. My site repo (`dhanson-wp/derekhanson-blog`) carries a copy under `plugins/tufte-formats` for deploys until I wire the two together. Change the plugin here, then copy it over, so the two don't drift.

## Deploying

This repo deploys itself through WordPress.com's GitHub deployments in advanced mode. The connection is manual, and its destination is `/wp-content/plugins/tufte-formats`. `.github/workflows/deploy.yml` uploads an artifact named `wpcom` with only `tufte-formats.php`, `assets`, and `includes`, so `tests`, this README, and `.gitignore` are left out on purpose. The site repo's own deploy doesn't ship this folder; two repos must not deploy the same one.

## License

GPL-2.0-or-later.
