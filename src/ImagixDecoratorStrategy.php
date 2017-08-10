<?php

namespace Greg\Imagix;

interface ImagixDecoratorStrategy
{
    public function output($url): string;

    public function input($url): string;
}
