<?php

namespace App\Controller;

use App\Entity\MateriaLoadout;
use App\Entity\MateriaLoadoutItem;
use App\Entity\MateriaType;
use App\Form\ApiMateriaLoadoutType;
use App\Form\MateriaLoadoutItemType;
use App\Service\LoadoutHelper;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ApiController extends AbstractFOSRestController {
	private EntityManagerInterface $em;
	private LoadoutHelper $lh;

	public function __construct(EntityManagerInterface $em, LoadoutHelper $lh) {
		$this->em = $em;
		$this->lh = $lh;
	}

	/**
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_VIEW", subject="loadout")
	 */
	public function getLoadoutAction(MateriaLoadout $loadout) {
		$view = $this->view($loadout);
		$view->getContext()
			->enableMaxDepth()
			->setAttribute('circular_reference_limit', 4)
			->addGroup('view');

		return $view;
	}

	/**
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_EDIT", subject="loadout")
	 */
	public function patchLoadoutAction(MateriaLoadout $loadout, Request $request) {
		$form = $this->createForm(ApiMateriaLoadoutType::class, $loadout, ['csrf_protection' => false]);
		$form->submit($request->request->all(), false);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->em->persist($loadout);
			$this->em->flush();

			return $this->getLoadoutAction($loadout);
		}

		return $form;
	}

	/**
	 * @Rest\View(serializerGroups={"notes"})
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_VIEW", subject="loadout")
	 */
	public function getLoadoutNotesAction(MateriaLoadout $loadout) {
		return $loadout;
	}

	/**
	 * @Rest\View(serializerGroups={"listing"})
	 */
	public function getMateriaAction() {
		return $this->em->getRepository(MateriaType::class)
			->findAll();
	}

	/**
	 * @Rest\View(serializerGroups={"view"})
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_EDIT", subject="loadout")
	 */
	public function patchLoadoutItemAction(MateriaLoadout $loadout, MateriaLoadoutItem $item, Request $request) {
		if ($item->getLoadout() !== $loadout) {
			throw $this->createAccessDeniedException();
		}

		$form = $this->createForm(MateriaLoadoutItemType::class, $item, ['csrf_protection' => false]);
		$form->submit($request->request->all(), false);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->em->persist($item);
			$this->em->flush();

			return $item;
		}

		return $form;
	}

	/**
	 * @Rest\View(serializerGroups={"tree"})
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_VIEW", subject="loadout")
	 */
	public function getLoadoutTreeAction(MateriaLoadout $loadout) {
		return [
			'root'     => $loadout->getRoot(),
			'loadout'  => $loadout,
			'children' => $this->getChildren($loadout)
		];
	}

	protected function getChildren(MateriaLoadout $parent) {
		$r = [];

		foreach ($parent->getChildren() as $child) {
			if ($child === $parent) {
				// happened once in dev, why?
				continue;
			}

			$r[] = ['loadout' => $child, 'children' => $this->getChildren($child)];
		}

		return $r;
	}

	/**
	 * @Rest\View(serializerGroups={"diff"})
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_VIEW", subject="loadout")
	 */
	public function getLoadoutDiffAction(MateriaLoadout $loadout, Request $request) {
		if (null === $loadout->getParent()) {
			return $this->view(['error' => 'no_parent'], Response::HTTP_BAD_REQUEST);
		}

		$diffWithParent = $this->lh->diffWithParent($loadout,
			$request->query->has('deep') ? 5 : 1,
			$request->query->has('auto')
				? LoadoutHelper::DIFF_MODE_AUTO
				: LoadoutHelper::DIFF_MODE_MANUAL);

		if ($request->query->has('all')) {
			return $diffWithParent;
		}

		return array_merge($diffWithParent, [
			'allSolutions'     => array_slice($diffWithParent['allSolutions'], 0, 25),
			'matchingDistance' => array_slice($diffWithParent['matchingDistance'], 0, 25)
		]);
	}

	/**
	 * @Rest\View(serializerGroups={"diff"})
	 * @IsGranted("ROLE_USER")
	 * @Get("/api/loadouts/diff/{from}/{to}")
	 */
	public function getLoadoutDiffAuto($from, $to, Request $request, SessionInterface $session) {
		$cacheId = static function () {
			return substr(md5(uniqid('', true) . microtime()), 0, 12);
		};

		// required to interact with a db-backed collection
		$fixCollection = static function (MateriaLoadout $l) {
			$refl = new \ReflectionProperty(MateriaLoadout::class, 'children');
			$refl->setAccessible(true);

			$c = $refl->getValue($l);

			if ($c instanceof AbstractLazyCollection) {
				$items = $c->toArray();
				$refl->setValue($l, new ArrayCollection($items));
			}
		};

		if (is_numeric($from) && (int) $to === 0) {
			$fromO = $this->em->getRepository(MateriaLoadout::class)
				->getLoadoutWithMaterias($from);

			$this->denyAccessUnlessGranted('LOADOUT_VIEW', $fromO);

			$children = [];
			$this->lh->getLeveledChildren($fromO, $children);

			$fromO->setParent(null);
			$fixCollection($fromO);
			$fromO->getChildren()->clear();

			$cid = $cacheId();
			$session->set($cid, $fromO);

			return [
				'cacheId' => $cid,
				'target'  => array_sum(array_map(static function (array $e) {
					return count($e);
				}, $children))
			];
		} else if (is_numeric($to) && $session->has($from)) {
			$toO = $this->em->getRepository(MateriaLoadout::class)
				->getLoadoutWithMaterias($to);
			$this->denyAccessUnlessGranted('LOADOUT_VIEW', $toO);

			/** @var MateriaLoadout $fromO */
			$fromO = unserialize(serialize($session->get($from)));
			$toO->setParent($fromO);

			$solutions = $this->lh->diffWithParent($toO, 2, LoadoutHelper::DIFF_MODE_AUTO);
			$solution  = null;

			$cid = null;
			if (!empty($solutions['matchingDistance'])) {
				$solution = $solutions['matchingDistance'][0];

				// FIXME removed simulate+identical check because it both :
				// - makes no sense since any unpinned materia can be moved
				// - *should* be covered in the diffWithParent checks
				// would love to double check though

				$toO->setParent(null);
				$fixCollection($toO);

				$cid = $cacheId();
				$session->set($cid, $toO);

				$fromChild = $from . '_children';
				$a         = $session->get($fromChild, []);

				if (!in_array($cid, $a, true)) {
					$a[] = $cid;
				}

				$session->set($fromChild, $a);
			}

			return [
				'cacheId'  => $cid,
				'solution' => $solution
			];
		}

		return $this->view(null, Response::HTTP_BAD_REQUEST);
	}

	/**
	 * @Post("/api/loadouts/diff/to_loadout/{cacheId}")
	 * @IsGranted("ROLE_USER")
	 * @Rest\View(serializerGroups={"view"})
	 */
	public function postLoadoutDiffAuto($cacheId, SessionInterface $session) {
		/** @var MateriaLoadout $cachedMl */
		$cachedMl = $session->get($cacheId);

		if (null === $cachedMl) {
			throw $this->createNotFoundException();
		}

		$this->fixLoadoutFromCache($cachedMl, $cacheId, $session);

		/** @var MateriaLoadout $ml */
		$ml = $this->lh->cleanupAndPersist($this->getUser(), $cachedMl, true, false);

		$ml->setName(sprintf('Autogenerated %s - %s', date('d/m/Y G:i'), $ml->getName()));
		$this->em->persist($ml);
		$this->em->flush();

		return $ml;
	}

	private function fixLoadoutFromCache(MateriaLoadout $l, $cache, SessionInterface $session) {
		$children = $session->get($cache . '_children', []);

		if (!empty($children)) {
			$l->getChildren()->clear();

			foreach ($children as $childCache) {
				$childO = $session->get($childCache);
				$l->addChild($childO);

				$this->fixLoadoutFromCache($childO, $childCache, $session);
			}
		}
	}
}