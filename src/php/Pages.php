<?php

namespace Legacy;

use League\Flysystem\Filesystem;

class Pages
{
    private $env;
    private $site;
    private $sites;
    private $config;
    private $target;
    private $defaults = [
        'css' => '',
        'theme' => '',
        'logo' => 'AZ',
        'local' => true,
        'basepath' => '',
        'title' => 'TITLE',
        'contributors' => [],
        'subtitle' => 'SUBTITLE',
        'homeUrl' => 'index.html',
        'sitemapUrl' => 'sitemap.html',
        'logoTagline' => 'LOGO TAG LINE',
        'urlFormat' => Article::URL_FORMAT,
        'subtitleTagline' => 'SUBTITLE TAG LINE'
    ];

    const PAGE_HOME = 'home';
    const PAGE_TOPIC = 'topic';
    const PAGE_ARTICLE = 'article';
    const PAGE_SITEMAP = 'sitemap';

    public function __construct(Filesystem $target, Filesystem $sites, $env)
    {
        $this->env = $env;
        $this->sites = $sites;
        $this->target = $target;
    }

    public function configure($site, $config)
    {
        $this->site = $site;

        // Extend defaults with config options
        $this->config = array_merge(
            $this->defaults,
            array_intersect_key((array)$config, $this->defaults));

        if (! $this->config['basepath']) {
            $this->config['basepath'] = sprintf(
                "file://%s/build/%s/",
                WD,
                $this->site['basename']);
        }
    }

    public function write(Articles $articles, &$fileWriteCount)
    {
        $data = $this->config;
        // Set up common base data
        $data['local'] = $this->env === BUILD;
        $data['topics'] = $articles->topics->getActive();

        $this->home($articles, $data, $fileWriteCount);
        $this->topics($articles, $data, $fileWriteCount);
        $this->sitemap($articles, $data, $fileWriteCount);
        $this->articles($articles, $data, $fileWriteCount);
    }

    /**
     * Write the index HTML page
     */
    private function home(Articles $articles, $data, &$fileWriteCount)
    {
        $data['page'] = self::PAGE_HOME;
        $data['content'] = render(TPL_HOME, [
            'articles' => $articles->getSorted()
        ]);

        // Write out the file
        $this->target->put(
            "{$this->site['basename']}/index.html",
            render(TPL_MAIN, $data));
        $fileWriteCount++;
    }

    /**
     * Write each article page to the articles folder.
     */
    private function articles(Articles $articles, $data, &$fileWriteCount)
    {
        $data['page'] = self::PAGE_ARTICLE;

        foreach ($articles->articles as $article) {
            $data['content'] = $article->render();

            // Write out the article file. Writing file implicitly
            // creates directories
            $this->target->put(
                "{$this->site['basename']}/{$article->url}",
                render(TPL_MAIN, $data));
            $fileWriteCount++;

            // Copy all assets to the assets directory
            foreach ($article->medias as $media) {
                $assetUrl = $article->getAssetUrl($media['basename']);

                $this->target->put(
                    "{$this->site['basename']}/{$assetUrl}",
                    $this->sites->read($media['path']));
                $fileWriteCount++;
            }
        }
    }

    private function topics(Articles $articles, $data, &$fileWriteCount)
    {
        $data['articles'] = $articles;
        $data['page'] = self::PAGE_TOPIC;

        foreach ($articles->topics->getActive() as $topic) {
            $data['topic'] = $topic;
            $data['articles'] = $articles->getArticles($topic->articles);
            $data['content'] = render(TPL_TOPIC, $data);

            // Write out the article file. Writing file implicitly
            // creates directories
            $this->target->put(
                "{$this->site['basename']}/{$topic->url}",
                render(TPL_MAIN, $data));
            $fileWriteCount++;
        }
    }

    private function sitemap(Articles $articles, $data, &$fileWriteCount)
    {
        $data['articles'] = $articles;
        $data['page'] = self::PAGE_SITEMAP;
        $data['content'] = render(TPL_SITEMAP, $data);

        // Write out the file
        $this->target->put(
            "{$this->site['basename']}/sitemap.html",
            render(TPL_MAIN, $data));
        $fileWriteCount++;
    }
}