<?php
namespace App\Security;

use InvalidArgumentException;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use function count;

class CustomLdapUserProvider extends LdapUserProvider {

	private $passwordAttribute;
	private array $extraFields = ["mail", "sn", "givenName"];
	private bool $allowBlankFields = true;

	/**
	 * Loads a user from an LDAP entry
	 *
	 * @param string $identifier
	 * @param Entry $entry
	 * @return LdapUser
	 */
	protected function loadUser(string $identifier, Entry $entry): LdapUser {
		$password = null;
		$extraFields = [];
		$roles = ["ROLE_USER"];

		if ($this->passwordAttribute !== null) {
			$password = $this->getAttributeValue($entry, $this->passwordAttribute);
		}

		foreach ($this->extraFields as $field) {
			$extraFields[$field] = $this->getAttributeValue($entry, $field);
		}

		if (strpos($entry->getDn(), "01-Service Informatique") !== false) {
			$roles[] = "ROLE_ADMIN";
		}

		return new LdapUser($entry, $identifier, $password, $roles, $extraFields);
	}

	/**
	 * @param Entry $entry
	 * @param string $attribute
	 * @return mixed|void
	 */
	private function getAttributeValue(Entry $entry, string $attribute) {
		if (!$entry->hasAttribute($attribute)) {
			if (!$this->allowBlankFields) {
				throw new InvalidArgumentException(sprintf('Missing attribute "%s" for user "%s"', $attribute, $entry->getDn()));
			}

			return "";
		}

		$values = $entry->getAttribute($attribute);

		if (count($values) !== 1) {
			throw new InvalidArgumentException(sprintf('Attribute "%s" has multiple values.', $attribute));
		}

		return $values[0];
	}

}
