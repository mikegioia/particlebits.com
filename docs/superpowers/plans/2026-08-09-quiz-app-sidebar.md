# Quiz App Sidebar & Condensed Screens Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Title-only app header, a generic fixed right-column `#app-side` slot (companion note on landing, progress + Back/Next during the quiz, results nav on results, bottom bar on mobile), condensed single-viewport category screens, and click-time validation.

**Architecture:** The template's app-layout branch drops the header slot and swaps the Topics section for an empty `section.app-side` that mirrors the topics column's fixed positioning and width ladder (bottom bar ≤768px). The quiz JS gains `renderSide()` (per-screen sidebar contents), `updateSideProgress()`, and `showValidation()`; all `.pq-nav` button rows leave the content. The companion note stays server-rendered in a hidden div and is copied into the sidebar on the landing only.

**Tech Stack:** PHP 8 static compiler (existing), vanilla JS/CSS, Node 20 tests (unchanged), raw headless Chrome over CDP for verification.

**Spec:** `docs/superpowers/specs/2026-08-09-quiz-app-sidebar-design.md`

## Global Constraints

- No changes to `quiz-data.js`, test assertions (`node --test tests/` stays 9/9), the donut, the worksheet, or the PDF.
- Non-app pages must compile byte-identically — the app conditionals stay dormant elsewhere. New PHP control lines in `template.phtml` go at column 0 (this file's convention; HTML-indented `<?php ?>` control lines corrupt output via trailing-newline swallowing).
- Primary `#007db6` / hover `#006ca0` / secondary gray button styling unchanged; End Quiz, unload guard, `?preview=results`, and in-place resets unchanged.
- The Next button is NEVER disabled; validation is click-time (`pq-missing` highlight + sidebar message + scroll to first missing row); highlight clears per-row on answer; message clears when the screen completes or advances.
- The companion note appears ONLY on the landing screen, in the sidebar; its links stay server-rendered PHP (`$al`, topic url).
- `dist/` regenerated only via `make compile`, committed. Branch `quiz-app-sidebar`; squash-merge at the end (controller).
- Browser checks: raw headless Chrome over CDP (`--headless=new --remote-debugging-port`), never claude-in-chrome tools. Known quirk: beforeunload dialog events don't fire in this headless build — not needed this round.
- End every commit message with:
  `Claude-Session: https://claude.ai/code/session_012HTWUAHFpfqZvUwGuJSTBk`

`QUIZ_DIR` = `sites/particlebits.com/articles/privacy/2018/privacy-quiz`. Paths relative to repo root.

---

### Task 1: Template + CSS — title-only header and the `#app-side` slot

**Files:**
- Modify: `src/html/template.phtml` (subtitle branch; topics section wrap)
- Modify: `src/css/site.css` (subtitle flex rule; `main section.app-side` base rule)
- Modify: `src/css/media.css` (width ladder; ≤768px bottom bar; ≤440px flex fix)
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: existing `$isAppLayout` variable and `body.app` class from the previous round.
- Produces (Task 2 relies on): on app pages, an empty `<section class="app-side" id="app-side"></section>` in place of the Topics section, fixed at the topics column's position/widths on desktop and a fixed bottom bar ≤768px; NO `#app-header-slot` anywhere; header shows only the title, vertically centered.

- [ ] **Step 1: Header — remove the slot**

In `src/html/template.phtml`, in the app-layout subtitle branch, delete the line:

```php
                <div id="app-header-slot"></div>
```

(The branch keeps only `<span class="larger"><?php echo $article->title; ?></span>`.)

- [ ] **Step 2: Swap the Topics section on app pages**

Still in `template.phtml`: the Topics block runs from `<section class="topics">` to its matching `</section>` (it contains the `<h2>Topics</h2>`, the topics `<ul>`/no-results div, and the Table of Contents block). Wrap it:

- Immediately BEFORE `<section class="topics">`, insert (column 0):

```php
<?php if ($isAppLayout): ?>
            <section class="app-side" id="app-side"></section>
<?php else: ?>
```

- Immediately AFTER the Topics block's closing `</section>`, insert (column 0):

```php
<?php endif; ?>
```

- [ ] **Step 3: site.css rules**

After the `header nav.menu a.button[hidden] { ... }` rule, add:

```css
body.app header section.subtitle {
    display: flex;
    align-items: center;
    font-size: 1.6rem;
}
```

After the `body.article main section.topics { ... }` rule, add:

```css
/* Per-article right-column slot for layout:"app" pages; stays fixed */
main section.app-side {
    right: 0;
    width: 14rem;
    padding: 1rem;
    position: fixed;
    top: calc(4rem + 1px);
    box-sizing: border-box;
}
```

- [ ] **Step 4: media.css rules**

Four width-ladder additions — in each of these existing rules, change the selector `main section.topics` to `main section.topics, main section.app-side` (ONLY in rules that set `width`; leave the `display`/`position` rules alone):

1. `@media screen and (min-width: 768px)` → `main section.topics { width: 12rem; }`
2. `@media screen and (min-width: 1200px)` → `main section.topics { width: 18rem; }`
3. `@media screen and (min-width: 1400px)` → `main section.topics { width: 20rem; }`
4. `@media screen and (min-width: 1600px)` → `main section.topics { width: calc(30%); }`

In `@media screen and (max-width: 768px)` (the block that sets `main section.topics { display: none; }`), add after that rule:

```css
    main section.app-side {
        top: auto;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        z-index: 10;
        display: flex;
        gap: 0.75rem;
        background: #fff;
        position: fixed;
        align-items: center;
        padding: 0.5rem 1rem;
        border-top: 1px solid #ddd;
    }
    body.app main section.content {
        padding-bottom: 5rem;
    }
```

In `@media screen and (max-width: 440px)`, change the existing rule:

```css
    body.app header section.subtitle {
        display: block;
    }
```

to:

```css
    body.app header section.subtitle {
        display: flex;
    }
```

- [ ] **Step 5: Verify**

```bash
rm -rf build && make build
grep -c 'id="app-side"' build/particlebits.com/2018/privacy-quiz.html
grep -c 'app-header-slot\|<h2>Topics</h2>' build/particlebits.com/2018/privacy-quiz.html
grep -c '<h2>Topics</h2>' build/particlebits.com/2018/six-privacy-spheres.html
```

Expected: build `Wrote 35 files`; quiz page has `app-side` (1) and zero header-slot/Topics matches; six-spheres still has Topics (1). Byte-identity for non-app pages: stash a pre-change build if needed, or rely on the greps + the dormant-conditional pattern proven last round. The quiz page will temporarily show no progress bar (its JS still targets the removed `#app-header-slot` and null-guards) — Task 2 fixes that; confirm the quiz still loads with zero console errors via a quick headless `--dump-dom` or CDP load.

- [ ] **Step 6: Update CLAUDE.md**

Replace the `layout: "app"` paragraph's description so it reads:

```markdown
Setting `"layout": "app"` in about.json gives the article an app-style layout: the site subtitle is replaced by the article title (vertically centered, title only), the Sitemap button is replaced by a hidden `#app-action` anchor, the right-column Topics section is replaced by an empty `<section class="app-side" id="app-side">` slot (fixed on desktop, a bottom bar ≤768px) — anchor and slot are filled by the article's own JS — and the in-body `<h1>`/byline are suppressed.
```

- [ ] **Step 7: Commit**

```bash
git add src/html/template.phtml src/css/site.css src/css/media.css CLAUDE.md
git commit -m "Add app-side right-column slot and title-only app header"
```

---

### Task 2: Quiz rework — sidebar contents, condensed screens, validation

**Files:**
- Modify: `QUIZ_DIR/article.phtml` (markup, style block, JS)

**Interfaces:**
- Consumes: Task 1's `#app-side` (empty section, fixed/bottom-bar), existing JS names (`answers`, `screen`, `previewMode`, `answeredCount`, `progressPercent`, `progressLabel(index, percent)`, `resetQuiz`, `render`, `renderIntro`, `renderCategory`, `renderResults`, `isScreenComplete`, `ratingRow`, `renderPie`, `categoryWeights`).
- Produces: `renderSide()`, `updateSideProgress(index)`, `showValidation(index)`, `sideProgressHtml(index)`; markup `#pq-side-intro` (hidden server-rendered companion note); no `.pq-nav` anywhere; no `#app-header-slot`/`updateHeaderProgress` references.

- [ ] **Step 1: Markup — move the companion note**

In `QUIZ_DIR/article.phtml`:

1. Delete the `<aside>...</aside>` block (lines starting `<aside>` through `</aside>`) from inside `<div id="pq-intro">`, leaving the two `<p>` paragraphs.
2. After the `<div id="pq-app" class="pq-app"></div>` line, add:

```html
<div id="pq-side-intro" hidden>
    <aside>
        This quiz is a companion to
        <a href="<?php $al('2018', 'six-privacy-spheres'); ?>">The Six Spheres
        of Privacy</a>, part of the
        <a href="<?php echo $article->getTopic()->url; ?>">Privacy and Security
        series</a>.
    </aside>
</div>
```

- [ ] **Step 2: Style block rework**

1. DELETE the four `#app-header-slot` rules (`#app-header-slot { ... }`, `.pq-hprogress`, `.pq-hfill`, `.pq-hlabel` variants under it).
2. DELETE the `.pq-nav { ... }` rule.
3. REPLACE the three rules `.pq-sphere { ... }`, `.pq-sphere legend { ... }`, `.pq-audience { ... }` with:

```css
.pq-audience { color: #888; font-size: 0.875em; margin: 0 0 0.5rem; }
.pq-cat-head { margin: 0 0 0.125rem; text-align: center; }
.pq-cat-desc { text-align: center; margin: 0 0 0.75rem; }
.pq-sphere {
    border: 1px solid #ddd;
    border-radius: 0.25rem;
    margin: 0 0 0.5rem;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
}
.pq-sphere-head { margin-bottom: 0.125rem; }
.pq-sphere-aud { color: #888; font-size: 0.8125em; }
```

4. CHANGE `.pq-rating { display: flex; flex-wrap: wrap; align-items: baseline; margin: 0.375rem 0; }` → margin becomes `0.125rem 0`.
5. ADD after the `.pq-rating label { ... }` rule:

```css
.pq-rating.pq-missing {
    background: #fdecea;
    border-radius: 0.125rem;
    box-shadow: inset 3px 0 0 #e34948;
}
#app-side .pq-side-progress { margin-bottom: 0.75rem; }
#app-side .pq-hprogress {
    height: 0.375rem;
    background: #f0f0f0;
    border-radius: 0.1875rem;
    overflow: hidden;
}
#app-side .pq-hfill {
    height: 100%;
    width: 0%;
    background: #007db6;
    transition: width 0.2s;
}
#app-side .pq-hlabel {
    color: #888;
    font-size: 0.8125rem;
    margin-top: 0.25rem;
    line-height: 1.2;
}
#app-side .pq-button {
    width: 100%;
    display: block;
    text-align: center;
    box-sizing: border-box;
    margin-bottom: 0.5rem;
}
#app-side aside {
    margin: 0;
    color: #777;
    line-height: 1.4;
    font-size: 0.9375rem;
}
#app-side .pq-invalid-msg {
    color: #b3261e;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}
@media screen and (max-width: 768px) {
    #app-side .pq-side-progress { flex: 1 1 8rem; margin-bottom: 0; }
    #app-side .pq-hlabel { margin-top: 0.125rem; }
    #app-side .pq-button {
        width: auto;
        margin-bottom: 0;
        display: inline-block;
    }
    #app-side aside { font-size: 0.8125rem; }
}
```

6. CHANGE the print rule `header, .pq-nav, .sidebar, .topics, footer { display: none; }` → `header, .sidebar, .topics, .app-side, footer { display: none; }`.

- [ ] **Step 3: JS rework**

All in the `<script>` block:

1. Replace `var headerSlot = document.getElementById('app-header-slot');` with:

```js
    var sideSlot = document.getElementById('app-side');
    var sideIntro = document.getElementById('pq-side-intro');
```

2. DELETE the entire `updateHeaderProgress(index)` function. In its place add:

```js
    function sideProgressHtml(index) {
        var percent = progressPercent();

        return '<div class="pq-side-progress">'
            + '<div class="pq-hprogress"><div class="pq-hfill" style="width:'
            + percent + '%"></div></div>'
            + '<div class="pq-hlabel">' + progressLabel(index, percent)
            + '</div></div>';
    }

    function updateSideProgress(index) {
        if (!sideSlot) {
            return;
        }

        var fill = sideSlot.querySelector('.pq-hfill');
        var label = sideSlot.querySelector('.pq-hlabel');
        var percent = progressPercent();

        if (fill) {
            fill.style.width = percent + '%';
        }

        if (label) {
            label.innerHTML = progressLabel(index, percent);
        }
    }

    function showValidation(index) {
        var category = PrivacyQuiz.categories[index];
        var first = null;

        PrivacyQuiz.spheres.forEach(function (sphere) {
            var cell = answers[category.key][sphere.key];

            ['actual', 'desired'].forEach(function (field) {
                if (cell[field] !== null) {
                    return;
                }

                var input = app.querySelector('input[name="' + category.key
                    + '-' + sphere.key + '-' + field + '"]');
                var row = input && input.closest('.pq-rating');

                if (row) {
                    row.classList.add('pq-missing');
                    first = first || row;
                }
            });
        });

        var msg = document.getElementById('pq-invalid-msg');

        if (msg) {
            msg.hidden = false;
        }

        if (first) {
            first.scrollIntoView({ block: 'center' });
        }
    }

    function renderSide() {
        if (!sideSlot) {
            return;
        }

        if (screen === -1) {
            sideSlot.innerHTML = sideIntro ? sideIntro.innerHTML : '';
            return;
        }

        if (screen < PrivacyQuiz.categories.length) {
            sideSlot.innerHTML =
                sideProgressHtml(screen)
                + '<button id="pq-next" class="pq-button pq-primary">'
                + (screen === PrivacyQuiz.categories.length - 1
                    ? 'See my results' : 'Next')
                + '</button>'
                + '<button id="pq-back" class="pq-button pq-secondary">Back</button>'
                + '<div id="pq-invalid-msg" class="pq-invalid-msg" hidden>'
                + 'Please answer the highlighted rows.</div>';

            document.getElementById('pq-next').onclick = function () {
                if (!isScreenComplete(screen)) {
                    showValidation(screen);
                    return;
                }

                screen += 1;
                render();
            };
            document.getElementById('pq-back').onclick = function () {
                screen -= 1;
                render();
            };
            return;
        }

        sideSlot.innerHTML =
            '<button id="pq-print" class="pq-button pq-primary">'
            + 'Print my results</button>'
            + '<button id="pq-retake" class="pq-button pq-secondary">'
            + 'Start over</button>'
            + '<button id="pq-back" class="pq-button pq-secondary">Back</button>';

        document.getElementById('pq-print').onclick = function () {
            window.print();
        };
        document.getElementById('pq-retake').onclick = function () {
            resetQuiz();
        };
        document.getElementById('pq-back').onclick = function () {
            screen -= 1;
            render();
        };
    }
```

3. In `render()`, replace the block:

```js
        var onCategories = screen >= 0 && screen < PrivacyQuiz.categories.length;

        updateHeaderProgress(onCategories ? screen : null);
```

with:

```js
        renderSide();
```

4. Replace the entire `renderIntro()` function body with:

```js
    function renderIntro() {
        app.innerHTML =
            '<button id="pq-next" class="pq-button pq-primary pq-start">'
            + 'Start the quiz</button>';

        document.getElementById('pq-next').onclick = function () {
            screen = 0;
            render();
        };
    }
```

(The intro's Start-click handler stays here; the five-groups paragraph and sphere list are gone.)

5. Replace the entire `renderCategory(index)` function with:

```js
    function renderCategory(index) {
        var category = PrivacyQuiz.categories[index];

        app.innerHTML =
            '<h3 class="pq-cat-head">' + category.name + '</h3>'
            + '<p class="pq-audience pq-cat-desc">' + category.description + '</p>'
            + PrivacyQuiz.spheres.map(function (sphere) {
                return '<fieldset class="pq-sphere">'
                    + '<div class="pq-sphere-head"><b>' + sphere.name
                    + '</b> <span class="pq-sphere-aud">&ndash; '
                    + sphere.audience + '</span></div>'
                    + ratingRow(category, sphere, 'actual', 'I currently share:')
                    + ratingRow(category, sphere, 'desired', 'I want to share:')
                    + '</fieldset>';
            }).join('');

        app.querySelectorAll('input[type=radio]').forEach(function (input) {
            input.onchange = function () {
                var parts = input.name.split('-');

                answers[parts[0]][parts[1]][parts[2]] =
                    parseInt(input.value, 10);

                var row = input.closest('.pq-rating');

                if (row) {
                    row.classList.remove('pq-missing');
                }

                if (isScreenComplete(index)) {
                    var msg = document.getElementById('pq-invalid-msg');

                    if (msg) {
                        msg.hidden = true;
                    }
                }

                updateSideProgress(index);
            };
        });
    }
```

6. In `renderResults()`, delete the `.pq-nav` tail of the innerHTML statement — i.e. replace:

```js
            + '<div class="pq-nav">'
            + '<button id="pq-back" class="pq-button pq-secondary">Back</button>'
            + '<span><button id="pq-retake" class="pq-button pq-secondary">'
            + 'Start over</button> '
            + '<button id="pq-print" class="pq-button pq-primary">'
            + 'Print my results</button></span></div>';
```

with:

```js
            ;
```

and DELETE the three handler assignments that immediately follow (`pq-back`, `pq-retake`, `pq-print` onclick blocks). Keep the trailing `if (items.length > 0) { ... renderPie(heaviest.key); }` block.

- [ ] **Step 4: Verify**

```bash
node --test tests/
rm -rf build && make build && make serve-build
```

CDP at 1280×800 on `http://localhost:8000/2018/privacy-quiz.html`:

1. Header: single line, "The 15-Minute Privacy Quiz" vertically centered (element's computed line box centered in the 4rem header), no second line.
2. Landing: content = two intro paragraphs + centered Start; sidebar = companion note with working links; no five-groups list anywhere.
3. Start → category 1: sidebar shows progress bar + "Category 1 of 6 · 0% answered" + Next + Back stacked; content shows centered category heading + centered description and five condensed sphere blocks (name + inline audience on one line, two rating rows each, no per-sphere description paragraph). Record `document.documentElement.scrollHeight` vs 800 viewport — report the ratio (goal ≈ ≤ 1.2).
4. Sidebar pinned: scroll the content; `#app-side`'s bounding rect stays put.
5. Validation: click Next with nothing answered → all 10 rows gain `pq-missing`, sidebar message visible, page scrolled to first row; answer one highlighted row → its class clears immediately; complete the screen → message hides; Next advances.
6. Complete via the scripted flow or jump with `?preview=results`: sidebar shows Print/Start over/Back; results content has no button row; Start over resets to landing (sidebar back to companion note).
7. Mobile 390×844: sidebar renders as fixed bottom bar (progress + buttons inline) on category screens; content not obscured (bottom padding); landing bar shows the companion note; header title visible.
8. Zero console errors throughout. Kill server/Chrome.

- [ ] **Step 5: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2018/privacy-quiz/article.phtml
git commit -m "Move quiz nav and progress into app sidebar, condense screens, add validation"
```

---

### Task 3: Dist recompile + smoke test

**Files:**
- Modify: `dist/` (via `make compile` only)

**Interfaces:** consumes everything above; produces the deployable site.

- [ ] **Step 1: Tests + compile**

```bash
node --test tests/
make compile
git status --short | head -20
```

Expected: 9/9; dist changes confined to `2018/privacy-quiz.html` and `css/{site,media,build,dist}.css` (no other pages — the topics swap is app-only).

- [ ] **Step 2: Smoke-test dist**

`make serve`, then CDP against `http://localhost:8000/2018/privacy-quiz`:
landing sidebar note → Start → category sidebar progress/buttons → validation ping on empty Next → `?preview=results` shows sidebar results nav. `/2018/six-privacy-spheres` still shows its Topics column and normal header. Kill the server.

- [ ] **Step 3: Commit**

```bash
git add -A dist
git commit -m "Recompile dist with app sidebar quiz layout"
```
