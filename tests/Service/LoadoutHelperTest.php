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

		$tests = [
			// "legacy"
			'c00c01=1',
			'c00c11=2',
			'c00c10=1',
			'c00b00=2',
			'c00a10=1',
			'c00a00=2',
			'c00t00=4',
			'c00t10=3',
			'c04c13=1',
			'c04c12=2',
			'c00a10=1',
			'c04i00=0',
			'c00c03=3',
			'c00c04=3',
			'c00c05=2',
			'c00c06=1',
			'c10c13=1',
			'c00c13=2',
			'c00b00=2',
			'c00b13=4',
			'c00a10=1',
			'c00a13=2',
			'c00c12=3',
			'c00c13=2',
			'c04b04=3',

			// basic movement
			'c00c01=1',
			'c00c10=1',
			'c00c11=2',
			'c00b00=2',
			'c00t00=4',
			// wraparounds up/down
			'c00t10=3',
			'c00a10=1',
			'a10c00=1',
			't00c00=4',
			't10c00=3',
			// wraparounds left/right
			'c00c06=1',
			'c10c13=1',
			'c00c13=2',
			'c06c00=1',
			'c13c10=1',
			// wraparounds special row 0 -> row 1
			'c04c13=1',
			'c13c04=2',
			'c04b04=3', // ..c13-b03-b04
			'c04t04=5', // ..c13-b03-b13-t03-t04
		];

		foreach ($tests as $test) {
			preg_match('/(\w)(\d)(\d)(\w)(\d)(\d)=(\d+)/', $test, $m);

			$this->assertEquals(
				(int) $m[7],
				$lh->distance(
					$this->getMLI((int) $m[2], (int) $m[3], $m[1]),
					$this->getMLI((int) $m[5], (int) $m[6], $m[4]), $order),
				$test
			);
		}
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
