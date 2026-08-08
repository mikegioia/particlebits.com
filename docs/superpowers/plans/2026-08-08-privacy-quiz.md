# Privacy Balance Quiz + Worksheet Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish "The Six Spheres of Privacy" article by shipping its two exercises as an interactive quiz page and a printable PDF worksheet, both driven by one shared data/scoring module.

**Architecture:** A shared vanilla-JS module (`quiz-data.js`) holds the six information categories, five rated spheres, 0–4 scale, band thresholds, all authored verdict/advice copy, and the pure scoring functions. `quiz.html` (interactive quiz) and `worksheet.html` (print layout, PDF source) both consume it. Everything user-facing lives in the article's `media/` folder, which the compiler copies verbatim — zero changes to `compile.php` or `src/php/`.

**Tech Stack:** Vanilla HTML/CSS/JS (no dependencies), Node 20 built-in test runner (`node --test`) for scoring tests, headless Google Chrome for HTML→PDF, existing PHP 8 static-site compiler.

**Spec:** `docs/superpowers/specs/2026-08-08-privacy-quiz-design.md`

## Global Constraints

- No new dependencies of any kind: no Composer, no npm, no `package.json`. Node is used only with built-in modules (`node:test`, `node:assert`).
- No changes to `compile.php` or anything in `src/php/`.
- The quiz makes zero network calls and uses zero storage (no localStorage, no cookies); it must state on the page that answers never leave the browser.
- Scale is 0–4 with these exact labels: `Nothing`, `A little`, `Some`, `A lot`, `Everything`.
- Bands on the per-sphere score (Σ|gap| over 6 categories, range 0–24): 0–3 `Balanced`, 4–8 `Minor imbalance`, 9–24 `Major imbalance`.
- Per-cell action items are emitted only when `|gap| ≥ 2`, sorted by `|gap|` descending.
- There is no combined score across spheres — results are per-sphere only.
- `dist/`, `build/`, `local/` are generated — never hand-edit them. `dist/` IS committed to git (repo convention: "Recompile dist…" commits).
- Article date is `2018-07-02`, so media assets deploy to `media/2018/six-privacy-spheres/` even though the source directory is under `2017/`.
- End every commit message with:
  `Claude-Session: https://claude.ai/code/session_012HTWUAHFpfqZvUwGuJSTBk`
- Site look to echo: text `#333`, links `#007db6`, privacy accent `rgba(135, 206, 250, 0.2)` (solid `#87cefa`), body font `'Alegreya Sans'` with system fallbacks, muted gray `#888`, borders `#ddd`.

Paths below are relative to the repo root `/Users/mike/Projects/particlebits.com`. `ARTICLE_DIR` means `sites/particlebits.com/articles/privacy/2017/six-privacy-spheres`.

---

### Task 1: Shared data model + scoring (`quiz-data.js`) with tests

**Files:**
- Create: `ARTICLE_DIR/media/quiz-data.js`
- Test: `tests/quiz-data.test.js`

**Interfaces:**
- Consumes: nothing.
- Produces: global `PrivacyQuiz` (also CommonJS-exported) with:
  - `scale: string[5]`
  - `categories: [{key, name, phrase, description}]` (6 entries)
  - `spheres: [{key, name, audience}]` (5 entries)
  - `bands: [{max, name}]`
  - `verdictText[sphereKey]: {balanced, over, under}` — authored paragraphs
  - `mixedText: string`
  - `cellAdvice[sphereKey]: {over, under}` — advice clause per direction
  - `gap(actual, desired) → number`
  - `band(score) → 'Balanced' | 'Minor imbalance' | 'Major imbalance'`
  - `sphereResult(answers, sphereKey) → {score, signedSum, band, direction, verdict}` where `direction ∈ 'balanced'|'over'|'under'|'mixed'`
  - `actionText(categoryKey, sphereKey, actual, desired) → string`
  - `actionItems(answers) → [{categoryKey, sphereKey, gap, text}]`
  - `answers` shape everywhere: `answers[categoryKey][sphereKey] = {actual: 0–4, desired: 0–4}`

- [ ] **Step 1: Write the failing tests**

