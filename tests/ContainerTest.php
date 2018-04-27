<?php

namespace Hodl\Tests;

use Hodl\Container;
use Hodl\Exceptions\ContainerException;

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
        	return new \Hodl\Tests\DummyClass('bar');
        });

        $this->assertTrue($hodl->has(DummyClass::class));

        return $hodl;
    }

    /**
     * @test
     * @depends an_object_can_be_added_to_the_container
     */
    public function get_returns_the_same_instance_every_time(Container $hodl)
    {
    	$firstAttempt = $hodl->get(DummyClass::class);
    	$secondAttempt = $hodl->get(DummyClass::class);

    	$this->assertSame($firstAttempt, $secondAttempt);
    	$this->assertSame($firstAttempt->foo, $secondAttempt->foo);
    }
}
