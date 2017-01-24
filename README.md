# Greg PHP Static Image

[![StyleCI](https://styleci.io/repos/70835580/shield?style=flat)](https://styleci.io/repos/70835580)
[![Build Status](https://travis-ci.org/greg-md/php-static-image.svg)](https://travis-ci.org/greg-md/php-static-image)
[![Total Downloads](https://poser.pugx.org/greg-md/php-static-image/d/total.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![Latest Stable Version](https://poser.pugx.org/greg-md/php-static-image/v/stable.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![Latest Unstable Version](https://poser.pugx.org/greg-md/php-static-image/v/unstable.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![License](https://poser.pugx.org/greg-md/php-static-image/license.svg)](https://packagist.org/packages/greg-md/php-static-image)

Cache images in real-time in different formats using [Intervention Image](http://image.intervention.io/).

# Requirements

* PHP Version `^5.6 || ^7.0`

# How It Works

**First of all**, you have to create a manager and an URL decorator for it.

The decorator helps you to rewrite image urls to/from HTTP Server(Apache/Nginx).

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

It will generate an url in format `<image_path>/<image_name>@<format>@<last_modified>.<image_extension>`.

```php
// return: /static/pictures/picture@square@129648839.jpg
$imageUrl = $manager->url('/pictures/picture.jpg', 'square');

echo '<img src="' . $imageUrl . '" width="600" height="600" alt="Picture" />';
```

To see the results, you have to config your http server.

**Nginx**

```nginxconfig
location ~* ^/static/ {
    if (!-f $document_root$uri) {
        rewrite ^/static/.+ /image.php last;
    }

    expires max;
    add_header Pragma public;
    add_header Cache-Control "public";
    add_header Vary "Accept-Encoding";
}
```

In **image.php** you will dispatch new files that was not generated yet in /static path.

```php
$manager->send($_SERVER['REQUEST_URI']);
```
