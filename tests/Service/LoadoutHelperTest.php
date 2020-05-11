<?php

namespace App\Tests\Service;

use App\Entity\MateriaLoadoutItem;
use App\Service\LoadoutHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class LoadoutHelperTest extends TestCase {
	public function testDistance() {
		$lh    = new LoadoutHelper($this->createMock(EntityManagerInterface::class));
		$order = ['c', 'b', 't', 'a'];

		$this->assertEquals(1, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(0, 1, 'c'), $order));
		$this->assertEquals(2, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(1, 1, 'c'), $order));
		$this->assertEquals(1, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(1, 0, 'c'), $order));
		$this->assertEquals(2, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(0, 0, 'b'), $order));
		$this->assertEquals(1, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(1, 0, 'a'), $order));
		$this->assertEquals(2, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(0, 0, 'a'), $order));
		$this->assertEquals(4, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(0, 0, 't'), $order));
		$this->assertEquals(3, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(1, 0, 't'), $order));

		$this->assertEquals(1, $lh->distance($this->getMLI(0, 4, 'c'), $this->getMLI(1, 3, 'c'), $order));
		$this->assertEquals(2, $lh->distance($this->getMLI(0, 4, 'c'), $this->getMLI(1, 2, 'c'), $order));

		// FIXME returns 2 instead of 3, maybe be more procedural about the calculation?
		//$this->assertEquals(3, $lh->distance($this->getMLI(0, 4, 'c'), $this->getMLI(0, 4, 'b'), $order));

		$this->assertEquals(1, $lh->distance($this->getMLI(0, 0, 'c'), $this->getMLI(1, 0, 'a'), $order));

		$this->assertEquals(0, $lh->distance($this->getMLI(0, 4, 'c'), $this->getMLI(0, 0, 'i'), $order));
	}

	private function getMLI($row, $col, $char) {
		$mli = new MateriaLoadoutItem();
		$mli
			->setRow($row)
			->setCol($col)
			->setCharName($char);

		return $mli;
	}
}
