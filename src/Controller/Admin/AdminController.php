<?php
namespace App\Controller\Admin;

use App\Service\AsteriskApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController {

	#[Route('/', name: 'home')]
	public function home(): Response {
		return $this->render('admin/home.html.twig', [
			"calls" => AsteriskApi::getConfList(),
			"confs" => AsteriskApi::getCurrentConfs()
		]);
	}

}
