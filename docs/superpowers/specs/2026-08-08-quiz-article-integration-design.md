# Quiz Article Integration — Design

**Date:** 2026-08-08
**Branch:** `quiz-compile-integration` (off master; squash-merge when done)
**Supersedes parts of:** `2026-08-08-privacy-quiz-design.md` (delivery mechanism only —
the content model, gap scoring, bands, and action-item rules are unchanged)

## Goal

Move the privacy quiz and worksheet out of hand-maintained standalone HTML in the
six-spheres article's `media/` folder and into the site's compile pipeline: the
quiz becomes a dedicated compiled article, the worksheet becomes a compiled
standalone `.phtml`, and the quiz UI is redesigned to match the site's look
(solid topic-chip buttons, bigger type, progress meter, interactive results pie).

## 1. New article: `sites/particlebits.com/articles/privacy/2018/privacy-quiz/`

- **about.json** — title `The 15-Minute Privacy Quiz`, date `2018-07-09` (URL
  `/2018/privacy-quiz`), author Mike Gioia, topic `privacy`, slug `privacy-quiz`,
  hero `six_spheres.png`, snippet: "An interactive companion to The Six Spheres
  of Privacy: inventory what you share, see where your privacy is out of
  balance, and get a personalized list of actions. Also available as a printable
  worksheet." Assets: `{"data": "quiz-data.js", "worksheet":
  "privacy-worksheet.pdf", "spheres": "six_spheres.png"}`.
- **article.phtml** — the quiz UI: a short intro (links back to the six-spheres
  article via `$al('2018', 'six-privacy-spheres')`, offers the PDF via
  `$a('worksheet')`), then the quiz screens rendered by inline vanilla JS that
  loads the data module via `<script src="<?php $a('data'); ?>">`. The
  template's `<base>` makes all URLs env-correct; no hardcoded absolute links.
- **worksheet.phtml** — the print source (moved from the six-spheres directory,
  renamed from `.html`), script tag switched to `$a('data')`. Compiled by the
  standalone convention (§2) to `media/2018/privacy-quiz/worksheet.html` in the
  output — a printable web version ships alongside the PDF.
- **media/** — `quiz-data.js` (moved unchanged; still the single shared
  data/scoring module), `privacy-worksheet.pdf` (regenerated per §6),
  `six_spheres.png` (copy, for the hero card).
- **sitemap.json** — `privacy-quiz` listed in the privacy topic's `articles`
  directly after `six-privacy-spheres`.

## 2. Compiler extensions (`src/php/`)

Two small, backward-compatible additions:

1. **Standalone pages:** any `*.phtml` in an article's directory other than
   `article.phtml` is rendered with the article's helper closures (`$e`, `$a`,
   `$d`, `$wl`, `$al`, `$article`) but WITHOUT the article/site template wrap,
   and written to the article's media output as `<name>.html`. Implemented as
   `Article::renderStandalone($file)` plus discovery in `Articles` and a write
   loop in `Pages::articles()`. No existing article has extra `.phtml` files, so
   no output changes elsewhere.
2. **Cross-article link helper:** `$al($year, $slug)` echoes an env-correct
   article URL built from the site's `urlFormat` (`%YEAR%`/`%SLUG%`
   substitution, as `makeUrl` does today). Available in `article.phtml` and
   standalone `.phtml` files.

`CLAUDE.md` documents both conventions.

## 3. Six-spheres article slims down

- The CTA block becomes a single large block-level button — "Take the 15-Minute
  Privacy Quiz →" — styled like the site's solid topic chips
  (`.topic-privacy` background, dark text, rounded), linking via
  `$al('2018', 'privacy-quiz')`. The "quiz computes for you / worksheet is
  printable" sentence moves to the quiz article's intro; surrounding prose is
  lightly adjusted to hand off to the quiz article. Series `<aside>` links stay
  text links.
- `about.json` drops the `quiz` and `worksheet` assets (keeps `spheres`).
- Deleted from the article directory: `quiz.html`, `quiz-data.js` (media),
  `privacy-worksheet.pdf` (media), `worksheet.html`.

## 4. Quiz UI redesign (inside the article)

The content model, screens, scoring, and copy are unchanged from the prior spec.
Presentation changes:

- **Theme:** inherits site CSS. Controls (Start / Next / Back / See my results /
  Print / Start over) are solid buttons in the topic-chip style, not pale
  outlined ones. A scoped `<style>` block carries only quiz-specific rules
  (rating rows, cards, badges, progress meter, pie) — no duplicated site CSS.
- **Typography:** a scoped rule raises quiz-UI type to `1.15rem` (≈16px
  against the site's 14px base). Quiz pages only; the rest of the site is
  untouched.
- **Progress meter:** a slim bar pinned at the top of the quiz UI, solid
  privacy-color fill, showing the fraction of all 60 ratings answered, labeled
  "Category N of 6". Updates on every radio click.
- **Client-side privacy statement, no network calls, no storage** — unchanged.

## 5. Results: per-sphere cards + clickable category pie

- The five per-sphere verdict cards (band + score + verdict paragraph) remain
  first.
- "Your action items" becomes an interactive donut-variant pie (inline SVG,
  vanilla JS, no libraries):
  - One slice per category with nonzero weight; **weight = Σ|gap| across all
    five spheres for that category**; slice size = share of total imbalance.
  - **Click a slice or its legend row** to select a category; the selected
    category's action items (the same |gap| ≥ 2 items, its subset, sorted by
    |gap| desc) render in a panel below/beside the chart. Heaviest category
    pre-selected.
  - **Donut center** shows the selected category's name and weight.
  - **Legend always present**, listing all six categories in fixed order with
    their weights; zero-weight categories appear grayed with "balanced" and
    have no slice. Legend rows are click targets too.
  - **Accessibility:** slices are keyboard-focusable (role=button, aria-label
    "Money — 5 points, view recommendations"); selection never depends on hover;
    all action items remain plain HTML in the panel; identity is carried by
    legend text, not color alone.
  - **Palette:** six categorical hues assigned in fixed category order (never
    cycled), starting from the dataviz skill's reference palette; MUST be
    validated with the dataviz skill's `validate_palette.js` during
    implementation (adjacent-pair CVD ΔE ≥ 8 target; fix failures by snapping
    to passing steps). Site pastels are explicitly not used for slices.
  - **Marks:** 2px surface gaps between slices; no number printed on every
    slice; values live in the legend, center readout, and panel.
  - **Edge case:** all categories balanced → no chart; show the existing
    "no action items" message.

## 6. Worksheet / PDF changes

- Grid row labels are the full words **"Actual"** and **"Want"** (not A/W), and
  worksheet copy uses "Want" consistently (legend, scoring instructions).
- Body type returns to **12px**; the **page budget is 5 pages** (the prior
  4-page constraint is dropped — ≥11px empirically cannot fit 4). Section
  order unchanged: instructions, inventory grid, scoring, guidance (guidance
  may flow across pages 4–5). `tr { page-break-inside: avoid; }` stays. The
  `.small` size scales proportionally (11px against 12px body).
- **`make worksheet` target:** compiles the build env, runs headless Chrome
  against `build/particlebits.com/media/2018/privacy-quiz/worksheet.html`, and
  writes the PDF to the article's source `media/privacy-worksheet.pdf`.
  Documented in CLAUDE.md. PDF generation stays an authoring step — headless
  Chrome is not a compile dependency.

## 7. Tests

`tests/quiz-data.test.js` changes only its `require` path to the new module
location. The 9-test suite must stay green — the scoring module itself does not
change.

## 8. Cleanup

The compiler never deletes outputs, so stale committed files are removed
explicitly with `git rm`:
`dist/particlebits.com/media/2018/six-privacy-spheres/{quiz.html,quiz-data.js,privacy-worksheet.pdf}`.
Then `make compile` regenerates dist with the new article, its media, and the
updated six-spheres page.

## 9. Verification

- `node --test tests/` green.
- `make build` + `make serve-build`: `/2018/privacy-quiz.html` renders the quiz
  inside site chrome; full end-to-end pass (scripted case from the prior spec
  still yields Professional 5/Minor/over etc.); progress meter fills; pie
  renders, slices/legend select categories, keyboard selection works;
  compiled `worksheet.html` is chrome-less and populated; six-spheres CTA
  button navigates to the quiz; privacy topic page lists three articles.
- Palette validator passes for the six slice colors.
- `make worksheet` regenerates a 12px, ≤5-page PDF with "Actual"/"Want" labels,
  nothing clipped, no row splits.
- `make compile`; dist contains no files under
  `media/2018/six-privacy-spheres/` except `six_spheres.png`; squash-merge to
  master per workflow.
