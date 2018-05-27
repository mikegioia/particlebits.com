<?php

namespace Legacy;

class Topic
{
    public $url;
    public $slug;
    public $color;
    public $count;
    public $label;

    private $articles; // TBD
    private $rgbValues;

    public function __construct($data, $rgbValues, $count)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->count = $count;
        $this->rgbValues = $rgbValues;
        $this->url = "topics/{$this->slug}";
    }

    public function rgb()
    {
        return implode(', ', array_slice($this->rgbValues, 0, 3));
    }

    public function rgba()
    {
        return implode(', ', $this->rgbValues);
    }

    public function isActive()
    {
        return $this->count > 0;
    }
}