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
define('DIST', 'dist');
define('BUILD', 'build');
define('TYPE_DIR', 'dir');
define('TYPE_FILE', 'file');
define('TPL_HOME', 'home.phtml');
define('TPL_MAIN', 'template.phtml');
define('TPL_ARTICLE', 'article.phtml');
define('TPL_CONTRIBUTORS', 'contributors.phtml');
// Set up directories if they don't exist
@mkdir("$WD/build", 0755, true);
@mkdir("$WD/sites", 0755, true);
// Environment can be one of 'build' or 'dist'
$env = get($argv, 1, BUILD);
$env = in_array($env, [BUILD, DIST]) ? $env : BUILD;
// Set up file system drivers
$src = new FileSystem(new Local("$WD/src"));
$dist = new FileSystem(new Local("$WD/dist"));
$build = new Filesystem(new Local("$WD/build"));
$sites = new Filesystem(new Local("$WD/sites"));
$target = $env === BUILD ? $build : $dist;
// Set up system classes
$pages = new Pages($target, $sites, $env);
$articles = new Articles($src, $sites, $env);

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

    extend($config, get($config, $env, []));

    if (! get($config, 'topics')) {
        error("Sitemap missing topics array in sites/{$site['basename']}");
        continue;
    }

    // Create base directories for assets
    $target->createDir($site['basename']);
    $target->createDir("{$site['basename']}/css");
    $target->createDir("{$site['basename']}/fonts");
    $target->createDir("{$site['basename']}/media");
    $target->createDir("{$site['basename']}/topics");

    // Copy site assets to build target
    // @TODO -- Maybe move to build script?
    foreach (['css', 'fonts', 'media'] as $type) {
        foreach ($sites->listContents($type) as $meta) {
            $file = new File($sites, $meta['path']);
            $target->put("{$site['basename']}/{$meta['path']}", $file->read());
        }
    }

    // Copy system assets to build target
    // @TODO -- Maybe move to build script?
    foreach (['css', 'fonts', 'media'] as $type) {
        foreach ($src->listContents($type) as $meta) {
            $file = new File($src, $meta['path']);
            $target->put("{$site['basename']}/{$meta['path']}", $file->read());
        }
    }

    // Process sites directory for articles
    $articles->load($site, $config);
    // Extend the config object with the environment options
    $pages->configure($site, $config);
    // Write out all the HTML pages
    $pages->write($articles);
}