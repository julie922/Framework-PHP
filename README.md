# Documentation Technique — API Marketplace de Services

## 1. Présentation du Projet

### Objectif

Créer une API backend sécurisée qui met en relation :
- **Demandeurs** : utilisateurs ayant des besoins spécifiques
- **Prestataires** : utilisateurs proposant des services

L'API est **stateless** (JWT), prête pour une consommation par application web ou mobile.

### Fonctionnalités Réalisées

#### Gestion des utilisateurs
- Création de compte et authentification JWT
- Système de rôles (Demandeur, Prestataire, Admin) avec hiérarchie
- Profils utilisateur modifiables

#### Gestion des contenus
- **Services** : les prestataires publient leurs offres avec titre, description, catégorie et prix
- **Demandes** : les demandeurs publient leurs besoins avec budget
- Consultation publique avec filtres (catégorie, prix, recherche texte)
- Modification/suppression réservée au propriétaire

#### Interactions entre utilisateurs
- Les prestataires soumettent des **propositions** en réponse aux demandes
- Système d'acceptation/refus des propositions
- Transitions d'état automatiques (cascade sur acceptation)

#### Interface web (Bonus)
- 7 pages Twig pour tester l'API sans Postman
- Le WebController appelle les services Symfony directement (pas via HTTP) pour éviter un deadlock PHP-CGI

---

## 2. Conception

### 2.1 Entités Principales

#### User
```
- id (UUID v4, généré manuellement)
- email (unique)
- password (hashé bcrypt via Symfony PasswordHasher)
- firstName, lastName
- roles (Many-to-Many → Role)
- createdAt, updatedAt
```

#### Role
```
- id (UUID v4)
- name (ROLE_USER | ROLE_PRESTATAIRE | ROLE_ADMIN)
- permissions (JSON, réservé pour extensions futures)
```

#### Service
```
- id (UUID v4)
- title, description, category
- price (float)
- prestataire (FK → User)
- status (active | inactive | archived)
- createdAt, updatedAt
```

#### Demande
```
- id (UUID v4)
- title, description, category
- budget (float)
- demandeur (FK → User)
- status (ouverte | en_cours | validée | fermée)
- createdAt, updatedAt
```

#### Proposition
```
- id (UUID v4)
- demande (FK → Demande)
- prestataire (FK → User)
- service (FK → Service, OPTIONNEL)
- price (float)
- message (text)
- status (en_attente | acceptée | refusée | annulée)
- createdAt, updatedAt
```

### 2.2 Relations Entre Entités

```
User ↔ Role            Many-to-Many
                       Un utilisateur peut avoir plusieurs rôles

User → Service         One-to-Many
                       Un prestataire crée plusieurs services

User → Demande         One-to-Many
                       Un demandeur crée plusieurs demandes

User → Proposition     One-to-Many
                       Un prestataire soumet plusieurs propositions

Demande → Proposition  One-to-Many
                       Une demande reçoit plusieurs propositions

Proposition → Service  Many-to-One (nullable)
                       Une proposition peut être liée à un service existant
```

### 2.3 Choix de Modélisation

#### 1. Many-to-Many pour User-Role

Un utilisateur peut être à la fois demandeur ET prestataire. La table de jointure permet de gérer ça sans dupliquer les comptes, et d'ajouter des rôles futurs sans migration complexe.

Hiérarchie côté application (security.yaml) :
```
ROLE_ADMIN
  ↳ hérite de ROLE_PRESTATAIRE et ROLE_USER

ROLE_PRESTATAIRE
  ↳ hérite de ROLE_USER

ROLE_USER
  ↳ rôle de base (demandeur)
```

#### 2. UUID v4 au lieu d'auto-increment

- Pas de séquence prévisible → les IDs ne sont pas énumérables
- Aucun risque de collision même en architecture distribuée
- Généré manuellement avec `random_bytes(16)` pour éviter une dépendance externe

#### 3. Statuts en constantes PHP

Les statuts sont définis comme constantes dans chaque entité (`STATUS_OUVERTE`, `STATUS_EN_COURS`…). Cela garantit l'intégrité et évite les typos. La validation `Assert\Choice(choices: [...])` s'appuie dessus.

#### 4. Proposition optionnellement liée à un Service

