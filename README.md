# Présentation

Confcall est une interface web conçue afin de planifier des conférences audio avec Asterisk, avec comme base d'utilisateur un serveur ldap/ad.

Elle utilise le framework symfony afin de permettre une grande modularité, tout en ayant la possibilité de modifier selon ses besoins.

Le logiciel s'assure que le numéro de conférence est unique.

![page de connexion](https://dl.tutosfaciles48.fr/confcall/login_final.png)

![page d'accueil](https://dl.tutosfaciles48.fr/confcall/index_final.png)

![page d'administration](https://dl.tutosfaciles48.fr/confcall/admin_final.png)

# Installation et configuration

## Prérequis techniques
Php : ^7.4 avec le module ldap d'activé dans php.ini

Serveur web : de préférence apache ou nginx

Asterisk version 13.33.0

## Installation

Pour installer, rien de plus simple :

`git clone https://github.com/romain-neil/confcall.git`

Se placer dans le répertoire, puis faire : `composer install`

Note : Cette commande demande que composer soit installé (https://getcomposer.org/download/)

À noter : si vous n'utiliser pas apache comme serveur web, il sera nécessaire de mettre en place la réécriture d'url, comme indiqué [ici](https://symfony.com/doc/current/setup/web_server_configuration.html).

Actuellement, la dépendance qui gère la communication avec le serveur asterisk ne gérant pas le versioning, il est obligatoire de récupérer l'archive contenant la dépendance *phpagi* à l'adresse https://dl.tutosfaciles48.fr/confcall/d4rkstar_phpagi.zip.
Une fois l'archive décompressée dans le dossier vendor/ à la racine, ne pas oublier de modifier le fichier vendor/d4rkstart/phpagi/phpagi-asmanager.php aux lignes 118 à 121 avec: l'adresse du serveur asterisk, l'utilisateur **autorisé** à se connecter, ainsi que sont mot de passe.

Une autre dépendance, html2pdf, connait également actuellement un problème, qui peut se résoudre en récupérant l'archive de cette dépendance à l'adresse https://dl.tutosfaciles48.fr/confcall/html2pdf.zip

## Configuration supplémentaire

Afin d'adapter l'interface à votre organisation, il faut modifier certains fichiers avec vôtre prope configuration.

- config/service.yaml : paramètres du serveur ldap
- config/package/twig.yaml : variables globales des templates (adresse de l'entreprise/administration et son nom)
- src/Security/CustomLdapUserProvider : groupe ldap/ad du service informatique (ligne 37)
- src/Controller/HomeController.php : ip du serveur smtp (ligne 131)

## Suppression des anciennes conférences

La purge s'effectue via l'administration (bouton Purger) ou encore via une tâche cron qui doit exécute le script /cron.php

ex: `0 0 * * * php /var/www/html/cron.php`

## Notes

Afin de prévenir la suppression des modifications effectuées au niveau des variables globales, il pourra être nécessaire de modifier ces valeurs dans les fichiers suivantes:
- templates/index.html.twig (ligne 37)
- templates/pdf/invitation.html.twig (ligne 40)

Vous pouvez utiliser un logo **logo.jpg** qui se trouve dans le répertoire public/assets/img .

**Attention** Si confcall n'est pas installé dans le répertoire _/var/www/html_, il est obligatoire de mettre le chemin **absolu** du logo dans le fichier templates/pdf/invitation.html.twig (ligne 18)

Une fois sur le serveur de production, lancer la commande suivante: `composer dump-env prod`

Afin d'assurer une compatibilité maximale, merci de s'asurer que les champs suivants sont renseignés sur le serveur LDAP/AD:
- givenName
- sn

Si le champs mail n'est pas présent pour l'utilisateur (ou si il est vide), seul l'export au format pdf lui sera proposé.

## Annexe

Si vous souhaitez vider le cache interne des templates, il suffit de lancer la commande suivante: `php bin/console cache:clear`

Testé avec apache et le module symfony/apache-pack
