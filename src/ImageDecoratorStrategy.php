<?php

namespace Greg\StaticImage;

interface ImageDecoratorStrategy
{
    public function output($url);

    public function input($url);
}
