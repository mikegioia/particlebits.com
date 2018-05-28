<?php

namespace Legacy;

use Legacy\Article;
use Legacy\Articles;
use League\Flysystem\Filesystem;

class Pages
{
    private $build;

    private $defaults = [
        'css' => '',
        'theme' => '',
        'logo' => 'AZ',
        'title' => 'TITLE',
        'contributors' => [],
        'subtitle' => 'SUBTITLE',
        'logoTagline' => 'LOGO TAG LINE',
        'subtitleTagline' => 'SUBTITLE TAG LINE'
    ];

    const PAGE_HOME = 'home';
    const PAGE_TOPIC = 'topic';
    const PAGE_ARTICLE = 'article';

    public function __construct(Filesystem $build)
    {
        $this->build = $build;
    }


    public function write($site, Articles $articles, $config)
    {
        $this->home($site, $articles, $config);
        //$this->articles();
        //$this->topics();
        //$this->sitemap();
    }

    /**
     * Write the index HTML page
     */
    private function home($site, Articles $articles, $config)
    {
        // Extend defaults with config options
        $data = array_merge(
            $this->defaults,
            array_intersect_key((array)$config, $this->defaults));

        // Set up page content
        $data['page'] = self::PAGE_HOME;
        $data['topics'] = $articles->topics->getActive();
        $data['content'] = render(TPL_HOME, [
            'articles' => $articles->articles
        ]);

        // Write out the file
        $this->build->put("{$site['basename']}/index.html", render(TPL_MAIN, $data));
    }
}