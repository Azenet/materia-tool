<?php

namespace App\Event\Listener;

use App\Entity\User;
use App\Service\TwitchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RefreshTokenListener {
	private AuthorizationCheckerInterface $aci;
	private TokenStorageInterface $tsi;
	private EntityManagerInterface $em;
	private TwitchService $ts;

	public function __construct(AuthorizationCheckerInterface $aci,
								TokenStorageInterface $tsi,
								EntityManagerInterface $em,
								TwitchService $ts) {
		$this->aci = $aci;
		$this->tsi = $tsi;
		$this->em  = $em;
		$this->ts  = $ts;
	}

	public function onKernelRequest() {
		if ($this->tsi->getToken() !== null &&
			($this->aci->isGranted('IS_AUTHENTICATED_FULLY') ||
			 $this->aci->isGranted('IS_AUTHENTICATED_REMEMBERED'))) {
			/** @var User $user */
			$user = $this->tsi->getToken()->getUser();

			if ($this->ts->isTokenExpired($user->getToken())) {
				$user->setToken($this->ts->refreshToken($user->getToken()['refresh_token']));

				$this->em->persist($user);
				$this->em->flush();
			}
		}
	}
}