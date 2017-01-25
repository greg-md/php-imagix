# Greg PHP Static Image

[![StyleCI](https://styleci.io/repos/70835580/shield?style=flat)](https://styleci.io/repos/70835580)
[![Build Status](https://travis-ci.org/greg-md/php-static-image.svg)](https://travis-ci.org/greg-md/php-static-image)
[![Total Downloads](https://poser.pugx.org/greg-md/php-static-image/d/total.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![Latest Stable Version](https://poser.pugx.org/greg-md/php-static-image/v/stable.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![Latest Unstable Version](https://poser.pugx.org/greg-md/php-static-image/v/unstable.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![License](https://poser.pugx.org/greg-md/php-static-image/license.svg)](https://packagist.org/packages/greg-md/php-static-image)

Save images as static in real-time in different formats using [Intervention Image](http://image.intervention.io/).

You don't care anymore about generating new images from their sources when your app UI was changed. Only thing you should do is to add new formats or change existent.

# Table of contents:

* [Requirements](#requirements)
* [How It Works](#how-it-works)
* [Magic methods](#magic-methods)
* [Methods](#methods)

# Requirements

* PHP Version `^5.6 || ^7.0`

# How It Works

**First of all**, you have to initialize the manager.

Optionally you can create an URL decorator for it.
It helps to rewrite image URLs to/from HTTP Server(Apache/Nginx).

```php
class StaticDecorator implements ImageDecoratorStrategy
{
    // In Nginx we will use /static path for static content.
    private $nginxUri = '/static';
    
    public function output($url)
    {
        return $this->nginxUri . $url;
    }

    public function input($url)
    {
        return \Greg\Support\Str::shift($url, $this->nginxUri);
    }
}

$sourcePath = __DIR__ . '/img';

$destinationPath = __DIR__ . '/static';

$manager = new \Greg\StaticImage\StaticImageManager(
    new Intervention\Image\ImageManager(), $sourcePath, $destinationPath, new StaticDecorator()
);
```

**Next**, create image formats.

```php
$manager->format('square', function (\Intervention\Image\Image $image) {
    $image->resize(600, 600, function (\Intervention\Image\Constraint $constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });
});
```

**Now**, it's time to use it in your app.

It will generate an URL in format `<image_path>/<image_name>@<format>@<last_modified>.<image_extension>`.

```php
// result: /static/pictures/picture@square@129648839.jpg
$imageUrl = $manager->url('/pictures/picture.jpg', 'square');

echo '<img src="' . $imageUrl . '" width="600" height="600" alt="Picture" />';
```

To see the results, you have to config your `http server`.

**Nginx**

```nginxconfig
location ~* ^/static/ {
    # Determine if static file exists. If not, send to PHP to create it.
    if (!-f $document_root$uri) {
        rewrite .+ /image.php last;
    }

    expires max;
    add_header Pragma public;
    add_header Cache-Control "public";
    add_header Vary "Accept-Encoding";
}
```

In **image.php** you will dispatch new files that was not generated yet in `/static` path.

```php
$manager->send($_SERVER['REQUEST_URI']);
```

# Magic Methods

* [__construct](#__construct)

## __construct

Initialize the manager.

_Example:_

```php
class StaticDecorator implements ImageDecoratorStrategy
{
    // In Nginx we will use /static path for static content.
    private $nginxUri = '/static';
    
    public function output($url)
    {
        return $this->nginxUri . $url;
    }

    public function input($url)
    {
        return \Greg\Support\Str::shift($url, $this->nginxUri);
    }
}

$sourcePath = __DIR__ . '/img';

$destinationPath = __DIR__ . '/static';

$manager = new \Greg\StaticImage\StaticImageManager(
    new Intervention\Image\ImageManager(), $sourcePath, $destinationPath, new StaticDecorator()
);
```

# Methods

* [format](#format) - Register an image format;
* [url](#url) - Get formatted image URL;
* [source](#source) - Get source URL of a formatted image URL;
* [effective](#effective) - Get effective URL of a formatted image URL;
* [compile](#compile) - Compile a formatted image URL;
* [send](#send) - Send image of a formatted image URL;
* [unlink](#unlink) - Remove formatted versions of an image;
* [remove](#remove) - Remove formatted images.

## format

Register an image format.

```php
format(string $name, callable(\Intervention\Image\Image $image): void $callable): $this
```

`$name` - Format name;  
`$callable` - A callable to format an image.  
&nbsp;&nbsp;&nbsp;&nbsp;`$image` - The image.

_Example:_

```php
$manager->format('square', function (\Intervention\Image\Image $image) {
    $image->resize(600, 600, function (\Intervention\Image\Constraint $constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });
});
```

## url

Get formatted image URL.

```php
url(string $source, string $format): string
```

`$source` - Original image URL;  
`$format` - Format name.

_Example:_

```php
$manager->url('/pictures/picture.jpg', 'square'); // result: /pictures/picture@square@129648839.jpg
```

## source

Get source URL of a formatted image URL.

```php
source(string $destination): string
```

`$destination` - Formatted image URL.

_Example:_

```php
$manager->source('/pictures/picture@square@129648839.jpg'); // result: /pictures/picture.jpg
```

## effective

Get effective URL of a formatted image URL.

Every time a image changes, it's effective URL also changes.
So, if you have an old URL, you will get the new one.

> This is useful when you store somewhere formatted urls.

> By default, [`send`](#send) method will return a 301 redirect to the new URL.

```php
effective(string $destination): string
```

`$destination` - Formatted image URL.

_Example:_

```php
$manager->effective('/pictures/picture@square@129642346.jpg'); // result: /pictures/picture@square@129648839.jpg
```

## compile

Compile a formatted image URL. Will return the real path of the image.

```php
compile(string $destination): string
```

`$destination` - Formatted image URL.

_Example:_

```php
$manager->compile('/pictures/picture@square@129648839.jpg'); // result: /path/to/pictures/picture@square@129648839.jpg
```

## send

Send image of a formatted image URL.

```php
send(string $destination): $this
```

`$destination` - Formatted image URL.

_Example:_

```php
$manager->send('/pictures/picture@square@129648839.jpg');
```

## unlink

Remove formatted versions of an image.

```php
unlink(string $source, string $format = null, int $lifetime = 0): $this
```

`$source` - Formatted image URL;  
`$format` - Format name;  
`$lifetime` - If set, will remove only expired files.

_Example:_

```php
$manager->unlink('/pictures/picture.jpg');
```

## remove

Remove formatted images.

```php
remove(string $format = null, int $lifetime = 0): $this
```

`$format` - Format name;  
`$lifetime` - If set, will remove only expired files.

_Example:_

```php
$manager->remove(); // Will remove all formatted images.

$manager->remove('square'); // Will remove only square images.
```
