# Exploitation et recette de livraison

Ce document décrit une procédure à exécuter sur un environnement de préproduction
isolé, représentatif de la cible. Les résultats locaux des tests ne remplacent pas
l'application des migrations, la recette métier et une restauration réellement vérifiée
sur l'infrastructure de déploiement.

## Avant chaque mise en production

1. **Isoler la recette.** Utiliser une base PostgreSQL et des comptes sans accès aux
   données de production. Vérifier la cible avec `SELECT current_database()` avant
   toute opération de migration ou de restauration.
2. **Sauvegarder la cible.** Créer et vérifier une sauvegarde avant les migrations.
   Conserver le fichier hors du dépôt et avec des accès restreints.
3. **Vérifier les migrations.** Sur la préproduction restaurée ou son clone :

   ```sh
   php artisan migrate:status
   php artisan migrate --force
   php artisan migrate:status
   ```

   Examiner la sortie et les journaux. Ne pas exécuter `migrate:fresh` sur des données
   à conserver. Plusieurs migrations refusent explicitement un rollback qui perdrait
   l'historique; privilégier une migration corrective vers l'avant.
4. **Tester l'application.**

   ```sh
   php artisan test --compact
   php artisan route:list --path=api/v1
   php artisan schedule:list
   ```

   La suite PHPUnit utilise PostgreSQL et `centre_cours_test`; confirmer que cette base
   est dédiée aux tests et distincte de toute base contenant des données utiles.
5. **Faire la recette par rôle** avec des comptes de test distincts et des données
   identifiables :

   | Rôle | Parcours essentiels | Contrôles d'accès |
   |---|---|---|
   | Administration | Ouvrir le tableau de bord, gérer élèves/classes/inscriptions, saisir ou annuler un paiement, consulter rapports et exports. | Les parcours admin refusent les rôles non autorisés; l'annulation conserve la trace du paiement. |
   | Enseignant | Consulter ses affectations, faire l'appel, saisir et publier des notes, consulter son emploi du temps. | Une classe, une séance ou une évaluation hors affectation n'est pas accessible. |
   | Parent / tuteur | Consulter ses enfants, échéancier, impayés, paiements, reçus, présences, notes et communications. | Aucun détail ni reçu d'un enfant lié à un autre parent n'est exposé. |
   | Élève | Consulter son tableau de bord, ses notes publiées et son emploi du temps. | Les résultats et données d'un autre élève ne sont pas exposés. |

6. **Recetter l'API** avec un client HTTP :
   - `POST /api/v1/login` réussit avec un compte de test actif et refuse des identifiants
     invalides ou un compte désactivé.
   - Une route protégée refuse l'absence de jeton; `GET /api/v1/me` fonctionne avec le
     jeton valide.
   - Tester au moins un accès autorisé et un accès refusé par rôle, puis
     `POST /api/v1/logout` et vérifier que le jeton révoqué ne permet plus d'accès.
   - Vérifier les opérations d'écriture permises, leurs erreurs de validation et les
     données persistées, sans envoyer de données réelles de familles dans la recette.
7. **Valider l'exploitation** : planificateur actif, travailleurs de file si des tâches
   sont mises en file, réception des erreurs serveur et restauration du test de
   sauvegarde décrite plus bas.

Les tests automatisés pertinents comprennent notamment
`tests/Feature/Auth/RoleAccessTest.php`,
`tests/Feature/Api/V1/ApiAccessTest.php`,
`tests/Feature/Attendance/AttendancePolicyTest.php`,
`tests/Feature/Billing/ReceiptAccessTest.php`,
`tests/Feature/Guardians/GuardianFinancialTest.php` et
`tests/Feature/ReportingTest.php`. La recette manuelle valide les opérations réelles,
la navigation et la configuration du déploiement que ces tests ne peuvent pas couvrir.

## Sauvegarde et exercice de restauration PostgreSQL

La restauration doit se faire dans une base neuve dédiée, jamais par-dessus la base
source. Les commandes suivantes sont un exemple d'exercice à adapter à la politique
d'accès et aux noms de la plateforme. Fournir l'authentification via `.pgpass` ou le
gestionnaire de secrets de l'environnement; ne pas placer de mot de passe dans le
dépôt ni dans l'historique du terminal.