Create `tests/quiz-data.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert');
const PQ = require('../sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/quiz-data.js');

// Build a full answers object where every cell is {actual: a, desired: d}
function uniformAnswers(a, d) {
    const answers = {};
    for (const category of PQ.categories) {
        answers[category.key] = {};
        for (const sphere of PQ.spheres) {
            answers[category.key][sphere.key] = { actual: a, desired: d };
        }
    }
    return answers;
}

test('data model shape', () => {
    assert.strictEqual(PQ.scale.length, 5);
    assert.deepStrictEqual(PQ.scale, ['Nothing', 'A little', 'Some', 'A lot', 'Everything']);
    assert.strictEqual(PQ.categories.length, 6);
    assert.strictEqual(PQ.spheres.length, 5);
    assert.deepStrictEqual(PQ.spheres.map(s => s.key),
        ['private', 'family', 'social', 'professional', 'public']);
    for (const sphere of PQ.spheres) {
        assert.ok(PQ.verdictText[sphere.key].balanced.length > 0);
        assert.ok(PQ.verdictText[sphere.key].over.length > 0);
        assert.ok(PQ.verdictText[sphere.key].under.length > 0);
        assert.ok(PQ.cellAdvice[sphere.key].over.length > 0);
        assert.ok(PQ.cellAdvice[sphere.key].under.length > 0);
    }
});

test('gap is actual minus desired', () => {
    assert.strictEqual(PQ.gap(4, 1), 3);
    assert.strictEqual(PQ.gap(1, 4), -3);
    assert.strictEqual(PQ.gap(2, 2), 0);
});

test('band boundaries', () => {
    assert.strictEqual(PQ.band(0), 'Balanced');
    assert.strictEqual(PQ.band(3), 'Balanced');
    assert.strictEqual(PQ.band(4), 'Minor imbalance');
    assert.strictEqual(PQ.band(8), 'Minor imbalance');
    assert.strictEqual(PQ.band(9), 'Major imbalance');
    assert.strictEqual(PQ.band(24), 'Major imbalance');
});

test('all-balanced answers give zero scores and balanced verdicts', () => {
    const answers = uniformAnswers(2, 2);
    for (const sphere of PQ.spheres) {
        const r = PQ.sphereResult(answers, sphere.key);
        assert.strictEqual(r.score, 0);
        assert.strictEqual(r.band, 'Balanced');
        assert.strictEqual(r.direction, 'balanced');
        assert.strictEqual(r.verdict, PQ.verdictText[sphere.key].balanced);
    }
    assert.deepStrictEqual(PQ.actionItems(answers), []);
});

test('over-sharing sphere: score, band, direction, verdict', () => {
    const answers = uniformAnswers(2, 2);
    // Professional sphere: Money gap +3, Beliefs gap +2 → score 5, Minor, over
    answers.money.professional = { actual: 4, desired: 1 };
    answers.beliefs.professional = { actual: 3, desired: 1 };
    const r = PQ.sphereResult(answers, 'professional');
    assert.strictEqual(r.score, 5);
    assert.strictEqual(r.signedSum, 5);
    assert.strictEqual(r.band, 'Minor imbalance');
    assert.strictEqual(r.direction, 'over');
    assert.strictEqual(r.verdict, PQ.verdictText.professional.over);
});

test('under-sharing sphere and major band', () => {
    const answers = uniformAnswers(2, 2);
    // Family: three -3 gaps → score 9, Major, under
    answers.health.family = { actual: 0, desired: 3 };
    answers.emotions.family = { actual: 0, desired: 3 };
    answers.daily.family = { actual: 0, desired: 3 };
    const r = PQ.sphereResult(answers, 'family');
    assert.strictEqual(r.score, 9);
    assert.strictEqual(r.signedSum, -9);
    assert.strictEqual(r.band, 'Major imbalance');
    assert.strictEqual(r.direction, 'under');
    assert.strictEqual(r.verdict, PQ.verdictText.family.under);
});

test('mixed direction when signed gaps cancel but score is imbalanced', () => {
    const answers = uniformAnswers(2, 2);
    answers.money.social = { actual: 4, desired: 1 };    // +3
    answers.emotions.social = { actual: 0, desired: 3 }; // -3
    const r = PQ.sphereResult(answers, 'social');
    assert.strictEqual(r.score, 6);
    assert.strictEqual(r.signedSum, 0);
    assert.strictEqual(r.band, 'Minor imbalance');
    assert.strictEqual(r.direction, 'mixed');
    assert.strictEqual(r.verdict, PQ.mixedText);
});

test('small nonzero score stays Balanced with balanced verdict', () => {
    const answers = uniformAnswers(2, 2);
    answers.money.public = { actual: 3, desired: 2 }; // +1 → score 1
    const r = PQ.sphereResult(answers, 'public');
    assert.strictEqual(r.score, 1);
    assert.strictEqual(r.band, 'Balanced');
    assert.strictEqual(r.verdict, PQ.verdictText.public.balanced);
});

test('actionItems: threshold |gap| >= 2, sorted by |gap| desc, direction-aware text', () => {
    const answers = uniformAnswers(2, 2);
    answers.money.professional = { actual: 4, desired: 1 };  // +3
    answers.emotions.family = { actual: 0, desired: 3 };     // -3
    answers.beliefs.professional = { actual: 3, desired: 1 }; // +2
    answers.health.social = { actual: 3, desired: 2 };       // +1 → excluded
    const items = PQ.actionItems(answers);
    assert.strictEqual(items.length, 3);
    assert.strictEqual(Math.abs(items[0].gap), 3);
    assert.strictEqual(Math.abs(items[1].gap), 3);
    assert.strictEqual(Math.abs(items[2].gap), 2);
    const moneyItem = items.find(i => i.categoryKey === 'money');
    assert.match(moneyItem.text, /You share everything about your finances/);
    assert.match(moneyItem.text, /want to share a little/);
    assert.ok(moneyItem.text.includes(PQ.cellAdvice.professional.over));
    const emotionsItem = items.find(i => i.categoryKey === 'emotions');
    assert.ok(emotionsItem.text.includes(PQ.cellAdvice.family.under));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `node --test tests/`
Expected: FAIL — `Cannot find module '.../media/quiz-data.js'`

- [ ] **Step 3: Write `quiz-data.js`**

Create `ARTICLE_DIR/media/quiz-data.js` with exactly this content:

```js
/**
 * Shared data model and scoring for the Privacy Balance exercises
 * from "The Six Spheres of Privacy". Loaded by quiz.html (interactive
 * quiz) and worksheet.html (the PDF print source), and by the node
 * test suite in tests/quiz-data.test.js.
 *
 * Each category x sphere cell is rated twice on a 0-4 scale: how much
 * is actually shared vs how much the person wants shared. The gap
 * (actual - desired) drives everything: per-sphere balance score is
 * the sum of |gap| across categories, and the sign of a gap tells
 * whether it is over-sharing (+) or under-sharing (-).
 */
