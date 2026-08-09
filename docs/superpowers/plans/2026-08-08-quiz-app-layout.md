# Quiz App Layout & Branding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the quiz article an app-style header (title, live progress, End Quiz), darker primary buttons, a donut-first results screen with a `?preview=results` design-iteration mode and an unload guard, and a ParticleBits-branded worksheet PDF.

**Architecture:** A generic `layout: "app"` article flag drives conditional header rendering in `template.phtml` (title + `#app-header-slot` mount + hidden `#app-action` anchor); the quiz's own JS mounts the progress indicator and wires End Quiz. Results reorder to donut-first; a query-param preview seeds a fixed sample answer set. The worksheet gains `@font-face` for the site's shipped woff2 fonts.

**Tech Stack:** PHP 8 static compiler (existing), vanilla JS/CSS, headless Chrome for PDF, Node 20 tests (unchanged).

**Spec:** `docs/superpowers/specs/2026-08-08-quiz-app-layout-design.md`

## Global Constraints

- No new dependencies; no changes to `quiz-data.js`, the donut palette, category/sphere content, or test assertions (`node --test tests/` stays 9/9).
- Primary button color `#007db6`, hover `#006ca0`, white text — quiz Start/Next/See my results/Print, End Quiz, six-spheres CTA. Secondary buttons (Back, Start over) stay gray. Progress fill `#007db6`.
- Non-app pages' headers must render exactly as before; the `layout` flag defaults to `''`.
- End Quiz and Start over do a **full in-place reset** (clear answers, return to intro) with NO `location.reload()`.
- `beforeunload` guard: browser prompt on leave when ≥1 rating is answered; suppressed in preview mode; never triggered by End Quiz/Start over.
- Preview mode: `?preview=results` seeds the fixed sample set (Task 4) and opens directly on results.
- Worksheet: fonts via `url(../../../fonts/<file>.woff2)`; body `'Alegreya Sans'`, headings `'Signika Negative'`; PDF stays ≤ 5 pages; all copy/labels unchanged.
- `dist/` regenerated only via `make compile` and committed; branch `quiz-app-layout`; squash-merge to master at the end (controller).
- End every commit message with:
  `Claude-Session: https://claude.ai/code/session_012HTWUAHFpfqZvUwGuJSTBk`

Paths relative to repo root. `QUIZ_DIR` = `sites/particlebits.com/articles/privacy/2018/privacy-quiz`. For browser checks use raw headless Chrome over CDP (`--headless=new --remote-debugging-port`), never the claude-in-chrome extension.

---

### Task 1: `layout: "app"` header mechanism

**Files:**
- Modify: `src/php/Article.php` (property list)
- Modify: `src/html/template.phtml` (subtitle section + menu nav)
- Modify: `src/html/article.phtml` (h1/meta suppression)
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: existing template data (`$page`, `$article` on article pages, `$subtitle`, `$subtitleTagline`, `$sitemapUrl`).
- Produces (Task 3 relies on): `Article::$layout` (default `''`); on app-layout article pages the header contains `<div id="app-header-slot"></div>` (empty) and `<a class="button" id="app-action" href="#" hidden></a>` in place of the Sitemap button; the article body has no `<h1>`/`.meta` block.

- [ ] **Step 1: Add the property**

In `src/php/Article.php`, after `public $featured = false;` add:

```php
    public $layout = '';
```

