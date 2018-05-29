<?php

namespace Legacy;

use Legacy\Topic;
use League\Flysystem\Filesystem;

class Article
{
    public $url;
    public $date;
    public $slug;
    public $title;
    public $topic;
    public $author;
    public $snippet;
    public $data = [];
    public $assets = [];
    public $medias = [];
    public $weblinks = [];
    public $featured = false;

    // Path to article's directory
    private $path;
    // Collection of config data
    private $meta;
    // Topic object
    private $_topic;

    const NOT_FOUND = 'notfound.jpg';
    const ASSET_URL_FORMAT = 'media/%YEAR%/%SLUG%/';
    const URL_FORMAT = 'articles/%YEAR%/%SLUG%.html';

    public function __construct($data, $path, $urlFormat = null)
    {
        foreach ($data as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }

        $this->convertHtml($this->title);
        $this->convertHtml($this->snippet);

        $this->path = $path;
        $this->meta = $data;
        $this->urlFormat = $urlFormat ?: self::URL_FORMAT;

        // Uses path and urlFormat
        $this->url = $this->getUrl();
    }

    public function dateString($format = 'F jS, Y')
    {
        return $this->date
            ? date($format, strtotime($this->date))
            : 'N/A';
    }

    public function setTopic(Topic $topic)
    {
        $this->_topic = $topic;
    }

    public function getTopic()
    {
        return $this->_topic;
    }

    public function render()
    {
        // Set up helper functions for processing config
        $data = [
            'a' => function ($key) {
                echo $this->getAssetUrl(get($this->assets, $key, self::NOT_FOUND));
            },
            'd' => function ($key) {
                echo get($this->data, $key, '');
            },
            'wl' => function ($key) {
                echo get($this->weblinks, $key, '#notfound' );
            }
        ];

        $data['article'] = $this;
        $data['content'] = render("{$this->path}/article.phtml", $data, false);

        return render(TPL_ARTICLE, $data);
    }

    private function convertHtml(&$text)
    {
        $this->markdown($text, '_', 'em', true);
        $this->markdown($text, '*', 'strong', true);
        $text = str_replace('{}', '/', $text);
    }

    private function markdown(&$text, $code, $tag, $open = true)
    {
        if (strpos($text, $code)) {
            $insertTag = $open ? '<'.$tag.'>' : '<{}'.$tag.'>';
            $reg = '['. preg_quote($code) .']';
            $text = preg_replace($reg, $insertTag, $text, 1);

            $this->markdown($text, $code, $tag, !$open);
        }
    }

    /**
     * Default format is `/articles/YYYY/slug.html`
     */
    public function getUrl()
    {
        return $this->makeUrl($this->urlFormat);
    }

    public function getAssetUrl($filename)
    {
        return $this->makeUrl(self::ASSET_URL_FORMAT . $filename);
    }

    private function makeUrl($format)
    {
        // Available parts are the year, the month, and the slug
        $time = strtotime($this->date);
        $year = date("Y", $time);
        $month = date("m", $time);
        $pathParts = explode("/", $this->path);
        $slug = array_pop($pathParts);

        $url = str_replace('%YEAR%', $year, $format );
        $url = str_replace('%MONTH%', $month, $url);
        $url = str_replace('%SLUG%', $slug, $url);

        return $url;
    }
}