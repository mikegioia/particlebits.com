# Quiz App Sidebar & Condensed Screens — Design

**Date:** 2026-08-09
**Branch:** `quiz-app-sidebar` (off master; squash-merge when done)
**Builds on:** `2026-08-08-quiz-app-layout-design.md` (app header, dark buttons,
donut-first results, preview mode, unload guard)

## Goal

Round-4 refinements from testing: a title-only app header, a generic
per-article fixed right-column slot (holding the companion note on the
landing, progress + Back/Next during the quiz, and results nav on results),
condensed single-viewport category screens, and click-time validation
instead of a disabled Next button.

## 1. Header: title only

- The app-layout subtitle branch in `template.phtml` renders ONLY the article
  title — the `#app-header-slot` div is removed from the template, its CSS
  rules are removed, and the JS no longer mounts anything in the header.
- `body.app header section.subtitle` becomes a flex container
  (`display: flex; align-items: center;`) with ~`1.6rem` type so the title is
  large and vertically centered on one line at every breakpoint. The ≤440px
  media override changes from `display: block` to `display: flex`.

## 2. Generic app sidebar (`#app-side`)

- For app-layout articles, `template.phtml` replaces the entire Topics
  section with `<section class="app-side" id="app-side"></section>` — an
  empty per-article slot the article's own JS fills. Non-app pages keep the
  Topics section untouched.
- **Desktop CSS:** `main section.app-side` mirrors the topics column's
  positioning (`position: fixed; right: 0; top: calc(4rem + 1px);
  padding: 1rem; box-sizing: border-box; overflow-y: auto`) and inherits the
  responsive width ladder by appending `, main section.app-side` to every
  `main section.topics` width rule in `site.css` and `media.css`. It does
  NOT receive the `body.article main section.topics { position: absolute }`
  scroll-along override — it stays fixed (pinned) while content scrolls.
- **≤768px:** where the topics column is hidden, the app sidebar becomes a
  fixed bottom bar: full-width, `bottom: 0`, white background, 1px top
  border, contents laid out inline with flex; `body.app main
  section.content` gains bottom padding (~5rem) so content is never hidden
  behind the bar. This applies at ≤440px too (where the header goes static).

## 3. Sidebar contents per screen

The quiz JS adds `renderSide()` called from `render()`:

- **Landing (intro screen):** the companion note ("This quiz is a companion
  to The Six Spheres of Privacy, part of the Privacy and Security series.")
  moves out of the content flow into the sidebar. Because its links are
  PHP-generated (`$al`, topic URL), the markup stays server-rendered inside
  a hidden `<div id="pq-side-intro" hidden>` in the article body; JS copies
  its innerHTML into `#app-side` on the intro screen only. It appears on no
  other screen.
- **Category screens:** progress bar (thin `#007db6` fill) with the
  "Category N of 6 · X% answered" label at the top, then a full-width
  **Next** button (primary; labeled "See my results" on the last category)
  and a **Back** button (secondary) beneath it, then the validation message
  area (§5).
- **Results:** **Print my results** (primary), **Start over** (secondary),
  **Back** (secondary). The `.pq-nav` button rows are removed from the
  content entirely on all screens.
- The Start button remains the big centered block button in the content on
  the landing. The End Quiz header button, unload guard, and preview mode
  are unchanged (preview exercises the sidebar's results nav).

## 4. Condensed quiz screens

- **Landing:** delete the "The five groups you'll rate…" paragraph and the
  sphere `<ul>` from `renderIntro()` — the landing is the article intro
  paragraphs plus Start.
- **Category screens:**
  - Each sphere fieldset's legend + audience paragraph collapse into one
    header line: bold sphere name, then the audience description inline
    after an en dash, small (~0.8125em) and muted.
  - Fieldset padding tightens to ~0.375rem 0.75rem, margin-bottom ~0.5rem;
    rating-row margins tighten; the quiz form area (`.pq-sphere`, ratings)
    drops to 1rem type. Prose outside the form keeps 1.15rem.
  - The category description line is centered (`text-align: center`) under
    a tightened category heading.
  - Goal: a full category screen fits one viewport at typical desktop sizes
    (measure at ~1024px-wide viewport; "as much as possible", not a hard
    guarantee).

## 5. Click-time validation

- The Next button is never disabled.
- Clicking Next/"See my results" on an incomplete screen: each unanswered
  `.pq-rating` row gets a `pq-missing` class (soft red background
  `#fdecea`-family + red left edge), a short message ("Please answer the
  highlighted rows") appears in the sidebar beneath the Next button, and
  the page scrolls to the first highlighted row.
- Answering a highlighted row clears its highlight immediately; the sidebar
  message clears when the screen advances or the screen becomes complete.

## 6. Housekeeping

- `updateHeaderProgress` is repurposed/renamed as the sidebar progress
  updater inside `renderSide`'s domain; no dangling references to the old
  header slot remain in template, CSS, or JS.
- CLAUDE.md's `layout: "app"` paragraph now describes: title-only header,
  the `#app-action` menu anchor, and the `#app-side` right-column slot.
- No changes to scoring (`quiz-data.js`), tests' assertions, the worksheet,
  the PDF, the donut, or any non-app page.

## 7. Verification

- `node --test tests/` stays 9/9.
- CDP at desktop (~1280×800) and 390×844: single-line vertically centered
  header title; sidebar pinned while scrolling (desktop) / fixed bottom bar
  (mobile); companion note only on landing; progress + Back/Next during
  quiz; results nav in sidebar; validation highlights + scroll on premature
  Next, clearing on answer; category screen height measured against the
  viewport; `?preview=results` works; six-spheres and topic pages
  unchanged; zero console errors.
- Dist recompiled; squash-merge per workflow.
