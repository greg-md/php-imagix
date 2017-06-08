<?php

namespace Greg\StaticImage;

use Greg\Support\Accessor\AccessorTrait;
use Greg\Support\Dir;
use Greg\Support\File;
use Greg\Support\Http\Response;
use Greg\Support\Image;
use Greg\Support\Str;
use Intervention\Image\ImageManager;

class StaticImageManager
{
    use AccessorTrait;

    private $sourcePath = null;

    private $destinationPath = null;

    /**
     * @var ImageManager
     */
    private $manager = null;

    /**
     * @var ImageDecoratorStrategy|null
     */
    private $decorator = null;

    public function __construct(ImageManager $manager, $sourcePath, $destinationPath, ImageDecoratorStrategy $decorator = null)
    {
        $this->setManager($manager);

        $this->setSourcePath($sourcePath);

        $this->setDestinationPath($destinationPath);

        $this->decorator = $decorator;

        return $this;
    }

    protected function setSourcePath($path)
    {
        $this->sourcePath = (string) $path;

        return $this;
    }

    protected function getSourcePath()
    {
        return $this->sourcePath;
    }

    protected function setDestinationPath($path)
    {
        $this->destinationPath = (string) $path;

        return $this;
    }

    protected function getDestinationPath()
    {
        return $this->destinationPath;
    }

    protected function setManager(ImageManager $manager)
    {
        $this->manager = $manager;

        return $this;
    }

    protected function getManager()
    {
        return $this->manager;
    }

    protected function setDecorator(ImageDecoratorStrategy $decorator)
    {
        $this->decorator = $decorator;

        return $this;
    }

    protected function getDecorator()
    {
        return $this->decorator;
    }

    public function format($name, callable $callable)
    {
        $this->setToAccessor($name, $callable);

        return $this;
    }

    public function url($source, $format)
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

        $url = $this->baseDir($source) . '/' . $destinationName . $destinationExtension;

        if ($this->decorator) {
            $this->decorator->output($url);
        }

        return $url;
    }

    public function source($destination)
    {
        $this->checkDestination($destination);

        if ($this->decorator) {
            $destination = $this->decorator->input($destination);
        }

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

    public function effective($destination)
    {
        list($source, $formatName) = $this->source($destination);

        return $this->url($source, $formatName);
    }

    public function compile($destination)
    {
        $this->checkDestination($destination);

        if ($this->decorator) {
            $destination = $this->decorator->input($destination);
        }

        $destinationFile = $this->imageFile($this->destinationPath, $destination);

        list($source, $formatName) = $this->source($destination);

        if (!$sourceFile = $this->imageFile($this->sourcePath, $source)) {
            $this->unlink($source, $formatName);

            throw new \Exception('Source file does not exists.');
        }

        if ($destinationFile) {
            return $destinationFile;
        }

        $this->unlink($source, $formatName);

        $currentDestination = $this->url($source, $formatName);

        $currentDestinationFile = $this->destinationPath . $currentDestination;

        $this->generate($sourceFile, $currentDestinationFile, $formatName);

        return $currentDestinationFile;
    }

    public function send($destination)
    {
        if ($destination !== ($currentDestination = $this->effective($destination))) {
            Response::sendLocation($currentDestination, 301);

            return $this;
        }

        $destinationFile = $this->compile($destination);

        if (!Response::unmodified(filemtime($destinationFile))) {
            Response::sendImage($destinationFile);
        }

        return $this;
    }

    public function unlink($source, $format = null, $lifetime = 0)
    {
        $sourceName = pathinfo($source, PATHINFO_FILENAME);

        $destinationNames = $sourceName . '@' . ($format ?: '*') . '@*';

        $destinationPath = $this->baseDir($source);

        if ($destinationExtension = pathinfo($source, PATHINFO_EXTENSION)) {
            $destinationExtension = '.' . $destinationExtension;
        }

        $destinations = $destinationPath . '/' . $destinationNames . $destinationExtension;

        $this->unlinkExpired($destinations, $lifetime);

        return $this;
    }

    public function remove($format = null, $lifetime = 0)
    {
        if ($format) {
            $this->unlinkExpired('/*@' . $format . '@*.*', $lifetime);
        } else {
            if ($lifetime) {
                $this->unlinkExpired('/*@*@*.*', $lifetime);
            } else {
                Dir::unlink($this->destinationPath);
            }
        }

        return $this;
    }

    protected function unlinkExpired($search, $lifetime = 0)
    {
        $files = glob($this->destinationPath . $search);

        if ($lifetime > 0) {
            $time = time();

            foreach ($files as $file) {
                if (filemtime($file) < $time - $lifetime) {
                    unlink($file);
                }
            }
        } else {
            array_map('unlink', $files);
        }

        return $this;
    }

    protected function hasFormat($name)
    {
        return $this->inAccessor($name);
    }

    protected function getFormat($name = null)
    {
        if (func_num_args()) {
            $this->checkFormat($name);

            return $this->getFromAccessor($name);
        }

        return $this->getAccessor();
    }

    protected function generate($source, $destination, $format)
    {
        File::makeDir($destination);

        $image = $this->manager->make($source);

        call_user_func_array($this->getFormat($format), [$image]);

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
}
