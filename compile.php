<?php

/**
 * Site Compiler
 *
 * This script compiles HTML/CSS/Image assets into
 * a web and server-accessible format. You can specify
 * a specific site or let the utility compile all sites
 * in your /sites directory.
 */

use Legacy\Pages;
use Legacy\Articles;
use League\Flysystem\File;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

// Set up maximum error reporting and UTC time
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');
// Load our source files and vendor libraries and constants
$WD = __DIR__;
require("$WD/vendor/autoload.php");
// Application constants
define('WD', $WD);
define('TYPE_DIR', 'dir');
define('TYPE_FILE', 'file');
define('TPL_HOME', 'home.phtml');
define('TPL_MAIN', 'template.phtml');
define('TPL_ARTICLE', 'article.phtml');
// Set up directories if they don't exist
@mkdir("$WD/build", 0755, true);
@mkdir("$WD/sites", 0755, true);
// Set up system libraries
$sites = new Filesystem(new Local("$WD/sites"));
$build = new Filesystem(new Local("$WD/build"));
$src = new FileSystem(new Local("$WD/src"));
$articles = new Articles($src, $sites);
$pages = new Pages($build, $sites);

if (! $argc) {
    exit("Must be run from the command line!");
}

// Get by key, return default if not set
function get($mixed, $key, $default = null) {
    return is_array($mixed)
        ? (isset($mixed[$key]) ? $mixed[$key] : $default)
        : (isset($mixed->$key) ? $mixed->$key : $default);
}

function error($message, $halt = false) {
    echo 'Error: ', $message, PHP_EOL;
    $halt && exit;
}

function extend(&$object, $mixed) {
    foreach ($mixed as $key => $value) {
        if (is_array($object)) {
            $object[ $key ] = $value;
        } else {
            $object->$key = $value;
        }
    }
}

// Simplest templating engine
function render($file, $data = [], $useSrc = true) {
    extract($data);
    ob_start();
    include(WD.($useSrc ? "/src/html/$file" : "/sites/$file"));
    return ob_get_clean();
}

// Target can be one of 'build' or 'dist'
$target = get($argv, 1, 'build');
$target = in_array($target, ['build', 'dist']) ? $target : 'build';

// Read in all of the sites and process each one, writing
// the entire site HTML and assets to /build.
foreach ($sites->listContents() as $site) {
    if ($site['type'] !== TYPE_DIR) {
        continue;
    }

    if (! $sites->has("{$site['basename']}/sitemap.json")) {
        error("Missing sitemap.json in sites/{$site['basename']}");
        continue;
    }

    $config = json_decode($sites->read("{$site['basename']}/sitemap.json"));

    if (! $config) {
        error("Bad sitemap.json formatting");
        continue;
    }

    extend($config, get($config, $target, []));

    if (! get($config, 'topics')) {
        error("Sitemap missing topics array in sites/{$site['basename']}");
        continue;
    }

    // Create base directories for assets
    $build->createDir($site['basename']);
    $build->createDir("{$site['basename']}/css");
    $build->createDir("{$site['basename']}/fonts");
    $build->createDir("{$site['basename']}/media");
    $build->createDir("{$site['basename']}/topics");

    // Copy site assets to build target
    // @TODO -- Maybe move to build script?

    // Copy system assets to build target
    foreach (['css', 'fonts', 'media'] as $type) {
        foreach ($src->listContents($type) as $meta) {
            $file = new File($src, $meta['path']);
            $build->put("{$site['basename']}/{$meta['path']}", $file->read());
        }
    }

    // Process sites directory for articles
    $articles->load($site, $config);
    // Extend the config object with the environment options
    $pages->configure($site, $config);
    // Write out all the HTML pages
    $pages->write($articles);
}