Un prestataire peut répondre à une demande en citant un de ses services existants, ou simplement faire une offre custom. Le champ `service` est nullable.

#### 5. TimestampableTrait

`createdAt` et `updatedAt` sont gérés par un trait partagé entre toutes les entités via les callbacks Doctrine `#[PrePersist]` et `#[PreUpdate]`.

---

## 3. Logique Métier

### 3.1 Transitions d'État

#### Demande
```
ouverte
  ↓ (une proposition est acceptée → automatique)
en_cours
  ↓ (manuel via PUT)
validée
  ↓ (manuel via PUT)
fermée
```

#### Service
```
active (par défaut)
  ↓
inactive (prestataire désactive temporairement)
  ↓
archived (suppression logique — disparaît des listes)
```

#### Proposition
```
en_attente (par défaut)
  ├→ acceptée  (demandeur accepte)
  └→ refusée   (demandeur refuse)

acceptée → annulée (si prestataire abandonne)
```

### 3.2 Cascades Automatiques

**Quand une Proposition est acceptée :**
```
1. Proposition.status = "acceptée"
2. Demande.status    = "en_cours"
3. Toutes les autres Propositions de cette Demande = "refusée"
```

**Quand une Demande est fermée :**
```
1. Demande.status = "fermée"
2. Toutes les Propositions "en_attente" passent à "annulée"
```

---

## 4. Architecture

### 4.1 Structure du Projet

```
src/
├── Controller/
│   ├── AuthController.php          register, login, refresh token
│   ├── UserController.php          profil /me, profil public /{id}
│   ├── ServiceController.php       CRUD services
│   ├── DemandeController.php       CRUD demandes
│   ├── PropositionController.php   soumettre, accepter/refuser
│   └── WebController.php           interface Twig (bonus)
│
├── Entity/
│   ├── User.php
│   ├── Role.php
│   ├── Service.php
│   ├── Demande.php
│   ├── Proposition.php
│   └── Traits/
│       └── TimestampableTrait.php  createdAt/updatedAt partagés
│
├── Repository/
│   ├── UserRepository.php
│   ├── RoleRepository.php
│   ├── ServiceRepository.php       filtres category/price/search/status
│   ├── DemandeRepository.php       filtres + ?mine=1
│   └── PropositionRepository.php
│
├── Service/
│   ├── JWTService.php              encode/decode JWT HS256 (implémentation manuelle)
│   ├── AuthService.php             register, login, refresh
│   ├── ServiceService.php          logique métier services
│   ├── DemandeService.php          logique métier demandes
│   └── PropositionService.php      acceptation + cascades automatiques
│
├── Security/
│   ├── JWTAuthenticator.php        valide le Bearer token sur chaque requête
│   └── ResourceVoter.php           vérifie la propriété d'une ressource
│
└── DataFixtures/
    └── AppFixtures.php

templates/                          interface Twig (bonus)
├── base.html.twig
├── home.html.twig
├── login.html.twig
├── register.html.twig
├── dashboard.html.twig
├── services.html.twig
└── demandes.html.twig
```

### 4.2 Flux d'une Requête HTTP

```
1. Client → GET /api/services/abc123
            Authorization: Bearer eyJhbG...

2. JWTAuthenticator
   └── vérifie signature HMAC-SHA256
   └── vérifie expiration (exp < now)
   └── vérifie type = "access" (rejette les refresh tokens)
   └── charge l'utilisateur depuis la DB

3. Route Matcher → ServiceController::show()

4. ResourceVoter (si PUT/DELETE)
   └── user est propriétaire ? ou ROLE_ADMIN ?

5. Contrôleur
   └── valide les inputs (Assert Symfony Validator)
   └── appelle le Service métier
   └── retourne JsonResponse

6. Client reçoit la réponse JSON
```

### 4.3 Séparation des Responsabilités

| Couche | Responsabilité |
|--------|----------------|
| **Controller** | Valider l'input HTTP, appeler le Service, retourner JSON |
| **Service** | Logique métier, transitions d'état, cascades |
| **Repository** | Requêtes spécialisées (filtres, recherches) |
| **Entity** | Structure de données, getters/setters, constantes de statut |
| **Security** | Authentification JWT, vérification de propriété (Voter) |

