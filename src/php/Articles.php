<?php

namespace Legacy;

use League\Flysystem\Filesystem;

class Articles
{
    // Environment
    public $env;
    // Container for Topics
    public $topics;
    // Stores Article objects
    public $articles;
    // Contains counts of articles by topic
    public $topicCounts = [];

    private $src;
    private $sites;

    public function __construct(Filesystem $src, Filesystem $sites, $env)
    {
        $this->src = $src;
        $this->env = $env;
        $this->sites = $sites;
    }

    public function load($site, $config)
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
                    $this->loadArticle($slug, $topic, $config);
                }
            }
        }

        // Set up the topics and attach them to the articles
        $this->loadTopics($config->topics);

        return $this->articles;
    }

    public function getSorted()
    {
        $sorted = $this->articles;

        usort($sorted, function ($a, $b) {
            return $a->date < $b->date;
        });

        return $sorted;
    }

    public function getArticle($slug)
    {
        return isset($this->articles[$slug])
            ? $this->articles[$slug]
            : new Article;
    }

    public function getArticles($slugs)
    {
        $articles = [];

        foreach ($slugs as $slug) {
            if (isset($this->articles[$slug])) {
                $articles[] = $this->articles[$slug];
            }
        }

        return $articles;
    }

    private function loadArticle($slug, $topic, $config)
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

        $meta->medias = [];

        // Build index of media assets if they exist
        if ($this->sites->has("{$slug['path']}/media")) {
            foreach ($this->sites->listContents("{$slug['path']}/media") as $media) {
                $meta->medias[] = $media;
            }
        }

        $this->incrementTopicCount($topic['basename']);
        $this->addArticle($meta, $slug, $config);
    }

    private function loadTopics($siteTopics)
    {
        // Process the topics from the config file and article meta
        $this->topics->load($siteTopics, $this->topicCounts, $this->env);

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

    private function addArticle($meta, $slug, $config)
    {
        $article = new Article($meta, $slug['path'], $config->urlFormat);

        $this->articles[$article->slug] = $article;
    }
}