<?php

namespace Hodl;

use Hodl\Exceptions\ConcreteClassNotFoundException;
use Hodl\Exceptions\ContainerException;
use Hodl\Exceptions\InvalidKeyException;
use Hodl\Exceptions\KeyExistsException;
use Hodl\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

/**
 * A simple Service container with automatic constructor resolution abilities.
 *
 * A Frankenstein based on Pimple and Laravel Container.
 */
class Container extends ContainerArrayAccess implements ContainerInterface
{
    /**
     * Holds the object storage class.
     */
    private ObjectStorage $storage;

    /**
     * Stores current resolution stack.
     */
    private array $stack = [];

    /**
     * Boot up.
     */
    public function __construct()
    {
        $this->storage = new ObjectStorage();
    }

    /**
     * Add a class.
     *
     * Classes added via this method will return as a new instance when retrieved.
     *
     * @param string $key The key to store the object under
     * @param callable $closure A closure which returns a new instance of the desired object
     *                          A reference to this DIContainer is passed as a param to the closure
     * @throws InvalidKeyException
     * @throws KeyExistsException
     */
    public function add(string $key, callable $closure): void
    {
        $this->storage->factory($key, $closure);
    }

    /**
     * Add a singleton class.
     *
     * This class is initialized when first retrieved via get(), and is persistent unless explicitly destroyed.
     *
     * @param string $key The key to store the object under.
     * @param callable $closure A closure which returns a new instance of the desired object
     *                          A reference to this DIContainer is passed as a param to the closure
     * @throws InvalidKeyException
     * @throws KeyExistsException
     */
    public function addSingleton(string $key, callable $closure): void
    {
        $this->storage->object($key, $closure);
    }

    /**
     * Add a specific object instance to the container.
     *
     * @param object|string $key    The key to add the instance as. Can be omitted.
     *                              If an object is passed and the key is omitted, the namespaced class name
     *                              will be used instead.
     * @param object|null   $object $object The object instance to add.
     *
     * @throws ContainerException If no object was supplied.
     */
    public function addInstance(object|string $key, ?object $object = null): void
    {
        if (is_object($key)) {
            $this->storage->instance(get_class($key), $key);
        } elseif (is_object($object)) {
            $this->storage->instance($key, $object);
        } else {
            throw new ContainerException('An object instance must be passed');
        }
    }

    /**
     * Bind a given service to an alias.
     *
     * @param string $key   The service key to attach the alias to.
     * @param string $alias The alias to attach.
     */
    public function alias(string $key, string $alias): void
    {
        $this->storage->addAlias($key, $alias);
    }

    /**
     * Bind a given concrete class to an interface.
     *
     * Alias for alias().
     *
     * @param string $key       The concrete fully qualified class name to bind.
     * @param string $interface The interface fully qualified name to bind.
     */
    public function bind(string $key, string $interface): void
    {
        $this->storage->addAlias($key, $interface);
    }

    /**
     * Check if a given key exists within this container, either as an object or a factory.
     *
     * @param string $id The key to check for.
     * @return boolean If the key exists.
     */
    public function has(string $id): bool
    {
        return $this->storage->hasObject($id) || $this->storage->hasFactory($id);
    }

    /**
     * Retrieves an object for a given key.
     *
     * @param string $id   The key to lookup.
     * @return object|bool The requested object.
     *
     * @throws Exceptions\NotFoundException  If the $key was not present.
     * @throws Exceptions\ContainerException If the $key is not a valid string.
     */
    public function get(string $id)
    {
        if ($this->storage->hasStored($id)) {
            return $this->storage->getStored($id);
        }

        // key exists but hasn't been initialized yet
        if ($this->storage->hasObject($id)) {
            $this->storage->store($id, $this->storage->getDefinition($id)($this));
            return $this->storage->getStored($id);
        }

        if ($this->storage->hasFactory($id)) {
            $definition = $this->storage->getFactory($id);

            return $definition($this);
        }

        // the key was not found
        throw new NotFoundException("The key [$id] could not be found");
    }

    /**
     * Retrieves an object for a given key while providing arguments.
     *
     * @param string $id   The key to lookup.
     * @param array  $args The key to lookup.
     * @return object|bool The requested object.
     *
     * @throws Exceptions\NotFoundException  If the $key was not present.
     * @throws Exceptions\ContainerException If the $key is not a valid string.
     */
    public function getWith(string $id, ...$args): object|bool
    {
        // key exists but hasn't been initialized yet
        if ($this->storage->hasObject($id)) {
            $this->storage->store($id, $this->storage->getDefinition($id)($this, ...$args));
            return $this->storage->getStored($id);
        }

        if ($this->storage->hasFactory($id)) {
            $definition = $this->storage->getFactory($id);

            return $definition($this, ...$args);
        }

        // the key was not found
        throw new NotFoundException("The key [$id] could not be found");
    }

    /**
     * Unset a given key, and removes any objects associated with it from the container.
     *
     * @param string $key The key to remove. Can also be an alias or bound interface.
     * @return bool        Whether the key and associated object were removed.
     */
    public function remove(string $key): bool
    {
        return $this->storage->remove($key);
    }

    /**
     * Remove just an alias or binding, leaving the object and key intact.
     *
     * @param string $alias The alias to remove.
     * @return bool
     */
    public function removeAlias(string $alias): bool
    {
        return $this->storage->removeAlias($alias);
    }

