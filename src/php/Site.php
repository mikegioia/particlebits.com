<?php

namespace Legacy;

use League\Flysystem\File;
use League\Flysystem\Filesystem as FS;

class Site
{
    private $env;
    private $src;
    private $sites;
    private $target;

    public function __construct(FS $src, FS $target, FS $sites, $env)
    {
        $this->env = $env;
        $this->src = $src;
        $this->sites = $sites;
        $this->target = $target;
    }

    /**
     * Process a site and write all content and files.
     * @param Pages    $pages
     * @param Articles $articles
     * @param array    $site
     */
    public function run(Pages $pages, Articles $articles, $site)
    {
        if ($site['type'] !== TYPE_DIR) {
            return;
        }

        if (! $this->sites->has("{$site['basename']}/sitemap.json")) {
            error("Missing sitemap.json in sites/{$site['basename']}");
            return;
        }

        $fileWriteCount = 0;
        $config = $this->getConfig($site);

        if (! $config) {
            return;
        }

        message("Processing site {$site['basename']}");

        $this->createDirectories($site);
        $this->writeAssets($site, $fileWriteCount);

        message('Writing HTML files');

        // Process sites directory for articles
        $articles->load($site, $config);
        // Extend the config object with the environment options
        $pages->configure($site, $config);
        // Write out all the HTML pages
        $pages->write($articles, $fileWriteCount);

        message("Wrote $fileWriteCount files");

        if ($this->env === LOCAL) {
            info("Open file://". WD ."/local/{$site['basename']}/index.html");
        } else {
            info("Open {$config->basepath}");
        }
    }

    private function getConfig($site)
    {
        $json = $this->sites->read("{$site['basename']}/sitemap.json");
        $config = json_decode($json);

        if (! $config) {
            error("Bad sitemap.json formatting");
            return;
        }

        extend($config, get($config, $this->env, []));

        if (! get($config, 'topics')) {
            error("Sitemap missing topics array in sites/{$site['basename']}");
            return;
        }

        return $config;
    }

    private function createDirectories($site)
    {
        message('Creating base directories for assets');

        $this->target->createDir($site['basename']);
        $this->target->createDir("{$site['basename']}/css");
        $this->target->createDir("{$site['basename']}/fonts");
        $this->target->createDir("{$site['basename']}/media");
        $this->target->createDir("{$site['basename']}/topics");
    }

    private function writeAssets($site, &$fileWriteCount)
    {
        message('Writing asset files');

        // Copy site assets to build target
        foreach (['css', 'fonts', 'media'] as $type) {
            foreach ($this->sites->listContents($type) as $meta) {
                $fileWriteCount++;
                $file = new File($this->sites, $meta['path']);
                $this->target->put("{$site['basename']}/{$meta['path']}", $file->read());
            }
        }

        // Copy system assets to build target
        foreach (['css', 'fonts', 'media'] as $type) {
            foreach ($this->src->listContents($type) as $meta) {
                $fileWriteCount++;
                $file = new File($this->src, $meta['path']);
                $this->target->put("{$site['basename']}/{$meta['path']}", $file->read());
            }
        }

        // Copy any favicon files
        foreach(['favicon.ico', 'favicon-256.png'] as $icon) {
            if ($this->sites->has("{$site['basename']}/$icon")) {
                $fileWriteCount++;
                $file = new File($this->sites, "{$site['basename']}/$icon");
                $this->target->put("{$site['basename']}/$icon", $file->read());
            }
        }
    }
}
