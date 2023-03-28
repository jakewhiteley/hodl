<?php

namespace Hodl\Tests\Classes;

class NeedsServiceAndConstructorParams
{
    /**
     * @var string
     */
    private $foo;

    public $resolver = null;

    /**
     * CanHaveConstructorParams constructor.
     *
     * @param \Hodl\Tests\Classes\Resolver $resolver
     * @param string                       $foo
     */
    public function __construct(Resolver $resolver, $foo = 'foo')
    {
        $this->foo = $foo;
        $this->resolver = $resolver;
    }

    /*
     * @return string
     */
    public function getFoo(): string
    {
        return $this->foo;
    }
}