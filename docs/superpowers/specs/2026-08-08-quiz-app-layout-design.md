# Quiz App Layout & Branding Refinements — Design

**Date:** 2026-08-08
**Branch:** new branch off master (squash-merge when done)
**Builds on:** `2026-08-08-quiz-article-integration-design.md` (the quiz article,
standalone worksheet, and compiler extensions from that spec are the baseline)

## Goal

Round-3 refinements from testing: an app-style header layout for the quiz
article (title, progress, and an End Quiz control in the site header), darker
primary buttons, a restructured results screen with the donut on top, a
query-param results preview for design iteration, an unload guard for
in-progress quizzes, and a ParticleBits-branded worksheet PDF.

## 1. Primary button color + Start treatment

- New primary button style: background `#007db6` (the site link blue), white
  text, hover `#006ca0`. Applied to: quiz Start / Next / See my results /
  Print buttons, the End Quiz header button, and the six-spheres CTA button
  (which drops its `topic-privacy` class pairing in favor of the solid dark
  blue — `a.cta-button` in `site.css` gains the background/color/hover
  directly). Secondary buttons (Back, Start over) stay gray.
- The Start button becomes a centered block button: ~1.25em type, centered
  via auto margins, `margin-bottom: 2rem`.
- The progress fill color becomes `#007db6` to match.

## 2. App header layout (`layout: "app"`)

New optional `about.json` field `layout`, carried as `Article::$layout`
(default `''`). When `template.phtml` renders an article page whose article
has `layout === 'app'`:

- **Subtitle section** (normally the site subtitle + tagline): first line is
  the article's title; below it an empty `<div id="app-header-slot"></div>`
  mount point. The quiz JS mounts a compact progress indicator there — a thin
  (~0.375rem) `#007db6` fill bar plus a small "Category N of 6" label —
  visible only during category screens; the in-body progress bar is removed.
- **Menu nav**: instead of the Sitemap button, a generic
  `<a class="button" id="app-action" hidden></a>`. The quiz JS unhides it,
  labels it "End Quiz", and wires it to a full in-place reset (see §5).
- `src/html/article.phtml` (the article wrapper template) suppresses the
  in-body `<h1>` and `.meta` byline for app-layout articles — the title
  lives in the header, not duplicated.

The mechanism is generic (title + slot + action anchor), reusable by any
future interactive article. Non-app pages render exactly as before.

## 3. Results screen restructure

- The quiz article's intro content (the `<aside>` and two paragraphs) is
  wrapped in `<div id="pq-intro">`. On the results screen it is hidden, so
  the page content starts at the "Your Privacy Balance" heading. It is
  visible on the intro and category screens.
- New results order: `<h3>Your Privacy Balance</h3>` → the donut + legend +
  recommendations panel → `<h3>Sphere by sphere</h3>` → the five verdict
  cards → nav buttons (Back / Start over / Print).
- All-balanced case: the "every gap is small" message replaces the donut
  block; cards still follow under "Sphere by sphere".

## 4. Results preview mode

`/2018/privacy-quiz?preview=results` (any env) jumps directly to the results
screen with a built-in sample answer set, no interaction required — a stable
target for tweaking results design. The sample set exercises every state:

- Professional: Major imbalance, over-sharing (gaps +4, +3, +2)
- Family: Minor imbalance, under-sharing (gaps −3, −2)
- Social: Minor imbalance, mixed (gaps +3, −3)
- Private: Balanced with a small nonzero score (gap −1)
- Public: Balanced at 0
- Donut shows multiple slices with at least one zero-weight grayed category.

Implementation is a query-string check in the quiz JS; no compiler
involvement. The mode also suppresses the unload guard (§5). Exact cell
values are fixed at plan time and hand-verified against the scoring rules.

## 5. Unload guard + in-place resets

- A `beforeunload` handler is registered whenever `answeredCount() > 0` and
  preview mode is off, triggering the browser-native "leave site?" prompt on
  navigation, refresh, or tab close. It unregisters when no progress exists.
- End Quiz and Start over stop using `location.reload()`: they reset state
  in place (clear all `answers` cells to null, `screen = -1`, re-render,
  restore the intro visibility, reset the header progress) so intentional
  resets never trip the guard, and the guard disarms because progress is
  gone.

## 6. Worksheet PDF branding

`worksheet.phtml` adopts the site's typography and palette:

- `@font-face` for Alegreya Sans (400, 400 italic, 700) and Signika Negative
  (400), `src: url(../../../fonts/<file>.woff2)` — resolves from the
  standalone's output location (`media/2018/privacy-quiz/`) to the deployed
  `/fonts/` directory, for both headless-Chrome PDF generation from `build/`
  and the deployed web version.
- Body: `'Alegreya Sans'` with the existing fallbacks. Headings (`h1`–`h3`):
  `'Signika Negative'`.
- Palette: text `#333`, muted `#666`/`#888`, existing privacy-blue accents
  retained (`#87cefa` heading rule, `rgba(135, 206, 250, 0.2)` table
  headers).
- Page budget stays ≤ 5 pages after the font change; re-verify with the
  regenerated PDF (`make worksheet`), adjusting nothing else unless the new
  metrics overflow, in which case the established page-break rules apply.

## 7. Out of scope

- No changes to scoring (`quiz-data.js` logic), the test suite's assertions,
  category/sphere content, or the donut palette.
- No changes to non-app pages' headers or any other article.

## 8. Verification

- `node --test tests/` stays 9/9.
- CDP checks on the served build: quiz header shows title + live progress +
  End Quiz (labels, reset behavior); another article's header is unchanged;
  results order per §3 with intro hidden; `?preview=results` renders the
  sample results directly; `beforeunload` registered exactly when progress
  exists (checked via CDP without triggering a blocking dialog — assert the
  handler's presence/registration state, do not navigate away with it armed).
- Regenerated PDF: ≤ 5 pages, Alegreya Sans/Signika Negative embedded
  (verify via PDF font listing or visual inspection), labels/copy unchanged.
- Dist recompiled; squash-merge per workflow.
