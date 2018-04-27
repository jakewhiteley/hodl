<?php

namespace Hodl\Tests;

use Hodl\Container;
use Hodl\Exceptions\ContainerException;
use Hodl\Exceptions\NotFoundException;
use Hodl\Tests\Classes\DummyClass;
use Hodl\Tests\Classes\NoConstructor;
use Hodl\Tests\Classes\NeedsResolving;

class ContainerTest extends \PHPUnit\Framework\TestCase
{
	/** 
	 * @test
	 */
    public function a_container_can_be_booted_and_extends_psr11()
    {
        $hodl = new Container();
        $this->assertInstanceOf(\Psr\Container\ContainerInterface::class, $hodl);
    }	

    /** 
     * @test
     */
    public function get_only_accepts_strings_as_a_key()
    {
        $hodl = new Container();

        $this->expectException(ContainerException::class);

        $hodl->get(12);
    }    

    /** 
     * @test
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function an_object_can_be_added_to_the_container()
    {
        $hodl = new Container();

        $hodl->add(DummyClass::class, function() {
        	return new DummyClass('bar');
        });

        $this->assertTrue($hodl->has(DummyClass::class));
        $this->assertFalse($hodl->hasFactory(DummyClass::class));
        $this->assertTrue($hodl->hasObject(DummyClass::class));

        return $hodl;
    }

    /**
     * @test
     * @depends an_object_can_be_added_to_the_container
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function get_returns_the_same_class_instance_every_time(Container $hodl)
    {
    	$firstAttempt = $hodl->get(DummyClass::class);
    	$secondAttempt = $hodl->get(DummyClass::class);

    	$this->assertSame($firstAttempt, $secondAttempt);
        $this->assertSame($firstAttempt->foo, $secondAttempt->foo);
    	$this->assertSame($firstAttempt->bar, $secondAttempt->bar);

    	return $hodl;
    }    

    /**
     * @test
     * @depends get_returns_the_same_class_instance_every_time
     */
    public function get_throws_NotFoundException_when_key_not_present(Container $hodl)
    {
        $hodl->remove(DummyClass::class);

    	$this->expectException(NotFoundException::class);

    	$hodl->get(DummyClass::class);
    }

     /** 
     * @test
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function an_object_can_be_added_to_the_container_as_a_factory()
    {
        $hodl = new Container();

        $hodl->addFactory(DummyClass::class, function() {
            return new DummyClass('foo');
        });

        $this->assertTrue($hodl->has(DummyClass::class));
        $this->assertTrue($hodl->hasFactory(DummyClass::class));
        $this->assertFalse($hodl->hasObject(DummyClass::class));

        return $hodl;
    }

    /**
     * @test
     * @depends an_object_can_be_added_to_the_container_as_a_factory
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function get_returns_a_different_factory_instance_every_time(Container $hodl)
    {
        $firstAttempt = $hodl->get(DummyClass::class);
        $secondAttempt = $hodl->get(DummyClass::class);

        $this->assertSame($firstAttempt->foo, $secondAttempt->foo);
        $this->assertNotEquals($firstAttempt->bar, $secondAttempt->bar);

        return $hodl;
    }
    
    /**
     * @test
     * @depends get_returns_a_different_factory_instance_every_time
     */
    public function a_factory_can_be_removed(Container $hodl)
    {
        $this->assertTrue($hodl->remove(DummyClass::class));
        $this->assertFalse($hodl->has(DummyClass::class));
        $this->assertFalse($hodl->remove(DummyClass::class));

        return $hodl;
    }

    /**
     * @test
     */
    public function container_impliments_array_access_correctly()
    {
        $hodl = new Container();

        $hodl['key'] = function() {
            return new DummyClass('foo');
        };

        $this->assertTrue(isset($hodl['key']));
        $this->assertTrue($hodl['key'] instanceof DummyClass);
        unset($hodl['key']);
        $this->assertFalse(isset($hodl['key']));
    }

    /**
     * @test
     */
    public function an_object_can_be_resolved_explicitly()
    {
        $hodl = new Container();

        $resolved = $hodl->resolve(NeedsResolving::class);

        $this->assertEquals('foobar', $resolved->resolver->var);
        $this->assertEquals('nested', $resolved->resolver->nested->var);

        $doesntNeedResolving = $hodl->resolve(DummyClass::class);

        $this->assertEquals('not_set', $doesntNeedResolving->foo);

        $hasNoConstructor = $hodl->resolve(NoConstructor::class);

        $this->assertInstanceOf(NoConstructor::class, $hasNoConstructor);
    }

        /**
     * @test
     */
    public function an_object_can_be_resolved_explicitly_with_params()
    {
        $hodl = new Container();

        $doesntNeedResolving = $hodl->resolve(DummyClass::class, ['string' => 'has_been_set']);

        $this->assertEquals('has_been_set', $doesntNeedResolving->foo);
    }
}
