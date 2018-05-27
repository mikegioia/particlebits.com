<?php

namespace Legacy;

use Legacy\Pages;
use Legacy\Topics;
use Legacy\Article;
use League\Flysystem\Filesystem;

class Articles
{
    // Container for Topics
    public $topics;
    // Stores Article objects
    public $articles;
    // Contains counts of articles by topic
    public $topicCounts = [];

    private $src;
    private $sites;

    public function __construct(Filesystem $src, Filesystem $sites)
    {
        $this->src = $src;
        $this->sites = $sites;
    }

    public function load($site, $siteTopics)
    {
        $this->articles = [];
        $this->topics = new Topics;
        $path = "{$site['basename']}/articles";

        foreach ($this->sites->listContents($path) as $topic) {
            if ($topic['type'] !== TYPE_DIR) {
                continue;
            }

            foreach ($this->sites->listContents($topic['path']) as $year) {
                if ($year['type'] !== TYPE_DIR) {
                    continue;
                }

                foreach ($this->sites->listContents($year['path']) as $slug) {
                    $this->loadArticle($slug, $topic);
                }
            }
        }

        // Set up the topics and attach them to the articles
        $this->loadTopics($siteTopics);

        return $this->articles;
    }

    private function loadArticle($slug, $topic)
    {
        if ($slug['type'] !== TYPE_DIR) {
            return;
        }

        if (! $this->sites->has("{$slug['path']}/about.json")) {
            error("Missing about.json in {$slug['path']}");
            return;
        }

        if (! $this->sites->has("{$slug['path']}/article.phtml")) {
            error("Missing article.phtml in {$slug['path']}");
            return;
        }

        $meta = json_decode($this->sites->read("{$slug['path']}/about.json"));

        if (is_null($meta)) {
            error("Failed parsing JSON in {$slug['path']}/about.json");
            return;
        }

        $this->incrementTopicCount($topic['basename']);
        $this->addArticle($meta, $slug);
    }

    private function loadTopics($siteTopics)
    {
        // Process the topics from the config file and article meta
        $this->topics->load($siteTopics, $this->topicCounts);

        // Attach topic objects to all active articles
        foreach ($this->articles as $article) {
            $article->setTopic($this->topics->find($article->topic));
        }
    }

    private function incrementTopicCount($topicSlug)
    {
        if (! isset($this->topicCounts[$topicSlug])) {
            $this->topicCounts[$topicSlug] = 0;
        }

        $this->topicCounts[$topicSlug] += 1;
    }

    private function addArticle($meta, $slug)
    {
        $article = new Article($meta, $slug['path']);

        $this->articles[] = $article;
    }
}