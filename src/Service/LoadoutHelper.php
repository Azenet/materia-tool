<?php

namespace App\Service;

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

	// rewrote this function several times, if you're an algorithm genius and see a better way please open an issue
	public function diffWithParent(MateriaLoadout $loadout, $timeLimit = 2) {
		if (null === $loadout->getParent()) {
			throw new \InvalidArgumentException('No parent');
		}

		$empty = [
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
					&& $newItem->getMateria() !== $oldItem->getMateria()) {
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
			->setCharName($order[0])
			->setCol(0)
			->setRow(0);

		while (true) {
			if (microtime(true) - $timeLimit > $start) {
				$timeout = true;
				break;
			}

			// increment if a random pick is done when distance is equal, if == 0 after process then break
			// TODO: infinite loop only broken by timeout, check whether all combinations have been tried instead?
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
					$timeout = true;
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

				/** @var MateriaLoadoutItem $unmatchedItem */
				$unmatchedItem = $fd[$lowest][random_int(0, count($fd[$lowest]) - 1)];

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
						// check that this materia can be moved from this slot
						foreach ($tu as $u) {
							if ($u === $unmatchedItem) {
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

					$counterpart = $fd[$lowest][random_int(0, count($fd[$lowest]) - 1)];
				} else {
					// inventory swap
					$counterpart = new MateriaLoadoutItem();
					$counterpart
						->setCharName('i')
						->setRow(0)
						->setCol(0)
						->setMateria($unmatchedItem->getMateria());
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

			foreach ($solution['moves'] as $m) {
				$from = $to = null;

				foreach ($hypo->getItems() as $i) {
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
					goto invalid;
				}

				$m = $from->getMateria();
				$from->setMateria($to->getMateria());
				$to->setMateria($m);
			}

			foreach ($hypo->getItems() as $i) {
				$rr = null;
				foreach ($loadout->getItems() as $ri) {
					if ($ri->getCharName() === $i->getCharName()
						&& $ri->getRow() === $i->getRow()
						&& $ri->getCol() === $i->getCol()
						&& (
							(($ri->getMateria() === null) !== ($i->getMateria() === null))
							|| ($ri->getMateria() !== null
								&& $ri->getMateria()->getId() !== $i->getMateria()->getId()))) {
						goto invalid;
					}
				}
			}

			continue;

			invalid:
			$invalid[] = $solution;
			unset($solutions[$k]);
		}

		usort($solutions, static function ($a, $b) {
			return $a['distance'] - $b['distance'];
		});

		$min = $solutions[0]['distance'] ?? null;

		return [
			'allSolutions'     => $solutions,
			'minimumDistance'  => $min,
			'matchingDistance' => array_filter($solutions, static function ($e) use ($min) {
				return $e['distance'] === $min;
			}),
			'timeout'          => $timeout
		];
	}

	public function distance(MateriaLoadoutItem $from, MateriaLoadoutItem $to, array $order) {
		if ($from->getCharName() === 'i' || $to->getCharName() === 'i') {
			// swap from inventory, distance is not considered
			return 0;
		}

		$startOrder = array_search($from->getCharName(), $order, true);
		$endOrder   = array_search($to->getCharName(), $order, true);

		// if total height is ==5 then it wraps at 3, if it is 6 it wraps at 2, 7 (4B<>1A) -> 1
		// A5/A6 + down goes to B4

		$bd = 2 * (abs($endOrder - $startOrder));

		if ($endOrder === $startOrder && $from->getRow() !== $to->getRow()) {
			$bd = 1;
		}

		/*
		 * 1A->2B -> +1
		 * 1B->2A -> -1
		 * 2B->1A -> +1
		 * 1B->2A -> -1
		 */
		if (($endOrder > $startOrder && $to->getRow() > $from->getRow()) ||
			($endOrder < $startOrder && $to->getRow() < $from->getRow())) {
			$bd++;
		} elseif (($endOrder > $startOrder && $to->getRow() < $from->getRow()) ||
				  ($endOrder < $startOrder && $to->getRow() > $from->getRow())) {
			$bd--;
		}

		if ($bd >= 5) {
			$bd -= 2 * ($bd % 4);
		}

		$extra = 0;
		if ($from->getCol() >= 4 || $to->getCol() >= 4) {
			// FIXME mostly invalid
			$cf = clone $from;
			$ct = clone $to;

			$cf->setCol(min(3, $cf->getCol()));
			$ct->setCol(min(3, $ct->getCol()));

			return $this->distance($cf, $ct, $order);
		}

		return $bd + abs($from->getCol() - $to->getCol()) + $extra;
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
				->setPartyOrder($child->getPartyOrder())
				->setPreferredChangeKey($child->getPreferredChangeKey());

			$to->addChild($cl);

			$this->addChildLoadouts($child, $cl);
			$this->em->persist($cl);
		}
	}
}