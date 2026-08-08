# Privacy Balance Quiz + Worksheet — Design

**Date:** 2026-08-08
**Article:** `sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/`
**Goal:** Finish "The Six Spheres of Privacy" by replacing the `[TBD]` placeholder
with the article's two exercises, delivered as (1) an interactive, self-contained
HTML quiz and (2) a downloadable print-and-write PDF worksheet.

## Background

The article defines privacy as the balance between three factors: what you share,
what you feel comfortable sharing, and with whom. Imbalance goes both ways —
over-sharing (leaks, exposure) and under-sharing (weakened relationships, e.g. the
"call your mother" example). The `about.json` TOC already promises two steps:
"Step 1: Inventory what you're sharing" and "Step 2: Decide what and with whom you
want to share."

A single "how much do you share" 1–5 score summed up would contradict the article:
an intentionally open person would score "worst" while an unaware leaker scored
well. The exercises therefore use **gap-based scoring** — the difference between
actual and desired sharing — not raw sharing volume.

## 1. Content model (shared by quiz and PDF)

One inventory matrix: **6 information categories × 5 outward spheres**.

Categories (rows):

1. Health — physical/mental health, conditions, treatment
2. Money — income, debts, spending, financial situation
3. Romantic & sexual life — relationships, desires, history
4. Beliefs & opinions — religion, politics, controversial views
5. Emotions & struggles — fears, failures, what you're going through
6. Whereabouts & daily life — location, routines, plans, what you did today

Spheres (columns): Private (partner), Family, Social, Professional, Public.
The Personal sphere ("only you") is the baseline and is explained in the intro,
not rated — sharing with yourself is definitionally full.

Each cell is rated twice on a 0–4 scale (Nothing / A little / Some / A lot /
Everything):

- **Actual** — how much you currently share into that sphere
- **Desired** — how much you would want shared there

Total: 60 judgments, presented **category-by-category** (hold one topic in mind,
sweep the five audiences). Categories, sphere descriptions, scale labels, and all
action text live in one data structure so the quiz and PDF cannot drift apart.

## 2. Scoring (Exercise 1)

- Per cell: **gap = Actual − Desired**, range −4 to +4.
- Per sphere: **balance score = Σ |gap|** across the 6 categories, range 0–24.

| Balance score | Verdict |
|---|---|
| 0–3 | Balanced |
| 4–8 | Minor imbalance |
| 9+ | Major imbalance |

Gap sign classifies direction: positive = **over-sharing** (protect, tighten,
audit); negative = **under-sharing** (open up, strengthen the relationship).

There is deliberately **no combined total across spheres** — the article's thesis
is that balance is per-relationship, so results stay per-sphere.

## 3. Action mapping (Exercise 2)

Two layers:

1. **Per-sphere verdict:** band + dominant direction (sign of the signed gap sum;
   ties read as "mixed") selects a short authored paragraph per sphere — e.g.
   major over-share imbalance in Public → audit accounts, tighten settings, prune
   old posts; under-share in Family → the article's "call your mother" guidance.
2. **Specific action items:** every cell with |gap| ≥ 2 emits a concrete line
   item ("You share *a lot* about Money professionally but want to share
   *a little* — consider…"), sorted by gap magnitude. This is the personalized
   action list the article promises.

The same mapping appears in the PDF as a lookup table consulted after
hand-scoring.

## 4. Interactive quiz

- Single self-contained file: `media/quiz.html` in the article directory,
  copied verbatim by the compiler to `media/2018/six-privacy-spheres/` — no
  changes to `compile.php` or any `src/php/` class.
- Inline CSS + vanilla JS, zero dependencies, styling echoing the site.
- Flow: intro (brief sphere recap, link back to the article) → 6 category
  screens of 10 ratings each (radio scales) → results page.
- Results: per-sphere balance score, band, direction, and the sorted action
  list; a print button for keeping a copy.
- All computation is client-side; the page states explicitly that answers never
  leave the browser. No storage, no network calls.

## 5. PDF worksheet

`media/privacy-worksheet.pdf`, checked into the article's `media/` folder and
copied verbatim at compile time. A clean print-and-write document:

1. Instructions page (spheres recap, the 0–4 scale, how to fill the grid)
2. Inventory grid — 6 × 5 with blank Actual/Desired sub-cells
3. Scoring section — compute gaps, sum |gap| per sphere, find your band
4. Action-mapping lookup table (same content as the quiz's authored actions)

Built from an HTML print layout at implementation time; the generated PDF is the
committed artifact.

## 6. Article integration

- Replace the `[TBD]` placeholder in `article.phtml` with the finished
  "Practicing and Exercising" content: two subsections with ids
  `step-1-inventory` and `step-2-decide` (matching the existing `about.json`
  TOC), describing the two exercises and linking to the quiz and PDF via the
  `assets` map (`$a()` helper).
- Add `id="practicing-exercising"` to the "Practicing and Exercising" `<h3>` —
  the TOC references an anchor that does not yet exist.
- Register `quiz` and `worksheet` entries in `about.json` `assets`.
- Cleanups: "Private Shere" → "Private Sphere" in the TOC; "carless" →
  "careless" and "more larger" → "larger" in the article body.

## 7. Error handling

- Quiz: every rating defaults to unanswered; the Next button is disabled until
  a screen is complete, so results can never be computed from partial input.
  No other failure modes exist (no network, no storage).
- PDF: static artifact; nothing to handle.

## 8. Verification

No test infrastructure exists in this repo. Verification is:

- Hand-computed scoring cases (including an all-balanced case, a pure
  over-share case, a pure under-share case, and a mixed case) checked against
  the quiz's rendered results in the browser.
- `make build` + `make serve-build` to confirm links and media assets resolve.
- Print preview of the PDF layout.
