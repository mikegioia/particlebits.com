<?php

namespace Legacy;

class Topic
{
    public $url;
    public $slug;
    public $sort;
    public $color;
    public $count;
    public $label;
    public $articles;

    private $env;
    private $rgbValues;

    public function __construct($data, $rgbValues, $env)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->env = $env;
        $this->url = $this->getUrl();
        $this->rgbValues = $rgbValues;
    }

    public function rgb($darkness = 1)
    {
        $rgb = array_slice($this->rgbValues, 0, 3);
        $rgb = array_map(function ($i) use ($darkness) {
            return round($i/$darkness);
        }, $rgb);

        return implode(', ', $rgb);
    }

    public function rgba($lightness = 1)
    {
        $rgb = $this->rgbValues;
        $rgb[3] = round($rgb[3]/$lightness, 2);

        return implode(', ', $rgb);
    }

    public function isActive()
    {
        return $this->count > 0;
    }

    public function getUrl()
    {
        $filename = $this->env === BUILD ? '/index.html' : '';

        return "topics/{$this->slug}{$filename}";
    }
}