<?php

namespace Hodl\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class KeyExistsException extends ContainerException implements NotFoundExceptionInterface
{
}
