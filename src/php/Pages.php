<?php

namespace Legacy;

use Legacy\Article;
use Legacy\Articles;
use League\Flysystem\Filesystem;

class Pages
{
    private $site;
    private $sites;
    private $build;
    private $config;

    private $defaults = [
        'css' => '',
        'theme' => '',
        'logo' => 'AZ',
        'basepath' => '',
        'title' => 'TITLE',
        'contributors' => [],
        'subtitle' => 'SUBTITLE',
        'logoTagline' => 'LOGO TAG LINE',
        'urlFormat' => Article::URL_FORMAT,
        'subtitleTagline' => 'SUBTITLE TAG LINE'
    ];

    const PAGE_HOME = 'home';
    const PAGE_TOPIC = 'topic';
    const PAGE_ARTICLE = 'article';

    public function __construct(Filesystem $build, Filesystem $sites)
    {
        $this->build = $build;
        $this->sites = $sites;
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

    public function write(Articles $articles)
    {
        $this->home($articles);
        $this->articles($articles);
        //$this->topics();
        //$this->sitemap();
    }

    /**
     * Write the index HTML page
     */
    private function home(Articles $articles)
    {
        $data = $this->config;

        // Set up page content
        $data['page'] = self::PAGE_HOME;
        $data['topics'] = $articles->topics->getActive();
        $data['content'] = render(TPL_HOME, [
            'articles' => $articles->articles
        ]);

        // Write out the file
        $this->build->put(
            "{$this->site['basename']}/index.html",
            render(TPL_MAIN, $data));
    }

    /**
     * Write each article page to the artciles folder.
     */
    private function articles(Articles $articles)
    {
        $data = $this->config;

        // Set up page content
        $data['page'] = self::PAGE_ARTICLE;
        $data['topics'] = $articles->topics->getActive();

        foreach ($articles->articles as $article) {
            $data['content'] = $article->render();

            // Write out the article file. Writing file implicitly
            // creates directories
            $this->build->put(
                "{$this->site['basename']}/{$article->url}",
                render(TPL_MAIN, $data));

            // Copy all assets to the assets directory
            foreach ($article->medias as $media) {
                $assetUrl = $article->getAssetUrl($media['basename']);

                $this->build->put(
                    "{$this->site['basename']}/{$assetUrl}",
                    $this->sites->read($media['path']));
            }
        }
    }
}