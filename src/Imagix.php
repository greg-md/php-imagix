<?php

namespace Greg\Imagix;

use Greg\Support\Dir;
use Greg\Support\File;
use Greg\Support\Http\Response;
use Greg\Support\Image;
use Greg\Support\Str;
use Intervention\Image\ImageManager;

class Imagix
{
    private $storage = [];

    private $manager;

    private $sourcePath;

    private $destinationPath;

    private $decorator;

    public function __construct(ImageManager $manager, string $sourcePath, string $destinationPath, ImagixDecoratorStrategy $decorator = null)
    {
        $this->manager = $manager;

        $this->sourcePath = realpath($sourcePath) ?: null;

        $this->destinationPath = realpath($destinationPath) ?: null;

        $this->decorator = $decorator;

        return $this;
    }

    public function manager(): ImageManager
    {
        return $this->manager;
    }

    public function sourcePath(): ?string
    {
        return $this->sourcePath;
    }

    public function destinationPath(): ?string
    {
        return $this->destinationPath;
    }

    public function decorator(): ?ImagixDecoratorStrategy
    {
        return $this->decorator;
    }

    public function format(string $name, callable $callable)
    {
        $this->validateFormat($name);

        $this->storage[$name] = $callable;

        return $this;
    }

    public function url(string $source, string $format): string
    {
        $destination = $this->prepareDestination($source, $format);

        if ($this->decorator) {
            $destination = $this->decorator->output($destination);
        }

        return $destination;
    }

    public function source(string $destination): array
    {
        $this->checkDestination($destination);

        if ($this->decorator) {
            $destination = $this->decorator->input($destination);
        }

        return $this->sourceFromDestination($destination);
    }

    public function effective(string $destination): string
    {
        list($source, $formatName) = $this->source($destination);

        return $this->url($source, $formatName);
    }

    public function compile(string $destination): string
    {
        $this->checkDestination($destination);

        if ($this->decorator) {
            $destination = $this->decorator->input($destination);
        }

        $destinationFile = $this->imageFile($this->destinationPath, $destination);

        list($source, $formatName) = $this->sourceFromDestination($destination);

        if (!$sourceFile = $this->imageFile($this->sourcePath, $source)) {
            $this->unlink($source, $formatName);

            throw new \Exception('Source file does not exists.');
        }

        if ($destinationFile) {
            return $destinationFile;
        }

        $this->unlink($source, $formatName);

        $currentDestination = $this->prepareDestination($source, $formatName);

        $currentDestinationFile = $this->destinationPath . $currentDestination;

        $this->generate($sourceFile, $currentDestinationFile, $formatName);

        return $currentDestinationFile;
    }

    public function send(string $destination)
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

    public function unlink(string $source, string $format = null, int $lifetime = 0)
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

    public function remove(string $format = null, int $lifetime = 0)
    {
        if ($format) {
            $this->validateFormat($format);

            $this->unlinkExpired('/*@' . $format . '@*.*', $lifetime);
        } else {
            if ($lifetime) {
                $this->unlinkExpired('/*@*@*.*', $lifetime);
            } else {
                Dir::unlink($this->destinationPath . '/*');
            }
        }

        return $this;
    }

    private function unlinkExpired(string $search, int $lifetime = 0)
    {
        $files = glob($this->destinationPath . $search);

        if ($lifetime > 0) {
            $time = time();

            foreach ($files as $file) {
                if (filemtime($file) <= $time - $lifetime) {
                    unlink($file);
                }
            }
        } else {
            array_map('unlink', $files);
        }

        return $this;
    }

    private function hasFormat(string $name): bool
    {
        return array_key_exists($name, $this->storage);
    }

    private function getFormat(string $name): callable
    {
        $this->checkFormat($name);

        return $this->storage[$name];
    }

    private function generate(string $source, string $destination, string $format)
    {
        File::makeDir($destination);

        $image = $this->manager->make($source);

        call_user_func_array($this->getFormat($format), [$image]);

        $image->save($destination);

        return $this;
    }

    private function checkSource(string $source)
    {
        if (!pathinfo($source, PATHINFO_BASENAME)) {
            throw new \Exception('Source is not a file.');
        }

        return $this;
    }

    private function checkDestination(string $destination)
    {
        if (!pathinfo($destination, PATHINFO_BASENAME)) {
            throw new \Exception('Destination is not a file.');
        }

        return $this;
    }

    private function validateFormat(string $format)
    {
        if (pathinfo($format, PATHINFO_BASENAME) !== $format) {
            throw new \Exception('Format name contains forbidden characters.');
        }

        return $this;
    }

    private function baseDir(string $path): string
    {
        $path = pathinfo($path, PATHINFO_DIRNAME);

        return $path !== '/' ? $path : '';
    }

    private function realFile(string $path, string $name): ?string
    {
        $file = realpath($path . $name);

        return ($file and Str::startsWith($file, $path)) ? $file : null;
    }

    private function checkFormat(string $format)
    {
        if (!$this->hasFormat($format)) {
            throw new \Exception('Image format `' . $format . '` was not defined.');
        }

        return $this;
    }

    private function imageFile(string $path, string $name): string
    {
        if ($file = realpath($path . $name)) {
            $this->checkPathIsAllowed($path, $file);

            Image::check($file);
        }

        return $file;
    }

    private function checkPathIsAllowed(string $path, string $file)
    {
        if (!Str::startsWith($file, $path)) {
            throw new \Exception('You are not allowed in this path.');
        }

        return $this;
    }

    private function sourceFromDestination(string $destination): array
    {
        $destinationName = pathinfo($destination, PATHINFO_FILENAME);

        if (strpos($destinationName, '@') === false) {
            throw new \Exception('Wrong destination file format.');
        }

        list($sourceName, $format) = explode('@', $destinationName);

        if ($sourceExtension = pathinfo($destination, PATHINFO_EXTENSION)) {
            $sourceExtension = '.' . $sourceExtension;
        }

        $source = $this->baseDir($destination) . '/' . $sourceName . $sourceExtension;

        return [$source, $format];
    }

    private function prepareDestination(string $source, string $format): string
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
}
