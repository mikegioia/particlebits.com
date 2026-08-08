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
