# Hodl DI

[![Travis](https://img.shields.io/travis/jakewhiteley/hodl.svg?style=for-the-badge)](https://travis-ci.org/jakewhiteley/hodl) [![PHP from Packagist](https://img.shields.io/packagist/php-v/jakewhiteley/hodl.svg?style=for-the-badge)](https://packagist.org/packages/jakewhiteley/hodl)



Hodl provides full autowiring capabilities for Inversion of Control, but is simple enough to use as a standalone service container without any faff.

It's a simple `ArrayAccess` Service container which takes inspiration from both Laravel and Pimple (Symfony), but sits comfortably in the middle.

## Basic Usage

A service can be added to the container by providing the class name, and a definition for the service.

```` php
$hodl->add('Some\Namespace\Foo', function() {
	return new Foo();
});
````

You should always register a service using it's full class name. This is so that the autowiring can work and classes can have their dependencies injected with no fuss.

### Retrieving a service

As simple as it gets:

```` php
$foo = $hodl->get('Some\Namespace\Foo');
````

### Checking if a service exists

As all services are referenced by the key you defined it with, you can use `has()` to check if that key has been defined previously:

```` php
use Namespace\Foo;

// using the ::class shorthand
$hodl->add(Foo::class, function() {
    return new Foo();
});

$hodl->has(Foo::class); // true
$hodl->has('some\other\class'); // false
````

### Removing services

As the Container implements ArrayAccess you can use `unset()` or the `remove()` method to remove a class:

```` php
$hodl['foo'] = function(){
    return new Foo();
};

$hodl->has('foo'); // true

$hodl->remove('foo');

$hodl->has('foo'); // false
````

Removing a service will also remove any aliases or bound interfaces as well (more on that below).

### ArrayAccess style

As Hodl implements `ArrayAccess`, you can achieve the above like this instead:

````php
// add
$hodl['Some\Namespace\Foo'] = function(){
    return new Foo();
};

// get
$foo = $hodl['Some\Namespace\Foo'];

// check
if (isset($hodl['Some\Namespace\Foo')) // ...

// remove
unset($hodl['Some\Namespace\Foo']);
````

### Service definitions

When adding a new service definition, the `callable` which returns the class is passed an instance of `Hodl`, which can be used for passing arguments derived from services already within the container. **Note** You should not pass services directly into the constructor of your service. For that we have the magic of [autowiring](#autowiring-resolving-dependencies).

```` php
$hodl->get('Baz', function($hodl) {
	return new Baz($hodl->get('Some\Namespace\Foo')->someProp);
});
````

## Singletons

The above examplse will return a new instance of the service no matter when it is fetched.
You can also specify that the same instance should be returned each time by using the `addFactory()` method:

```` php
$hodl->addSingleton(Bar::class, function() {
	return new Bar();
});
````

## Instances
You can also add a specific instance as a service. As this is already booted, `Hodl` can derrive the class name pretty easily so there is no need to supply that.

````php
$instance = new Foo\Bar();
$instance->prop = 'foobar';

$hodl->addInstance($instance);

// ...

$hodl->get('Foo\Bar')->prop // equals 'foobar'
````

## Aliases

Sometimes it is tiresome typing in fully qualified class names to get access to a service. Luckily you can also define an **alias** to a service for quick retrieval:

```` php
// using the ::class shorthand
$hodl->add(Foo::class, function() {
    return new Foo();
});

// Adda alias.
$hodl->alias(Foo:class, 'myAlias');

$hodl->has(Foo::class); // true
$hodl->has('myAlias'); // true
````

#### Removing aliases
If at somepoint you need to remove an alias, or binding (see below) then you can use the `removeAlias($alias)` method.

## Autowiring (resolving dependencies)

Aside as using it as a container for passing objects around, it can also be used to auotmatically resolve objects using the Reflection API and achieve Inversion of Control.

Consider the following object:

```` php
namespace Foo;

class Foo
{
    function __construct( Bar $bar )
    {
       	$this->bar = $bar;
    }
}
````

When this object is created, it needs to be passed an instance of `Foo\Bar` as the first argument.

Using the `resolve()` method this is super easy:

```` php
$foo = $hodl->resolve('Foo\Foo');
````

The object will be created an an instance of `Foo\Bar` will be initialized and passed through automatically. 

This works recursively so any dependencies of `Foo\Bar` will be magically resolved as well.

### Passing arguments

The resolve method also accepts a second argument, which is an array of extra parameters you want to pass to the object constructors.

The keys of this array must be the variable name of the parameter.

```` php
// using the Foo class in the previous example, but with a following constructor:
// function __construct( Bar $bar, int $someInt, string $someString = 'string' ) { ...

// by not passing someString as a key, the default of 'string' will be used
$foo = $hodl->resolve('Foo\Foo', [
	'someInt' => 42
]);
````

### Resolving using services

The above examples have an empty container, so all services are injected as new generic instances of that class. But if a service exists within the container, that service will be used instead - allowing your specific instance or a persistent object to be passed to any object which needs it.

````php
class Foo
{
	public $var = 'foo';
}

class Bar
{
	public $foo;
	
	public function __construct(Foo $foo)
	{
		$this->foo = $foo;
	}
}

// Add Foo as a singleton
$hodl->addSingleton('Foo', function() {
	return new Foo();
});

$hodl['Foo']->var = 'changed!';


$var = $hodl->resolve('Bar')->foo->var; // equals 'changed!'
````

Don't be afraid to use `resolve()` when adding a service definition to the container either!

```` php
$hodl->add('Bar', function($hodl) {
	return $hodl->resolve('Bar'); // All of Bar's dependencies will be injected as soon as it is fetched
});

````

### Binding implementations to interfaces

A really useful feature when using the autowiring functionality is to be able to specify in a constructor an interface, and have Hodl deal with passing the correct implementation to the resolved class.

Consider the following:

```` php
// Basic interface
interface HelloWorld
{
	public function output();
}

// Service
class NeedsResolving
{
	public function __construct(HelloWorld $writer)
	{
		$this->writer = $writer;
	}
	
	public function output()
	{
		$this->writer->output();
	}
}
````

We know the `NeedsResolving` class needs some kind of `HelloWorld` implementation to actually work. We can let Hodl know which one using the `bind()` method:

```` php
class MyPrinter implements HelloWorld
{
	public function output()
	{
		echo 'Hello world!';
	}
}

$hodl->bind(MyPrinter::class, HellowWorld::class);

// Correctly gets an instance of MyPrinter
$foo = $hodl->resolve(NeedsResolving::class);

$foo->output(); // Outputs 'Hello world!'
````

#### Removing bindings
As under the hood `bind()` as an alias for `alias()`, the `removeAlias($interface)` method will remove a binding. Useful if for whatever reason you had to hot-swap an implementation out for another.

## Resolving methods
The `resolveMethod($class, $methodName, $args)` method allows autowiring of class members the same way that `resolve()` works on classes.

````php
$hodl->resolveMethod(Foo::class, 'someMethod');
````

`resolveMethod` will call the supplied method, recursively inject dependencies and allow you to pass extra non-object parameters as per the `resolve` examples above. This works on static methods as well as public ones.

### Resolving instance methods

The example above shows `someMethod` being execcuted and returned on a new instance of `Foo`, but you can also pass a specific instance instead of the class name:

````php
$foo = new Foo();
$return = $hodl->resolveMethod($foo, 'someMethod', ['amount_of_awesome' => 100]);
````

Both `resolve` and `resolveMethod` could therefore be used together to create a new fully resolved object and execute a method.

````php
class Bar
{
	public $foo;
	
	public function __construct(Foo $foo)
	{
		$this->foo = $foo;
	}
	
	public function methodName(Foo\Baz $baz)
	{
		return $this->foo->var * $baz->var;
	}
}

// Fully resolves methodName and returns an instance of Foo\Baz
$resolvedBaz = $hodl->resolveMethod(
	$hodl->resolve('Bar'),
	'methodName'
);
````

## Conclusion

By adding services to Hodl, your code can achieve complete inversion of control and manage classes application-wide with no need for a single `new` keyword or singleton in sight.

## Contributing

If you have any improvements, bugs, or feature requests; feel free to open up an issue or PR.
