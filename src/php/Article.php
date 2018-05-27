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
    public $weblinks = [];
    public $featured = false;

    // Path to article content file
    private $path;
    // Topic object
    private $_topic;

    public function __construct($data, $path)
    {
        foreach ($data as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }

        $this->convertHtml($this->title);
        $this->convertHtml($this->snippet);

        $this->path = "$path/article.phtml";
        $this->url = implode("/", array_slice(explode("/", $path), -2));
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

    public function render(Filesystem $build)
    {
        //
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
}