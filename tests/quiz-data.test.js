const test = require('node:test');
const assert = require('node:assert');
const PQ = require('../sites/particlebits.com/articles/privacy/2018/privacy-quiz/media/quiz-data.js');

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
