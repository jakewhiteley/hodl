<?php

namespace Hodl\Tests\Classes;

class NeedsResolving
{
    public $resolver = null;

    public $methodResolver = null;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function hasNoStaticParams(Resolver $resolver)
    {
        $this->methodResolver = $resolver;

        return $this->methodResolver;
    }
}
