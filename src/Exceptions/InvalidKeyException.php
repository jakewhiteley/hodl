<?php

namespace Hodl\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class InvalidKeyException extends ContainerException implements NotFoundExceptionInterface
{
}
