<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Http\Message\MessageFactory;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class TwitchService {
	private EntityManagerInterface $em;
	private UrlGeneratorInterface $ugi;
	private MessageFactory $mf;
	private ClientInterface $client;

	private string $clientId;
	private string $clientSecret;

	public function __construct(EntityManagerInterface $em,
								UrlGeneratorInterface $ugi,
								MessageFactory $mf,
								ClientInterface $client) {
		$this->em     = $em;
		$this->ugi    = $ugi;
		$this->mf     = $mf;
		$this->client = $client;
	}

	public function setCredentials($clientId, $clientSecret) {
		$this->clientId     = $clientId;
		$this->clientSecret = $clientSecret;
	}

	public function getAuthorizeUrl() {
		return 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
				'client_id'     => $this->clientId,
				'redirect_uri'  => $this->ugi->generate('twitch_auth_return', [], UrlGeneratorInterface::ABSOLUTE_URL),
				'response_type' => 'code',
				'scope'         => 'user:read:email',
				'force_verify'  => 'true'
			]);
	}

	public function getTokenFromCode($code) {
		$req = $this->mf->createRequest('POST', 'https://id.twitch.tv/oauth2/token?' . http_build_query([
				'client_id'     => $this->clientId,
				'client_secret' => $this->clientSecret,
				'redirect_uri'  => $this->ugi->generate('twitch_auth_return', [], UrlGeneratorInterface::ABSOLUTE_URL),
				'grant_type'    => 'authorization_code',
				'scope'         => 'user:read:email',
				'code'          => $code
			]));

		$res = $this->client->sendRequest($req);

		if ($res->getStatusCode() !== 200) {
			throw new BadCredentialsException('Failed to request token from Twitch.');
		}

		$token = json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

		$token['created'] = time();

		return $token;
	}

	public function refreshToken($refreshToken) {
		$req = $this->mf->createRequest('POST', 'https://id.twitch.tv/oauth2/token?' . http_build_query([
				'client_id'     => $this->clientId,
				'client_secret' => $this->clientSecret,
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refreshToken
			]));

		$res = $this->client->sendRequest($req);

		if ($res->getStatusCode() !== 200) {
			throw new BadCredentialsException('Failed to request refresh token from Twitch.');
		}

		$token = json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

		$token['created'] = time();

		return $token;
	}

	public function isTokenExpired(array $token) {
		return time() >= $token['created'] + $token['expires_in'];
	}

	public function getUserInfo($token) {
		$uiReq = $this->mf->createRequest('GET', 'https://api.twitch.tv/helix/users', [
			'Authorization' => 'Bearer ' . $token,
			'Client-ID'     => $this->clientId
		]);

		$uiRes = $this->client->sendRequest($uiReq);

		return json_decode($uiRes->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR)['data'][0];
	}
}