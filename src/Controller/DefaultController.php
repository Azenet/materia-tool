<?php

namespace App\Controller;

use App\Entity\MateriaLoadout;
use App\Form\DocumentType;
use App\Form\MateriaLoadoutType;
use App\Service\LoadoutHelper;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;

class DefaultController extends AbstractController {
	private EntityManagerInterface $em;
	private LoadoutHelper $lh;

	public function __construct(EntityManagerInterface $em, LoadoutHelper $lh) {
		$this->em = $em;
		$this->lh = $lh;
	}

	/**
	 * @Route("/", name="default_index")
	 */
	public function index(Request $request) {
		$loadouts = $this->em->getRepository(MateriaLoadout::class)
			->findBy(['owner' => $this->getUser()]);

		$form = $this->createFormBuilder()->getForm();
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$loadout = $this->lh->addDemoLoadoutToUser($this->getUser());
			$this->addFlash('success', 'Demo loadout added to your account.');

			return $this->redirectToRoute('default_view_loadout', ['loadout' => $loadout->getId()]);
		}

		return $this->render('default/index.html.twig', [
			'loadouts' => $loadouts,
			'demoform' => $form->createView()
		]);
	}

	/**
	 * @Route("/add", name="default_add_loadout")
	 * @Route("/edit/{loadout}", name="default_edit_loadout")
	 * @Security("is_granted('ROLE_USER') && (null == loadout || is_granted('LOADOUT_EDIT', loadout))")
	 */
	public function add(Request $request, MateriaLoadout $loadout = null) {
		if (null === $loadout) {
			$loadout = new MateriaLoadout();
			$loadout
				->setOwner($this->getUser());
		}

		$form = $this->createForm(MateriaLoadoutType::class, $loadout);

		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->em->persist($loadout);
			$this->em->flush();

			return $this->redirectToRoute('default_view_loadout', ['loadout' => $loadout->getId()]);
		}

		return $this->render('default/add.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	 * @Route("/view/{loadout}", name="default_view_loadout")
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_VIEW", subject="loadout")
	 */
	public function view(MateriaLoadout $loadout, Request $request, FormFactoryInterface $ffi) {
		$cloneForm = $ffi->createNamedBuilder('clone')->getForm();

		$cloneForm->handleRequest($request);
		if ($cloneForm->isSubmitted() && $cloneForm->isValid()) {
			$new = $this->lh->createNewLoadoutFrom($loadout);

			$this->addFlash('success', 'Created new loadout from parent.');

			return $this->redirectToRoute('default_edit_loadout', ['loadout' => $new->getId()]);
		}

		return $this->render('default/view.html.twig', [
			'loadout' => $loadout,
			'clone'   => $cloneForm->createView()
		]);
	}

	/**
	 * @Route("/delete/{loadout}", name="default_delete_loadout")
	 * @IsGranted("ROLE_USER")
	 * @IsGranted("LOADOUT_DELETE", subject="loadout")
	 */
	public function delete(MateriaLoadout $loadout, Request $request) {
		$deleteForm = $this->createFormBuilder()
			->add('checkbox', CheckboxType::class, [
				'label'       => 'I understand this action cannot be undone.',
				'required'    => true,
				'constraints' => [
					new NotBlank()
				]
			]);

		$deleteForm = $deleteForm->getForm();
		$deleteForm->handleRequest($request);

		if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
			$this->em->remove($loadout);
			$this->em->flush();

			$this->addFlash('warning', sprintf('Deleted loadout \'%s\'.', $loadout->getName()));

			return $this->redirectToRoute('default_index');
		}

		$children = [];
		foreach($loadout->getChildren() as $child) {
			$this->getLeveledChildren($child, $children);
		}

		return $this->render('default/delete.html.twig', [
			'loadout'  => $loadout,
			'children' => $children,
			'form'     => $deleteForm->createView()
		]);
	}

	private function getLeveledChildren(MateriaLoadout $parent, &$result, $level = 0) {
		if (!isset($result[$level])) {
			$result[$level] = [];
		}

		$result[$level][] = $parent;

		foreach ($parent->getChildren() as $child) {
			$this->getLeveledChildren($child, $result, $level + 1);
		}
	}

	/**
	 * @Route("/login", name="default_login")
	 */
	public function auth() {
		$this->denyAccessUnlessGranted('ROLE_USER');

		return $this->redirectToRoute('default_index');
	}
}