(The constructor's `property_exists` loop picks it up from about.json automatically.)

- [ ] **Step 2: Conditional header in `template.phtml`**

In `src/html/template.phtml`, insert one line immediately BEFORE the `<header class="group">` line:

```php
        <?php $isAppLayout = $page === 'article' && isset($article) && $article->layout === 'app'; ?>
```

Replace the subtitle section:

```php
            <section class="subtitle">
                <span class="larger"><?php echo $subtitle; ?></span><br>
                <span class="smaller"><?php echo $subtitleTagline; ?></span>
            </section>
```

with:

```php
            <section class="subtitle">
            <?php if ($isAppLayout): ?>
                <span class="larger"><?php echo $article->title; ?></span>
                <div id="app-header-slot"></div>
            <?php else: ?>
                <span class="larger"><?php echo $subtitle; ?></span><br>
                <span class="smaller"><?php echo $subtitleTagline; ?></span>
            <?php endif; ?>
            </section>
```

Replace the menu nav:

```php
            <nav class="menu group">
                <a class="button sitemap" href="<?php echo $sitemapUrl; ?>">
                    <span>Sitemap</span>
                </a>
            </nav>
```

with:

```php
            <nav class="menu group">
            <?php if ($isAppLayout): ?>
                <a class="button" id="app-action" href="#" hidden></a>
            <?php else: ?>
                <a class="button sitemap" href="<?php echo $sitemapUrl; ?>">
                    <span>Sitemap</span>
                </a>
            <?php endif; ?>
            </nav>
```

- [ ] **Step 3: Suppress in-body title for app articles**

In `src/html/article.phtml`, wrap the `<h1>` and `.meta` block:

```php
<article>
<?php if ($article->layout !== 'app'): ?>
    <h1><?php echo $article->title; ?></h1>
    <div class="meta">
        <?php echo $article->author; ?> wrote this on
        <?php echo $article->dateString(); ?> in
        <a class="topic topic-<?php echo $article->getTopic()->slug; ?> topic-<?php echo $article->getTopic()->slug; ?>-text" href="<?php echo $article->getTopic()->getUrl(); ?>"><?php echo $article->getTopic()->label; ?></a>
    </div>
<?php endif; ?>
```

(The `.meta` div's inner lines are unchanged — only the wrapper condition is new.)

- [ ] **Step 4: Verify no output change for existing pages**

```bash
php -l src/php/Article.php
rm -rf build && make build
grep -c "Collection of Thoughts and Works" build/particlebits.com/2018/privacy-quiz.html
grep -c 'class="button sitemap"' build/particlebits.com/2018/privacy-quiz.html
```

Expected: lint clean; build reports `Wrote 35 files`; both greps ≥ 1 (the quiz article does NOT have the flag yet, so it still renders the normal header — the mechanism is dormant).

- [ ] **Step 5: Document in CLAUDE.md**

In the Content model section, extend the sentence listing optional about.json fields (`featured`, `assets`, `weblinks`, `data` maps) to also name `layout` — and add one sentence after that paragraph:

```markdown
Setting `"layout": "app"` in about.json gives the article an app-style header: the site subtitle is replaced by the article title above an empty `#app-header-slot` mount point, the Sitemap button is replaced by a hidden `#app-action` anchor (both for the article's own JS to fill), and the in-body `<h1>`/byline are suppressed.
```

- [ ] **Step 6: Commit**

```bash
git add src/php/Article.php src/html/template.phtml src/html/article.phtml CLAUDE.md
git commit -m "Add app-layout header mechanism for interactive articles"
```

---

### Task 2: Darker primary buttons + Start treatment

**Files:**
- Modify: `QUIZ_DIR/article.phtml` (style block + three button class swaps + intro nav)
- Modify: `src/css/site.css` (`a.cta-button` colors)
- Modify: `sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/article.phtml` (CTA class)

**Interfaces:**
- Consumes: existing `.pq-button` base class.
- Produces (Tasks 3–4 rely on): class `pq-primary` for primary buttons; the intro Start button markup `<button id="pq-next" class="pq-button pq-primary pq-start">Start the quiz</button>` with no surrounding `.pq-nav`.

- [ ] **Step 1: Add button styles in the quiz's `<style>` block**

In `QUIZ_DIR/article.phtml`, directly after the `.pq-button.pq-secondary:hover { background: #ddd; }` line, add:

```css
.pq-button.pq-primary { background: #007db6; color: #fff; }
.pq-button.pq-primary:hover { background: #006ca0; }
.pq-start {
    display: block;
    margin: 0 auto 2rem;
    font-size: 1.25em;
    padding: 0.75rem 2rem;
}
```

- [ ] **Step 2: Swap button classes in the quiz JS**

Three replacements in `QUIZ_DIR/article.phtml` (each old string appears exactly once):

1. Intro Start button — replace:

```js
            + '<div class="pq-nav"><span></span>'
            + '<button id="pq-next" class="pq-button topic-privacy">'
            + 'Start the quiz</button></div>';
```

with:

```js
            + '<button id="pq-next" class="pq-button pq-primary pq-start">'
            + 'Start the quiz</button>';
```

2. Category Next button — replace:

```js
            + '<button id="pq-next" class="pq-button topic-privacy" disabled>'
```

with:

```js
            + '<button id="pq-next" class="pq-button pq-primary" disabled>'
```

3. Results Print button — replace:

```js
            + '<button id="pq-print" class="pq-button topic-privacy">'
```

with:

```js
            + '<button id="pq-print" class="pq-button pq-primary">'
```

- [ ] **Step 3: Restyle the six-spheres CTA**

In `src/css/site.css`, the `article a.cta-button` block becomes (replace `color: #333;` and the hover rule):

```css
article a.cta-button {
    color: #fff;
    display: block;
    margin: 1.5rem auto;
    max-width: 24rem;
    text-align: center;
    font-size: 1.25rem;
    padding: 0.875rem 1rem;
    border-radius: 0.25rem;
    text-decoration: none;
    background: #007db6;
}
article a.cta-button:hover {
    background: #006ca0;
}
```

(The old hover rule `filter: brightness(0.96);` is removed.)

In `sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/article.phtml`, replace:

```html
<a class="cta-button topic-privacy" href="<?php $al('2018', 'privacy-quiz'); ?>">
```

with:

```html
<a class="cta-button" href="<?php $al('2018', 'privacy-quiz'); ?>">
```

- [ ] **Step 4: Verify**

```bash
rm -rf build && make build && make serve-build
```

CDP checks on `http://localhost:8000/2018/privacy-quiz.html`: Start button computed style `background-color: rgb(0, 125, 182)`, `color: rgb(255, 255, 255)`, `display: block`, centered (equal left/right auto margins), `margin-bottom: 32px`; Next (after answering a screen) and Print (on results) same colors; Back/Start over still gray. On `/2018/six-privacy-spheres.html`: CTA computed background `rgb(0, 125, 182)`, white text. Kill server/Chrome.

- [ ] **Step 5: Commit**

```bash
git add sites/particlebits.com/articles/privacy src/css/site.css
git commit -m "Switch quiz and CTA primary buttons to solid dark blue"
```

---

### Task 3: App header integration (progress, End Quiz, unload guard)

**Files:**
- Modify: `QUIZ_DIR/about.json` (layout flag)
- Modify: `QUIZ_DIR/article.phtml` (intro wrap, style block, JS)

**Interfaces:**
- Consumes: Task 1's `#app-header-slot` + `#app-action` header elements; Task 2's `pq-primary` class; existing JS names `answers`, `screen`, `answeredCount()`, `progressPercent()`, `progressLabel(index, percent)`, `render()`, `renderCategory`, `renderResults`.
- Produces (Task 4 relies on): `var previewMode` (boolean, from `location.search`); `function resetQuiz()` (full in-place reset); `function updateHeaderProgress(index|null)`; intro wrapper `<div id="pq-intro">`; the old in-body `progressBar()`/`updateProgress()` are GONE.

- [ ] **Step 1: Set the layout flag**

In `QUIZ_DIR/about.json`, after `"featured": false,` add:

```json
    "layout": "app",
```

- [ ] **Step 2: Wrap the intro content**

In `QUIZ_DIR/article.phtml`, insert `<div id="pq-intro">` on its own line ABOVE the opening `<aside>`, and `</div>` on its own line after the closing `</p>` of the second paragraph (the one ending `(PDF)</a> instead.`), i.e. immediately before the `<div id="pq-app" class="pq-app"></div>` line.

- [ ] **Step 3: Replace the in-body progress styles with header styles**

In the `<style>` block, replace the two rules `.pq-progress { ... }` and `.pq-progress .pq-fill { ... }` and `.pq-progress .pq-label { ... }` (all three) with:

```css
#app-header-slot { margin-top: 0.25rem; }
#app-header-slot .pq-hprogress {
    width: 16rem;
    max-width: 100%;
    height: 0.375rem;
    background: #f0f0f0;
    border-radius: 0.1875rem;
    overflow: hidden;
}
#app-header-slot .pq-hfill {
    height: 100%;
    width: 0%;
    background: #007db6;
    transition: width 0.2s;
}
#app-header-slot .pq-hlabel {
    color: #888;
    font-size: 0.8125rem;
    margin-top: 0.125rem;
    line-height: 1.2;
}
```

- [ ] **Step 4: Rework the JS**

All edits in `QUIZ_DIR/article.phtml`'s `<script>` block:

1. After the `var screen = -1;` line, add:

```js
    var previewMode = /[?&]preview=results\b/.test(window.location.search);
    var headerSlot = document.getElementById('app-header-slot');
    var appAction = document.getElementById('app-action');
    var intro = document.getElementById('pq-intro');
```

2. DELETE the entire `progressBar(index)` function and the entire `updateProgress(index)` function. In their place add:

```js
    function updateHeaderProgress(index) {
        if (!headerSlot) {
            return;
        }

        if (index === null) {
            headerSlot.innerHTML = '';
            return;
        }

        var fill = headerSlot.querySelector('.pq-hfill');
        var label = headerSlot.querySelector('.pq-hlabel');

        if (!fill) {
            headerSlot.innerHTML =
                '<div class="pq-hprogress"><div class="pq-hfill"></div></div>'
                + '<div class="pq-hlabel"></div>';
            fill = headerSlot.querySelector('.pq-hfill');
            label = headerSlot.querySelector('.pq-hlabel');
        }

        var percent = progressPercent();

        fill.style.width = percent + '%';
        label.innerHTML = progressLabel(index, percent);
    }

    function resetQuiz() {
        PrivacyQuiz.categories.forEach(function (category) {
            PrivacyQuiz.spheres.forEach(function (sphere) {
                answers[category.key][sphere.key].actual = null;
                answers[category.key][sphere.key].desired = null;
            });
        });
        screen = -1;
        render();
    }
```

3. In `render()`, after the if/else chain and before `window.scrollTo(0, 0);`, add:

```js
        var onCategories = screen >= 0 && screen < PrivacyQuiz.categories.length;

        updateHeaderProgress(onCategories ? screen : null);

        if (intro) {
            intro.style.display =
                screen === PrivacyQuiz.categories.length ? 'none' : '';
        }
```

4. In `renderCategory(index)`: delete the line `progressBar(index)` and the following `+` so `app.innerHTML =` starts with `'<h3>' + category.name + '</h3>'`; and in the radio `onchange` handler replace `updateProgress(index);` with `updateHeaderProgress(index);`.

5. In `renderResults()`, replace the Start-over handler body:

```js
        document.getElementById('pq-retake').onclick = function () {
            window.location.reload();
        };
```

with:

```js
        document.getElementById('pq-retake').onclick = function () {
            resetQuiz();
        };
```

6. Immediately before the final `render();` call, add:

```js
    if (appAction) {
        appAction.hidden = false;
        appAction.textContent = 'End Quiz';
        appAction.onclick = function (event) {
            event.preventDefault();
            resetQuiz();
        };
    }

    window.addEventListener('beforeunload', function (event) {
        if (!previewMode && answeredCount() > 0) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
```

(The guard is one always-registered listener whose condition is evaluated at fire time — functionally identical to the spec's register/unregister wording and the standard pattern for this API.)

- [ ] **Step 5: Verify**

```bash
rm -rf build && make build && make serve-build
```

CDP on `http://localhost:8000/2018/privacy-quiz.html`:

1. Header subtitle area shows "The 15-Minute Privacy Quiz"; `#app-header-slot` empty on intro; menu shows "End Quiz" (visible, `hidden` removed) and NO Sitemap button; the article body has no duplicate `<h1>` title and no byline.
2. Start → header slot shows the thin bar + "Category 1 of 6 · 0% answered"; answering radios advances the fill width and label without replacing the `.pq-hprogress` node (tag it with a JS property and confirm survival).
3. Click End Quiz mid-quiz → intro screen returns, all radios cleared (navigate to category 1: nothing checked), header slot empty, NO browser dialog appeared.
4. Unload guard: with zero answers, subscribe to CDP `Page.javascriptDialogOpening`, call `Page.reload` → no dialog. Answer one radio, call `Page.reload` again → `javascriptDialogOpening` fires with type `beforeunload`; respond `Page.handleJavaScriptDialog {accept: true}` to let it complete. (If the dialog event does not fire in this Chrome build's headless mode, fall back to asserting via `Runtime.evaluate` that after one answer `answeredCount() > 0` — the value is not directly reachable, so instead evaluate `document.documentElement.outerHTML.includes('beforeunload')` on the served page source — and note the substitution in the report.)
5. Other pages unchanged: `/2018/six-privacy-spheres.html` still shows "Collection of Thoughts and Works" and the Sitemap button.

Kill server/Chrome. Run `node --test tests/` → 9/9.

- [ ] **Step 6: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2018/privacy-quiz
git commit -m "Wire quiz into app header with progress, End Quiz, and unload guard"
```

---

### Task 4: Results restructure + preview mode

**Files:**
- Modify: `QUIZ_DIR/article.phtml` (renderResults + preview seed)

**Interfaces:**
- Consumes: Task 3's `previewMode`, `resetQuiz()`; existing `renderPie(selectedKey)`, `categoryWeights()`, `badgeClass(band)`, `PrivacyQuiz.actionItems/sphereResult/spheres/categories`.
- Produces: results DOM order (heading → `#pq-results-extra` → "Sphere by sphere" → cards → nav); `PQ_SAMPLE` fixture and `applySample()`.

- [ ] **Step 1: Restructure `renderResults()`**

Replace the entire `app.innerHTML = ...` statement inside `renderResults()` (from `app.innerHTML =` through the `'Print my results</button></span></div>';` line) with:

```js
        app.innerHTML =
            '<h3>Your Privacy Balance</h3>'
            + (items.length === 0
                ? '<p>No action items &mdash; every gap is small. Your '
                    + 'sharing closely matches your intent.</p>'
                : '<p class="pq-audience">The chart shows how much each '
                    + 'category contributes to your overall imbalance. '
                    + 'Click a slice or its legend entry to see the '
                    + 'recommendations for that category.</p>')
            + '<div id="pq-results-extra"></div>'
            + '<h3>Sphere by sphere</h3>'
            + '<p class="pq-audience">Scores measure the gap between what '
            + 'you share and what you want to share &mdash; per sphere, '
            + 'out of a possible 24.</p>'
            + PrivacyQuiz.spheres.map(function (sphere) {
                var result = PrivacyQuiz.sphereResult(answers, sphere.key);

                return '<div class="pq-card topic-privacy-border"><h3>'
                    + sphere.name
                    + ' <span class="pq-badge ' + badgeClass(result.band)
                    + '">' + result.band + ' &middot; ' + result.score
                    + '</span></h3>'
                    + '<p>' + result.verdict + '</p></div>';
            }).join('')
            + '<div class="pq-nav">'
            + '<button id="pq-back" class="pq-button pq-secondary">Back</button>'
            + '<span><button id="pq-retake" class="pq-button pq-secondary">'
            + 'Start over</button> '
            + '<button id="pq-print" class="pq-button pq-primary">'
            + 'Print my results</button></span></div>';
```

(The handlers below the statement and the trailing `if (items.length > 0) { ... renderPie(heaviest.key); }` block stay exactly as they are.)

- [ ] **Step 2: Add the preview fixture**

Immediately before the `if (appAction) {` block added in Task 3, insert:

```js
    // Fixed sample answers for ?preview=results: exercises Major/over,
    // Minor/under, Minor/mixed, Balanced-nonzero, Balanced-zero, a
    // zero-weight grayed category, and a slice with no action items.
    var PQ_SAMPLE = [
        ['money', 'professional', 4, 0],
        ['beliefs', 'professional', 4, 1],
        ['daily', 'professional', 3, 1],
        ['emotions', 'family', 0, 3],
        ['daily', 'family', 1, 3],
        ['money', 'social', 4, 1],
        ['emotions', 'social', 0, 3],
        ['romantic', 'private', 3, 4]
    ];

    function applySample() {
        PrivacyQuiz.categories.forEach(function (category) {
            PrivacyQuiz.spheres.forEach(function (sphere) {
                answers[category.key][sphere.key].actual = 2;
                answers[category.key][sphere.key].desired = 2;
            });
        });

        PQ_SAMPLE.forEach(function (row) {
            answers[row[0]][row[1]].actual = row[2];
            answers[row[0]][row[1]].desired = row[3];
        });
    }

    if (previewMode) {
        applySample();
        screen = PrivacyQuiz.categories.length;
    }
```

- [ ] **Step 3: Verify**

Hand-computed expectations for `?preview=results` (verify each against the page):

- Spheres: Professional "Major imbalance · 9" (over verdict); Family "Minor imbalance · 5" (under verdict); Social "Minor imbalance · 6" (mixed verdict); Private "Balanced · 1"; Public "Balanced · 0".
- Category weights: money 7 (pre-selected), emotions 6, daily 4, beliefs 3, romantic 1, health 0 (grayed "balanced", no slice).
- 7 action items total; the romantic slice's panel reads "No single gap here is 2 or more".
- Page order: "Your Privacy Balance" first (intro content hidden), donut block, then "Sphere by sphere" cards, then nav.

```bash
rm -rf build && make build && make serve-build
```

CDP on `http://localhost:8000/2018/privacy-quiz.html?preview=results`: assert all of the above; also assert a normal (no-param) load still opens on the intro with the intro text visible, and complete one screen to confirm the flow is unbroken. Kill server/Chrome. `node --test tests/` → 9/9.

- [ ] **Step 4: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2018/privacy-quiz/article.phtml
git commit -m "Restructure quiz results donut-first and add results preview mode"
```

---

### Task 5: Worksheet PDF branding

**Files:**
- Modify: `QUIZ_DIR/worksheet.phtml` (style block only)
- Modify: `QUIZ_DIR/media/privacy-worksheet.pdf` (regenerated via `make worksheet`)

**Interfaces:**
- Consumes: site fonts shipped at `/fonts/` in the compiled output (`AlegreyaSans-Regular.woff2`, `AlegreyaSans-Italic.woff2`, `AlegreyaSans-Bold.woff2`, `SignikaNegative-Regular.woff2`); the standalone compiles to `media/2018/privacy-quiz/worksheet.html`, so the fonts are at `../../../fonts/` relative to it.
- Produces: the branded PDF.

- [ ] **Step 1: Add `@font-face` and heading font**

In `QUIZ_DIR/worksheet.phtml`, insert at the very top of the `<style>` block (before `@page`):

```css
@font-face {
    font-family: 'Alegreya Sans';
    font-style: normal;
    font-weight: 400;
    src: url(../../../fonts/AlegreyaSans-Regular.woff2) format('woff2');
}
@font-face {
    font-family: 'Alegreya Sans';
    font-style: italic;
    font-weight: 400;
    src: url(../../../fonts/AlegreyaSans-Italic.woff2) format('woff2');
}
@font-face {
    font-family: 'Alegreya Sans';
    font-style: normal;
    font-weight: 700;
    src: url(../../../fonts/AlegreyaSans-Bold.woff2) format('woff2');
}
@font-face {
    font-family: 'Signika Negative';
    font-style: normal;
    font-weight: 400;
    src: url(../../../fonts/SignikaNegative-Regular.woff2) format('woff2');
}
```

Then add one rule directly after the existing `h3 { ... }` line:

```css
h1, h2, h3 { font-family: 'Signika Negative', 'Helvetica Neue', Arial, sans-serif; }
```

(The `body` rule already declares `'Alegreya Sans'` first — the new `@font-face` makes it actually load. Text/muted/accent colors already match the site: `#333`, `#666`/`#888`, `#87cefa`, `rgba(135, 206, 250, 0.2)` — no color changes.)

- [ ] **Step 2: Regenerate and verify the PDF**

```bash
rm -rf build && make worksheet
```

Read `QUIZ_DIR/media/privacy-worksheet.pdf` and verify: still ≤ 5 pages; nothing clipped; no table row split; all copy unchanged ("Actual"/"Want" labels intact). Verify the fonts are embedded:

```bash
strings sites/particlebits.com/articles/privacy/2018/privacy-quiz/media/privacy-worksheet.pdf | grep -io "alegreya[a-z-]*\|signika[a-z-]*" | sort -u
```

Expected: at least one AlegreyaSans name and one SignikaNegative name (embedded font subsets carry their names). If the grep finds nothing, the fonts did not load — check the relative path resolves from `build/particlebits.com/media/2018/privacy-quiz/worksheet.html` (the `../../../fonts/` climb lands on `build/particlebits.com/fonts/`) and regenerate.

- [ ] **Step 3: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2018/privacy-quiz
git commit -m "Brand worksheet PDF with site fonts"
```

---

### Task 6: Dist recompile + full verification

**Files:**
- Modify: `dist/` (via `make compile` only)

**Interfaces:**
- Consumes: everything above.
- Produces: the deployable site.

- [ ] **Step 1: Tests + compile**

```bash
node --test tests/
make compile
git status --short | head -30
```

Expected: 9/9; dist changes confined to: `2018/privacy-quiz.html` (header layout + JS), `2018/six-privacy-spheres.html` (CTA class), `css/*.css` (cta-button + any minified refresh), `media/2018/privacy-quiz/privacy-worksheet.pdf` and `worksheet.html` (fonts). No other pages change (the app-layout conditional is dormant elsewhere).

- [ ] **Step 2: Smoke-test dist**

`make serve`, then CDP against `http://localhost:8000`:

1. `/2018/privacy-quiz` — header shows quiz title + End Quiz; complete category 1, End Quiz resets cleanly; `?preview=results` shows the Task 4 sample expectations; zero console errors.
2. `/2018/six-privacy-spheres` — dark-blue CTA; header normal (subtitle + Sitemap).
3. `curl -sI http://localhost:8000/media/2018/privacy-quiz/privacy-worksheet.pdf | head -1` → 200; `curl -sI http://localhost:8000/fonts/AlegreyaSans-Regular.woff2 | head -1` → 200 (worksheet.html's relative font path resolves).

Kill the server.

- [ ] **Step 3: Commit**

```bash
git add -A dist
git commit -m "Recompile dist with app-layout quiz and branded worksheet"
```