const PrivacyQuiz = {
    scale: ['Nothing', 'A little', 'Some', 'A lot', 'Everything'],

    categories: [
        {
            key: 'health',
            name: 'Health',
            phrase: 'your health',
            description: 'Physical and mental health, conditions, treatment, habits'
        },
        {
            key: 'money',
            name: 'Money',
            phrase: 'your finances',
            description: 'Income, debts, spending, financial situation'
        },
        {
            key: 'romantic',
            name: 'Romantic & Sexual Life',
            phrase: 'your romantic life',
            description: 'Relationships, desires, preferences, history'
        },
        {
            key: 'beliefs',
            name: 'Beliefs & Opinions',
            phrase: 'your beliefs and opinions',
            description: 'Religion, politics, controversial or unpopular views'
        },
        {
            key: 'emotions',
            name: 'Emotions & Struggles',
            phrase: 'your emotions and struggles',
            description: 'Fears, failures, worries, what you are going through'
        },
        {
            key: 'daily',
            name: 'Whereabouts & Daily Life',
            phrase: 'your whereabouts and daily life',
            description: 'Location, routines, plans, what you did today'
        }
    ],

    spheres: [
        {
            key: 'private',
            name: 'Private',
            audience: 'your partner or closest intimate relationships'
        },
        {
            key: 'family',
            name: 'Family',
            audience: 'your family, and those you consider family'
        },
        {
            key: 'social',
            name: 'Social',
            audience: 'your friends and social circles'
        },
        {
            key: 'professional',
            name: 'Professional',
            audience: 'your coworkers, boss, and professional contacts'
        },
        {
            key: 'public',
            name: 'Public',
            audience: 'the outside world: acquaintances, strangers, the Internet'
        }
    ],

    bands: [
        { max: 3, name: 'Balanced' },
        { max: 8, name: 'Minor imbalance' },
        { max: 24, name: 'Major imbalance' }
    ],

    verdictText: {
        private: {
            balanced: 'What you share with your partner matches what you want to share. This balance is the foundation of trust in your closest relationship — keep tending it.',
            over: 'More of your life reaches your partner than you are comfortable with. Revisit what you consider yours alone and practice keeping it there — closeness does not require total transparency.',
            under: 'You are holding back more from your partner than you would like to. Sharing more here is one of the most direct ways to strengthen the relationship.'
        },
        family: {
            balanced: 'Your family knows about as much of your life as you want them to. Balance here tends to show up as easy, low-friction relationships.',
            over: 'Your family knows more than you would like. Decide which topics you want to reclaim and set gentle boundaries before resentment builds.',
            under: 'You want your family to know more of your life than they currently do. Think of the mother who keeps asking you to call — small, regular sharing strengthens these bonds.'
        },
        social: {
            balanced: 'You are sharing with friends at the level you are comfortable with. Your social life reflects the self you intend to show.',
            over: 'Your friends are getting more than you intend. Audit what you volunteer in conversation and on social media, and remember that anything shared socially tends to travel.',
            under: 'You would like your friends closer to your life than they are. Opening up a little more is a low-risk way to deepen friendships.'
        },
        professional: {
            balanced: 'Your work persona is under control — colleagues see what you want them to see, and little else.',
            over: 'Too much of your outside life is reaching your workplace. Leaks here can affect your livelihood — tighten what you bring into work conversations and keep personal accounts separate from professional ones.',
            under: 'You are more guarded at work than you want to be. Sharing selectively — interests, context, appropriate personal news — can build trust with colleagues.'
        },
        public: {
            balanced: 'Your public exposure matches your intent. Whether you have kept a low profile or chosen your openness deliberately, both are strong privacy.',
            over: 'You are exposing more publicly than you want. Audit your online accounts, tighten privacy settings, prune old posts, and reconsider what you post going forward — public information rarely comes back.',
            under: 'You want more public presence than you have. Decide deliberately what to publish and where; openness chosen on purpose is also good privacy.'
        }
    },

    mixedText: 'You are over-sharing some topics and under-sharing others here. Look at your individual action items below — each direction has its own fix.',

    cellAdvice: {
        private: {
            over: 'consider whether this belongs to you alone, and hold it back',
            under: 'try opening up about this; it can bring you closer'
        },
        family: {
            over: 'set a gentle boundary around this topic with family',
            under: 'let your family into this part of your life a bit more'
        },
        social: {
            over: 'be more deliberate about this in conversation and on social media',
            under: 'share a bit more of this with friends you trust'
        },
        professional: {
            over: 'keep this out of workplace conversations and accounts',
            under: 'share this selectively at work, where it builds trust'
        },
        public: {
            over: 'remove or lock down this information where it is publicly visible',
            under: 'publish this deliberately, on your own terms'
        }
    },

    gap: function (actual, desired) {
        return actual - desired;
    },

    band: function (score) {
        for (var i = 0; i < this.bands.length; i++) {
            if (score <= this.bands[i].max) {
                return this.bands[i].name;
            }
        }

        return this.bands[this.bands.length - 1].name;
    },

    sphereResult: function (answers, sphereKey) {
        var score = 0;
        var signedSum = 0;

        for (var i = 0; i < this.categories.length; i++) {
            var cell = answers[this.categories[i].key][sphereKey];
            var g = this.gap(cell.actual, cell.desired);

            score += Math.abs(g);
            signedSum += g;
        }

        var band = this.band(score);
        var direction = 'balanced';

        if (score > 0) {
            direction = signedSum > 0
                ? 'over'
                : (signedSum < 0 ? 'under' : 'mixed');
        }

        var verdict;

        if (band === 'Balanced') {
            verdict = this.verdictText[sphereKey].balanced;
        } else if (direction === 'mixed') {
            verdict = this.mixedText;
        } else {
            verdict = this.verdictText[sphereKey][direction];
        }

        return {
            score: score,
            signedSum: signedSum,
            band: band,
            direction: direction,
            verdict: verdict
        };
    },

    actionText: function (categoryKey, sphereKey, actual, desired) {
        var category = this.categories.find(function (c) {
            return c.key === categoryKey;
        });
        var sphere = this.spheres.find(function (s) {
            return s.key === sphereKey;
        });
        var direction = actual > desired ? 'over' : 'under';

        return 'You share ' + this.scale[actual].toLowerCase()
            + ' about ' + category.phrase
            + ' with your ' + sphere.name + ' sphere, but want to share '
            + this.scale[desired].toLowerCase()
            + ' — ' + this.cellAdvice[sphereKey][direction] + '.';
    },

    actionItems: function (answers) {
        var items = [];

        for (var i = 0; i < this.categories.length; i++) {
            for (var j = 0; j < this.spheres.length; j++) {
                var categoryKey = this.categories[i].key;
                var sphereKey = this.spheres[j].key;
                var cell = answers[categoryKey][sphereKey];
                var g = this.gap(cell.actual, cell.desired);

                if (Math.abs(g) >= 2) {
                    items.push({
                        categoryKey: categoryKey,
                        sphereKey: sphereKey,
                        gap: g,
                        text: this.actionText(
                            categoryKey, sphereKey, cell.actual, cell.desired)
                    });
                }
            }
        }

        items.sort(function (a, b) {
            return Math.abs(b.gap) - Math.abs(a.gap);
        });

        return items;
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = PrivacyQuiz;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `node --test tests/`
Expected: all tests PASS (9 tests)

- [ ] **Step 5: Commit**

```bash
git add tests/quiz-data.test.js sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/quiz-data.js
git commit -m "Add privacy quiz data model and gap-based scoring with tests"
```

---

### Task 2: Interactive quiz page (`quiz.html`)

**Files:**
- Create: `ARTICLE_DIR/media/quiz.html`

**Interfaces:**
- Consumes: global `PrivacyQuiz` from `quiz-data.js` (same directory, loaded via `<script src="quiz-data.js">`): `categories`, `spheres`, `scale`, `sphereResult(answers, sphereKey)`, `actionItems(answers)`.
- Produces: the deployed quiz page at `media/2018/six-privacy-spheres/quiz.html`. No exports.

- [ ] **Step 1: Write `quiz.html`**

Create `ARTICLE_DIR/media/quiz.html` with exactly this content:

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>The Privacy Balance Quiz &mdash; ParticleBits</title>
<style>
:root {
    --accent: rgba(135, 206, 250, 0.2);
    --accent-solid: #87cefa;
    --link: #007db6;
    --text: #333;
    --muted: #888;
    --border: #ddd;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    color: var(--text);
    background: #fff;
    font-family: 'Alegreya Sans', 'Helvetica Neue', Arial, sans-serif;
    font-size: 16px;
    line-height: 1.5;
}
header {
    padding: 1.5rem 1rem 1rem;
    text-align: center;
    border-bottom: 1px solid var(--border);
    background: var(--accent);
}
header h1 { margin: 0; font-weight: normal; font-size: 1.75rem; }
header .note { color: var(--muted); margin: 0.5rem 0 0; font-size: 0.875rem; }
main { max-width: 46rem; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
a { color: var(--link); }
button {
    font: inherit;
    color: var(--text);
    background: var(--accent);
    border: 1px solid var(--accent-solid);
    border-radius: 0.25rem;
    padding: 0.5rem 1.25rem;
    cursor: pointer;
}
button:disabled { opacity: 0.4; cursor: not-allowed; }
button.secondary { background: #fafafa; border-color: var(--border); }
.progress { color: var(--muted); font-size: 0.875rem; margin-bottom: 0.25rem; }
h2 { font-weight: normal; margin: 0 0 0.25rem; }
.description { color: var(--muted); margin: 0 0 1.25rem; }
fieldset {
    border: 1px solid var(--border);
    border-radius: 0.25rem;
    margin: 0 0 1rem;
    padding: 0.75rem 1rem;
}
legend { font-weight: bold; padding: 0 0.375rem; }
.audience { color: var(--muted); font-size: 0.875rem; margin: 0 0 0.5rem; }
.rating { display: flex; flex-wrap: wrap; align-items: baseline; margin: 0.375rem 0; }
.rating > span { flex: 0 0 9.5rem; font-size: 0.9375rem; }
.rating label {
    margin: 0.125rem 0.875rem 0.125rem 0;
    font-size: 0.9375rem;
    white-space: nowrap;
    cursor: pointer;
}
.nav { display: flex; justify-content: space-between; margin-top: 1.5rem; }
.card {
    border: 1px solid var(--border);
    border-left: 0.375rem solid var(--accent-solid);
    border-radius: 0.25rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
}
.card h3 { margin: 0; font-size: 1.125rem; }
.badge {
    font-size: 0.8125rem;
    font-weight: normal;
    padding: 0.125rem 0.625rem;
    border-radius: 1rem;
    border: 1px solid var(--border);
    margin-left: 0.5rem;
    vertical-align: middle;
    white-space: nowrap;
}
.badge.balanced { background: #e6f4ea; border-color: #b7dfc2; }
.badge.minor { background: #fff8e1; border-color: #f0dfa0; }
.badge.major { background: #fdecea; border-color: #f5c6c0; }
.card p { margin: 0.375rem 0 0; }
.actions li { margin-bottom: 0.5rem; }
@media print {
    header .note, .nav, .no-print { display: none; }
    .card { break-inside: avoid; }
}
</style>
</head>
<body>
<header>
    <h1>The Privacy Balance Quiz</h1>
    <p class="note">
        Part of
        <a href="https://particlebits.com/2018/six-privacy-spheres">The
        Six Spheres of Privacy</a>. All answers stay on this page &mdash;
        nothing is sent or saved anywhere.
    </p>
</header>
<main id="app"></main>
<script src="quiz-data.js"></script>
<script>
(function () {
    var app = document.getElementById('app');
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
            '<h2>Two exercises, fifteen minutes</h2>'
            + '<p>This quiz walks you through the two exercises from the '
            + 'article: taking inventory of what you share, and deciding '
            + 'what you actually <em>want</em> to share. For six categories '
            + 'of information in your life, you will rate &mdash; for each '
            + 'group of people around you &mdash; how much you currently '
            + 'share and how much you would want to share, from '
            + '&ldquo;nothing&rdquo; to &ldquo;everything.&rdquo;</p>'
            + '<p>There are no wrong answers. The quiz measures the '
            + '<em>gap</em> between what you share and what you want to '
            + 'share, not how private or how open you are. Answer with '
            + 'your first instinct.</p>'
            + '<p>The five groups, from the article&rsquo;s six spheres '
            + '(your Personal sphere is the baseline &mdash; it always '
            + 'holds everything):</p>'
            + '<ul>'
            + PrivacyQuiz.spheres.map(function (sphere) {
                return '<li><b>' + sphere.name + '</b> &mdash; '
                    + sphere.audience + '</li>';
            }).join('')
            + '</ul>'
            + '<div class="nav"><span></span>'
            + '<button id="next">Start</button></div>';

        document.getElementById('next').onclick = function () {
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

        return '<div class="rating"><span>' + label + '</span>'
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
            '<p class="progress">Category ' + (index + 1) + ' of '
            + PrivacyQuiz.categories.length + '</p>'
            + '<h2>' + category.name + '</h2>'
            + '<p class="description">' + category.description + '</p>'
            + PrivacyQuiz.spheres.map(function (sphere) {
                return '<fieldset><legend>' + sphere.name + '</legend>'
                    + '<p class="audience">' + sphere.audience + '</p>'
                    + ratingRow(category, sphere, 'actual', 'I currently share:')
                    + ratingRow(category, sphere, 'desired', 'I want to share:')
                    + '</fieldset>';
            }).join('')
            + '<div class="nav">'
            + '<button id="back" class="secondary">Back</button>'
            + '<button id="next" disabled>'
            + (index === PrivacyQuiz.categories.length - 1
                ? 'See my results' : 'Next')
            + '</button></div>';

        app.querySelectorAll('input[type=radio]').forEach(function (input) {
            input.onchange = function () {
                var parts = input.name.split('-');

                answers[parts[0]][parts[1]][parts[2]] = parseInt(input.value, 10);
                document.getElementById('next').disabled = !isScreenComplete(index);
            };
        });

        document.getElementById('next').disabled = !isScreenComplete(index);
        document.getElementById('next').onclick = function () {
            screen += 1;
            render();
        };
        document.getElementById('back').onclick = function () {
            screen -= 1;
            render();
        };
    }

    function badgeClass(band) {
        if (band === 'Balanced') {
            return 'balanced';
        }

        return band === 'Minor imbalance' ? 'minor' : 'major';
    }

    function renderResults() {
        var items = PrivacyQuiz.actionItems(answers);

        app.innerHTML =
            '<h2>Your privacy balance</h2>'
            + '<p class="description">Scores measure the gap between what '
            + 'you share and what you want to share &mdash; per sphere, '
            + 'out of a possible 24.</p>'
            + PrivacyQuiz.spheres.map(function (sphere) {
                var result = PrivacyQuiz.sphereResult(answers, sphere.key);

                return '<div class="card"><h3>' + sphere.name
                    + ' <span class="badge ' + badgeClass(result.band) + '">'
                    + result.band + ' &middot; ' + result.score + '</span></h3>'
                    + '<p>' + result.verdict + '</p></div>';
            }).join('')
            + '<h2>Your action items</h2>'
            + (items.length === 0
                ? '<p>No action items &mdash; every gap is small. Your '
                    + 'sharing closely matches your intent.</p>'
                : '<ol class="actions">' + items.map(function (item) {
                    return '<li>' + item.text + '</li>';
                }).join('') + '</ol>')
            + '<div class="nav">'
            + '<button id="back" class="secondary">Back</button>'
            + '<span><button id="retake" class="secondary">Start over</button> '
            + '<button id="print">Print my results</button></span></div>';

        document.getElementById('back').onclick = function () {
            screen -= 1;
            render();
        };
        document.getElementById('retake').onclick = function () {
            window.location.reload();
        };
        document.getElementById('print').onclick = function () {
            window.print();
        };
    }

    render();
})();
</script>
</body>
</html>
```

- [ ] **Step 2: Verify the flow in a browser**

Run: `open sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/quiz.html` (or verify with browser tools).

Check:
1. Intro shows five spheres and the never-leaves-browser note; Start works.
2. Each category screen: 5 fieldsets × 2 radio rows; Next stays disabled until all 10 answered; Back preserves previous answers (radios re-checked).
3. Enter this scripted case — all cells `actual=2, desired=2` EXCEPT: Money/Professional `actual=4, desired=1`; Beliefs/Professional `actual=3, desired=1`; Emotions/Family `actual=0, desired=3`.
4. Expected results (hand-computed): Professional = score 5, "Minor imbalance", over-sharing verdict; Family = score 3, "Balanced"; Private, Social, Public = score 0, "Balanced". Exactly 3 action items, the two |gap|=3 items first.
5. Print button opens the print dialog with nav buttons hidden.

Expected: all five checks pass.

- [ ] **Step 3: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/quiz.html
git commit -m "Add interactive privacy balance quiz page"
```

---

### Task 3: Printable worksheet PDF

**Files:**
- Create: `ARTICLE_DIR/worksheet.html` (print source — outside `media/`, so it is never deployed)
- Create: `ARTICLE_DIR/media/privacy-worksheet.pdf` (generated, committed)

**Interfaces:**
- Consumes: global `PrivacyQuiz` from `media/quiz-data.js` (loaded via `<script src="media/quiz-data.js">`): `categories`, `spheres`, `scale`, `bands`, `verdictText`, `mixedText`, `cellAdvice`.
- Produces: the deployed PDF at `media/2018/six-privacy-spheres/privacy-worksheet.pdf`.

- [ ] **Step 1: Write `worksheet.html`**

Create `ARTICLE_DIR/worksheet.html` with exactly this content:

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>The Privacy Balance Worksheet</title>
<style>
@page { size: letter; margin: 0.6in; }
* { box-sizing: border-box; }
body {
    margin: 0;
    color: #333;
    font-family: 'Alegreya Sans', 'Helvetica Neue', Arial, sans-serif;
    font-size: 12px;
    line-height: 1.45;
}
.page { page-break-after: always; }
.page:last-child { page-break-after: auto; }
h1 { font-weight: normal; font-size: 22px; margin: 0 0 2px; }
h2 { font-weight: normal; font-size: 17px; margin: 0 0 10px; padding-bottom: 4px; border-bottom: 2px solid #87cefa; }
h3 { font-size: 13px; margin: 14px 0 4px; }
.subtitle { color: #888; margin: 0 0 14px; }
table { border-collapse: collapse; width: 100%; margin: 8px 0 14px; }
th, td { border: 1px solid #999; padding: 3px 6px; text-align: left; vertical-align: top; }
th { background: rgba(135, 206, 250, 0.2); font-weight: bold; }
td.category { font-weight: bold; }
td.category small { font-weight: normal; color: #666; }
td.blank { height: 22px; min-width: 52px; }
td.rowlabel { color: #666; font-style: italic; }
.legend td:first-child { font-weight: bold; text-align: center; width: 28px; }
p { margin: 0 0 8px; }
.small { font-size: 11px; color: #666; }
</style>
</head>
<body>

<section class="page">
    <h1>The Privacy Balance Worksheet</h1>
    <p class="subtitle">From &ldquo;The Six Spheres of Privacy&rdquo; &mdash; particlebits.com</p>
    <h2>How this works</h2>
    <p>
        Privacy is the balance between three things: what you share, what
        you feel comfortable sharing, and with whom. This worksheet takes
        you through the article&rsquo;s two exercises: <b>Step&nbsp;1</b>,
        inventory what you are sharing today; <b>Step&nbsp;2</b>, decide
        what you actually <i>want</i> to share. The difference between the
        two answers &mdash; not how private or open you are &mdash; is
        what gets scored.
    </p>
    <p>
        On the next page, for every row (category of information) and
        every column (sphere of your life), write two numbers from the
        scale below: <b>A</b> for how much you <i>actually</i> share, and
        <b>W</b> for how much you <i>want</i> to share. Go with your first
        instinct; there are no wrong answers.
    </p>
    <table class="legend"><tbody id="legend"></tbody></table>
    <h3 id="sphere-list-heading">The five spheres you will rate</h3>
    <p class="small">
        (Your Personal sphere is the baseline &mdash; it always holds
        everything, so it has no column.)
    </p>
    <ul id="sphere-list"></ul>
</section>

<section class="page">
    <h2>Steps 1 &amp; 2: The inventory</h2>
    <p class="small">
        A = how much you actually share today &nbsp;&bull;&nbsp;
        W = how much you want to share &nbsp;&bull;&nbsp;
        each is a number from 0 (nothing) to 4 (everything)
    </p>
    <table id="grid"></table>
</section>

<section class="page">
    <h2>Scoring</h2>
    <p>
        For each cell in your grid, the <b>gap</b> is A&nbsp;&minus;&nbsp;W.
        A positive gap means you are sharing <i>more</i> than you want
        (over-sharing); a negative gap means <i>less</i> (under-sharing).
    </p>
    <p>
        For each sphere (column), add up the gaps <i>ignoring their
        signs</i> &mdash; six numbers per column, each between 0 and 4.
        Write the total below, then look up your verdict.
    </p>
    <table id="tally"></table>
    <table id="bands"></table>
    <p class="small">
        Direction: if most of a column&rsquo;s gaps were positive, read
        the &ldquo;over-sharing&rdquo; guidance on the next page for that
        sphere; if mostly negative, read &ldquo;under-sharing&rdquo;; if
        both, read both &mdash; each direction has its own fix.
    </p>
</section>

<section class="page">
    <h2>What your results mean</h2>
    <div id="verdicts"></div>
    <h3>Your action items</h3>
    <p>
        Circle every cell in your grid where the gap is <b>2 or more</b>
        in either direction. Each circled cell is one concrete action
        item &mdash; use the guidance below for its sphere and direction.
    </p>
    <table id="advice"></table>
</section>

<script src="media/quiz-data.js"></script>
<script>
(function () {
    document.getElementById('legend').innerHTML =
        PrivacyQuiz.scale.map(function (label, value) {
            return '<tr><td>' + value + '</td><td>' + label + '</td></tr>';
        }).join('');

    document.getElementById('sphere-list').innerHTML =
        PrivacyQuiz.spheres.map(function (sphere) {
            return '<li><b>' + sphere.name + '</b> &mdash; '
                + sphere.audience + '</li>';
        }).join('');

    document.getElementById('grid').innerHTML =
        '<tr><th></th><th></th>'
        + PrivacyQuiz.spheres.map(function (sphere) {
            return '<th>' + sphere.name + '</th>';
        }).join('') + '</tr>'
        + PrivacyQuiz.categories.map(function (category) {
            var blanks = PrivacyQuiz.spheres.map(function () {
                return '<td class="blank"></td>';
            }).join('');

            return '<tr><td class="category" rowspan="2">' + category.name
                + '<br><small>' + category.description + '</small></td>'
                + '<td class="rowlabel">A</td>' + blanks + '</tr>'
                + '<tr><td class="rowlabel">W</td>' + blanks + '</tr>';
        }).join('');

    document.getElementById('tally').innerHTML =
        '<tr><th></th>'
        + PrivacyQuiz.spheres.map(function (sphere) {
            return '<th>' + sphere.name + '</th>';
        }).join('') + '</tr>'
        + '<tr><td class="rowlabel">Total of |gaps| (0&ndash;24)</td>'
        + PrivacyQuiz.spheres.map(function () {
            return '<td class="blank"></td>';
        }).join('') + '</tr>'
        + '<tr><td class="rowlabel">Verdict</td>'
        + PrivacyQuiz.spheres.map(function () {
            return '<td class="blank"></td>';
        }).join('') + '</tr>';

    var previousMax = -1;

    document.getElementById('bands').innerHTML =
        '<tr><th>Total</th><th>Verdict</th></tr>'
        + PrivacyQuiz.bands.map(function (band) {
            var label = (previousMax + 1) + '&ndash;' + band.max;

            previousMax = band.max;

            return '<tr><td>' + label + '</td><td>' + band.name + '</td></tr>';
        }).join('');

    document.getElementById('verdicts').innerHTML =
        PrivacyQuiz.spheres.map(function (sphere) {
            var text = PrivacyQuiz.verdictText[sphere.key];

            return '<h3>' + sphere.name + '</h3>'
                + '<p><b>Balanced:</b> ' + text.balanced + '</p>'
                + '<p><b>Over-sharing:</b> ' + text.over + '</p>'
                + '<p><b>Under-sharing:</b> ' + text.under + '</p>';
        }).join('')
        + '<p class="small"><b>Both directions at once?</b> '
        + PrivacyQuiz.mixedText.replace('below', 'from your circled cells')
        + '</p>';

    document.getElementById('advice').innerHTML =
        '<tr><th>Sphere</th><th>Gap of +2 or more (over-sharing)</th>'
        + '<th>Gap of &minus;2 or less (under-sharing)</th></tr>'
        + PrivacyQuiz.spheres.map(function (sphere) {
            var advice = PrivacyQuiz.cellAdvice[sphere.key];

            return '<tr><td class="category">' + sphere.name + '</td>'
                + '<td>' + advice.over + '</td>'
                + '<td>' + advice.under + '</td></tr>';
        }).join('');
})();
</script>
</body>
</html>
```

- [ ] **Step 2: Generate the PDF**

```bash
cd sites/particlebits.com/articles/privacy/2017/six-privacy-spheres
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
    --headless=new --disable-gpu --no-pdf-header-footer \
    --print-to-pdf=media/privacy-worksheet.pdf worksheet.html
```

Expected: `media/privacy-worksheet.pdf` created, non-trivial size (> 20 KB).

- [ ] **Step 3: Verify the PDF visually**

Run: `open media/privacy-worksheet.pdf` (or read the PDF with the Read tool).

Check: 4 pages — (1) instructions with 0–4 legend and sphere list, (2) inventory grid with 12 A/W rows × 5 sphere columns of blank cells, (3) tally + band tables, (4) verdict paragraphs for all 5 spheres plus the advice table. Tables are not clipped, and no page overflows into a 5th page. If the grid overflows, reduce `body` font-size (e.g. 11px) and regenerate.

- [ ] **Step 4: Commit**

```bash
cd ../../../../../..  # back to repo root
git add sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/worksheet.html \
    sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/media/privacy-worksheet.pdf
git commit -m "Add printable privacy worksheet and generated PDF"
```

---

### Task 4: Article integration

**Files:**
- Modify: `ARTICLE_DIR/article.phtml` (the `<h3>Practicing and Exercising</h3>` section and the `[TBD]` placeholder near the end; typo fixes in the body)
- Modify: `ARTICLE_DIR/about.json`

**Interfaces:**
- Consumes: `about.json` `assets` keys resolve through the `$a()` helper to `media/2018/six-privacy-spheres/<file>` URLs (already-working mechanism, see `$a('spheres')` in the same file).
- Produces: article page linking to `quiz.html` and `privacy-worksheet.pdf`; anchors `practicing-exercising`, `step-1-inventory`, `step-2-decide` matching the TOC in `about.json`.

- [ ] **Step 1: Update `about.json`**

Two edits:

1. In `toc`, fix the typo `"private": "Private Shere"` → `"private": "Private Sphere"`.
2. Replace the `assets` map with:

```json
"assets": {
    "spheres": "six_spheres.png",
    "quiz": "quiz.html",
    "worksheet": "privacy-worksheet.pdf"
}
```

- [ ] **Step 2: Update `article.phtml`**

Three typo fixes in the body:
- `we were carless or ignorant` → `we were careless or ignorant`
- `Celebrities have a more larger public presence` → `Celebrities have a larger public presence`
- `there are six discreet lives` → `there are six discrete lives` (in the paragraph starting "When it comes to privacy")

Then replace this block (from the `<h3>Practicing and Exercising</h3>` heading's opening tag through the `[TBD]` line):

```html
<h3>Practicing and Exercising</h3>
```

with:

```html
<h3 id="practicing-exercising">Practicing and Exercising</h3>
```

and replace the final paragraph + placeholder:

```html
<p>
    These next two exercises can be done quickly and easily, but can require
    much introspection to do fully. I’ve put together a 15-minute quiz that
    asks you many different questions, and prepares a list of action items
    based on your own privacy preferences:
</p>

<p>[TBD] -- [Take the 15-minute Privacy Quiz]</p>
```

with:

```html
<p>
    These next two exercises can be done quickly and easily, but can require
    much introspection to do fully.
</p>

<h4 id="step-1-inventory">Step 1: Inventory what you&rsquo;re sharing</h4>

<p>
    The first exercise is to take inventory. For six categories of
    information in your life &mdash; your health, your money, your romantic
    life, your beliefs, your emotions and struggles, and your whereabouts
    and daily life &mdash; rate how much you currently share into each of
    the five outward spheres, from &ldquo;nothing&rdquo; to
    &ldquo;everything.&rdquo; The Personal Sphere is your baseline: it
    always holds everything, because it&rsquo;s the information you keep
    with yourself. Don&rsquo;t overthink any answer; your first instinct
    is usually right, and there are no wrong answers &mdash; this is an
    inventory, not a test.
</p>

<h4 id="step-2-decide">Step 2: Decide what and with whom you want to share</h4>

<p>
    The second exercise is to rate each of the same items again, this time
    answering how much you <i>want</i> to share. The difference between
    the two answers is where the insight lives. Wherever you&rsquo;re
    sharing more than you want, you&rsquo;ve found a leak to plug:
    information to reclaim, settings to tighten, boundaries to set.
    Wherever you&rsquo;re sharing less than you want, you&rsquo;ve found a
    relationship that could grow stronger by opening up. Adding up these
    gaps within each sphere shows where your privacy is balanced and where
    it needs attention, and yields a concrete list of actions tailored to
    you.
</p>

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

- [ ] **Step 3: Compile and verify links**

```bash
make build
ls build/particlebits.com/media/2018/six-privacy-spheres/
grep -o 'media/2018/six-privacy-spheres/[a-z.-]*' build/particlebits.com/2018/six-privacy-spheres.html | sort -u
grep -c 'step-1-inventory\|step-2-decide\|practicing-exercising' build/particlebits.com/2018/six-privacy-spheres.html
```

Expected: media listing shows `quiz-data.js`, `quiz.html`, `privacy-worksheet.pdf`, `six_spheres.png`; the grep shows links to `quiz.html` and `privacy-worksheet.pdf`; anchor grep count ≥ 3 (each id appears in the TOC and the heading). Then serve and spot-check in a browser:

```bash
make serve-build
```

Open `http://localhost:8000/2018/six-privacy-spheres.html` — TOC entries for Steps 1 and 2 jump to the new headings; the quiz link opens the working quiz; the PDF link downloads. Stop the server afterwards.

- [ ] **Step 4: Commit**

```bash
git add sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/article.phtml \
    sites/particlebits.com/articles/privacy/2017/six-privacy-spheres/about.json
git commit -m "Finish six-spheres article with exercise steps, quiz and worksheet links"
```

---

### Task 5: Full verification + dist recompile

**Files:**
- Modify: `dist/` (generated by `make compile`)

**Interfaces:**
- Consumes: everything above.
- Produces: the deployable production site.

- [ ] **Step 1: Run the full test suite one last time**

Run: `node --test tests/`
Expected: all PASS.

- [ ] **Step 2: Recompile production**

```bash
make compile
git status --short dist/ | head -20
```

Expected: changed `dist/particlebits.com/2018/six-privacy-spheres` page plus new files under `dist/particlebits.com/media/2018/six-privacy-spheres/` (`quiz.html`, `quiz-data.js`, `privacy-worksheet.pdf`).

- [ ] **Step 3: Smoke-test dist**

```bash
make serve
```

Open `http://localhost:8000/2018/six-privacy-spheres` (clean URL via `router.php`): article renders, quiz link works end-to-end (run one quick pass: answer everything `actual=2, desired=2`, expect all five spheres "Balanced · 0" and zero action items), PDF downloads. Stop the server.

- [ ] **Step 4: Commit dist**

```bash
git add dist
git commit -m "Recompile dist with privacy quiz and worksheet"
```
