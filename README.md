# particlebits.com

Flat-file website builder for [particlebits.com](https://particlebits.com).
Requires PHP 8 or newer; no other dependencies.

## Commands

```bash
make compile   # compile dist/, the deployed production site
make serve     # serve dist/ at http://localhost:8000
make build     # compile build/, for http://localhost:8000
make local     # compile local/, for browsing via file://
make watch     # recompile the local target every 5s while writing
```

Article sources live in `sites/particlebits.com/`, templates and
system assets in `src/`, and per-target settings (base path, URL
format) in `sites/particlebits.com/sitemap.json`.

## Local server

`make serve` runs:

```bash
php -d variables_order=EGPCS -S localhost:8000 -t dist/particlebits.com router.php
```

Use `make serve-build` to serve the build target instead. The
`router.php` script rewrites extensionless article URLs (e.g.
`/2018/intro-privacy-security`) to their `.html` files, the same
way the production host does; without it those URLs 404 under the
built-in PHP server.
