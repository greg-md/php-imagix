<?php

namespace Greg\StaticImage;

use Greg\Support\Arr;
use Greg\Support\File;
use Greg\Support\Http\Response;
use Greg\Support\Image;
use Greg\Support\Obj;
use Greg\Support\Str;
use Greg\Support\Url;
use Intervention\Image\ImageManager;

class ImageCollector
{
    protected $sourcePath = null;

    protected $cachePath = null;

    protected $publicPath = '/';

    protected $formats = [];

    protected $manager = null;

    public function __construct(ImageManager $manager, $sourcePath, $cachePath, $publicPath = '/')
    {
        $this->manager = $manager;

        $this->setSourcePath($sourcePath);

        $this->setCachePath($cachePath);

        $this->setPublicPath($publicPath);

        return $this;
    }

    public function url($src, $format)
    {
        if (Url::isFull($src)) {
            return $src;
        }

        $cacheSrc = $this->fetchCacheSrc($src, $format);

        return $cacheSrc ? Url::base($this->getPublicPath() . $cacheSrc) : $src;
    }

    public function getOrigin($src)
    {
        if (($path = $this->getPublicPath()) && !Str::startsWith($src, $path)) {
            return $src;
        }

        $cacheSrcInfo = pathinfo($src);

        list($cacheFileName) = explode('@', $cacheSrcInfo['filename']);

        $src = $cacheSrcInfo['dirname'] . '/' . $cacheFileName . '.' . $cacheSrcInfo['extension'];

        return Str::shift($src, $this->getPublicPath());
    }

    protected function fetchCacheSrc($src, $format)
    {
        $file = $this->getSourcePath() . $src;

        if (!file_exists($file)) {
            return null;
        }

        $pathInfo = pathinfo($src);

        $modified = (int)filemtime($file);

        $cacheFileName = $pathInfo['filename'] . '@' . $format . '@' . $modified;

        $path = $pathInfo['dirname'];

        if ($path == '/') {
            $path = null;
        }

        $cacheSrc = $path . '/' . $cacheFileName . '.' . $pathInfo['extension'];

        return $cacheSrc;
    }

    protected function checkImage($root, $src)
    {
        if ($file = realpath($root . $src)) {
            if (!Str::startsWith($file, $root)) {
                throw new \Exception('You are not allowed in this path.');
            }

            if (!Image::isFile($file)) {
                throw new \Exception('File is not an image.');
            }
        }

        return $file;
    }

    public function run($cacheSrc)
    {
        if (!$cacheSrc) {
            throw new \Exception('Undefined cache source.');
        }

        if ($cacheFile = $this->checkImage($this->getCachePath(), $cacheSrc)) {
            Response::isModifiedSince(filemtime($cacheFile));

            Response::sendImageFile($cacheFile);

            return $this;
        }

        $cacheSrcInfo = pathinfo($cacheSrc);

        list($cacheFileName, $formatName) = explode('@', $cacheSrcInfo['filename']);

        $format = $this->getFormat($formatName);

        $src = $cacheSrcInfo['dirname'] . '/' . $cacheFileName . '.' . $cacheSrcInfo['extension'];

        if (!$file = $this->checkImage($this->getSourcePath(), $src)) {
            throw new \Exception('Original source not exists.');
        }

        $newCacheSrc = $this->fetchCacheSrc($src, $formatName);

        if ($cacheSrc !== $newCacheSrc) {
            Response::sendLocation($this->getPublicPath() . $newCacheSrc, 301);

            return $this;
        }

        $newCacheFile = $this->getCachePath() . $newCacheSrc;

        File::fixFileDirRecursive($newCacheFile);

        $this->removeOldFiles($src, $formatName);

        $image = $this->getManager()->make($file);

        Obj::callCallableWith($format, $image);

        $image->save($newCacheFile);

        echo $image->response();

        return $this;
    }

    protected function removeOldFiles($src, $format)
    {
        $info = pathinfo($src);

        $fileNames = $info['filename'] . '@' . $format . '@*';

        $oldCacheFiles = $this->getCachePath() . $info['dirname'] . '/' . $fileNames . '.' . $info['extension'];

        array_map('unlink', glob($oldCacheFiles));

        return $this;
    }

    public function hasFormat($format)
    {
        return Arr::has($this->formats, $format);
    }

    public function getFormat($format)
    {
        if (!$this->hasFormat($format)) {
            throw new \Exception('Image cache format not found.');
        }

        return $this->formats[$format];
    }

    public function addFormat($name, callable $callable)
    {
        $this->formats[$name] = $callable;

        return $this;
    }

    public function setSourcePath($path)
    {
        $this->sourcePath = (string) $path;

        return $this;
    }

    public function getSourcePath()
    {
        return $this->sourcePath;
    }

    public function setCachePath($path)
    {
        $this->cachePath = (string) $path;

        return $this;
    }

    public function getCachePath()
    {
        return $this->cachePath;
    }

    public function setPublicPath($path)
    {
        $this->publicPath = (string) $path;

        return $this;
    }

    public function getPublicPath()
    {
        return $this->publicPath;
    }

    public function setManager(ImageManager $manager)
    {
        $this->manager = $manager;

        return $this;
    }

    public function getManager()
    {
        return $this->manager;
    }
}