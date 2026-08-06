<?php

namespace Legacy;

/**
 * Minimal local filesystem wrapper. All paths are relative
 * to the root directory given in the constructor.
 */
class Filesystem
{
    private $root;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/');
    }

    public function has($path)
    {
        return file_exists($this->fullPath($path));
    }

    public function read($path)
    {
        return file_get_contents($this->fullPath($path));
    }

    /**
     * Write a file, implicitly creating parent directories.
     */
    public function put($path, $contents)
    {
        $fullPath = $this->fullPath($path);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, $contents);
    }

    public function createDir($path)
    {
        if (! is_dir($this->fullPath($path))) {
            mkdir($this->fullPath($path), 0755, true);
        }
    }

    /**
     * List a directory, returning metadata arrays with the
     * keys 'path', 'basename', and 'type'. Dotfiles are
     * skipped and a missing directory returns no results.
     */
    public function listContents($path = '')
    {
        $items = [];
        $fullPath = $this->fullPath($path);

        if (! is_dir($fullPath)) {
            return $items;
        }

        foreach (scandir($fullPath) as $basename) {
            if ($basename[0] === '.') {
                continue;
            }

            $items[] = [
                'basename' => $basename,
                'path' => ltrim("$path/$basename", '/'),
                'type' => is_dir("$fullPath/$basename") ? TYPE_DIR : TYPE_FILE
            ];
        }

        return $items;
    }

    private function fullPath($path)
    {
        return $path === '' ? $this->root : "{$this->root}/{$path}";
    }
}
