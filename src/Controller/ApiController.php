<?php

namespace App\Controller;

use App\Entity\MateriaLoadout;
use App\Entity\MateriaLoadoutItem;
use App\Entity\MateriaType;
use App\Form\ApiMateriaLoadoutType;
use App\Form\MateriaLoadoutItemType;
use App\Service\LoadoutHelper;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

		$diffWithParent = $this->lh->diffWithParent($loadout, $request->query->has('deep') ? 5 : 1);

		if ($request->query->has('all')) {
			return $diffWithParent;
		}

		return array_merge($diffWithParent, [
			'allSolutions'     => array_slice($diffWithParent['allSolutions'], 0, 25),
			'matchingDistance' => array_slice($diffWithParent['matchingDistance'], 0, 25)
		]);
	}
}