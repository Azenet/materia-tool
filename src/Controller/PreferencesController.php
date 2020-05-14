<?php

namespace App\Controller;

use App\Form\UserPreferencesType;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class PreferencesController extends AbstractController {
	private EntityManagerInterface $em;

	public function __construct(EntityManagerInterface $em) {
		$this->em = $em;
	}

	/**
	 * @Route("/preferences", name="preferences")
	 * @IsGranted("ROLE_USER")
	 */
	public function index(Request $request) {
		$user = $this->getUser();

		$form = $this->createForm(UserPreferencesType::class, $user);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->em->persist($user);
			$this->em->flush();

			$this->addFlash('success', 'Preferences saved.');

			return $this->redirectToRoute('default_index');
		}

		return $this->render('preferences/index.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	 * @Route("/preferences/delete", name="preferences_delete")
	 * @IsGranted("ROLE_USER")
	 */
	public function delete(Request $request, TokenStorageInterface $tsi) {
		$form = $this->createFormBuilder()
			->add('understood', CheckboxType::class, [
				'required'    => true,
				'label'       => 'I understand that all data will be lost',
				'constraints' => [
					new NotBlank()
				]
			])
			->getForm();
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->em->remove($this->getUser());
			$this->em->flush();

			$tsi->setToken(null);

			$this->addFlash('warning', 'Your account has been deleted.');

			return $this->redirectToRoute('default_index');
		}

		return $this->render('preferences/delete.html.twig', [
			'form' => $form->createView()
		]);
	}
}
