<?php

namespace Hodl\Tests\Classes;

class DummyClass
{
    public $foo = null;

    public $bar = null;

    public function __construct($string = 'not_set')
    {
        $this->foo = $string;
        $this->bar = random_bytes(5);
    }
}
