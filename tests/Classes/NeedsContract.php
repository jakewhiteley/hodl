<?php

namespace Hodl\Tests\Classes;

class NeedsContract
{
	public $contract = null;

	public function __construct(Contract $contract)
	{
		$this->contract = $contract;
	}
}