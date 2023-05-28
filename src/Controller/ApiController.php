<?php
namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ApiController
 * @package App\Controller
 * @Route("/api", name="api_")
 */
class ApiController extends AbstractController {

	/**
	 * @Route("/search_user", name="search_user")
	 * @IsGranted("ROLE_USER")
	 * @param Request $req
	 * @return JsonResponse
	 */
	public function search_user(Request $req): JsonResponse {
		$username = $req->query->get('username');

		$ldap = Ldap::create("ext_ldap", [
			'host' => 'ldap_server_url'
		]);
		$ldap->bind("dn", "pass");
		$query = $ldap->query('dc=my-company,dc=com', "(&(objectClass=person)(cn=*$username*))");
		$results = $query->execute()->toArray();

		$arr = [];
		foreach ($results as $r) {

			$desc = $r->getAttribute("description"); //On assume qu'il n'y a qu'une valeur dans le champ description

			if(!is_null($desc)) {
				//On vérifie si le compte est désactivé
				if(strpos(strtolower($desc[0]), "compte desactive") !== false) {
					//Compte désactivé
					continue;
				}
			}

			$mail = $r->getAttribute('mail');

			$arr[] = [
				"nom" => $r->getAttribute('cn')[0],
				"mail" => !is_null($mail) ? (sizeof($mail) == 1 ? $mail[0] : '') : ''
			];
		}

		return new JsonResponse($arr);
	}

}
