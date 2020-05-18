<?php

namespace App\Service;

use App\Entity\Materia;
use App\Entity\MateriaLoadout;
use App\Entity\MateriaLoadoutItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class LoadoutHelper {
	private EntityManagerInterface $em;
	private $demoLoadoutId = null;

	public function __construct(EntityManagerInterface $em) {
		$this->em = $em;
	}

	public function setDemoLoadoutId($id) {
		$this->demoLoadoutId = $id;
	}

	public function createNewLoadoutFrom(MateriaLoadout $origin) {
		$new = new MateriaLoadout();
		$new
			->setOwner($origin->getOwner())
			->setName($origin->getName() . ' - copy')
			->setNotes($origin->getNotes())
			->setStartCharacter($origin->getStartCharacter())
			->setPartyOrder($origin->getPartyOrder())
			->setPreferredChangeKey($origin->getPreferredChangeKey())
			->setParent($origin);

		$origin->addChild($new);

		$this->resetItemsFromParent($new);

		$this->em->persist($origin);
		$this->em->persist($new);

		$this->em->flush();

		return $new;
	}

	public function resetItemsFromParent(MateriaLoadout $loadout) {
		if ($loadout->getParent() === null) {
			throw new \InvalidArgumentException('Loadout has no parent');
		}

		$loadout->getItems()->clear();

		foreach ($loadout->getParent()->getItems() as $item) {
			$ni = new MateriaLoadoutItem();
			$ni
				->setCol($item->getCol())
				->setRow($item->getRow())
				->setCharName($item->getCharName())
				->setMateria($item->getMateria());

			$loadout->addItem($ni);
		}

		$this->em->persist($loadout);
		$this->em->flush();
	}

	public const DIFF_MODE_MANUAL = 1;
	public const DIFF_MODE_AUTO = 2;

	// rewrote this function several times, if you're an algorithm genius and see a better way please open an issue
	public function diffWithParent(MateriaLoadout $loadout, $timeLimit = 2, $diffMode = self::DIFF_MODE_MANUAL) {
		if (null === $loadout->getParent()) {
			throw new \InvalidArgumentException('No parent');
		}

		$empty = [
			'startingOn'       => null,
			'allSolutions'     => [],
			'minimumDistance'  => null,
			'matchingDistance' => [],
			'timeout'          => false
		];

		$unmatched = [];

		foreach ($loadout->getItems() as $newItem) {
			foreach ($loadout->getParent()->getItems() as $oldItem) {
				if ($newItem->getCharName() === $oldItem->getCharName()
					&& $newItem->getCol() === $oldItem->getCol()
					&& $newItem->getRow() === $oldItem->getRow()
					&& (
						((null === $newItem->getMateria()) !== (null === $oldItem->getMateria()))
						|| (
							null !== $newItem->getMateria()
							&& ($newItem->getMateria()->getId() !== $oldItem->getMateria()->getId())
						))) {
					$unmatched[] = $newItem;
				}
			}
		}

		if (empty($unmatched)) {
			return $empty;
		}

		$solutions = [];
		$start     = microtime(true);
		$timeout   = false;

		$order = $loadout->getPartyOrder();
		$og    = new MateriaLoadoutItem();
		$og
			->setCharName($loadout->getStartCharacter() ?? $order[0])
			->setCol(0)
			->setRow(0);

		$possibilities = ['f' => [], 't' => []];

		$first = true;
		while (true) {
			if (microtime(true) - $timeLimit > $start) {
				$timeout = 'timed_out';
				break;
			}

			if ($first) {
				$first = false;
			} else {
				$discrep = 0;
				foreach ($possibilities as $p) {
					$discrep += count($p);
					foreach ($p as $v) {
						if ($v['has'] > 0 && count($v['tried']) === $v['has']) {
							$discrep--;
						}
					}
				}

				if ($discrep === 0) {
					$timeout = 'no_more_picks';
					break;
				}
			}

			// increment if a random pick is done when distance is equal, if == 0 after process then break
			$random = 0;

			$moves  = [];
			$cursor = unserialize(serialize($og));

			$td = 0;
			$tu = $unmatched;

			$hypo = new MateriaLoadout();
			$hypo->getItems()->clear();
			foreach ($loadout->getParent()->getItems() as $item) {
				$mli = new MateriaLoadoutItem();
				$mli
					->setCharName($item->getCharName())
					->setRow($item->getRow())
					->setCol($item->getCol())
					->setMateria($item->getMateria());

				$hypo->addItem($mli);
			}

			while (!empty($tu)) {
				if (microtime(true) - $timeLimit > $start) {
					$timeout = 'timed_out_inner';
					break 2;
				}

				// find closest unmatched from cursor
				$fd = [];
				foreach ($tu as $k => $u) {
					/** @var $u MateriaLoadoutItem */
					$distance = $this->distance($cursor, $u, $order);
					if (!isset($fd[$distance])) {
						$fd[$distance] = [];
					}

					$fd[$distance][] = $u;
				}

				ksort($fd);
				$lowest = array_keys($fd)[0];
				$random += count($fd[$lowest]) - 1;

				$i = random_int(0, count($fd[$lowest]) - 1);

				if (!isset($possibilities['f'][$cursor->__toString()])) {
					$possibilities['f'][$cursor->__toString()] = ['has' => count($fd[$lowest]), 'tried' => []];
				}

				$possibilities['f'][$cursor->__toString()]['tried'][$i] = true;

				/** @var MateriaLoadoutItem $unmatchedItem */
				$unmatchedItem = $fd[$lowest][$i];

				// find counterpart and current from self-tracked loadout
				$fd      = [];
				$current = null;
				foreach ($hypo->getItems() as $k => $mli) {
					if ($mli->getRow() === $unmatchedItem->getRow()
						&& $mli->getCol() === $unmatchedItem->getCol()
						&& $mli->getCharName() === $unmatchedItem->getCharName()) {
						$current = $mli;
						continue;
					}

					if (null !== $mli->getMateria()
						&& null !== $unmatchedItem->getMateria()
						&& $mli->getMateria()->getId() === $unmatchedItem->getMateria()->getId()) {
						if ($diffMode === self::DIFF_MODE_MANUAL) {
							$ud = $tu;
						} else {
							$ud = $loadout->getParent()->getItems();
						}

						// check that this materia can be moved from this slot
						foreach ($ud as $u) {
							if ($u === $unmatchedItem || $u->getPinned()) {
								continue;
							}

							if ($u->getRow() === $mli->getRow()
								&& $u->getCol() === $mli->getCol()
								&& $u->getCharName() === $mli->getCharName()) {
								$distance = $this->distance($unmatchedItem, $u, $order);
								if (!isset($fd[$distance])) {
									$fd[$distance] = [];
								}

								$fd[$distance][] = $mli;
								break;
							}
						}
					}
				}

				if (null === $current) {
					throw new \LogicException('Layout mismatch');
				}

				/** @var MateriaLoadoutItem $counterpart */
				$counterpart = null;
				if (!empty($fd)) {
					ksort($fd);
					$lowest = array_keys($fd)[0];
					$random += count($fd[$lowest]) - 1;

					$i = random_int(0, count($fd[$lowest]) - 1);

					if (!isset($possibilities['t'][$cursor->__toString()])) {
						$possibilities['t'][$cursor->__toString()] = ['has' => count($fd[$lowest]), 'tried' => []];
					}

					$possibilities['t'][$cursor->__toString()]['tried'][$i] = true;

					$counterpart = $fd[$lowest][$i];
				} else {
					// inventory swap
					$counterpart = new MateriaLoadoutItem();
					$counterpart
						->setCharName('i')
						->setRow(0)
						->setCol(0)
						->setMateria($unmatchedItem->getMateria());
				}

				if ($diffMode === self::DIFF_MODE_AUTO) {
					// need to check manually if the materia is still supposed to be there as $fd can be
					// not empty for frequent materias (like MP up)
					$found = false;
					foreach ($loadout->getItems() as $item) {
						if ($item->getMateria() !== null
							&& $current->getMateria() !== null
							&& $item->getMateria()->getId() === $current->getMateria()->getId()) {
							$found = true;
							break;
						}
					}

					if (!$found) {
						$counterpart = new MateriaLoadoutItem();

						$counterpart
							->setCharName('i')
							->setRow(0)
							->setCol(0)
							->setMateria($unmatchedItem->getMateria());
					}
				}

				$move = [
					'from' => unserialize(serialize($current)),
					'to'   => unserialize(serialize($counterpart))
				];

				$td +=
					$this->distance($cursor, $unmatchedItem, $order) +
					$this->distance($unmatchedItem, $counterpart, $order);

				if ($counterpart->getCharName() !== 'i') {
					[$row, $col, $char] = [
						$unmatchedItem->getRow(),
						$unmatchedItem->getCol(),
						$unmatchedItem->getCharName()
					];

					$current
						->setRow($counterpart->getRow())
						->setCol($counterpart->getCol())
						->setCharName($counterpart->getCharName());

					$counterpart
						->setRow($row)
						->setCol($col)
						->setCharName($char);

					$cursor = $move['to'];
				} else {
					$current->setMateria($counterpart->getMateria());
					$cursor = $unmatchedItem;
				}

				$moves[] = $move;

				foreach ($hypo->getItems() as $u) {
					foreach ($loadout->getItems() as $mli) {
						if ($u->getRow() === $mli->getRow()
							&& $u->getCol() === $mli->getCol()
							&& $u->getCharName() === $mli->getCharName()
							&& $u->getMateria() !== null
							&& $mli->getMateria() !== null
							&& $u->getMateria()->getId() === $mli->getMateria()->getId()) {
							foreach ($tu as $k => $ui) {
								if ($ui->getCol() === $u->getCol()
									&& $ui->getRow() === $u->getRow()
									&& $ui->getCharName() === $u->getCharName()
									&& (($ui->getMateria() === null && $u->getMateria() === null)
										|| $ui->getMateria()->getId() === $u->getMateria()->getId())) {
									unset($tu[$k]);
								}
							}
						}
					}
				}
			}

			if (empty($moves)) {
				if ($random === 0) {
					return $empty;
				}

				continue;
			}

			$hb = '';
			foreach ($moves as $move) {
				$hb .= sprintf('%s%d%d-%d|%s%d%d-%d#', $move['from']->getCharName(), $move['from']->getRow(),
					$move['from']->getCol(), $move['from']->getMateria() ? $move['from']->getMateria()->getId() : 0,
					$move['to']->getCharName(), $move['to']->getRow(),
					$move['to']->getCol(), $move['to']->getMateria() ? $move['to']->getMateria()->getId() : 0);
			}

			foreach ($solutions as $sol) {
				if ($sol['key'] === $hb) {
					continue 2;
				}
			}

			$solutions[] = [
				'distance' => $td,
				'moves'    => $moves,
				'key'      => $hb
			];

			if ($random === 0) {
				break;
			}
		}

		$invalid = [];

		foreach ($solutions as $k => $solution) {
			$solution = unserialize(serialize($solution));

			$hypo = new MateriaLoadout();
			$this->copyItems($loadout->getParent(), $hypo);

			$this->simulate($solution, $hypo);

			if ($diffMode === self::DIFF_MODE_MANUAL && $this->checkIdentical($loadout, $hypo)) {
				continue;
			}

			if ($diffMode === self::DIFF_MODE_AUTO) {
				$key = static function (MateriaLoadoutItem $i) {
					return sprintf('%s%d%d', $i->getCharName(), $i->getRow(), $i->getCol());
				};

				$touched = [];
				foreach ($solution['moves'] as $move) {
					$touched[] = $key($move['from']);
					$touched[] = $key($move['to']);
				}

				$touched = array_unique($touched);

				$failed = false;

				foreach ($touched as $t) {
					if ($t[0] === 'i') {
						continue;
					}

					$hypoItem = null;
					foreach ($hypo->getItems() as $item) {
						if ($item->getRow() === (int) $t[1]
							&& $item->getCol() === (int) $t[2]
							&& $item->getCharName() === $t[0]
						) {
							$hypoItem = $item;
							break;
						}
					}

					$targetItem = null;
					foreach ($loadout->getItems() as $item) {
						if ($item->getRow() === (int) $t[1]
							&& $item->getCol() === (int) $t[2]
							&& $item->getCharName() === $t[0]
						) {
							$targetItem = $item;
							break;
						}
					}

					if (null === $hypoItem
						|| null === $targetItem
						|| (
							((null === $hypoItem->getMateria()) !== (null === $targetItem->getMateria()))
							|| (
								null !== $hypoItem->getMateria()
								&& $hypoItem->getMateria()->getId() !== $targetItem->getMateria()->getId()
							)
						)
					) {
						$failed = true;
						break;
					}
				}

				if (!$failed) {
					continue;
				}
			}

			$invalid[] = $solution;
			unset($solutions[$k]);
		}

		usort($solutions, static function ($a, $b) {
			return $a['distance'] - $b['distance'];
		});

		$min = $solutions[0]['distance'] ?? null;

		return [
			'startingOn'       => $loadout->getStartCharacter() ?? $order[0],
			'allSolutions'     => $solutions,
			'minimumDistance'  => $min,
			'matchingDistance' => array_filter($solutions, static function ($e) use ($min) {
				return $e['distance'] === $min;
			}),
			'timeout'          => $timeout,
			'rejects'          => $invalid
		];
	}

	public function simulate(array $solution, MateriaLoadout $loadout) {
		$solution = unserialize(serialize($solution));

		foreach ($solution['moves'] as $m) {
			$from = $to = null;

			foreach ($loadout->getItems() as $i) {
				foreach (['from', 'to'] as $v) {
					if ($i->getCharName() === $m[$v]->getCharName()
						&& $i->getRow() === $m[$v]->getRow()
						&& $i->getCol() === $m[$v]->getCol()) {
						$$v = $i;
					}
				}
			}

			if ($m['to']->getCharName() === 'i') {
				$to = $m['to'];
			}

			if (null === $from || null === $to) {
				throw new \InvalidArgumentException('Error in layout/solution');
			}

			$m = $from->getMateria();
			$from->setMateria($to->getMateria());
			$to->setMateria($m);
		}
	}

	public function checkIdentical(MateriaLoadout $from, MateriaLoadout $to) {
		foreach ($to->getItems() as $i) {
			foreach ($from->getItems() as $ri) {
				if ($ri->getCharName() === $i->getCharName()
					&& $ri->getRow() === $i->getRow()
					&& $ri->getCol() === $i->getCol()
					&& (
						(($ri->getMateria() === null) !== ($i->getMateria() === null))
						|| ($ri->getMateria() !== null
							&& $ri->getMateria()->getId() !== $i->getMateria()->getId()))) {
					return false;
				}
			}
		}

		return true;
	}

	public function copyItems(MateriaLoadout $from, MateriaLoadout $to) {
		$to->getItems()->clear();

		foreach ($from->getItems() as $item) {
			$mli = new MateriaLoadoutItem();

			$mli
				->setRow($item->getRow())
				->setCol($item->getCol())
				->setCharName($item->getCharName())
				->setMateria($item->getMateria())
				->setLoadout($to)
				->setPinned($item->getPinned());

			$to->addItem($mli);
		}
	}

	private const DIR_UP = 0;
	private const DIR_RIGHT = 1;
	private const DIR_DOWN = 2;
	private const DIR_LEFT = 3;

	private function doMove(MateriaLoadoutItem $cursor, int $direction, array $order) {
		switch ($direction) {
			case self::DIR_UP:
				$cursor->setRow($cursor->getRow() - 1);
				break;

			case self::DIR_DOWN:
				$cursor->setRow($cursor->getRow() + 1);
				break;

			case self::DIR_LEFT:
				$cursor->setCol($cursor->getCol() - 1);
				break;

			case self::DIR_RIGHT:
				$cursor->setCol($cursor->getCol() + 1);
				break;
		}

		// move between characters
		if ($cursor->getRow() < 0) {
			$cursor
				->setRow(1)
				->setCharName($order[(array_search($cursor->getCharName(), $order, true) + 3) % count($order)]);
		} else if ($cursor->getRow() > 1) {
			$cursor
				->setRow(0)
				->setCharName($order[(array_search($cursor->getCharName(), $order, true) + 5) % count($order)]);
		}

		// handle going up/down from right materias on first row -> snaps to rightmost materia on second row
		if (in_array($direction, [self::DIR_UP, self::DIR_DOWN]) && $cursor->getRow() === 1 && $cursor->getCol() > 3) {
			$cursor->setCol(3);
		}

		// handle going left on leftmost materia
		if ($cursor->getCol() < 0) {
			if ($cursor->getRow() === 0) {
				$cursor->setCol(6);
			} else {
				$cursor->setCol(3);
			}
		}

		// handle going right on rightmost materia
		if (
			($cursor->getCol() > 6 && $cursor->getRow() === 0)
			|| ($cursor->getCol() > 3 && $cursor->getRow() === 1)) {
			$cursor->setCol(0);
		}
	}

	// rewritten in a "procedural" way to handle edge cases
	public function distance(MateriaLoadoutItem $from, MateriaLoadoutItem $to, array $order) {
		if ($from->getCharName() === 'i' || $to->getCharName() === 'i') {
			// swap from inventory, distance is not considered
			return 0;
		}

		$cursor = clone($from);

		$du = 0;
		$dd = 0;

		$cu = clone($cursor);
		$cd = clone($cursor);

		foreach ([self::DIR_UP => [$cu, &$du], self::DIR_DOWN => [$cd, &$dd]] as $k => $v) {
			while ($v[0]->getRow() !== $to->getRow() || $v[0]->getCharName() !== $to->getCharName()) {
				$v[1]++;
				$this->doMove($v[0], $k, $order);
			}
		}

		$cursor = $du > $dd ? $cd : $cu;

		$dl = 0;
		$dr = 0;

		$cl = clone($cursor);
		$cr = clone($cursor);

		foreach ([self::DIR_LEFT => [$cl, &$dl], self::DIR_RIGHT => [$cr, &$dr]] as $k => $v) {
			while ($v[0]->getCol() !== $to->getCol()) {
				$v[1]++;
				$this->doMove($v[0], $k, $order);
			}
		}

		return min($du, $dd) + min($dl, $dr);
	}

	public function addDemoLoadoutToUser(User $user) {
		if (null === $this->demoLoadoutId) {
			throw new \LogicException('No demo loadout defined');
		}

		/** @var MateriaLoadout $src */
		$src = $this->em->find(MateriaLoadout::class, $this->demoLoadoutId);

		$loadout = new MateriaLoadout();
		$loadout
			->setName($src->getName())
			->setOwner($user)
			->setPartyOrder($src->getPartyOrder())
			->setStartCharacter($src->getStartCharacter())
			->setNotes($src->getNotes())
			->setPreferredChangeKey($src->getPreferredChangeKey());

		$this->addChildLoadouts($src, $loadout);

		$this->em->persist($loadout);
		$this->em->flush();

		return $loadout;
	}

	private function addChildLoadouts(MateriaLoadout $from, MateriaLoadout $to) {
		$to->getItems()->clear();
		foreach ($from->getItems() as $item) {
			$new = new MateriaLoadoutItem();
			$new
				->setRow($item->getRow())
				->setCol($item->getCol())
				->setCharName($item->getCharName())
				->setMateria($item->getMateria());

			$to->addItem($new);
		}

		foreach ($from->getChildren() as $child) {
			$cl = new MateriaLoadout();
			$cl
				->setName($child->getName())
				->setOwner($to->getOwner())
				->setNotes($child->getNotes())
				->setPartyOrder($child->getPartyOrder())
				->setStartCharacter($child->getStartCharacter())
				->setPreferredChangeKey($child->getPreferredChangeKey());

			$to->addChild($cl);

			$this->addChildLoadouts($child, $cl);
			$this->em->persist($cl);
		}
	}

	public function cleanupAndPersist(User $owner, MateriaLoadout $cachedMl, $persist = true, $flush = true) {
		$ml = $this->doCleanCopy($owner, $cachedMl);

		if ($persist) {
			$this->em->transactional(function () use ($ml, $flush) {
				$this->em->persist($ml);

				if ($flush) {
					$this->em->flush();
				}
			});
		}

		return $ml;
	}

	private function doCleanCopy(User $owner, MateriaLoadout $current, MateriaLoadout $parent = null) {
		$new = new MateriaLoadout();

		$new
			->setOwner($owner)
			->setParent($parent)
			->setName($current->getName())
			->setNotes($current->getNotes())
			->setPreferredChangeKey($current->getPreferredChangeKey())
			->setPartyOrder($current->getPartyOrder())
			->setStartCharacter($current->getStartCharacter());

		$this->copyItems($current, $new);

		if ($new->getItems()->isEmpty()) {
			return null;
		}

		foreach ($new->getItems() as $item) {
			if (null !== $item->getMateria()) {
				$item->setMateria($this->em->getReference(Materia::class, $item->getMateria()->getId()));
			}
		}

		foreach ($current->getChildren() as $child) {
			$child = $this->doCleanCopy($owner, $child, $new);
			if (null !== $child) {
				$new->addChild($child);
			}
		}

		return $new;
	}

	public function getLeveledChildren(MateriaLoadout $parent, &$result, $level = 0) {
		if (!isset($result[$level])) {
			$result[$level] = [];
		}

		$result[$level][] = $parent;

		foreach ($parent->getChildren() as $child) {
			$this->getLeveledChildren($child, $result, $level + 1);
		}
	}
}