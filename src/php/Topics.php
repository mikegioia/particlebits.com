<?php

namespace Legacy;

use Legacy\Topic;

class Topics
{
    public $topics = [];

    private $build;
    private $COLORS = [
        'none' => [240, 240, 240, 0.15],
        'gold' => [255, 215, 0, 0.25],
        'plum' => [221, 160, 221, 0.22],
        'khaki' => [240, 230, 140, 0.3],
        'coral' => [255, 127, 80, 0.15],
        'salmon' => [250, 128, 114, 0.2],
        'thistle' => [216, 191, 216, 0.3 ],
        'peachpuff' => [255, 218, 185, 0.3],
        'lightblue' => [173, 216, 230, 0.3],
        'lightpink' => [255, 182, 193, 0.25],
        'sandybrown' => [244, 164, 96, 0.25],
        'burlywood' => [222, 184, 135, 0.25],
        'lightgreen' => [144, 238, 144, 0.2],
        'lightskyblue' => [135, 206, 250, 0.2],
        'paleturquoise' => [175, 238, 238, 0.3],
        'mediumaquamarine' => [102, 205, 170, 0.15]
    ];

    const NOCOLOR = 'none';

    public function load($topics, $topicCounts)
    {
        foreach ($topics as $slug => $topic) {
            $topic->slug = $slug;
            $color = get($topic, 'color', self::NOCOLOR);
            $rgb = get($this->COLORS, $color, $this->COLORS[self::NOCOLOR]);
            $this->topics[$slug] = new Topic($topic, $rgb, get($topicCounts, $slug, 0));
        }
    }

    public function getActive()
    {
        $active = array_filter($this->topics, function ($topic) {
            return $topic->isActive();
        });

        uasort($active, function ($a, $b) {
            return strcmp($a->sort, $b->sort);
        });

        return $active;
    }

    public function find($topicSlug)
    {
        if (! isset($this->topics[$topicSlug])) {
            error("$topicSlug not found in Topics getTopic()");
            return new Topic([], $this->COLORS[self::NOCOLOR], 0);
        }

        return $this->topics[$topicSlug];
    }
}