### 4.4 Choix Techniques

| Composant | Choix | Justification |
|-----------|-------|---------------|
| **Framework** | Symfony 8.0 | Standard pro, écosystème mature, sécurité intégrée |
| **PHP** | 8.4+ | Typage fort, performances, fibers |
| **ORM** | Doctrine 3 | Abstraction BD, migrations, relations automatiques |
| **Authentification** | JWT HS256 (implémentation manuelle) | Stateless, mobile-friendly. LexikJWT non disponible (problème réseau), implémentation manuelle conforme RFC 7519 |
| **Base de données** | SQLite | Zéro configuration, inclus dans PHP, idéal pour une démo/projet académique |
| **Hachage** | bcrypt via Symfony PasswordHasher | Standard, résistant aux attaques brute-force |
| **UUID** | Généré manuellement (`random_bytes`) | Évite la dépendance `symfony/uid` pour un projet léger |
| **Interface web** | Twig (bonus) | Permet de tester sans Postman, appels directs aux services (pas via HTTP) |

---

## 5. Sécurité

### 5.1 Authentification JWT

#### Flux complet

**1. Inscription**
```http
POST /api/auth/register
Content-Type: application/json

{
  "email": "alice@example.com",
  "password": "Password123!",
  "firstName": "Alice",
  "lastName": "Dupont",
  "roles": ["ROLE_USER"]
}

→ 201 Created
{
  "id": "550e8400-...",
  "email": "alice@example.com",
  "firstName": "Alice",
  "roles": ["ROLE_USER"]
}
```

Validations : email unique, format valide, mot de passe ≥ 8 caractères avec majuscule + minuscule + chiffre. Le mot de passe est hashé bcrypt, jamais retourné.

---

**2. Connexion**
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "alice@example.com",
  "password": "Password123!"
}

→ 200 OK
{
  "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refreshToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expiresIn": 1800,
  "user": {
    "id": "550e8400-...",
    "email": "alice@example.com",
    "firstName": "Alice",
    "roles": ["ROLE_USER"]
  }
}
```

**Payload du JWT :**
```json
{
  "sub": "550e8400-e29b-41d4-a716-446655440000",
  "email": "alice@example.com",
  "roles": ["ROLE_USER"],
  "iat": 1705930200,
  "exp": 1705932000,
  "type": "access"
}
```

---

**3. Utilisation du token**

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

---

**4. Renouvellement**
```http
POST /api/auth/refresh
Content-Type: application/json

{ "refreshToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." }

→ 200 OK
{ "accessToken": "...", "expiresIn": 1800 }
```

### 5.2 Durées de vie des tokens

```
Access Token   30 minutes   utilisé sur chaque requête API
Refresh Token  7 jours      stocké en base, rotation à chaque refresh, révocable
```

### 5.3 Gestion des Rôles

```
ROLE_USER (Demandeur)
├─ Créer/modifier ses demandes
├─ Consulter les services publics
└─ Accepter/refuser les propositions sur ses demandes

ROLE_PRESTATAIRE (Provider)
├─ Tout ce que ROLE_USER peut faire (héritage)
├─ Créer/modifier/supprimer ses services
└─ Soumettre des propositions sur des demandes

ROLE_ADMIN
├─ Accès complet à toutes les ressources
└─ Modifier/supprimer n'importe quel contenu (pas propriétaire requis)
```

Hiérarchie déclarée dans `security.yaml` :
```yaml
role_hierarchy:
  ROLE_ADMIN:       [ROLE_PRESTATAIRE, ROLE_USER]
  ROLE_PRESTATAIRE: [ROLE_USER]
```

### 5.4 Protection des Routes

**Routes publiques (sans token)**
```
POST  /api/auth/register
POST  /api/auth/login
POST  /api/auth/refresh
GET   /api/services
GET   /api/services/{id}
```

**Routes authentifiées (JWT requis)**
```
GET   /api/users/me
PUT   /api/users/me
GET   /api/users/{id}

GET   /api/demandes
GET   /api/demandes/{id}
POST  /api/demandes               ROLE_USER
PUT   /api/demandes/{id}          propriétaire
DELETE /api/demandes/{id}         propriétaire