    /**
     * Recursively resolve a given class name via DI.
     *
     * If a key exists within the container it will be injected, otherwise a new instance of the
     * dependency will be injected.
     *
     * For non-resolvable params such as strings etc, they can be set using $args.
     *
     * @param string $class The class to resolve.
     * @param array  $args  array of arguments to pass to resolved classes as [param name => value].
     * @return object        The resolved class
     *
     * @throws ContainerException If the class does not exist to resolve.
     * @throws ReflectionException
     */
    public function resolve(string $class, array $args = []): object
    {
        $this->stack[] = [];

        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ContainerException($e->getMessage());
        }

        // get the constructor method of the current class
        $constructor = $reflectionClass->getConstructor();

        // if there is no constructor, just return new instance
        if ($constructor === null) {
            $this->resetStack();
            return new $class();
        }

        // get constructor params
        $params = $constructor->getParameters();

        // If there is a constructor, but no params
        if (count($params) === 0) {
            $this->resetStack();
            return new $class();
        }

        $this->resolveParams($params, $args);

        $resolutions = end($this->stack);
        $this->resetStack();

        // return the resolved class
        return $reflectionClass->newInstanceArgs($resolutions);
    }

    /**
     * Recursively resolve a given class name via DI.
     *
     * If a key exists within the container it will be injected, otherwise a new instance of the
     * dependency will be injected.
     *
     * @param object|string $class  Class of which the method is a member.
     * @param string        $method Name of method to resolve.
     * @param array         $args   Array of arguments to pass to resolved classes as [param name => value].
     * @return mixed The return from the executed method
     *
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function resolveMethod(object|string $class, string $method, array $args = []): mixed
    {
        if (!is_callable([$class, $method])) {
            if (is_string($class)) {
                $error = $class . "::$method() does not exist or is not callable so could not be resolved";
            } else {
                $error = get_class($class) . "::$method() does not exist or is not callable so could not be resolved";
            }

            throw new ContainerException($error);
        }

        $reflectionMethod = new ReflectionMethod($class, $method);

        if ($reflectionMethod->isStatic()) {
            $classInstance = null;
        } elseif (is_string($class)) {
            $classInstance = new $class();
        } else {
            $classInstance = $class;
        }

        // get method params
        $params = $reflectionMethod->getParameters();

        // If there is a constructor, but no params
        if (count($params) === 0) {
            $this->resetStack();

            // as we are dealing with a static method
            if ($reflectionMethod->isStatic() === true) {
                return $reflectionMethod->invoke(null);
            }

            return $reflectionMethod->invoke($classInstance);
        }

        $this->resolveParams($params, $args);

        $resolutions = end($this->stack);
        $this->resetStack();

        // return the resolved class
        return $reflectionMethod->invokeArgs($classInstance, $resolutions);
    }

    /**
     * Resets the resolutions stack.
     */
    private function resetStack(): void
    {
        array_pop($this->stack);
    }

    /**
     * Adds a value the resolutions stack.
     *
     * @param mixed $value Value to add.
     */
    private function addToStack(mixed $value): void
    {
        $keys = array_keys($this->stack);
        $this->stack[end($keys)][] = $value;
    }

    /**
     * Searches for a class name in the container, and adds to resolutions if found.
     *
     * @param string $className Key to search for.
     * @return bool Whether the key was found.
     * @throws ContainerException This will never happen, but just to keep IDEs happy.
     */
    private function resolveFromContainer(string $className): bool
    {
        if ($this->has($className)) {
            $this->addToStack($this->get($className));
            return true;
        }
        return false;
    }

    /**
     * Loop through all params of a method/constructor to resolve, and attempt to resolve them.
     *
     * @param array<ReflectionParameter> $params List of params to loop through.
     * @param array $args   Arguments passed to the parent resolve method.
     *
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function resolveParams(array $params, array $args): void
    {
        foreach ($params as $param) {
            $reflectionType = $param->getType();

            // if the param is not type-hinted, check $args for the value
            if (is_null($reflectionType)) {
                $this->resolveParam($args, $param);
                continue;
            }

            $typeName = $reflectionType->getName();

            // if the class exists in the container, inject it
            if ($this->resolveFromContainer($typeName)) {
                continue;
            }

            if (class_exists($typeName) || interface_exists($typeName)) {
                $reflectionClass = new ReflectionClass($typeName);

                if ($reflectionClass->isInterface()) {
                    throw new ConcreteClassNotFoundException("$typeName is an interface with no bound implementation.");
                }

                // else the param is a class, so run $this->resolve on it
                $this->addToStack($this->resolve($typeName, $args));
                continue;
            }

            // It was type-hinted but not a defined class, so assume it's a primitive and try to resolve
            $this->resolveParam($args, $param);
        }
    }

    /**
     * Searches for $key in $args, and adds to resolutions if present.
     *
     * @param array $args  List of arguments passed to resolve.
     * @param       $param $key  The current parameter reflection class.
     */
    private function resolveParam(array $args, ReflectionParameter $param): void
    {
        $name = $param->name;

        if (isset($args[$name])) {
            $this->addToStack($args[$name]);
            return;
        }

        if ($param->isDefaultValueAvailable()) {
            $this->addToStack($param->getDefaultValue());
        }
    }
}
