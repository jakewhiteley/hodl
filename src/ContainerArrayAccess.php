<?php

namespace Hodl;

use ArrayAccess;

/**
 * ArrayAccess implementaion for Hodl\Container
 */
class ContainerArrayAccess implements ArrayAccess
{
    /**
     * Sets the value at specified offset.
     *
     * @param  string  $offset The key to set
     * @param  \closure $value  The value to set
     */
    public function offsetSet($offset, $value)
    {
        $this->add($offset, $value);
    }

    /**
     * Checks if a given offset exists.
     *
     * @param  string  $offset The key to set
     * @return  bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Gets the value at specified offset.
     *
     * @param  string  $offset The key to get
     * @return  object|closure  $value  The object instance or closure if a factory class
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Unsets the value at specified offset.
     *
     * @param  string  $offset The key to set
     * @return  bool
     */
    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }
}
