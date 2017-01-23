<?php

namespace Greg\StaticImage;

use Greg\Support\Arr;
use Greg\Support\File;
use Greg\Support\Http\Response;
use Greg\Support\Image;
use Greg\Support\Obj;
use Greg\Support\Str;
use Intervention\Image\ImageManager;

class ImageCollector
{
    private $sourcePath = null;

    private $destinationPath = null;

    private $formats = [];

    private $manager = null;

    public function __construct(ImageManager $manager, $sourcePath, $destinationPath)
    {
        $this->manager = $manager;

        $this->setSourcePath($sourcePath);

        $this->setDestinationPath($destinationPath);

        return $this;
    }

    public function sourcePath()
    {
        return $this->sourcePath;
    }

    public function destinationPath()
    {
        return $this->destinationPath;
    }

    public function manager()
    {
        return $this->manager;
    }

    public function format($name, callable $callable)
    {
        $this->formats[$name] = $callable;

        return $this;
    }

    public function destination($source, $format)
    {
        $this->checkSource($source);

        $this->checkFormat($format);

        if (!$sourceFile = $this->realFile($this->sourcePath, $source)) {
            return $source;
        }

        $destinationName = pathinfo($source, PATHINFO_FILENAME) . '@' . $format . '@' . (int) filemtime($sourceFile);

        if ($destinationExtension = pathinfo($source, PATHINFO_EXTENSION)) {
            $destinationExtension = '.' . $destinationExtension;
        }

        return $this->baseDir($source) . '/' . $destinationName . $destinationExtension;
    }

    public function source($destination)
    {
        $this->checkDestination($destination);

        $destinationName = pathinfo($destination, PATHINFO_FILENAME);

        if (strpos($destinationName, '@') === false) {
            throw new \Exception('Wrong destination file format.');
        }

        list($sourceName, $format) = explode('@', $destinationName);

        $this->checkFormat($format);

        if ($sourceExtension = pathinfo($destination, PATHINFO_EXTENSION)) {
            $sourceExtension = '.' . $sourceExtension;
        }

        $source = $this->baseDir($destination) . '/' . $sourceName . $sourceExtension;

        return [$source, $format];
    }

    public function currentDestination($destination)
    {
        list($source, $formatName) = $this->source($destination);

        return $this->destination($source, $formatName);
    }

    public function image($destination)
    {
        $this->checkDestination($destination);

        $destinationFile = $this->imageFile($this->destinationPath, $destination);

        list($source, $formatName) = $this->source($destination);

        if (!$sourceFile = $this->imageFile($this->sourcePath, $source)) {
            $this->removeOldFiles($source, $formatName);

            throw new \Exception('Source file does not exists.');
        }

        if ($destinationFile) {
            return $destinationFile;
        }

        $this->removeOldFiles($source, $formatName);

        $currentDestination = $this->destination($source, $formatName);

        $currentDestinationFile = $this->destinationPath . $currentDestination;

        $this->generate($sourceFile, $currentDestinationFile, $formatName);

        return $currentDestinationFile;
    }

    public function send($destination)
    {
        if ($destination !== ($currentDestination = $this->currentDestination($destination))) {
            Response::sendLocation($currentDestination, 301);

            return $this;
        }

        $destinationFile = $this->image($destination);

        if (!Response::unmodified(filemtime($destinationFile))) {
            Response::sendImage($destinationFile);
        }

        return $this;
    }

    protected function generate($source, $destination, $format)
    {
        File::makeDir($destination);

        $image = $this->manager->make($source);

        Obj::call($this->formats[$format], $image);

        $image->save($destination);
    }

    protected function checkSource($source)
    {
        if (!pathinfo($source, PATHINFO_FILENAME)) {
            throw new \Exception('Source is not a file.');
        }

        return $this;
    }

    protected function checkDestination($destination)
    {
        if (!pathinfo($destination, PATHINFO_FILENAME)) {
            throw new \Exception('Destination is not a file.');
        }

        return $this;
    }

    protected function baseDir($path)
    {
        $path = pathinfo($path, PATHINFO_DIRNAME);

        return $path !== '/' ? $path : '';
    }

    protected function realFile($path, $name)
    {
        return ($file = realpath($path . $name) and Str::startsWith($file, $path)) ? $file : null;
    }

    protected function setSourcePath($path)
    {
        $this->sourcePath = (string) $path;

        return $this;
    }

    protected function setDestinationPath($path)
    {
        $this->destinationPath = (string) $path;

        return $this;
    }

    protected function hasFormat($format)
    {
        return Arr::has($this->formats, $format);
    }

    protected function checkFormat($format)
    {
        if (!$this->hasFormat($format)) {
            throw new \Exception('Image format `' . $format . '` was not defined.');
        }

        return $this;
    }

    protected function imageFile($path, $name)
    {
        if ($file = realpath($path . $name)) {
            if (!Str::startsWith($file, $path)) {
                throw new \Exception('You are not allowed in this path.');
            }

            Image::check($file);
        }

        return $file;
    }

    protected function removeOldFiles($source, $format)
    {
        $sourceName = pathinfo($source, PATHINFO_FILENAME);

        $destinationNames = $sourceName . '@' . $format . '@*';

        $destinationPath = $this->baseDir($source);

        if ($destinationExtension = pathinfo($source, PATHINFO_EXTENSION)) {
            $destinationExtension = '.' . $destinationExtension;
        }

        $destinations = $destinationPath . '/' . $destinationNames . $destinationExtension;

        array_map('unlink', glob($this->destinationPath . $destinations));

        return $this;
    }
}