```sh
export SOURCE_DB=centre_cours
export RESTORE_DB=centre_cours_restore_check
export BACKUP_FILE=/secure-backups/centre-cours-before-release.dump

pg_dump --format=custom --no-owner --no-acl --file="$BACKUP_FILE" "$SOURCE_DB"
pg_restore --list "$BACKUP_FILE"
createdb "$RESTORE_DB"
pg_restore --exit-on-error --no-owner --no-acl --dbname="$RESTORE_DB" "$BACKUP_FILE"
psql --dbname="$RESTORE_DB" --command='SELECT current_database(), COUNT(*) FROM migrations;'
```

Après restauration :

- Vérifier le nom de la base et la version attendue dans `migrations`.
- Vérifier que les tables métier et leurs contraintes sont présentes, et comparer des
  effectifs agrégés convenus avec l'équipe sans exporter de données personnelles.
- Démarrer une instance de préproduction configurée uniquement sur la base restaurée;
  tester une connexion de recette, les tableaux de bord, un reçu, un rapport et un
  export.
- Consigner la durée de sauvegarde/restauration, les erreurs rencontrées et les
  résultats, puis supprimer la base de vérification seulement après confirmation
  explicite qu'elle n'est plus nécessaire.

À décider avec le responsable métier/exploitation avant lancement : fréquence des
sauvegardes, durée de conservation, chiffrement et lieu de stockage, objectif de perte
de données (RPO) et délai de reprise (RTO). Ce dépôt ne fixe pas ces engagements.

## Planificateur

Le projet enregistre le nettoyage périodique des notifications dans
`routes/console.php`. En local, inspecter les tâches avec `php artisan schedule:list`
et, si nécessaire, les exécuter avec `php artisan schedule:work`.

En production, configurer un seul déclencheur cron par minute sous l'utilisateur
d'exploitation du site :

```cron
* * * * * cd /chemin/vers/centre-cours && php artisan schedule:run >> /dev/null 2>&1
```

Surveiller l'exécution de la commande planifiée et les erreurs dans les journaux.
`php artisan schedule:clear-cache` ne doit servir qu'à résoudre un verrou de tâche
confirmé comme périmé.

## Files d'attente

La configuration par défaut est `QUEUE_CONNECTION=database`; les tables de jobs sont
créées par les migrations Laravel. Au moment de cette documentation, les listeners
applicatifs observés ne déclarent pas `ShouldQueue`. Si une fonctionnalité ou une
notification est déplacée vers une file asynchrone, superviser un processus persistant
comme suit :

```sh
php artisan queue:work database --sleep=3 --tries=3 --timeout=60
```

Le timeout du worker est plus court que `retry_after=90` de la connexion database.
Utiliser un superviseur de processus du système d'exploitation pour redémarrage,
arrêt contrôlé et journalisation; `queue:work` seul dans une session SSH n'est pas un
déploiement de worker durable. Surveiller les jobs en attente et consulter les échecs
avec :

```sh
php artisan queue:failed
```

Diagnostiquer une erreur avant de relancer un job avec `php artisan queue:retry`.

## Surveillance et configuration du déploiement

- En production, régler `APP_ENV=production`, `APP_DEBUG=false`, une `APP_KEY` stable,
  `APP_URL` et les secrets PostgreSQL sur la plateforme; ne pas déployer les comptes
  ni les mots de passe de démonstration.
- Après une modification des variables d'environnement ou de configuration, reconstruire
  le cache avec `php artisan config:cache` dans la procédure de déploiement.
- Collecter et surveiller le canal de logs configuré (`storage/logs/laravel.log` avec
  les valeurs d'exemple), les erreurs HTTP 5xx, les erreurs SQL, les échecs de jobs et
  les alertes de capacité disque/base de données. Définir les seuils et la rétention
  avec l'équipe d'exploitation.
- Vérifier régulièrement les sauvegardes, le stockage disponible, le fonctionnement du
  scheduler et l'état des workers effectivement déployés.

**État de validation :** les tests automatisés de l'application sont exécutables contre
la base de test isolée. L'application des migrations sur l'environnement de déploiement,
la recette manuelle par rôle sur cet environnement et la restauration d'une sauvegarde
de celui-ci restent à exécuter et à consigner par l'équipe disposant des accès.
