<?php

namespace App\Security;

use App\Entity\User;
use App\Service\TwitchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppTwitchAuthenticator extends AbstractGuardAuthenticator {
	use TargetPathTrait;

	private EntityManagerInterface $em;
	private UrlGeneratorInterface $ugi;
	private TwitchService $ts;

	public function __construct(EntityManagerInterface $em,
								UrlGeneratorInterface $ugi,
								TwitchService $ts) {
		$this->em  = $em;
		$this->ugi = $ugi;
		$this->ts  = $ts;
	}

	public function supports(Request $request): bool {
		return $request->attributes->get('_route') === 'twitch_auth_return' && $request->isMethod('GET');
	}

	public function getCredentials(Request $request): array {
		return [
			'code' => $request->query->get('code'),
		];
	}

	public function getUser($credentials, UserProviderInterface $userProvider): User {
		$token = $this->ts->getTokenFromCode($credentials['code']);

		$ui = $this->ts->getUserInfo($token['access_token']);

		$user = $this->em->getRepository(User::class)
			->findOneBy([
				'id' => $ui['id']
			]);

		if (null === $user) {
			$user = new User();
		}

		$user
			->setId($ui['id'])
			->setUsername($ui['login'])
			->setEmail($ui['email'])
			->setToken($token);

		$this->em->persist($user);
		$this->em->flush();

		return $user;
	}

	public function checkCredentials($credentials, UserInterface $user): bool {
		return !empty($credentials['code']);
	}

	public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse {
		return new RedirectResponse($this->ugi->generate('default_index'));
	}

	public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): RedirectResponse {
		if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
			return new RedirectResponse($targetPath);
		}

		return new RedirectResponse($this->ugi->generate('default_index'));
	}

	public function start(Request $request, AuthenticationException $authException = null): RedirectResponse {
		return new RedirectResponse($this->ts->getAuthorizeUrl());
	}

	public function supportsRememberMe(): bool {
		return true;
	}
}