POST  /api/demandes/{id}/propositions   ROLE_PRESTATAIRE
GET   /api/propositions
GET   /api/propositions/{id}
PUT   /api/propositions/{id}      demandeur de la demande concernée
DELETE /api/propositions/{id}     propriétaire

POST  /api/services               ROLE_PRESTATAIRE
PUT   /api/services/{id}          propriétaire
DELETE /api/services/{id}         propriétaire
```

### 5.5 Voter de Sécurité (Propriété des Ressources)

`ResourceVoter` intercepte les opérations PUT/DELETE et vérifie que l'utilisateur connecté est bien le propriétaire de la ressource — ou qu'il est ROLE_ADMIN.

```php
// src/Security/ResourceVoter.php
if ($this->isOwner($resource, $user) || $user->hasRole('ROLE_ADMIN')) {
    return self::ACCESS_GRANTED;
}
return self::ACCESS_DENIED;
```

### 5.6 Autres Mesures de Sécurité

- **Injection SQL** : impossible via Doctrine (requêtes préparées automatiques)
- **Mots de passe** : bcrypt, jamais retournés en réponse, jamais loggés
- **Tokens** : signés HMAC-SHA256, `hash_equals` pour comparaison à durée constante (anti timing-attack)
- **Refresh token** : champ `jti` unique pour invalidation ciblée
- **Type check** : le champ `type` dans le JWT empêche d'utiliser un refresh token comme access token

---

## 6. Endpoints API

### 6.1 Authentification

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| POST | `/api/auth/register` | — | Créer un compte |
| POST | `/api/auth/login` | — | Se connecter (retourne JWT) |
| POST | `/api/auth/refresh` | — | Renouveler le token |

### 6.2 Utilisateurs

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| GET | `/api/users/me` | ✅ | Mon profil complet |
| PUT | `/api/users/me` | ✅ | Modifier mon profil |
| GET | `/api/users/{id}` | ✅ | Profil public d'un utilisateur |

### 6.3 Services

| Méthode | Endpoint | Auth | Rôle | Description |
|---------|----------|------|------|-------------|
| GET | `/api/services` | — | — | Lister (filtres disponibles) |
| GET | `/api/services/{id}` | — | — | Voir le détail |
| POST | `/api/services` | ✅ | PRESTATAIRE | Créer un service |
| PUT | `/api/services/{id}` | ✅ | Propriétaire | Modifier |
| DELETE | `/api/services/{id}` | ✅ | Propriétaire | Supprimer |

Filtres : `?search=mot&category=design&priceMin=100&priceMax=1000&status=active`

### 6.4 Demandes

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| GET | `/api/demandes` | ✅ | Toutes les demandes (`?mine=1` pour les miennes) |
| GET | `/api/demandes/{id}` | ✅ | Voir le détail |
| POST | `/api/demandes` | ✅ | Créer une demande |
| PUT | `/api/demandes/{id}` | ✅ | Modifier (propriétaire) |
| DELETE | `/api/demandes/{id}` | ✅ | Supprimer (propriétaire) |

### 6.5 Propositions

| Méthode | Endpoint | Auth | Rôle | Description |
|---------|----------|------|------|-------------|
| POST | `/api/demandes/{id}/propositions` | ✅ | PRESTATAIRE | Soumettre une proposition |
| GET | `/api/propositions` | ✅ | — | Mes propositions |
| GET | `/api/propositions/{id}` | ✅ | — | Voir le détail |
| PUT | `/api/propositions/{id}` | ✅ | Demandeur | Accepter ou refuser |
| DELETE | `/api/propositions/{id}` | ✅ | Propriétaire | Supprimer |

**Accepter une proposition :**
```json
PUT /api/propositions/{id}
{ "status": "acceptée" }
```

---

## 7. Flux Métier Complet

### Cas d'usage : Un demandeur trouve un prestataire

**Étape 1 — Demandeur crée une demande**
```http
POST /api/demandes
Authorization: Bearer JWT_DEMANDEUR

{
  "title": "Besoin développeur PHP/Symfony",
  "description": "Créer une API REST pour ma marketplace",
  "budget": 2000,
  "category": "développement"
}
→ 201 { "id": "abc123", "status": "ouverte", ... }
```

**Étape 2 — Prestataire soumet une proposition**
```http
POST /api/demandes/abc123/propositions
Authorization: Bearer JWT_PRESTATAIRE

