<?php

namespace Legacy;

class Site
{
    private $env;
    private $src;
    private $sites;
    private $target;

    public function __construct(Filesystem $src, Filesystem $target, Filesystem $sites, $env)
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
        $this->minifyAssets($site, $fileWriteCount);

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
                $this->target->put(
                    "{$site['basename']}/{$meta['path']}",
                    $this->sites->read($meta['path']));
            }
        }

        // Copy system assets to build target
        foreach (['css', 'fonts', 'media'] as $type) {
            foreach ($this->src->listContents($type) as $meta) {
                $fileWriteCount++;
                $this->target->put(
                    "{$site['basename']}/{$meta['path']}",
                    $this->src->read($meta['path']));
            }
        }

        // Copy any favicon files
        foreach(['favicon.ico', 'favicon-256.png'] as $icon) {
            if ($this->sites->has("{$site['basename']}/$icon")) {
                $fileWriteCount++;
                $this->target->put(
                    "{$site['basename']}/$icon",
                    $this->sites->read("{$site['basename']}/$icon"));
            }
        }
    }

    private function minifyAssets($site, &$fileWriteCount)
    {
        message('Combining and minifying CSS files');

        // First combine all the source CSS files into build.css
        $combined = '';

        foreach (['fonts.css', 'site.css', 'media.css'] as $file) {
            $combined .= $this->src->read("css/$file") . PHP_EOL;
        }

        $this->target->put("{$site['basename']}/css/build.css", $combined);
        $fileWriteCount++;

        // Then minify the file into dist.css
        $this->target->put(
            "{$site['basename']}/css/dist.css",
            $this->minifyCss($combined));
        $fileWriteCount++;
    }

    /**
     * Basic CSS minification: strips comments, collapses
     * whitespace, and drops semicolons before closing braces.
     * The source CSS keeps no whitespace-sensitive content
     * (strings, data URIs) so this is safe here.
     */
    private function minifyCss($css)
    {
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }
}
