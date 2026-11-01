# Centre de Cours AfriTech

Application web de gestion d'un établissement scolaire : élèves et familles, classes,
inscriptions, emplois du temps, présences, évaluations et bulletins, paiements et reçus,
communications et tableaux de pilotage.

L'interface est organisée par rôle (administration, enseignant, parent/tuteur et élève).
Une API REST versionnée `/api/v1` permet également l'accès aux principales données et
opérations, avec authentification par jeton Sanctum et contrôles d'accès.

## Technologies

- PHP 8.3 ou supérieur (développé avec PHP 8.5) et Laravel 13.
- PostgreSQL. Plusieurs migrations s'appuient sur des fonctionnalités PostgreSQL, dont
  les contraintes `CHECK`, les index partiels et les clés étrangères composites.
- Configuration régionale par défaut : français (`fr`) et fuseau horaire
  `Africa/Porto-Novo`. Les dates affichées suivent `JJ/MM/AAAA`; les dates des exports
  CSV restent au format ISO `AAAA-MM-JJ`.
- Composer, Node.js et npm pour les dépendances PHP et les ressources frontend.
- Blade, Vite, Tailwind CSS et Alpine.js côté interface.
- Pest pour les tests.

## Démarrage local

### 1. Préparer PostgreSQL

Créer une base de données et un utilisateur PostgreSQL disposant des droits sur cette
base, puis renseigner leurs paramètres dans `.env`. Exemple de création avec un compte
PostgreSQL déjà autorisé :

```sh
createdb -U postgres centre_cours
```

Dans `.env`, vérifier au minimum :

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_TIMEZONE=Africa/Porto-Novo

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=centre_cours
DB_USERNAME=postgres
DB_PASSWORD=
```

Adapter le nom de base, l'utilisateur et le mot de passe à l'installation locale.
Ne pas reprendre ces valeurs d'exemple telles quelles pour un environnement partagé ou
de production.

### 2. Installer et initialiser l'application

À la racine du dépôt :

```sh
composer install
test -f .env || cp .env.example .env
```

Configurer PostgreSQL dans `.env`, puis exécuter :

```sh
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

`DatabaseSeeder` installe les données de référence et, uniquement lorsque
`APP_ENV=local` ou `APP_ENV=testing`, ajoute les données scolaires fictives. Le seeder
de démonstration est réexécutable sans créer de doublons.

### 3. Lancer l'application

Dans un terminal :

```sh
php artisan serve
```

Dans un autre terminal, pour reconstruire les ressources à chaque modification :

```sh
npm run dev
```

Ouvrir ensuite l'URL locale configurée dans `APP_URL` (par défaut
`http://localhost:8000`).

## Comptes de démonstration

Disponibles après `php artisan migrate --seed` en environnement local ou de test :

| Rôle | Adresse | Mot de passe local |
|---|---|---|
| Administrateur | `admin@centre.test` | `password` |
| Enseignant | `enseignant@centre.test` | `password` |
| Parent / tuteur | `parent@centre.test` | `password` |
| Élève | `eleve@centre.test` | `password` |

Ces comptes et leurs données sont fictifs. **Ne pas utiliser ce mot de passe en
production ni exposer ces comptes sur un environnement accessible au public.** Le
seeder des comptes ne crée pas ces comptes en production; le seeder scolaire de
démonstration s'arrête également en production.

## Tests et qualité

La configuration PHPUnit utilise PostgreSQL et cible la base `centre_cours_test`.
Créer cette base et fournir les paramètres de connexion PostgreSQL nécessaires dans
`.env.testing` avant le premier lancement. Les tests recréent leurs données : ne jamais
les lancer contre une base contenant des données à conserver.

```sh
createdb -U postgres centre_cours_test
```

```sh
php artisan test --compact
```

Pour une suite ciblée :

```sh
php artisan test --compact tests/Feature/Billing
```

Formatage PHP :

```sh
vendor/bin/pint --dirty --format agent
```

Compilation de production du frontend :

```sh
npm run build
```

## API REST

Les routes versionnées sont déclarées dans `routes/api.php`. Elles couvrent notamment
les utilisateurs, élèves, enseignants, classes, matières, présences, notes, paiements
et emplois du temps. Pour consulter la liste à jour des routes :

```sh
php artisan route:list --path=api/v1
```

L'authentification utilise des jetons Sanctum :

1. `POST /api/v1/login` avec `email`, `password` et, facultativement, `device_name`.
2. Pour les routes protégées, envoyer le champ `access_token` de la réponse dans
   l'en-tête d'authentification HTTP au format Bearer.
3. `GET /api/v1/me` renvoie le profil du compte authentifié.
4. `POST /api/v1/logout` révoque le jeton courant.

Exemple de connexion (remplacer les valeurs fictives par un compte autorisé) :

```sh
curl -X POST http://localhost:8000/api/v1/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@centre.test","password":"password","device_name":"local"}'
```

Les opérations d'écriture et la visibilité des données dépendent des autorisations du
rôle. La route historique `GET /api/user` est également présente; pour les nouvelles
intégrations, utiliser les routes versionnées `/api/v1`.

## Organisation du code

- `app/Http/Controllers/Web` : parcours web et tableaux de bord par rôle.
- `app/Http/Controllers/Api/V1` et `app/Http/Resources/Api/V1` : contrôleurs et formats
  de réponse de l'API versionnée.
- `app/Models` : modèles Eloquent et relations.
- `app/Services` : règles métier, dont les calculs financiers et les rapports.
- `app/Policies`, `app/Http/Requests` et `app/Http/Middleware` : autorisation,
  validation et contrôles d'accès.
- `database/migrations`, `database/factories` et `database/seeders` : schéma, données de
  test et données de démonstration.
- `resources/views` et `resources/js` : vues Blade et ressources frontend.
- `tests/Feature` et `tests/Unit` : tests Pest.

Le diagramme relationnel simplifié est dans [`docs/database-schema.md`](docs/database-schema.md).
Les migrations restent la source de vérité pour les contraintes et colonnes du schéma.

Les consignes d'exploitation, de recette par rôle, de migration et de restauration
PostgreSQL sont dans [`docs/operations-and-acceptance.md`](docs/operations-and-acceptance.md).
