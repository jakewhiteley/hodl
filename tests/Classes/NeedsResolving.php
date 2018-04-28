<?php

namespace Hodl\Tests\Classes;

class NeedsResolving
{
    public $resolver = null;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }
}
