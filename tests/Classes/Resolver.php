<?php

namespace Hodl\Tests\Classes;

class Resolver
{
    public $var = 'foobar';

    public $nested = null;

    public function __construct(Nested\Resolver $resolver)
    {
        $this->nested = $resolver;
    }
}
