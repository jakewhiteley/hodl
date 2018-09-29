<?php

namespace Hodl\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class ConcreteClassNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
