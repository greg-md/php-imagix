# Greg PHP Static Image

[![StyleCI](https://styleci.io/repos/70835580/shield?style=flat)](https://styleci.io/repos/70835580)
[![Build Status](https://travis-ci.org/greg-md/php-static-image.svg)](https://travis-ci.org/greg-md/php-static-image)
[![Total Downloads](https://poser.pugx.org/greg-md/php-static-image/d/total.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![Latest Stable Version](https://poser.pugx.org/greg-md/php-static-image/v/stable.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![Latest Unstable Version](https://poser.pugx.org/greg-md/php-static-image/v/unstable.svg)](https://packagist.org/packages/greg-md/php-static-image)
[![License](https://poser.pugx.org/greg-md/php-static-image/license.svg)](https://packagist.org/packages/greg-md/php-static-image)

# Nginx Configuration

```nginx
location ~* @.+\.(png|jpe?g|gif)$ {
    if (!-f $document_root$uri) {
        rewrite ^/static/.+ /image.php last;
    }

    expires max;
    add_header Pragma public;
    add_header Cache-Control "public";
    add_header Vary "Accept-Encoding";
}
```
