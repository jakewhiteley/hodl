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

    public function hasNoStaticParams(Resolver $resolver)
    {
        return $resolver;
    }

    public static function isStatic(Resolver $resolver)
    {
        return $resolver;
    }

    public static function staticHasNoParams()
    {
        return true;
    }

    public function hasNoParams()
    {
        return true;
    }

    public function hasParams(Resolver $resolver, $param = null)
    {
        return $param;
    }
}
