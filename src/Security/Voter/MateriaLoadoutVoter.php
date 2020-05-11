<?php

namespace App\Security\Voter;

use App\Entity\MateriaLoadout;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class MateriaLoadoutVoter extends Voter {
	private AuthorizationCheckerInterface $aci;

	public function __construct(AuthorizationCheckerInterface $aci) {
		$this->aci = $aci;
	}

	protected function supports($attribute, $subject) {
		return in_array($attribute, ['LOADOUT_VIEW', 'LOADOUT_EDIT'])
			   && $subject instanceof MateriaLoadout;
	}

	protected function voteOnAttribute($attribute, $subject, TokenInterface $token) {
		$user = $token->getUser();
		if (!$user instanceof UserInterface) {
			return false;
		}

		/** @var MateriaLoadout $subject */

		return $subject->getOwner() === $user || $this->aci->isGranted('ROLE_SUPER_ADMIN');
	}
}
