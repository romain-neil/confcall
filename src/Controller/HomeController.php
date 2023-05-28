<?php
namespace App\Controller;

use App\Service\AsteriskApi;
use DateTime;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Swift_Mime_SimpleMessage;
use Swift_SmtpTransport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController {

	/**
	 * @Route("/", name="app_index")
	 * @return Response
	 * @IsGranted("ROLE_USER")
	 */
	public function index(): Response {
		return $this->render("index.html.twig", [
			"calls" => AsteriskApi::getConfList()
		]);
	}

	/**
	 * @Route("/admin", name="admin")
	 * @IsGranted("ROLE_ADMIN")
	 * @return Response
	 */
	public function admin(): Response {
		return $this->render("admin.html.twig", [
			"calls" => AsteriskApi::getConfList(),
			"confs" => AsteriskApi::getCurrentConfs()
		]);
	}

	/**
	 * Tâche cron qui est appelée en ajax depuis l'administration
	 *
	 * @Route("/cron", name="cron")
	 * @IsGranted("ROLE_ADMIN")
	 * @return Response
	 */
	public function cron(): Response {
		AsteriskApi::cron();

		return new Response("OK.");
	}

	/**
	 * @Route("/create", name="create")
	 * @param Request $req
	 * @return Response
	 * @IsGranted("ROLE_USER")
	 * @throws Exception
	 */
	public function create(Request $req): Response {
		$user = $this->getUser();
		$date = new DateTime($req->query->get('d'));
		$now = new DateTime("now");
		$now->setTime(0, 0);

		if($date >= $now) {
			$conf = AsteriskAPI::createConf(
				$user->getUserIdentifier(),
				$req->query->get('d'),
				$req->query->get('s'),
				$req->query->get('e')
			);

			return new Response("OK, ConfID = " . $conf['id'] . ', PIN = ' . $conf['pin']);
		}

		return new Response("ERROR: DATE IN THE PAST");
	}

	/**
	 * @Route("/del/{id}", name="del_conf")
	 * @IsGranted("ROLE_USER")
	 * @param $id
	 * @return RedirectResponse
	 */
	public function deleteConf($id): RedirectResponse {
		AsteriskAPI::deleteConference($id);

		return $this->redirect("/");
	}

	/**
	 * @Route("/pdf", name="gen_pdf")
	 * @IsGranted("ROLE_USER")
	 * @param Request $request
	 * @return RedirectResponse|Response
	 * @throws Html2PdfException
	 */
	public function genPdf(Request $request) {
		/** @var \Symfony\Component\Ldap\Security\LdapUser $user */
		$user = $this->getUser();

		$id = $request->query->get('id');
		$pin = $request->query->get('pin');
		$start = urldecode($request->query->get('start'));
		$end = urldecode($request->query->get('end'));
		$shouldSendMail = $request->query->get('sendMail', false);

		//ex: mr.michu@gmail.com;mme.michu@isp.net,...
		$addresses = $request->query->get('addr', "");

		$pdf = new Html2Pdf();
		$pdf->writeHTML($this->renderView("pdf/invitation.html.twig", [
			"d" => date('d/m/Y', strtotime($start)),
			"start" => date('G:i', strtotime($start)),
			"end" => date('G:i', strtotime($end)),
			"num" => $id,
			"pin" => $pin
		])
		);

		if($shouldSendMail) {
			$transport = new Swift_SmtpTransport("ip_serveur_smtp", 25); // ip/url ; port

			//Attention: ne pas supprimer les 2 prochaines lignes
			$transport->setUsername(""); //Mettre ici l'utilisateur smtp
			$transport->setPassword(""); //Mettre ici le mot de passe de l'utilisateur

			$mailer = new Swift_Mailer($transport);

			$attachment = new Swift_Attachment($pdf->output('', 'S'), "invitation_$id.pdf", "application/pdf");

			$message = (new Swift_Message())
				->setSubject("Fiche récapitulative audio conférence")
				->setFrom("no-reply@company.com", "ne-pas-repondre")
				->setTo($user->getEntry()->getAttribute('mail'))
				->setBody($this->renderView('emails/invitation.html.twig'))
				->setContentType('text/html')
				->setPriority(Swift_Mime_SimpleMessage::PRIORITY_HIGH)
				->attach($attachment)
			;

			//On a plusieurs messages à envoyer
			if($addresses !== false && $addresses !== "") {
				$message->setFrom($user->getExtraFields()["mail"]);
				$addresses = explode(";", urldecode($addresses));

				foreach($addresses as $addr) {
					if($addr !== '') {
						$message->setTo($addr);
						$mailer->send($message);
					}
				}

				$this->addFlash(
					'success',
					'La fiche récapitulative a bien été envoyée aux utilisateurs'
				);
			} else {
				$mailer->send($message);

				$this->addFlash(
					'success',
					'Le message a bien été envoyé sur votre mail'
				);
			}

			return $this->redirect("/");
		} else {
			//On affiche juste le pdf
			try {
				return new Response(
					$pdf->output("invitation_$id.pdf", "S"),
					200,
					[
						"Content-Type" => "application/pdf",
						'Content-Disposition' => 'attachment; filename="invitation_' . $id . '.pdf"'
					]
				);
			} catch (Html2PdfException $e) {
				$formatter = new ExceptionFormatter($e);

				return new Response($formatter->getHtmlMessage(), 500);
			}
		}
	}

}
