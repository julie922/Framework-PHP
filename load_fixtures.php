<?php
$db = new PDO('sqlite:' . __DIR__ . '/var/marketplace.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function uuid(): string {
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

$hash = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
$now  = date('Y-m-d H:i:s');

$rU = 'd76610bc-facb-4ad1-bfdd-8a0935dc5e18';
$rP = 'aa9620cc-6346-4198-82d2-df66f1548898';
$rA = '75f4b156-80d6-4563-9585-27972cfb7648';
$uC = '582568cb-336b-4484-85fa-d07b97aa62bc';
$uP = '2b959a80-eead-4073-b6d9-ad45741b7a9d';
$uA = '7ec6069c-e426-4d24-a368-6f180aa38a0f';

// Roles
$stmtRole = $db->prepare('INSERT INTO role (id, name, permissions) VALUES (?, ?, ?)');
$stmtRole->execute([$rU, 'ROLE_USER',        '[]']);
$stmtRole->execute([$rP, 'ROLE_PRESTATAIRE', '[]']);
$stmtRole->execute([$rA, 'ROLE_ADMIN',       '[]']);

// Users
$stmtUser = $db->prepare(
    'INSERT INTO user (id, email, password, first_name, last_name, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmtUser->execute([$uC, 'client@test.com',   $hash, 'Alice', 'Demandeur',   $now, $now]);
$stmtUser->execute([$uP, 'provider@test.com', $hash, 'Bob',   'Prestataire', $now, $now]);
$stmtUser->execute([$uA, 'admin@test.com',    $hash, 'Admin', 'System',      $now, $now]);

// User roles
$stmtUR = $db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
$stmtUR->execute([$uC, $rU]);
$stmtUR->execute([$uP, $rP]);
$stmtUR->execute([$uA, $rA]);

// Services
$stmtSvc = $db->prepare(
    'INSERT INTO service (id, prestataire_id, title, description, category, price, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$s1 = uuid();
$s2 = uuid();
$s3 = uuid();
$stmtSvc->execute([$s1, $uP, 'Développement Symfony', 'API REST Symfony professionnelle', 'développement', 1500, 'active', $now, $now]);
$stmtSvc->execute([$s2, $uP, 'Design UI/UX',           'Maquettes Figma complètes',        'design',        800,  'active', $now, $now]);
$stmtSvc->execute([$s3, $uP, 'SEO et Référencement',   'Audit et optimisation SEO',        'marketing',     500,  'active', $now, $now]);

// Demandes
$stmtDem = $db->prepare(
    'INSERT INTO demande (id, demandeur_id, title, description, category, budget, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$d1 = uuid();
$d2 = uuid();
$stmtDem->execute([$d1, $uC, 'Besoin développeur Symfony', 'Créer une API REST marketplace avec Symfony 8', 'développement', 2000, 'ouverte', $now, $now]);
$stmtDem->execute([$d2, $uC, 'Logo et charte graphique',   'Créer une identité visuelle complète',          'design',         600, 'ouverte', $now, $now]);

// Propositions
$stmtProp = $db->prepare(
    'INSERT INTO proposition (id, demande_id, prestataire_id, service_id, price, message, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$p1 = uuid();
$p2 = uuid();
$stmtProp->execute([$p1, $d1, $uP, $s1, 1900, 'Livraison en 3 semaines avec tests et documentation.', 'en_attente', $now, $now]);
$stmtProp->execute([$p2, $d2, $uP, $s2, 550,  'Design complet logo + charte en 1 semaine.',           'en_attente', $now, $now]);

echo "Fixtures chargées avec succès!\n";
echo "  - Rôles        : " . $db->query('SELECT COUNT(*) FROM role')->fetchColumn()        . "\n";
echo "  - Utilisateurs : " . $db->query('SELECT COUNT(*) FROM user')->fetchColumn()        . "\n";
echo "  - Services     : " . $db->query('SELECT COUNT(*) FROM service')->fetchColumn()     . "\n";
echo "  - Demandes     : " . $db->query('SELECT COUNT(*) FROM demande')->fetchColumn()     . "\n";
echo "  - Propositions : " . $db->query('SELECT COUNT(*) FROM proposition')->fetchColumn() . "\n";
echo "\nComptes de test:\n";
echo "  client@test.com   / Password123! (ROLE_USER)\n";
echo "  provider@test.com / Password123! (ROLE_PRESTATAIRE)\n";
echo "  admin@test.com    / Password123! (ROLE_ADMIN)\n";
