<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LdapFormAuthenticator extends AbstractFormLoginAuthenticator {
	use TargetPathTrait;

	public const LOGIN_ROUTE = 'app_login';

	private UrlGeneratorInterface $urlGenerator;
	private CsrfTokenManagerInterface $csrfTokenManager;
	protected Ldap $ldap;

	public function __construct(UrlGeneratorInterface $urlGenerator, CsrfTokenManagerInterface $csrfTokenManager, Ldap $ldap) {
		$this->urlGenerator = $urlGenerator;
		$this->csrfTokenManager = $csrfTokenManager;
		$this->ldap = $ldap;
	}

	public function supports(Request $request): bool {
		return self::LOGIN_ROUTE === $request->attributes->get('_route')
			&& $request->isMethod('POST');
	}

	public function getCredentials(Request $request): array {
		$credentials = [
			'username' => $request->request->get('username'),
			'password' => $request->request->get('password'),
			'csrf_token' => $request->request->get('_csrf_token'),
		];
		$request->getSession()->set(
			Security::LAST_USERNAME,
			$credentials['username']
		);

		return $credentials;
	}

	public function getUser($credentials, UserProviderInterface $userProvider): ?UserInterface {
		$token = new CsrfToken('authenticate', $credentials['csrf_token']);
		if (!$this->csrfTokenManager->isTokenValid($token)) {
			throw new InvalidCsrfTokenException();
		}

		/** @var LdapUser $user */
		$user = $userProvider->loadUserByUsername($credentials['username']);

		if (!$user) {
			// fail authentication with a custom error
			throw new CustomUserMessageAuthenticationException('Username could not be found.');
		}

		return $user;
	}

	public function checkCredentials($credentials, UserInterface $user): bool {
		try {
			$this->ldap->bind($user->getEntry()->getDn(), $credentials['password']);
		} catch (ConnectionException $e) {
			return false;
		}

		return true;
	}

	public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): RedirectResponse {
		if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
			return new RedirectResponse($targetPath);
		}

		return new RedirectResponse($this->urlGenerator->generate("app_index"));
	}

	protected function getLoginUrl(): string {
		return $this->urlGenerator->generate(self::LOGIN_ROUTE);
	}

}
