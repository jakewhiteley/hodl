<?php

namespace Hodl\Tests\Classes;

class PrimitivesTest
{
    public $foo = null;

    public function __construct(string $string = 'not_set')
    {
        $this->foo = $string;
    }
}
