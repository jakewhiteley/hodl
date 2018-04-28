# Jaws DI Container


[![Travis](https://img.shields.io/travis/jakewhiteley/hodl.svg?style=for-the-badge)](https://travis-ci.org/jakewhiteley/hodl) 
[![Packagist](https://img.shields.io/packagist/v/jakewhiteley/hodl.svg?style=for-the-badge)](https://packagist.org/packages/jakewhiteley/hodl) 
[![PHP from Packagist](https://img.shields.io/packagist/php-v/jakewhiteley/hodl.svg?style=for-the-badge)](https://packagist.org/packages/jakewhiteley/hodl)




A simple Service container which takes inspiration from both Laravel and Pimple (Symfony).

## Basic Usage

A class can be added to the container as follows:

```` php
use \Some\Namespace\Foo;

$hodl[Foo::class] = function(){
    return new Foo();
};

// or
$hodl->add(Foo::class, function() {
	return new Foo();
});
````

And then called using any of the following:

```` php
$foo = $hodl[Foo::class];

$foo = $hodl->get(Foo::class);
````

When adding a new class or factory, the closure which returns the class is passed an instance of the DI Container which can be used for passing arguments derived from services already within the container:

```` php
$hodl['Baz'] = function($hodl) {
	return new Baz($hodl[Foo::class]->propertyName, 'string');
};
````

## Factories

The above example will return the same instance of Foo no matter when it is called.
You can also specify that a new instance should be returned each time by using the `addFactory()` method:

```` php
$hodl->addFactory(Bar::class, function() {
	return new Bar();
});
````

## Checking if a key exists

As all objects are referenced by the key you defined it with, you can use has() to check if that key has been defined previously:

```` php
$hodl[Foo::class] = function() {
    return new Foo();
};

$hodl->has(Foo::class); // true
$hodl->has('some\other\class'); // false
````

## Removing classes

As the Container implements ArrayAccess you can use `unset()` or the `remove()` method to remove a class:
```` php
$hodl['foo'] = function(){
    return new Foo();
};

$hodl->has('foo'); // true

$hodl->remove('foo');

$hodl->has('foo'); // false
````

## Resolving Classes

Aside as using it as a container for passing objects around, it can also be used to auotmatically resolve objects using the Reflection API.

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
$foo = $hodl->resolve('foo');
````

The object will be created an an instance of Foo\Bar will be initialized and passed through automatically. This works recursively so any dependencies of Bar will be magically resolved as well.
This can of course be used when adding classes to the container so that they are already resolved when you call them.

The resolve method also accepts a second argument, which is an array of extra parameters you want to pass to the object constructors.
The keys of this array must be the variable name of the parameter.

```` php
// using the Foo class in the previous example, but with a following constructor:
// function __construct( Bar $bar, int $someInt, string $someString = 'string' ) { ...

// by not passing someString as a key, the default of 'string' will be used
$foo = $hodl->resolve('foo', [
	'someInt' => 42,
	// 'someString' => 'Not passed'
]);
````

This is all very well when you want the resolved object to use new instances, but what if you wanted to pass an object from the container itself? For that you can use the `using()` method, which indicates that a given class name should be loaded from a given container key instead of a new instance.

A good example would be passing a database connection wrapper:

```` php
/*
class Needs_Db
{
	public function __construct( Some\Namespace\Database $connection, OtherClass $other ) {...}
}
*/

// $connection will be passed as $hodl->get('db') while $other is passed as a new instance of OtherClass
$ClassWhichNeedsDatabase = $hodl->using([
	'Some\Namespace\Database' => 'db'
])->resolve('Needs_Db');
````

Don't be afraid to use `resolve()` when adding a class to the container either!

```` php
$hodl->add('foo', function($di) {
	// Foo will be returned with bar taken from the container, and Other\Class as a new object with all dependencies resolved
	// including an instance of \Database\Class from the container
	return new Foo(
		$di['bar'], 
		$di->using([ 'Database\Class' => 'db_class_key' ])->resolve('Other\Class')
	);
});

````
