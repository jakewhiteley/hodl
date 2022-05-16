<?php

namespace Hodl;

use ArrayAccess;
use Closure;

/**
 * ArrayAccess implementation for Hodl\Container
 */
class ContainerArrayAccess implements ArrayAccess
{
    /**
     * Sets the value at specified offset.
     *
     * @param  string  $offset The key to set
     * @param  Closure $value  The value to set
     */
    public function offsetSet($offset, $value): void
    {
        $this->add($offset, $value);
    }

    /**
     * Checks if a given offset exists.
     *
     * @param  string  $offset The key to set
     * @return  bool
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Gets the value at specified offset.
     *
     * @param  string  $offset The key to get
     * @return  object $value  The object instance or closure if a factory class
     */
    public function offsetGet($offset): object
    {
        return $this->get($offset);
    }

    /**
     * Unsets the value at specified offset.
     *
     * @param  string  $offset The key to set
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }
}
