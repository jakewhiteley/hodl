<?php

namespace Hodl\Tests\Classes;

class CanHaveConstructorParams
{
    /**
     * @var string
     */
    private $foo;

    /**
     * CanHaveConstructorParams constructor.
     *
     * @param string $foo
     */
    public function __construct($foo = 'foo')
    {
        $this->foo = $foo;
    }

    /*
     * @return string
     */
    public function getFoo(): string
    {
        return $this->foo;
    }
}