<?php
namespace App\Service;

use AGI_AsteriskManager;
use Exception;

class AsteriskApi {

	public static string $ASTERISK_DEL_CMD = 'database del conf ';
	public static string $ASTERISK_ADD_CMD = 'database put conf ';

	public static string $ASTERISK_USERS_FILE = '/etc/asterisk/pjsip.conf';
	public static string $ASTERISK_USERS_HEADER = '; ### [USERS LIST] ###';

	/**
	 * @return void
	 */
	public static function cron(): void {
		$today = date('d-m-Y G:i:s');

		foreach(AsteriskAPI::getConfList() as $call) {
			if((strtotime($today) - strtotime(date($call['end']))) > (24 * 3600)) { //Conférence plus vielle de 1 jour
				AsteriskAPI::deleteConference($call['id']);
			}
		}
	}

	/**
	 * Delete a conference by his id
	 * @param $id string|int The conference ID to delete
	 */
	public static function deleteConference($id): void {
		$asm = new AGI_AsteriskManager();

		if($asm->connect()) {
			$asm->command(AsteriskApi::$ASTERISK_DEL_CMD . $id . '/creation');
			$asm->command(AsteriskApi::$ASTERISK_DEL_CMD . $id . '/user');
			$asm->command(AsteriskApi::$ASTERISK_DEL_CMD . $id . '/utilisation');
			$asm->command(AsteriskApi::$ASTERISK_DEL_CMD . $id . '/pin');
			$asm->command(AsteriskApi::$ASTERISK_DEL_CMD . $id . '/start');
			$asm->command(AsteriskApi::$ASTERISK_DEL_CMD . $id . '/end');

			$asm->disconnect();
		}
	}

	/**
	 * @return int
	 */
	private static function r(): int {
		try {
			return random_int(1000, 9999);
		} catch (Exception $e) {
			return rand(1000, 9999);
		}

	}

	/**
	 * Create a conference
	 *
	 * @param $user string L'utilisateur
	 * @param $date string Date de la conférence
	 * @param $start string Heure de début de la conférence (heure:minute)
	 * @param $end string Heure de fin de la conférence (heure:minute)
	 * @since 1.0
	 * @author Romain Neil
	 * @return array
	 * @throws Exception
	 */
	public static function createConf(string $user, string $date, string $start, string $end): array {
		$asm = new AGI_AsteriskManager();

		$conferences = [];

		$confList = AsteriskAPI::getConfList($asm);
		$confNumber = AsteriskAPI::r(); //1000-9999
		$confPin = AsteriskAPI::r(); //1000-9999

		for($i = 0; $i != sizeof($confList); $i++) {
			$conferences[$i] = $confList[$i]["id"];
		}

		while(in_array($confNumber, $conferences)) {
			$confNumber = random_int(1000, 9999);
			$confList = AsteriskAPI::getConfList();

			for($i = 0; $i != sizeof($confList); $i++) {
				$conferences[$i] = $confList[$i]["id"];
			}
		}

		if($asm->connect()) {
			$asm->command(AsteriskApi::$ASTERISK_ADD_CMD . $confNumber . '/pin ' . $confPin);
			$asm->command(AsteriskApi::$ASTERISK_ADD_CMD . $confNumber . '/creation "' . date('d-m-Y H:i') . '"');
			$asm->command(AsteriskApi::$ASTERISK_ADD_CMD . $confNumber . '/start "' . date('d-m-Y H:i', strtotime($date . " " . $start)) . '"');
			$asm->command(AsteriskApi::$ASTERISK_ADD_CMD . $confNumber . '/end "' . date('d-m-Y H:i', strtotime($date . " " . $end)) . '"');
			$asm->command(AsteriskApi::$ASTERISK_ADD_CMD . $confNumber . '/user ' . $user);

			$asm->disconnect();
		}

		return [
			"id" => $confNumber,
			"pin" => $confPin,
			"start" => $start,
			"end" => $end
		];

	}

	/**
	 * Récupère la liste des conférences en cours
	 *
	 * @since 1.0
	 * @author Romain Neil
	 * @return array
	 */
	public static function getCurrentConfs(): array {
		$asm = new AGI_AsteriskManager();
		$result = [];

		if($asm->connect()) {
			$cmd = $asm->command('confbridge list');
			$i = 0;
			$j = 0;

			foreach(explode("\n", $cmd["data"]) as $line) {
				$i++;

				if($i < 4) {
					//Ignore les 2 premières lignes
					continue;
				}

				if($line != "") {
					$ln = explode(" ", $line);

					$result[$j]["id"] = $ln[0];
					$result[$j]["nb"] = $ln[34];
				}

				$j++;
			}

			$asm->disconnect();
		}

		return $result;
	}

	/**
	 * Récupère les conférences
	 *
	 * @param AGI_AsteriskManager|null $asm
	 * @return array
	 */
	public static function getConfList(AGI_AsteriskManager $asm = null): array {
		if(is_null($asm)) {
			$asm = new AGI_AsteriskManager();
		}

		if($asm->connect())  {
			//$result = $asm->command("database show conf ".$_SERVER['REMOTE_USER']);
			$result = $asm->command("database show conf");

			if(!isset($result["data"])) {
				return [];
			} else {
				$confs = [];

				foreach(explode("\n", $result['data']) as $line) {
					if (preg_match("/conf/", $line)) {
						$pieces = explode("/", $line);

						$status = explode(" : ", $pieces[3]);

						$status[0] = trim($status[0]);

						$pieces[3] = $status;

						if(!isset($confs[$pieces[2]])) {
							$confs[$pieces[2]] = array();
							$confs[$pieces[2]]["id"] = $pieces[2];
						}

						$confs[$pieces[2]][$status[0]] = trim($status[1]);
					}
				}
				$asm->disconnect();
				sort($confs);

				return $confs;
			}
		}

		return [];
	}

	/**
	 * Read the asterisk user files, then return an array of users (id, username)
	 */
	public static function listUsers(): array {
		$file = new \SplFileObject(self::$ASTERISK_USERS_FILE);
		$users = [];
		$i = 0;

		foreach($file as $line) {
			if (!str_contains($line, self::$ASTERISK_USERS_HEADER)) {
				$i++;
			} else {
				//Reach begin of users definition
				break;
			}
		}

		$iterator = new \LimitIterator($file, $i);
		$j = 0;
		foreach ($iterator as $line) {
			$ln = trim($line);

			// Extract user id
			if (str_starts_with($ln, '[') && preg_match('/\[([0-9]+)\]/', $ln, $matches)) {
				$userId = $matches[1];

				// Verify if user already parsed
				if (empty($users[$userId])) {
					$users[$userId] = [];
				}
			}

			// Get interesting lines
			if (str_contains($ln, '=')) {
				$explodedLine = explode('=', $line);

				if ($explodedLine[0] === 'username') {
					$users[$userId] = trim($explodedLine[1]);
				}
			}
		}

		return $users;
	}

}
