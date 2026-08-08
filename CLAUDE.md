# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Flat-file static site builder for particlebits.com. Requires PHP 8+; no other dependencies (no Composer, no tests, no linter). Everything is compiled by `compile.php` from the command line.

## Commands

```bash
make compile      # compile dist/, the deployed production site
make build        # compile build/, for http://localhost:8000
make local        # compile local/, for browsing via file://
make serve        # serve dist/ at http://localhost:8000
make serve-build  # serve build/ at http://localhost:8000
make watch        # recompile the local target every 5s while writing
make worksheet    # regenerate the privacy worksheet PDF (needs Chrome)
```

`make serve` runs PHP's built-in server with `router.php`, which rewrites extensionless article URLs (e.g. `/2018/intro-privacy-security`) to their `.html` files the same way the production host does. The `dist`, `build`, and `local` output directories are generated — never edit them by hand.

## Architecture

`compile.php` is the single entry point. It registers an autoloader for the `Legacy\` namespace (`src/php/`), defines global constants (`WD`, `ENV`, template names) and global helper functions used throughout the classes: `get()` (safe key access on arrays/objects), `extend()`, `render()` (the templating engine — `extract()` + `include` + output buffering), `message()`, `info()`, `error()`. It then iterates every directory in `sites/` and compiles each one.

Compile targets: `dist` (production, clean URLs), `build` (localhost:8000), `local` (file:// browsing). The target name is passed as `$argv[1]` and selects which block of `sites/<site>/sitemap.json` (`basepath`, `homeUrl`, `sitemapUrl`, `urlFormat`) gets merged into the site config via `extend()`.

Class responsibilities (`src/php/`):
- `Site` — orchestrates one site: copies assets, combines `src/css/{fonts,site,media}.css` into `css/build.css` and minifies to `css/dist.css`, then delegates to `Articles`/`Pages`. The page template links `css/<env>.css`, so each env resolves to a different stylesheet (`local.css` is a plain source file).
- `Articles` — walks `sites/<site>/articles/<topic>/<year>/<slug>/`, loads each article's metadata, indexes them by slug, counts articles per topic directory.
- `Article` — merges `about.json` fields into public properties, builds URLs from the env's `urlFormat` (`%YEAR%` from the `date` field, `%SLUG%` from the directory name), and renders the article body.
- `Topics`/`Topic` — built from the `topics` tree in `sitemap.json`; maps color names to hard-coded RGBA values (in `Topics::$COLORS`); a topic is only shown if active.
- `Pages` — writes the output HTML: home page, one page per active topic, sitemap, and every article page plus its media assets. All pages render their content, then wrap it in `src/html/template.phtml`.
- `Filesystem` — thin path-prefixed file I/O wrapper (`read`, `put`, `has`, `listContents`); `put()` creates parent directories implicitly.

## Content model

An article lives at `sites/particlebits.com/articles/<topic>/<year>/<slug>/` and requires:
- `about.json` — title, date, author, topic, slug, snippet, plus optional `featured`, `assets`, `weblinks`, `data` maps
- `article.phtml` — the body markup

Optional: `media/` (files copied to `media/<year>/<slug>/` in the output) and `comments.json`.

Inside `article.phtml`, helper closures are in scope: `$e(string)` (HTML-escape and echo), `$a(key)` (echo asset URL from the `assets` map), `$d(key)` (echo value from `data`), `$wl(key)` (echo URL from `weblinks`), `$al(year, slug)` (echo an env-correct URL to another article by year and slug — the year is the target article's URL year, i.e. the year of its `date` field in `about.json`, not necessarily its source directory year; e.g. six-spheres lives under `articles/privacy/2017/` but is linked as `$al('2018', ...)` because its `date` is 2018). Titles and snippets support a mini inline markup: `_..._` → `<em>`, `*...*` → `<strong>`, `{}` → `/`.

Any additional `*.phtml` file in an article's directory (besides `article.phtml`) is compiled as a **standalone page**: rendered with the same helper closures but without the site template, and written to `media/<year>/<slug>/<name>.html` in the output. Standalone pages have no `<base>` tag, so they must reference same-directory media assets by bare filename (e.g. `quiz-data.js`), not via `$a()`.

Adding an article requires **both** creating its directory and listing its slug in the appropriate topic's `articles` array in `sites/particlebits.com/sitemap.json` — the sitemap's `topics` tree (which supports nested sub-topics) controls what appears on topic pages, while topic article counts come from the directory layout.