{
  "price": 1900,
  "message": "Je peux faire ça en 2 semaines",
  "serviceId": "serv456"
}
→ 201 { "id": "prop789", "status": "en_attente", ... }
```

**Étape 3 — Demandeur accepte**
```http
PUT /api/propositions/prop789
Authorization: Bearer JWT_DEMANDEUR

{ "status": "acceptée" }
→ 200
```

Cascades automatiques :
```
Proposition prop789 → "acceptée"
Demande abc123     → "en_cours"
Autres propositions → "refusée"
```

---

## 8. Installation et Démarrage

### 8.1 Prérequis

```
PHP 8.4+
Composer
Symfony CLI
SQLite (inclus dans PHP)
```

### 8.2 Installation

```bash
# 1. Installer les dépendances
composer install

# 2. Créer la base de données + migrations
php bin/console doctrine:migrations:migrate

# 3. Charger les données de test
php load_fixtures.php

# 4. Lancer le serveur
symfony server:start --no-tls
```

**Accès :**
- Interface web : `http://127.0.0.1:8000`
- API : `http://127.0.0.1:8000/api`

### 8.3 Configuration (.env)

```dotenv
DATABASE_URL="sqlite:///%kernel.project_dir%/var/marketplace.db"
JWT_SECRET=change_this_to_a_long_random_secret_key_in_production
APP_SECRET=your_app_secret_here
```

---

## 9. Tests de l'API

### 9.1 Comptes de Test

| Email | Mot de passe | Rôle |
|-------|--------------|------|
| `client@test.com` | `Password123!` | ROLE_USER |
| `provider@test.com` | `Password123!` | ROLE_PRESTATAIRE |
| `admin@test.com` | `Password123!` | ROLE_ADMIN |

Créés automatiquement par `php load_fixtures.php`.

### 9.2 Avec Postman

1. Ouvrir Postman → **File → Import → `postman_collection.json`**
2. La variable `base_url` est déjà configurée sur `http://localhost:8000`
3. Exécuter **Auth → Login** → le token se stocke automatiquement
4. Tester les autres endpoints

### 9.3 Script de test automatisé (Git Bash)

```powershell
& "C:\Program Files\Git\bin\bash.exe" test_api.sh
```

Exécute 24 tests couvrant auth, CRUD, permissions et statuts.

### 9.4 Avec curl

**Login :**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "client@test.com", "password": "Password123!"}'
```

**Requête authentifiée :**
```bash
curl -X GET http://localhost:8000/api/users/me \
  -H "Authorization: Bearer TOKEN_ICI"
```

**Créer un service (prestataire) :**
```bash
curl -X POST http://localhost:8000/api/services \
  -H "Authorization: Bearer TOKEN_PRESTATAIRE" \
  -H "Content-Type: application/json" \
  -d '{"title": "Développement Symfony", "description": "API REST pro", "category": "développement", "price": 1500}'
```

### 9.5 Interface Web (Bonus)

Accessible sur `http://127.0.0.1:8000` sans Postman ni curl :

| Route | Page |
|-------|------|
| `/` | Accueil |
| `/login` | Connexion |
| `/register` | Inscription |
| `/dashboard` | Vue après connexion |
| `/services` | Services + création |
| `/demandes` | Demandes + création |
| `/logout` | Déconnexion |

---

## 10. Structure des Réponses

### Succès
```json
HTTP 200 OK
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "email": "alice@example.com",
  "firstName": "Alice",
  "roles": ["ROLE_USER"],
  "createdAt": "2024-05-25T14:30:00+00:00"
}
```

### Erreurs
```json
HTTP 400 Bad Request
{ "error": "Validation failed", "violations": { "email": "Cette valeur n'est pas une adresse email valide." } }

HTTP 401 Unauthorized
{ "error": "Unauthorized", "message": "Token JWT invalide ou expiré." }

HTTP 403 Forbidden
{ "error": "Access Denied", "message": "Accès refusé." }

HTTP 404 Not Found
{ "error": "Not Found", "message": "Ressource introuvable." }
```
