# Quiz Article Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the privacy quiz into a dedicated compiled article and the worksheet into a compiled standalone `.phtml`, restyled to match the site (solid topic-chip buttons, bigger quiz type, progress meter, clickable results pie, "Actual/Want" 12px worksheet).

**Architecture:** Two small compiler extensions (standalone `.phtml` pages rendered without the site template; an `$al()` cross-article link helper) let the quiz live at `sites/particlebits.com/articles/privacy/2018/privacy-quiz/` as a normal article whose `article.phtml` is the quiz UI. `quiz-data.js` stays the single shared data/scoring module for quiz, worksheet, and tests. The six-spheres article slims down to a CTA button.

**Tech Stack:** PHP 8 static compiler (existing), vanilla HTML/CSS/JS, inline SVG for the pie, Node 20 `node --test`, headless Google Chrome for the PDF.

**Spec:** `docs/superpowers/specs/2026-08-08-quiz-article-integration-design.md`

## Global Constraints

- No new dependencies: no Composer, no npm, no `package.json`. Node built-ins only for tests; headless Chrome only for PDF generation (never a compile dependency).
- The quiz makes zero network calls and uses zero storage; the page states answers never leave the browser.
- Scoring is unchanged: scale labels `Nothing / A little / Some / A lot / Everything` (0–4); bands 0–3 `Balanced`, 4–8 `Minor imbalance`, 9–24 `Major imbalance`; action items only when `|gap| ≥ 2`, sorted by `|gap|` desc; results per-sphere only. `quiz-data.js` content does not change in this plan — only its location.
- Quiz UI type is scoped to `1.15rem`; the rest of the site's typography is untouched.
- Pie slice colors, fixed category→slot order (never cycled, never re-ordered): health `#2a78d6`, money `#eb6834`, romantic `#1baf7a`, beliefs `#eda100`, emotions `#e87ba4`, daily `#008300`. Site pastel topic colors are never used for slices. Chart text uses text colors (`#333`/`#888`), never slice colors.
- Worksheet: body `12px`, `.small` `11px`, row labels are the full words `Actual` and `Want`, page budget ≤ 5 pages, `tr { page-break-inside: avoid; }` retained.
- Standalone compiled pages have NO `<base>` tag: they must reference same-directory assets with plain relative paths (`quiz-data.js`), never `$a()` URLs (which rely on the site template's `<base>`). This deliberately deviates from the spec's `$a('data')` wording — the spec missed that standalone pages lack `<base>`.
- `dist/`, `build/`, `local/` are generated; never hand-edit. `dist/` IS committed. The compiler never deletes outputs — stale files must be `git rm`'d explicitly.
- Branch: `quiz-compile-integration`; squash-merge to master at the end (controller handles the merge).
- End every commit message with:
  `Claude-Session: https://claude.ai/code/session_012HTWUAHFpfqZvUwGuJSTBk`

Paths are relative to repo root `/Users/mike/Projects/particlebits.com`. `OLD_DIR` = `sites/particlebits.com/articles/privacy/2017/six-privacy-spheres`. `NEW_DIR` = `sites/particlebits.com/articles/privacy/2018/privacy-quiz`.

---

### Task 1: Compiler extensions — standalone pages + `$al()` helper

**Files:**
- Modify: `src/php/Article.php`
- Modify: `src/php/Articles.php` (inside `loadArticle()`)
- Modify: `src/php/Pages.php` (inside `articles()`)
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: existing `render($file, $data, $useSrc)` global helper; `Filesystem::listContents()` items (`basename`, `path`, `type`).
- Produces (later tasks rely on these exact names):
  - `Article::$standalones` — array of `.phtml` basenames (excluding `article.phtml`) found in the article directory.
  - `Article::renderStandalone(string $basename): string` — renders `<article-dir>/<basename>` with the article helper closures, NO template wrap.
  - `Article::makeUrlFor(string $year, string $slug): string` — env-correct URL for another article.
  - Helper closure `$al($year, $slug)` — echoes `makeUrlFor()` — available in `article.phtml` AND standalone `.phtml` files, alongside the existing `$e`, `$a`, `$d`, `$wl`.
  - `Pages::articles()` writes each standalone as `media/%YEAR%/%SLUG%/<name>.html` in the output.

- [ ] **Step 1: Add `$standalones` property and refactor helpers in `Article.php`**

In `src/php/Article.php`, add the property after `public $medias = [];`:

```php
    public $standalones = [];
```

Replace the entire `render()` method (currently building `$data` inline) with:

```php
    public function render()
    {
        $data = $this->helpers();
        $data['content'] = render("{$this->path}/article.phtml", $data, false);

        return render(TPL_ARTICLE, $data);
    }

    /**
     * Renders an extra .phtml file from the article's directory as a
     * standalone page: same helper closures, no article or site template.
     */
    public function renderStandalone($basename)
    {
        return render("{$this->path}/{$basename}", $this->helpers(), false);
    }

    /**
     * Set up helper functions available inside article templates
     */
    private function helpers()
    {
        return [
            'e' => function ($string) {
                echo htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            },
            'a' => function ($key) {
                echo $this->getAssetUrl(get($this->assets, $key, self::NOT_FOUND));
            },
            'd' => function ($key) {
                echo get($this->data, $key, '');
            },
            'wl' => function ($key) {
                echo get($this->weblinks, $key, '#notfound' );
            },
            'al' => function ($year, $slug) {
                echo $this->makeUrlFor($year, $slug);
            },
            'article' => $this
        ];
    }
```

Note the `'article' => $this` entry replaces the old `$data['article'] = $this;` line — do not duplicate it.

Add after `getAssetUrl()`:

```php
    /**
     * Builds an env-correct URL to another article from its year and
     * slug, using the same urlFormat as this article.
     */
    public function makeUrlFor($year, $slug)
    {
        $url = str_replace('%YEAR%', $year, $this->urlFormat);

        return str_replace('%SLUG%', $slug, $url);
    }
```

- [ ] **Step 2: Discover standalone `.phtml` files in `Articles.php`**

In `src/php/Articles.php`, inside `loadArticle()`, directly after the media-index block (`$meta->medias[...]` foreach and its closing brace), add:

```php
        $meta->standalones = [];

        // Extra .phtml files compile to standalone pages in media output
        foreach ($this->sites->listContents($slug['path']) as $file) {
            if ($file['type'] === TYPE_FILE
                && substr($file['basename'], -6) === '.phtml'
                && $file['basename'] !== 'article.phtml'
            ) {
                $meta->standalones[] = $file['basename'];
            }
        }
```

- [ ] **Step 3: Write standalone pages in `Pages.php`**

In `src/php/Pages.php`, inside `articles()`, directly after the media-copy foreach loop (after its closing brace, still inside the outer article loop), add:

```php
            // Render standalone .phtml pages into the media directory
            foreach ($article->standalones as $standalone) {
                $standaloneUrl = $article->getAssetUrl(
                    substr($standalone, 0, -6) . '.html');

                $this->target->put(
                    "{$this->site['basename']}/{$standaloneUrl}",
                    $article->renderStandalone($standalone));
                $fileWriteCount++;
            }
```

- [ ] **Step 4: Verify compile is unchanged for the existing site**

```bash
php -l src/php/Article.php && php -l src/php/Articles.php && php -l src/php/Pages.php
rm -rf build && make build
```

Expected: lint passes; build reports `Wrote 33 files` (same as before this task — no article has extra `.phtml` files yet, and `worksheet.html` in OLD_DIR is `.html`, not `.phtml`, so it is not picked up). Spot-check an article still renders: `grep -c "practicing-exercising" build/particlebits.com/2018/six-privacy-spheres.html` ≥ 1.

- [ ] **Step 5: Document the conventions in `CLAUDE.md`**

In `CLAUDE.md`, in the "Content model" section, after the paragraph beginning "Inside `article.phtml`, helper closures are in scope:", extend that paragraph's helper list from `$wl(key)` to also name `$al(year, slug)` (echo an env-correct URL to another article by year and slug). Then add a new paragraph after it:

```markdown
Any additional `*.phtml` file in an article's directory (besides `article.phtml`) is compiled as a **standalone page**: rendered with the same helper closures but without the site template, and written to `media/<year>/<slug>/<name>.html` in the output. Standalone pages have no `<base>` tag, so they must reference same-directory media assets by bare filename (e.g. `quiz-data.js`), not via `$a()`.
```

- [ ] **Step 6: Commit**

```bash
git add src/php/Article.php src/php/Articles.php src/php/Pages.php CLAUDE.md
git commit -m "Add standalone phtml pages and cross-article link helper to compiler"
```

---

### Task 2: The quiz article

**Files:**
- Create: `NEW_DIR/about.json`
- Create: `NEW_DIR/article.phtml`
- Create: `NEW_DIR/media/quiz-data.js` (copy from OLD_DIR — old copy is deleted in Task 5)
- Create: `NEW_DIR/media/six_spheres.png` (copy from OLD_DIR)
- Modify: `sites/particlebits.com/sitemap.json` (privacy topic's `articles`)
- Modify: `tests/quiz-data.test.js` (require path only)

**Interfaces:**
- Consumes: Task 1's `$al` helper; `PrivacyQuiz` global from `quiz-data.js` (`categories` 6×{key,name,phrase,description}, `spheres` 5×{key,name,audience}, `scale` 5 labels, `sphereResult(answers, sphereKey)` → `{score, signedSum, band, direction, verdict}`, `actionItems(answers)` → `[{categoryKey, sphereKey, gap, text}]`, `gap(actual, desired)`). Radio names `<categoryKey>-<sphereKey>-<field>` split on `-`; all keys are hyphen-free. Site CSS classes: `.topic-privacy` (solid chip background), `.topic-privacy-border`.
- Produces: article at `/2018/privacy-quiz`; DOM contract for Task 3: `#pq-app` container, `renderResults()` builds `#pq-results-extra` (empty div) after the sphere cards and a flat `<ol class="pq-actions">` action list that Task 3 replaces; JS state lives in closure vars `answers`, `screen`; function names `render`, `renderIntro`, `renderCategory`, `renderResults`, `isScreenComplete`, `updateProgress`.

- [ ] **Step 1: Copy shared assets**

```bash
mkdir -p sites/particlebits.com/articles/privacy/2018/privacy-quiz/media
cp sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/quiz-data.js sites/particlebits.com/articles/privacy/2018/privacy-quiz/media/
cp sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/six_spheres.png sites/particlebits.com/articles/privacy/2018/privacy-quiz/media/
```

- [ ] **Step 2: Write `NEW_DIR/about.json`**

```json
{
    "title": "The 15-Minute Privacy Quiz",
    "date": "2018-07-09",
    "author": "Mike Gioia",
    "topic": "privacy",
    "slug": "privacy-quiz",
    "featured": false,
    "hero": "six_spheres.png",
    "snippet": "An interactive companion to _The Six Spheres of Privacy_: inventory what you share, see where your privacy is out of balance, and get a personalized list of actions. Also available as a printable worksheet.",
    "assets": {
        "data": "quiz-data.js",
        "worksheet": "privacy-worksheet.pdf",
        "spheres": "six_spheres.png"
    },
    "weblinks": {
    },
    "data": {
    }
}
```

- [ ] **Step 3: List the article in `sitemap.json`**

In `sites/particlebits.com/sitemap.json`, the privacy topic's `articles` array becomes:

```json
            "articles": [
                "intro-privacy-security",
                "six-privacy-spheres",
                "privacy-quiz",
                "disseminating-info-total-anonymity"
            ]
```

- [ ] **Step 4: Write `NEW_DIR/article.phtml`**

Exactly this content (quiz UI with intro, progress meter, screens, per-sphere result cards, and a flat action list — Task 3 adds the pie):

```php
<aside>
    This quiz is a companion to
    <a href="<?php $al('2018', 'six-privacy-spheres'); ?>">The Six Spheres
    of Privacy</a>, part of the
    <a href="<?php echo $article->getTopic()->url; ?>">Privacy and Security
    series</a>.
</aside>

<p>
    This quiz walks you through the two exercises from the article: taking
    inventory of what you share, and deciding what you actually <i>want</i>
    to share. For six categories of information in your life, you&rsquo;ll
    rate &mdash; for each group of people around you &mdash; how much you
    currently share and how much you would want to share, from
    &ldquo;nothing&rdquo; to &ldquo;everything.&rdquo;
</p>
<p>
    There are no wrong answers. The quiz measures the <i>gap</i> between
    what you share and what you want to share &mdash; not how private or
    how open you are &mdash; so answer with your first instinct. Your
    answers never leave your browser: nothing is sent or saved anywhere.
    If you&rsquo;d rather work on paper, you can
    <a href="<?php $a('worksheet'); ?>">download the printable worksheet
    (PDF)</a> instead.
</p>

<div id="pq-app" class="pq-app"></div>

<style>
.pq-app { font-size: 1.15rem; }
.pq-progress {
    position: relative;
    height: 1.75rem;
    background: #f0f0f0;
    border-radius: 0.875rem;
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.pq-progress .pq-fill {
    top: 0;
    left: 0;
    bottom: 0;
    width: 0%;
    position: absolute;
    transition: width 0.2s;
}
.pq-progress .pq-label {
    position: relative;
    color: #333;
    text-align: center;
    line-height: 1.75rem;
    font-size: 0.875rem;
}
.pq-button {
    font: inherit;
    border: 0;
    color: #333;
    cursor: pointer;
    display: inline-block;
    text-decoration: none;
    border-radius: 0.25rem;
    padding: 0.625rem 1.25rem;
}
.pq-button:disabled { opacity: 0.4; cursor: not-allowed; }
.pq-button.pq-secondary { background: #eee; }
.pq-button.pq-secondary:hover { background: #ddd; }
.pq-sphere {
    border: 1px solid #ddd;
    border-radius: 0.25rem;
    margin: 0 0 1rem;
    padding: 0.75rem 1rem;
}
.pq-sphere legend { font-weight: bold; padding: 0 0.375rem; }
.pq-audience { color: #888; font-size: 0.875em; margin: 0 0 0.5rem; }
.pq-rating { display: flex; flex-wrap: wrap; align-items: baseline; margin: 0.375rem 0; }
.pq-rating > span { flex: 0 0 10rem; font-size: 0.9375em; }
.pq-rating label {
    cursor: pointer;
    white-space: nowrap;
    font-size: 0.9375em;
    margin: 0.125rem 0.875rem 0.125rem 0;
}
.pq-nav { display: flex; justify-content: space-between; margin-top: 1.5rem; }
.pq-card {
    border: 1px solid #ddd;
    border-left-width: 0.375rem;
    border-left-style: solid;
    border-radius: 0.25rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
}
.pq-card h3 { margin: 0; font-size: 1.125em; }
.pq-badge {
    font-weight: normal;
    font-size: 0.8125em;
    padding: 0.125rem 0.625rem;
    border-radius: 1rem;
    border: 1px solid #ddd;
    margin-left: 0.5rem;
    vertical-align: middle;
    white-space: nowrap;
}
.pq-badge.pq-balanced { background: #e6f4ea; border-color: #b7dfc2; }
.pq-badge.pq-minor { background: #fff8e1; border-color: #f0dfa0; }
.pq-badge.pq-major { background: #fdecea; border-color: #f5c6c0; }
.pq-card p { margin: 0.375rem 0 0; text-indent: 0; font-size: 1em; }
.pq-actions li { margin-bottom: 0.5rem; }
@media print {
    header, .pq-nav, .sidebar, .topics, footer { display: none; }
    .pq-card { break-inside: avoid; }
}
</style>
<script src="<?php $a('data'); ?>"></script>
<script>
(function () {
    var app = document.getElementById('pq-app');
    var answers = {};

    PrivacyQuiz.categories.forEach(function (category) {
        answers[category.key] = {};
        PrivacyQuiz.spheres.forEach(function (sphere) {
            answers[category.key][sphere.key] = {
                actual: null,
                desired: null
            };
        });
    });

    // -1 = intro, 0..5 = category screens, 6 = results
    var screen = -1;

    function answeredCount() {
        var count = 0;

        PrivacyQuiz.categories.forEach(function (category) {
            PrivacyQuiz.spheres.forEach(function (sphere) {
                var cell = answers[category.key][sphere.key];

                count += (cell.actual !== null ? 1 : 0)
                    + (cell.desired !== null ? 1 : 0);
            });
        });

        return count;
    }

    function progressBar(index) {
        var total = PrivacyQuiz.categories.length
            * PrivacyQuiz.spheres.length * 2;
        var percent = Math.round(100 * answeredCount() / total);

        return '<div class="pq-progress">'
            + '<div class="pq-fill topic-privacy" style="width:'
            + percent + '%"></div>'
            + '<div class="pq-label">Category ' + (index + 1) + ' of '
            + PrivacyQuiz.categories.length
            + ' &middot; ' + percent + '% answered</div>'
            + '</div>';
    }

    function updateProgress(index) {
        var bar = app.querySelector('.pq-progress');

        if (bar) {
            bar.outerHTML = progressBar(index);
        }
    }

    function render() {
        if (screen === -1) {
            renderIntro();
        } else if (screen < PrivacyQuiz.categories.length) {
            renderCategory(screen);
        } else {
            renderResults();
        }

        window.scrollTo(0, 0);
    }

    function renderIntro() {
        app.innerHTML =
            '<p>The five groups you&rsquo;ll rate, from the article&rsquo;s '
            + 'six spheres (your Personal sphere is the baseline &mdash; it '
            + 'always holds everything):</p>'
            + '<ul>'
            + PrivacyQuiz.spheres.map(function (sphere) {
                return '<li><b>' + sphere.name + '</b> &mdash; '
                    + sphere.audience + '</li>';
            }).join('')
            + '</ul>'
            + '<div class="pq-nav"><span></span>'
            + '<button id="pq-next" class="pq-button topic-privacy">'
            + 'Start the quiz</button></div>';

        document.getElementById('pq-next').onclick = function () {
            screen = 0;
            render();
        };
    }

    function isScreenComplete(index) {
        var category = PrivacyQuiz.categories[index];

        return PrivacyQuiz.spheres.every(function (sphere) {
            var cell = answers[category.key][sphere.key];

            return cell.actual !== null && cell.desired !== null;
        });
    }

    function ratingRow(category, sphere, field, label) {
        var name = category.key + '-' + sphere.key + '-' + field;
        var current = answers[category.key][sphere.key][field];

        return '<div class="pq-rating"><span>' + label + '</span>'
            + PrivacyQuiz.scale.map(function (option, value) {
                return '<label><input type="radio" name="' + name
                    + '" value="' + value + '"'
                    + (current === value ? ' checked' : '')
                    + '> ' + option + '</label>';
            }).join('')
            + '</div>';
    }

    function renderCategory(index) {
        var category = PrivacyQuiz.categories[index];

        app.innerHTML =
            progressBar(index)
            + '<h3>' + category.name + '</h3>'
            + '<p class="pq-audience">' + category.description + '</p>'
            + PrivacyQuiz.spheres.map(function (sphere) {
                return '<fieldset class="pq-sphere"><legend>'
                    + sphere.name + '</legend>'
                    + '<p class="pq-audience">' + sphere.audience + '</p>'
                    + ratingRow(category, sphere, 'actual', 'I currently share:')
                    + ratingRow(category, sphere, 'desired', 'I want to share:')
                    + '</fieldset>';
            }).join('')
            + '<div class="pq-nav">'
            + '<button id="pq-back" class="pq-button pq-secondary">Back</button>'
            + '<button id="pq-next" class="pq-button topic-privacy" disabled>'
            + (index === PrivacyQuiz.categories.length - 1
                ? 'See my results' : 'Next')
            + '</button></div>';

        app.querySelectorAll('input[type=radio]').forEach(function (input) {
            input.onchange = function () {
                var parts = input.name.split('-');

                answers[parts[0]][parts[1]][parts[2]] =
                    parseInt(input.value, 10);
                document.getElementById('pq-next').disabled =
                    !isScreenComplete(index);
                updateProgress(index);
            };
        });

        document.getElementById('pq-next').disabled = !isScreenComplete(index);
        document.getElementById('pq-next').onclick = function () {
            screen += 1;
            render();
        };
        document.getElementById('pq-back').onclick = function () {
            screen -= 1;
            render();
        };
    }

    function badgeClass(band) {
        if (band === 'Balanced') {
            return 'pq-balanced';
        }

        return band === 'Minor imbalance' ? 'pq-minor' : 'pq-major';
    }

    function renderResults() {
        var items = PrivacyQuiz.actionItems(answers);

        app.innerHTML =
            '<h3>Your privacy balance</h3>'
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
            + '<h3>Your action items</h3>'
            + '<div id="pq-results-extra"></div>'
            + (items.length === 0
                ? '<p>No action items &mdash; every gap is small. Your '
                    + 'sharing closely matches your intent.</p>'
                : '<ol class="pq-actions">' + items.map(function (item) {
                    return '<li>' + item.text + '</li>';
                }).join('') + '</ol>')
            + '<div class="pq-nav">'
            + '<button id="pq-back" class="pq-button pq-secondary">Back</button>'
            + '<span><button id="pq-retake" class="pq-button pq-secondary">'
            + 'Start over</button> '
            + '<button id="pq-print" class="pq-button topic-privacy">'
            + 'Print my results</button></span></div>';

        document.getElementById('pq-back').onclick = function () {
            screen -= 1;
            render();
        };
        document.getElementById('pq-retake').onclick = function () {
            window.location.reload();
        };
        document.getElementById('pq-print').onclick = function () {
            window.print();
        };
    }

    render();
})();
</script>
```

- [ ] **Step 5: Update the test suite's require path**

In `tests/quiz-data.test.js`, change the require line to:

```js
const PQ = require('../sites/particlebits.com/articles/privacy/2018/privacy-quiz/media/quiz-data.js');
```

- [ ] **Step 6: Run tests**

Run: `node --test tests/`
Expected: 9/9 pass (the module is a byte-identical copy; only the path changed).

- [ ] **Step 7: Compile and verify in a browser**

```bash
rm -rf build && make build
ls build/particlebits.com/media/2018/privacy-quiz/
```

Expected: build succeeds; media listing shows `quiz-data.js` and `six_spheres.png`. Then `make serve-build` and drive `http://localhost:8000/2018/privacy-quiz.html` end-to-end with headless Chrome over the DevTools Protocol (launch with `--headless=new --remote-debugging-port`, dispatch real `.click()` events):

1. Page renders inside site chrome (header, sidebar, topics column); intro lists 5 spheres; "Start the quiz" is a solid privacy-blue button.
2. Progress bar appears on category screens and its width/percentage grows as radios are clicked; label reads "Category N of 6".
3. Scripted case — all cells `actual=2, desired=2` EXCEPT Money/Professional `4/1`, Beliefs/Professional `3/1`, Emotions/Family `0/3`. Expected results: Professional "Minor imbalance · 5" (over verdict), Family "Balanced · 3", others "Balanced · 0"; exactly 3 action items, the two |gap|=3 first.
4. Back preserves answers; Next stays disabled on incomplete screens; zero console errors.

Kill the server and Chrome afterwards.

- [ ] **Step 8: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2018/privacy-quiz sites/particlebits.com/sitemap.json tests/quiz-data.test.js
git commit -m "Add privacy quiz as a dedicated compiled article"
```

---

### Task 3: Results pie — clickable category donut

**Files:**
- Modify: `NEW_DIR/article.phtml` (extend the `<style>` block; replace the flat action list in `renderResults()`; add pie functions)

**Interfaces:**
- Consumes: Task 2's DOM contract (`#pq-results-extra`, `.pq-actions`, closure vars `answers`); `PrivacyQuiz.categories`, `PrivacyQuiz.gap`, `PrivacyQuiz.actionItems`.
- Produces: `PQ_COLORS` map (category key → hex, the Global Constraints palette); functions `categoryWeights()`, `renderPie(selectedKey)`, `selectCategory(key)`; DOM: `.pq-pie` (SVG donut), `.pq-legend` (always-present legend list), `.pq-panel` (selected category's action items).

- [ ] **Step 1: Validate the palette (do not skip)**

```bash
node "/private/tmp/claude-501/bundled-skills/2.1.226/aef986212f9ff651fa463ebf9b0a2e2a/dataviz/scripts/validate_palette.js" "#2a78d6,#eb6834,#1baf7a,#eda100,#e87ba4,#008300" --mode light
```

Expected: PASS on the adjacent pairlist (these are slots 1–6 of the skill's validated reference palette in order). If the script path no longer exists, re-invoke the `dataviz` skill to locate it. If any check FAILs, stop and report — do not substitute colors ad hoc.

- [ ] **Step 2: Add pie styles**

Append inside the existing `<style>` block of `NEW_DIR/article.phtml`:

```css
.pq-viz { display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-start; margin: 1rem 0; }
.pq-pie svg { display: block; }
.pq-slice { cursor: pointer; stroke: #fff; stroke-width: 2; }
.pq-slice:hover { opacity: 0.85; }
.pq-slice:focus { outline: none; }
.pq-slice.pq-selected, .pq-slice:focus-visible { stroke: #333; }
.pq-center-name { font-size: 1rem; fill: #333; text-anchor: middle; }
.pq-center-weight { font-size: 0.8125rem; fill: #888; text-anchor: middle; }
.pq-legend { list-style: none; margin: 0; padding: 0; font-size: 1rem; }
.pq-legend li { margin-bottom: 0.375rem; }
.pq-legend button {
    font: inherit;
    border: 0;
    padding: 0.125rem 0.25rem;
    background: none;
    cursor: pointer;
    color: #333;
    border-radius: 0.125rem;
}
.pq-legend button:hover { background: #f0f0f0; }
.pq-legend li.pq-selected button { font-weight: bold; }
.pq-legend .pq-swatch {
    width: 0.75rem;
    height: 0.75rem;
    display: inline-block;
    border-radius: 0.125rem;
    margin-right: 0.375rem;
    vertical-align: baseline;
}
.pq-legend li.pq-zero { color: #888; }
.pq-legend li.pq-zero .pq-swatch { background: #e5e5e5 !important; }
.pq-panel {
    flex: 1 1 16rem;
    border: 1px solid #ddd;
    border-radius: 0.25rem;
    padding: 0.75rem 1rem;
}
.pq-panel h4 { margin: 0 0 0.5rem; }
.pq-panel ol { margin: 0; padding-left: 1.25rem; }
.pq-panel li { margin-bottom: 0.5rem; }
```

- [ ] **Step 3: Add the pie code**

In the `<script>` block of `NEW_DIR/article.phtml`, add above `renderResults()`:

```js
    var PQ_COLORS = {
        health: '#2a78d6',
        money: '#eb6834',
        romantic: '#1baf7a',
        beliefs: '#eda100',
        emotions: '#e87ba4',
        daily: '#008300'
    };

    function categoryWeights() {
        return PrivacyQuiz.categories.map(function (category) {
            var weight = 0;

            PrivacyQuiz.spheres.forEach(function (sphere) {
                var cell = answers[category.key][sphere.key];

                weight += Math.abs(
                    PrivacyQuiz.gap(cell.actual, cell.desired));
            });

            return {
                key: category.key,
                name: category.name,
                weight: weight
            };
        });
    }

    function arcPath(cx, cy, rOuter, rInner, startAngle, sweep) {
        // Angles in radians from 12 o'clock, clockwise. Sweeps >= 360deg
        // break SVG arcs, so cap just below a full turn.
        sweep = Math.min(sweep, 2 * Math.PI - 0.0001);

        var a0 = startAngle - Math.PI / 2;
        var a1 = a0 + sweep;
        var large = sweep > Math.PI ? 1 : 0;
        var p = function (r, a) {
            return (cx + r * Math.cos(a)).toFixed(2) + ' '
                + (cy + r * Math.sin(a)).toFixed(2);
        };

        return 'M ' + p(rOuter, a0)
            + ' A ' + rOuter + ' ' + rOuter + ' 0 ' + large + ' 1 ' + p(rOuter, a1)
            + ' L ' + p(rInner, a1)
            + ' A ' + rInner + ' ' + rInner + ' 0 ' + large + ' 0 ' + p(rInner, a0)
            + ' Z';
    }

    function renderPie(selectedKey) {
        var extra = document.getElementById('pq-results-extra');
        var weights = categoryWeights();
        var total = weights.reduce(function (sum, w) {
            return sum + w.weight;
        }, 0);

        if (total === 0) {
            extra.innerHTML = '';
            return;
        }

        var selected = weights.find(function (w) {
            return w.key === selectedKey;
        });
        var items = PrivacyQuiz.actionItems(answers).filter(function (item) {
            return item.categoryKey === selectedKey;
        });
        var angle = 0;
        var slices = '';

        weights.forEach(function (w) {
            if (w.weight === 0) {
                return;
            }

            var sweep = 2 * Math.PI * w.weight / total;

            slices += '<path class="pq-slice'
                + (w.key === selectedKey ? ' pq-selected' : '')
                + '" d="' + arcPath(110, 110, 100, 55, angle, sweep)
                + '" fill="' + PQ_COLORS[w.key] + '"'
                + ' data-key="' + w.key + '" tabindex="0" role="button"'
                + ' aria-label="' + w.name + ' &mdash; ' + w.weight
                + ' points, view recommendations"></path>';
            angle += sweep;
        });

        extra.innerHTML =
            '<div class="pq-viz">'
            + '<div class="pq-pie"><svg width="220" height="220" '
            + 'viewBox="0 0 220 220" aria-hidden="false">'
            + slices
            + '<text class="pq-center-name" x="110" y="106">'
            + selected.name + '</text>'
            + '<text class="pq-center-weight" x="110" y="126">'
            + selected.weight + ' pts</text>'
            + '</svg></div>'
            + '<div>'
            + '<ul class="pq-legend">'
            + weights.map(function (w) {
                var zero = w.weight === 0;

                return '<li class="'
                    + (w.key === selectedKey ? 'pq-selected ' : '')
                    + (zero ? 'pq-zero' : '') + '">'
                    + (zero
                        ? '<span class="pq-swatch"></span>' + w.name
                            + ' &mdash; balanced'
                        : '<button type="button" data-key="' + w.key + '">'
                            + '<span class="pq-swatch" style="background:'
                            + PQ_COLORS[w.key] + '"></span>'
                            + w.name + ' &mdash; ' + w.weight + ' pts'
                            + '</button>')
                    + '</li>';
            }).join('')
            + '</ul></div>'
            + '<div class="pq-panel">'
            + '<h4>Recommendations: ' + selected.name + '</h4>'
            + (items.length === 0
                ? '<p>No single gap here is 2 or more &mdash; small '
                    + 'adjustments only.</p>'
                : '<ol>' + items.map(function (item) {
                    return '<li>' + item.text + '</li>';
                }).join('') + '</ol>')
            + '</div></div>';

        extra.querySelectorAll('.pq-slice, .pq-legend button').forEach(
            function (el) {
                el.onclick = function () {
                    selectCategory(el.getAttribute('data-key'));
                };
                el.onkeydown = function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectCategory(el.getAttribute('data-key'));
                    }
                };
            });
    }

    function selectCategory(key) {
        renderPie(key);
    }
```

- [ ] **Step 4: Wire the pie into `renderResults()`**

In `renderResults()`:

1. Replace the flat-list expression

```js
            + (items.length === 0
                ? '<p>No action items &mdash; every gap is small. Your '
                    + 'sharing closely matches your intent.</p>'
                : '<ol class="pq-actions">' + items.map(function (item) {
                    return '<li>' + item.text + '</li>';
                }).join('') + '</ol>')
```

with

```js
            + (items.length === 0
                ? '<p>No action items &mdash; every gap is small. Your '
                    + 'sharing closely matches your intent.</p>'
                : '<p class="pq-audience">The chart shows how much each '
                    + 'category contributes to your overall imbalance. '
                    + 'Click a slice or its legend entry to see the '
                    + 'recommendations for that category.</p>')
```

2. At the end of `renderResults()` (after the button handlers), add:

```js
        if (items.length > 0) {
            var heaviest = categoryWeights().reduce(function (best, w) {
                return w.weight > best.weight ? w : best;
            });

            renderPie(heaviest.key);
        }
```

(`reduce` without an initial value keeps the FIRST of tied weights — fixed category order breaks ties.)

- [ ] **Step 5: Verify in a browser**

`rm -rf build && make build && make serve-build`, then drive the scripted case from Task 2 Step 7 via headless-Chrome CDP. Check:

1. Results show a donut with three slices (money 3, beliefs 2, emotions 3 of total 8 — money ⅜, beliefs ¼, emotions ⅜) and 2px white gaps.
2. Money is pre-selected (tie with emotions broken by category order): bold legend row, `#333` stroke, center reads "Money / 3 pts", panel shows the Money/Professional item.
3. Clicking the emotions slice and the beliefs legend row switches the selection, center text, and panel contents.
4. Tab reaches slices; Enter/Space selects; aria-labels present.
5. Legend lists all six categories — health, romantic, daily grayed as "balanced" with gray swatches and no button.
6. All-balanced run (every cell 2/2) shows the "no action items" message and no chart. Zero console errors throughout.

- [ ] **Step 6: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2018/privacy-quiz/article.phtml
git commit -m "Add clickable category donut to quiz results"
```

---

### Task 4: Worksheet as standalone phtml + `make worksheet` + regenerated PDF

**Files:**
- Move: `OLD_DIR/worksheet.html` → `NEW_DIR/worksheet.phtml` (git mv, then edit)
- Create: `NEW_DIR/media/privacy-worksheet.pdf` (generated)
- Modify: `Makefile`
- Modify: `CLAUDE.md` (one line, documented below)

**Interfaces:**
- Consumes: Task 1's standalone compile (worksheet.phtml → `media/2018/privacy-quiz/worksheet.html` in output); `PrivacyQuiz` (`scale`, `categories`, `spheres`, `bands`, `verdictText`, `mixedText`, `cellAdvice`).
- Produces: `make worksheet` target; the PDF at `NEW_DIR/media/privacy-worksheet.pdf` (which `about.json` already references as the `worksheet` asset).

- [ ] **Step 1: Move the file**

```bash
git mv sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/worksheet.html sites/particlebits.com/articles/privacy/2018/privacy-quiz/worksheet.phtml
```

- [ ] **Step 2: Apply the content edits**

Exactly these edits to `NEW_DIR/worksheet.phtml` (each `old` string appears exactly once):

1. Script src (standalone pages have no `<base>`; quiz-data.js sits in the same output directory):
   - old: `<script src="media/quiz-data.js"></script>`
   - new: `<script src="quiz-data.js"></script>`
2. Body font size back to 12px:
   - old: `    font-size: 10px;` (the one inside the `body {` rule)
   - new: `    font-size: 12px;`
3. Small-text size proportional (the committed value is `9px` from an earlier fix round):
   - old: `.small { font-size: 9px; color: #666; }`
   - new: `.small { font-size: 11px; color: #666; }`
   - (If the rule's formatting differs slightly, locate the `.small` rule and set its font-size to `11px` — it must end smaller than the 12px body.)
4. Row labels — in the grid-building JS:
   - old: `+ '<td class="rowlabel">A</td>' + blanks + '</tr>'`
   - new: `+ '<td class="rowlabel">Actual</td>' + blanks + '</tr>'`
   - old: `+ '<tr><td class="rowlabel">W</td>' + blanks + '</tr>';`
   - new: `+ '<tr><td class="rowlabel">Want</td>' + blanks + '</tr>';`
5. Legend copy on the grid page:
   - old: `A = how much you actually share today &nbsp;&bull;&nbsp;`
   - new: `Actual = how much you actually share today &nbsp;&bull;&nbsp;`
   - old: `W = how much you want to share &nbsp;&bull;&nbsp;`
   - new: `Want = how much you want to share &nbsp;&bull;&nbsp;`
6. Instructions page copy:
   - old: `write two numbers from the`
     ` scale below: <b>A</b> for how much you <i>actually</i> share, and`
     ` <b>W</b> for how much you <i>want</i> to share.`
     (single paragraph; match the actual line wrapping in the file)
   - new: same sentence with `<b>Actual</b>` and `<b>Want</b>` in place of `<b>A</b>` / `<b>W</b>`.
7. Scoring page copy:
   - old: `the <b>gap</b> is A&nbsp;&minus;&nbsp;W.`
   - new: `the <b>gap</b> is Actual&nbsp;&minus;&nbsp;Want.`
   - If other sentences on the scoring page reference bare `A` or `W`, update them to `Actual`/`Want` the same way.

Keep everything else — `@page` rules, `tr { page-break-inside: avoid; }`, section structure, all table-generation JS — unchanged.

- [ ] **Step 3: Add the `make worksheet` target**

In `Makefile`, after the `local:` target, add:

```makefile
# Regenerate the privacy worksheet PDF from the compiled build output.
# Requires Google Chrome. Run `make compile` afterwards to propagate.
worksheet: build
	"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
		--headless=new --disable-gpu --no-pdf-header-footer \
		--print-to-pdf=sites/particlebits.com/articles/privacy/2018/privacy-quiz/media/privacy-worksheet.pdf \
		build/particlebits.com/media/2018/privacy-quiz/worksheet.html
```

And add `worksheet` to the `.PHONY` line. In `CLAUDE.md`'s command list, add:

```makefile
make worksheet    # regenerate the privacy worksheet PDF (needs Chrome)
```

- [ ] **Step 4: Generate and verify the PDF**

```bash
rm -rf build && make worksheet
```

Then Read `NEW_DIR/media/privacy-worksheet.pdf` and verify: ≤ 5 pages; body text visibly larger than the previous 10px edition; grid rows labeled "Actual" and "Want" (full words); legend and scoring pages use "Actual"/"Want"; nothing clipped; no table row split across a page boundary; all tables populated (JS ran). Also verify the compiled standalone: `grep -c "quiz-data.js" build/particlebits.com/media/2018/privacy-quiz/worksheet.html` ≥ 1 and the file contains no `<header` (chrome-less).

- [ ] **Step 5: Run tests (regression)**

Run: `node --test tests/`
Expected: 9/9 pass.

- [ ] **Step 6: Commit**

```bash
git add -A sites/particlebits.com/articles/privacy Makefile CLAUDE.md
git commit -m "Compile worksheet as standalone phtml with full labels and 12px type"
```

---

### Task 5: Six-spheres article slims down

**Files:**
- Modify: `OLD_DIR/article.phtml` (CTA block)
- Modify: `OLD_DIR/about.json` (assets)
- Modify: `src/css/site.css` (CTA button class)
- Delete: `OLD_DIR/media/quiz.html`, `OLD_DIR/media/quiz-data.js`, `OLD_DIR/media/privacy-worksheet.pdf`

**Interfaces:**
- Consumes: Task 1's `$al` helper; site topic classes (`.topic-privacy`).
- Produces: `a.cta-button` class in `site.css` (block CTA, colored by a `topic-<slug>` class alongside it).

- [ ] **Step 1: Add the CTA button style to `src/css/site.css`**

After the `article aside { ... }` rule block, add:

```css
/* Large call-to-action button; pair with a topic-<slug> class for color */
article a.cta-button {
    color: #333;
    display: block;
    margin: 1.5rem auto;
    max-width: 24rem;
    text-align: center;
    font-size: 1.25rem;
    padding: 0.875rem 1rem;
    border-radius: 0.25rem;
    text-decoration: none;
}
article a.cta-button:hover {
    filter: brightness(0.96);
}
```

- [ ] **Step 2: Replace the CTA block in `OLD_DIR/article.phtml`**

Replace this block (it appears exactly once, at the end of the Practicing and Exercising section):

```html
<p>
    You can do both exercises right now, in about fifteen minutes, in
    whichever form you prefer. The quiz computes your balance scores and
    action items for you; the worksheet is a printable version to fill out
    by hand. Neither sends your answers anywhere.
</p>

<p>
    <b><a href="<?php $a('quiz'); ?>">Take the 15-Minute Privacy Quiz</a></b>
    or
    <a href="<?php $a('worksheet'); ?>">download the printable worksheet (PDF)</a>.
</p>
```

with:

```html
<p>
    You can do both exercises right now, in about fifteen minutes. The
    quiz computes your balance scores and action items for you, and it
    includes a printable worksheet if you&rsquo;d rather work on paper.
    Neither sends your answers anywhere.
</p>

<a class="cta-button topic-privacy" href="<?php $al('2018', 'privacy-quiz'); ?>">
    Take the 15-Minute Privacy Quiz &rarr;
</a>
```

- [ ] **Step 3: Update `OLD_DIR/about.json`**

The `assets` map returns to:

```json
    "assets": {
        "spheres": "six_spheres.png"
    }
```

- [ ] **Step 4: Delete the superseded files**

```bash
git rm sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/quiz.html \
    sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/quiz-data.js \
    sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/privacy-worksheet.pdf
```

(`worksheet.html` was already moved in Task 4.)

- [ ] **Step 5: Compile and verify**

```bash
rm -rf build && make build
grep -c "cta-button topic-privacy" build/particlebits.com/2018/six-privacy-spheres.html
grep -o 'href="2018/privacy-quiz[^"]*"' build/particlebits.com/2018/six-privacy-spheres.html | head -3
ls build/particlebits.com/media/2018/six-privacy-spheres/
```

Expected: CTA count 1; href is `2018/privacy-quiz.html` (build env's urlFormat); six-spheres media output contains ONLY `six_spheres.png`. Serve and click through: six-spheres page → CTA button (solid privacy-blue block) → quiz article loads and works. The privacy topic page lists three articles (intro, six-spheres, privacy-quiz).

- [ ] **Step 6: Commit**

```bash
git add -A sites/particlebits.com/articles/privacy/2017/six-privacy-spheres src/css/site.css
git commit -m "Replace six-spheres exercise links with CTA button to quiz article"
```

---

### Task 6: Dist cleanup + full verification + recompile

**Files:**
- Delete: `dist/particlebits.com/media/2018/six-privacy-spheres/{quiz.html,quiz-data.js,privacy-worksheet.pdf}` (git rm)
- Modify: `dist/` (regenerated by `make compile`)

**Interfaces:**
- Consumes: everything above.
- Produces: the deployable production site.

- [ ] **Step 1: Remove stale dist outputs**

```bash
git rm dist/particlebits.com/media/2018/six-privacy-spheres/quiz.html \
    dist/particlebits.com/media/2018/six-privacy-spheres/quiz-data.js \
    dist/particlebits.com/media/2018/six-privacy-spheres/privacy-worksheet.pdf
```

- [ ] **Step 2: Full test + compile**

```bash
node --test tests/
make compile
git status --short | head -30
```

Expected: 9/9 tests; dist changes: modified six-spheres page, modified home/topic/sitemap pages (new article listed), new `dist/particlebits.com/2018/privacy-quiz` page, new files under `dist/particlebits.com/media/2018/privacy-quiz/` (`quiz-data.js`, `six_spheres.png`, `privacy-worksheet.pdf`, `worksheet.html`), deletions from Step 1, and the modified `css/dist.css`/`build.css` (CTA rule).

- [ ] **Step 3: Smoke-test dist**

`make serve`, then verify against `http://localhost:8000`:

1. `/2018/six-privacy-spheres` shows the CTA button; clicking navigates to `/2018/privacy-quiz` (clean URL via router).
2. On `/2018/privacy-quiz`: run the all-balanced quick pass (every cell 2/2) via CDP — five "Balanced · 0" cards, "no action items" message, no pie, zero console errors.
3. `curl -sI http://localhost:8000/media/2018/privacy-quiz/privacy-worksheet.pdf | head -1` → 200; same for `worksheet.html` and `quiz-data.js`.
4. `curl -sI http://localhost:8000/media/2018/six-privacy-spheres/quiz.html | head -1` → a 404 status (stale file gone).

Kill the server afterwards.

- [ ] **Step 4: Commit**

```bash
git add -A dist
git commit -m "Recompile dist with quiz article and cleaned six-spheres media"
```
