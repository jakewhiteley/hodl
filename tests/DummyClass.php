<?php

namespace Hodl\Tests;

class DummyClass
{
	public $foo = null;

	public function __construct($string)
	{
		$this->foo = $string;
	}
}