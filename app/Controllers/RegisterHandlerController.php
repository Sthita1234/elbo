<?php

namespace Elbo\Controllers;

use ReCaptcha\ReCaptcha;
use Elbo\{Library\Controller, Models\User, Library\Email};
use Symfony\Component\HttpFoundation\{Request, Response, RedirectResponse};

class RegisterHandlerController extends Controller {
	use \Elbo\Middlewares\Session;
	use \Elbo\Middlewares\CSRFProtected;
	use \Elbo\Middlewares\RedirectIfLoggedIn;

	protected $middlewares = [
		'manageSession',
		'redirectIfLoggedIn',
		'csrfProtected'
	];

	public function run(Request $request, array &$data) {
		$ip = $request->getClientIp();

		$email = $request->request->get('email');
		$password = $request->request->get('password');
		$agree_tos = $request->request->get('agree_tos');
		$grecaptcha_resp = $request->request->get('g-recaptcha-response');
		$password_confirm = $request->request->get('password_confirmation');

		$twig = $this->container->get(\Twig_Environment::class);
		$recaptcha = $this->container->get(ReCaptcha::class);

		if (!$recaptcha->verify($grecaptcha_resp, $ip)->isSuccess()) {
			return new Response($twig->render('auth/register.html.twig', [
				'email' => $email,
				'errors' => [
					'captcha' => true
				]
			]));
		}

		$errors = [];

		if (strlen($password) < 6) {
			$errors['password'] = true;
		}
		else if ($password_confirm !== $password) {
			$errors['password_confirm'] = true;
		}

		try {
			$normalized_email = Email::normalize($email);

			if (strlen($email) > 100 || !Email::isAllowed($email)) {
				$errors['email'] = 1;
			}
			else if (User::where('normalized_email', $normalized_email)->count() !== 0) {
				$errors['email'] = 2;
			}
		}
		catch (\InvalidArgumentException $e) {
			$errors['email'] = 1;
		}

		if (!$agree_tos) {
			$errors['agree_tos'] = true;
		}

		if ($errors) {
			return new Response($twig->render('auth/register.html.twig', [
				'errors' => $errors,
				'email' => $email,
			]));
		}

		$time = time();

		$user = User::create([
			'email' => $email,
			'password' => password_hash($password, PASSWORD_DEFAULT),
			'normalized_email' => $normalized_email,
			'created_at' => $time,
			'created_from' => $ip,
			'last_login' => $time,
			'last_login_ip' => $ip,
			'admin' => false,
			'disabled' => false
		]);

		if (!$this->session->isStarted()) {
			$this->session->start();
		}
		else {
			$this->session->regenerate();
		}

		$this->session->set('userid', $user->id);
		return new RedirectResponse('/');
	}
}