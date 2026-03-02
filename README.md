[documentation.php](https://github.com/user-attachments/files/25686006/documentation.php)
<!DOCTYPE html>
<html>
<head>
    <title>Documentation Technique - Gestion Accès Lycée Bel Air</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
        }
        h1 {
            color: #0e1f4c;
            border-bottom: 3px solid #0e1f4c;
            padding-bottom: 15px;
            font-size: 36px;
        }
        h2 {
            color: #1a3a7a;
            border-left: 5px solid #0e1f4c;
            padding-left: 15px;
            margin-top: 40px;
            font-size: 28px;
        }
        h3 {
            color: #2c3e50;
            font-size: 22px;
            margin-top: 30px;
        }
        .info-box {
            background: #f0f8ff;
            border-left: 4px solid #0e1f4c;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #0e1f4c;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .toc {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 30px 0;
        }
        .toc a {
            text-decoration: none;
            color: #0e1f4c;
        }
        .toc li {
            margin: 10px 0;
        }
    </style>
</head>
<body>

<!-- PAGE DE GARDE -->
<div style="text-align: center; margin-bottom: 60px;">
    <h1 style="font-size: 48px; border-bottom: none;">📚 GESTION ACCÈS</h1>
    <h2 style="border-left: none; font-size: 32px;">Documentation Technique</h2>
    <p style="font-size: 18px; color: #666;">Lycée Bel Air - Système de gestion des accès et des présences</p>
    <hr style="margin: 40px 0;">
    <p><strong>Version :</strong> 2.0</p>
    <p><strong>Date :</strong> <?= date('d/m/Y') ?></p>
    <p><strong>Auteur :</strong> Équipe technique</p>
</div>

<!-- TABLE DES MATIÈRES -->
<div class="toc">
    <h2>📑 Table des matières</h2>
    <ol>
        <li><a href="#presentation">Présentation du projet</a></li>
        <li><a href="#architecture">Architecture technique</a></li>
        <li><a href="#base-donnees">Structure de la base de données</a></li>
        <li><a href="#installation">Installation et configuration</a></li>
        <li><a href="#arborescence">Arborescence des fichiers</a></li>
        <li><a href="#fonctionnalites">Fonctionnalités détaillées</a></li>
        <li><a href="#utilisation">Guide d'utilisation</a></li>
        <li><a href="#securite">Sécurité et logs</a></li>
        <li><a href="#maintenance">Maintenance</a></li>
        <li><a href="#depannage">Dépannage</a></li>
    </ol>
</div>

<!-- 1. PRÉSENTATION -->
<h2 id="presentation">1. Présentation du projet</h2>

<div class="info-box">
    <strong>🎯 Objectif :</strong> Digitalisation des accès et gestion automatisée des présences au Lycée Bel Air
</div>

<p>Le système <strong>GESTION ACCÈS</strong> est une application web développée en PHP/MySQL permettant de :</p>
<ul>
    <li>Gérer les accès des élèves, professeurs et personnels via badges</li>
    <li>Automatiser l'appel en classe (Préappel) avec détection par badge</li>
    <li>Gérer les emplois du temps et l'assignation des salles</li>
    <li>Historiser les accès et incidents (logs Vigipirate)</li>
    <li>Administrer les utilisateurs, classes et statuts</li>
</ul>

<!-- 2. ARCHITECTURE -->
<h2 id="architecture">2. Architecture technique</h2>

<h3>2.1 Stack technologique</h3>
<table>
    <tr>
        <th>Composant</th>
        <th>Technologie</th>
        <th>Version</th>
    </tr>
    <tr>
        <td>Serveur</td>
        <td>WampServer / Apache</td>
        <td>3.3+</td>
    </tr>
    <tr>
        <td>Langage</td>
        <td>PHP</td>
        <td>8.2+</td>
    </tr>
    <tr>
        <td>Base de données</td>
        <td>MySQL / MariaDB</td>
        <td>8.0+</td>
    </tr>
    <tr>
        <td>Frontend</td>
        <td>HTML5, CSS3, JavaScript</td>
        <td>-</td>
    </tr>
    <tr>
        <td>Bibliothèques</td>
        <td>Chart.js, PDO</td>
        <td>-</td>
    </tr>
</table>

<h3>2.2 Structure MVC simplifiée</h3>
<pre>
gestion_acces/
├── index.php                  # Point d'entrée (connexion)
├── includes/                  # Fichiers d'inclusion
│   ├── db.php                # Connexion BDD + fonction logs
│   └── sidebar.php           # Menu latéral
├── pages/                     # Pages de l'application
│   ├── accueil.php           # Tableau de bord
│   ├── admin.php              # Gestion des élèves
│   ├── edt.php                # Emploi du temps
│   ├── preappel.php           # Système d'appel
│   ├── logs.php               # Journal des accès
│   ├── gestion_edt.php        # Admin - Gestion EDT
│   └── *.php                  # Autres pages
├── assets/                    # Ressources statiques
│   └── css/                   # Feuilles de style
│       ├── style.css          # Style global
│       └── img/                # Images
</pre>

<!-- 3. BASE DE DONNÉES -->
<h2 id="base-donnees">3. Structure de la base de données</h2>

<h3>3.1 Tables principales</h3>

<table>
    <tr>
        <th>Table</th>
        <th>Rôle</th>
        <th>Relations</th>
    </tr>
    <tr>
        <td><code>utilisateur</code></td>
        <td>Stocke tous les utilisateurs (élèves, profs, admins)</td>
        <td>→ statut, classe, badge</td>
    </tr>
    <tr>
        <td><code>statut</code></td>
        <td>Définit les rôles (1=Admin, 3=Utilisateur, 4=Eleve)</td>
        <td>← utilisateur</td>
    </tr>
    <tr>
        <td><code>classe</code></td>
        <td>Liste des classes (CIEL, GPME, CIEL2, GPME2)</td>
        <td>← utilisateur</td>
    </tr>
    <tr>
        <td><code>badge</code></td>
        <td>Badges RFID assignés aux utilisateurs</td>
        <td>→ utilisateur, ← entrer_badge</td>
    </tr>
    <tr>
        <td><code>emploie_du_temps</code></td>
        <td>Cours programmés par classe</td>
        <td>→ classe, cours, jours</td>
    </tr>
    <tr>
        <td><code>salle</code></td>
        <td>Salles de cours disponibles</td>
        <td>← salle_edt</td>
    </tr>
    <tr>
        <td><code>salle_edt</code></td>
        <td>Assignation des salles aux cours</td>
        <td>→ salle, emploie_du_temps</td>
    </tr>
    <tr>
        <td><code>logs_systeme</code></td>
        <td>Journalisation de toutes les actions</td>
        <td>→ utilisateur</td>
    </tr>
    <tr>
        <td><code>entrer_badge</code></td>
        <td>Enregistrement des passages badge</td>
        <td>→ badge, journal_entrer</td>
    </tr>
</table>

<h3>3.2 Schéma relationnel</h3>
<pre>
utilisateur ─┬─> statut
             ├─> classe
             └─> badge ──> entrer_badge ──> journal_entrer
             
emploie_du_temps ─┬─> classe
                  ├─> cours
                  ├─> jours
                  └─> salle_edt ──> salle
                  
logs_systeme ──> utilisateur
</pre>

<h3>3.3 Table logs_systeme</h3>
<p>Table créée automatiquement pour l'historisation :</p>
<pre>
CREATE TABLE logs_systeme (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    date_log DATETIME DEFAULT CURRENT_TIMESTAMP,
    niveau ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    type_action VARCHAR(50),
    id_utilisateur INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    description TEXT,
    details TEXT,
    INDEX idx_date (date_log),
    INDEX idx_niveau (niveau),
    INDEX idx_type (type_action)
);
</pre>

<!-- 4. INSTALLATION -->
<h2 id="installation">4. Installation et configuration</h2>

<h3>4.1 Prérequis</h3>
<ul>
    <li>WampServer, XAMPP ou MAMP</li>
    <li>PHP 8.0 ou supérieur</li>
    <li>MySQL 5.7 ou supérieur</li>
    <li>Navigateur web moderne</li>
</ul>

<h3>4.2 Installation étape par étape</h3>

<div class="info-box">
    <strong>📦 Étape 1 :</strong> Copier les fichiers
</div>
<pre>
1. Télécharger l'archive du projet
2. Extraire dans C:\wamp64\www\exophp\gestion_acces\
3. Vérifier que le chemin correspond à votre configuration
</pre>

<div class="info-box">
    <strong>🗄️ Étape 2 :</strong> Base de données
</div>
<pre>
1. Démarrer WampServer
2. Ouvrir phpMyAdmin (http://localhost/phpmyadmin)
3. Créer une base 'projet_bts'
4. Importer le fichier projet_bts.sql
5. Vérifier les données : classes, statuts, utilisateurs
</pre>

<div class="info-box">
    <strong>⚙️ Étape 3 :</strong> Configuration
</div>
<pre>
Fichier : includes/db.php

Modifier selon votre configuration :
$host = "localhost";
$user = "root"; 
$password = "votre_mot_de_passe";
$dbname = "projet_bts";
</pre>

<div class="info-box">
    <strong>🔑 Étape 4 :</strong> Comptes par défaut
</div>
<pre>
Admin : 
    Identifiant : test
    Mot de passe : test
    
Élèves :
    Identifiant : alice.dupont
    Mot de passe : mdp123
</pre>

<!-- 5. ARBORESCENCE DÉTAILLÉE -->
<h2 id="arborescence">5. Arborescence détaillée</h2>

<h3>5.1 Racine du projet</h3>
<table>
    <tr>
        <th>Fichier</th>
        <th>Description</th>
    </tr>
    <tr>
        <td><code>index.php</code></td>
        <td>Page de connexion, gère l'authentification et les logs de connexion</td>
    </tr>
</table>

<h3>5.2 Dossier includes/</h3>
<table>
    <tr>
        <th>Fichier</th>
        <th>Description</th>
    </tr>
    <tr>
        <td><code>db.php</code></td>
        <td>Connexion PDO à la base + fonction <code>addLog()</code> pour les logs</td>
    </tr>
    <tr>
        <td><code>sidebar.php</code></td>
        <td>Menu latéral adapté au statut de l'utilisateur</td>
    </tr>
</table>

<h3>5.3 Dossier pages/</h3>
<table>
    <tr>
        <th>Fichier</th>
        <th>Accès</th>
        <th>Description</th>
    </tr>
    <tr>
        <td><code>accueil.php</code></td>
        <td>Tous</td>
        <td>Tableau de bord personnalisé selon le rôle</td>
    </tr>
    <tr>
        <td><code>admin.php</code></td>
        <td>Admin (1)</td>
        <td>Gestion complète des élèves (CRUD, filtres, export)</td>
    </tr>
    <tr>
        <td><code>edt.php</code></td>
        <td>Tous</td>
        <td>Emploi du temps (classe pour élève, matières pour prof)</td>
    </tr>
    <tr>
        <td><code>preappel.php</code></td>
        <td>Prof (3) et Admin</td>
        <td>Système d'appel automatisé avec détection badges</td>
    </tr>
    <tr>
        <td><code>logs.php</code></td>
        <td>Admin (1)</td>
        <td>Journal des accès, graphiques, export, purge</td>
    </tr>
    <tr>
        <td><code>gestion_edt.php</code></td>
        <td>Admin (1)</td>
        <td>Gestion des emplois du temps et assignation salles</td>
    </tr>
    <tr>
        <td><code>technicien.php</code></td>
        <td>Technicien (2)</td>
        <td>Espace technicien (à développer)</td>
    </tr>
</table>

<!-- 6. FONCTIONNALITÉS -->
<h2 id="fonctionnalites">6. Fonctionnalités détaillées</h2>

<h3>6.1 Système de connexion</h3>
<ul>
    <li>Authentification par identifiant/mot de passe</li>
    <li>Vérification avec <code>password_verify()</code></li>
    <li>Session utilisateur avec rôle (id_statut)</li>
    <li>Logs automatiques des tentatives (réussies/échouées)</li>
</ul>

<h3>6.2 Gestion des élèves (admin.php)</h3>
<ul>
    <li><strong>CRUD complet :</strong> Ajouter, modifier, supprimer des élèves</li>
    <li><strong>Recherche :</strong> Par nom, prénom, identifiant, classe</li>
    <li><strong>Filtres :</strong> Par classe (toutes les classes affichées, même vides)</li>
    <li><strong>Export CSV :</strong> Téléchargement de la liste</li>
    <li><strong>Assignation classe :</strong> Modal pour élèves sans classe</li>
</ul>

<h3>6.3 Emploi du temps (edt.php)</h3>
<ul>
    <li><strong>Élève :</strong> Affiche l'EDT de sa classe</li>
    <li><strong>Professeur :</strong> Affiche ses cours selon ses matières</li>
    <li><strong>Admin :</strong> Vue complète avec salles</li>
    <li><strong>Cours en cours :</strong> Mise en évidence du cours actuel</li>
    <li><strong>Salles :</strong> Affichage des salles assignées</li>
</ul>

<h3>6.4 Préappel automatisé (preappel.php)</h3>
<ul>
    <li><strong>Détection badges :</strong> Affiche les badges scannés récemment</li>
    <li><strong>Cours du jour :</strong> Liste des cours du professeur</li>
    <li><strong>Appel :</strong> Interface de validation des présences</li>
    <li><strong>Timer Arduino :</strong> Simulation des 2 minutes de validation</li>
    <li><strong>Journalisation :</strong> Enregistrement dans logs_systeme</li>
</ul>

<h3>6.5 Logs système (logs.php)</h3>
<ul>
    <li><strong>Visualisation :</strong> Tableau avec tous les logs</li>
    <li><strong>Filtres :</strong> Par date, niveau, type, recherche texte</li>
    <li><strong>Graphiques :</strong> Activité par jour, top actions</li>
    <li><strong>Export CSV :</strong> Sauvegarde des logs</li>
    <li><strong>Purge :</strong> Nettoyage des logs anciens</li>
    <li><strong>Détails :</strong> Modal avec informations complètes</li>
</ul>

<h3>6.6 Gestion EDT (gestion_edt.php)</h3>
<ul>
    <li><strong>4 onglets :</strong> Salles, EDT, Assignation, Import/Export</li>
    <li><strong>Gestion salles :</strong> Ajout/modification/suppression</li>
    <li><strong>Gestion cours :</strong> Ajout aux classes</li>
    <li><strong>Assignation :</strong> Lier une salle à un cours</li>
</ul>

<!-- 7. UTILISATION -->
<h2 id="utilisation">7. Guide d'utilisation</h2>

<h3>7.1 Pour les administrateurs</h3>
<div class="info-box">
    <strong>🔑 Connexion :</strong> Utiliser un compte avec id_statut = 1
</div>
<ol>
    <li><strong>Tableau de bord</strong> - Vue d'ensemble des statistiques</li>
    <li><strong>Gestion élèves</strong> - Ajouter/modifier/supprimer des élèves</li>
    <li><strong>Emploi du temps</strong> - Voir tous les EDT</li>
    <li><strong>Gestion EDT</strong> - Configurer les cours et salles</li>
    <li><strong>Préappel</strong> - Tester le système (optionnel)</li>
    <li><strong>Logs</strong> - Surveiller les accès et incidents</li>
</ol>

<h3>7.2 Pour les professeurs</h3>
<div class="info-box">
    <strong>🔑 Connexion :</strong> Compte avec id_statut = 3
</div>
<ol>
    <li><strong>Tableau de bord</strong> - Infos personnelles</li>
    <li><strong>Emploi du temps</strong> - Ses cours avec salles</li>
    <li><strong>Préappel</strong> - Faire l'appel de ses classes</li>
</ol>

<h3>7.3 Pour les élèves</h3>
<div class="info-box">
    <strong>🔑 Connexion :</strong> Compte avec id_statut = 4
</div>
<ol>
    <li><strong>Tableau de bord</strong> - Infos personnelles, classe</li>
    <li><strong>Emploi du temps</strong> - EDT de sa classe</li>
</ol>

<!-- 8. SÉCURITÉ ET LOGS -->
<h2 id="securite">8. Sécurité et logs</h2>

<h3>8.1 Journalisation (logs_systeme)</h3>
<p>La table <code>logs_systeme</code> enregistre automatiquement :</p>
<table>
    <tr>
        <th>Type d'action</th>
        <th>Description</th>
        <th>Niveau</th>
    </tr>
    <tr>
        <td><code>CONNEXION</code></td>
        <td>Connexion réussie</td>
        <td>info</td>
    </tr>
    <tr>
        <td><code>CONNEXION_ECHOUEE</code></td>
        <td>Tentative échouée (mauvais mot de passe)</td>
        <td>warning</td>
    </tr>
    <tr>
        <td><code>PAGE_VUE</code></td>
        <td>Visite d'une page</td>
        <td>info</td>
    </tr>
    <tr>
        <td><code>APPEL_VALIDE</code></td>
        <td>Validation d'appel</td>
        <td>info</td>
    </tr>
    <tr>
        <td><code>SUPPRESSION_UTILISATEUR</code></td>
        <td>Suppression d'un élève</td>
        <td>warning</td>
    </tr>
    <tr>
        <td><code>ERREUR_SQL</code></td>
        <td>Erreur base de données</td>
        <td>error</td>
    </tr>
    <tr>
        <td><code>PURGE_LOGS</code></td>
        <td>Nettoyage des logs</td>
        <td>warning</td>
    </tr>
</table>

<h3>8.2 Fonction addLog()</h3>
<p>Définie dans <code>includes/db.php</code> :</p>
<pre>
function addLog($conn, $niveau, $type_action, $id_utilisateur, $description, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("
        INSERT INTO logs_systeme 
        (niveau, type_action, id_utilisateur, ip_address, user_agent, description, details)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$niveau, $type_action, $id_utilisateur, $ip, $user_agent, $description, $details]);
}
</pre>

<h3>8.3 Utilisation dans les pages</h3>
<pre>
// Dans chaque page après include 'db.php'
addLog($conn, 'info', 'PAGE_VUE', $_SESSION['user_id'], 
       "Description de l'action", "Détails optionnels");
</pre>

<h3>8.4 Sécurité des mots de passe</h3>
<ul>
    <li>Utilisation de <code>password_hash()</code> et <code>password_verify()</code></li>
    <li>Les mots de passe en clair dans la BDD doivent être migrés (actuellement en clair dans les données exemple)</li>
    <li>Protection contre les injections SQL via PDO et requêtes préparées</li>
</ul>

<!-- 9. MAINTENANCE -->
<h2 id="maintenance">9. Maintenance</h2>

<h3>9.1 Sauvegarde</h3>
<ul>
    <li>Sauvegarder régulièrement la base de données (phpMyAdmin export)</li>
    <li>Sauvegarder les fichiers du projet</li>
    <li>Les logs peuvent être exportés depuis logs.php</li>
</ul>

<h3>9.2 Nettoyage</h3>
<ul>
    <li>Purger les logs anciens via logs.php (recommandé : 90 jours)</li>
    <li>Vérifier les badges expirés</li>
    <li>Mettre à jour les emplois du temps chaque année</li>
</ul>

<h3>9.3 Mise à jour des mots de passe</h3>
<p>Pour migrer les mots de passe en clair vers des hash :</p>
<pre>
// Script temporaire
$users = $conn->query("SELECT id_utilisateur, mot_de_pass FROM utilisateur");
foreach($users as $user) {
    $hash = password_hash($user['mot_de_pass'], PASSWORD_DEFAULT);
    $conn->prepare("UPDATE utilisateur SET mot_de_pass = ? WHERE id_utilisateur = ?")
         ->execute([$hash, $user['id_utilisateur']]);
}
</pre>

<!-- 10. DÉPANNAGE -->
<h2 id="depannage">10. Guide de dépannage</h2>

<h3>10.1 Erreurs courantes</h3>

<table>
    <tr>
        <th>Erreur</th>
        <th>Cause</th>
        <th>Solution</th>
    </tr>
    <tr>
        <td>Cannot redeclare addLog()</td>
        <td>Fonction définie deux fois</td>
        <td>Garder uniquement dans db.php, supprimer des autres fichiers</td>
    </tr>
    <tr>
        <td>SQLSTATE[42S02]: Base table not found</td>
        <td>Table manquante</td>
        <td>Importer projet_bts.sql ou exécuter le CREATE TABLE dans db.php</td>
    </tr>
    <tr>
        <td>Connection failed</td>
        <td>Paramètres BDD incorrects</td>
        <td>Vérifier host/user/password dans db.php</td>
    </tr>
    <tr>
        <td>Identifiant ou mot de passe incorrect</td>
        <td>Mauvaises identifiants ou hash non vérifié</td>
        <td>Vérifier les identifiants en base</td>
    </tr>
    <tr>
        <td>Aucun log affiché</td>
        <td>Table vide ou addLog non appelé</td>
        <td>Se connecter/déconnecter pour générer des logs</td>
    </tr>
</table>

<h3>10.2 Logs de débogage</h3>
<p>Activer les erreurs PHP temporairement :</p>
<pre>
// En haut de index.php ou db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
</pre>

<h3>10.3 Vérifications rapides</h3>
<ol>
    <li>Le serveur Wamp est-il démarré ?</li>
    <li>La base 'projet_bts' existe-t-elle ?</li>
    <li>Les identifiants dans db.php sont-ils corrects ?</li>
    <li>Les tables ont-elles été créées ?</li>
    <li>Les sessions sont-elles activées ?</li>
</ol>

<!-- ANNEXES -->
<h2>Annexes</h2>

<h3>A. Codes statut</h3>
<table>
    <tr>
        <th>id_statut</th>
        <th>Libellé</th>
        <th>Accès</th>
    </tr>
    <tr>
        <td>1</td>
        <td>Admin</td>
        <td>Toutes les pages</td>
    </tr>
    <tr>
        <td>2</td>
        <td>Technicien</td>
        <td>Espace technicien (à définir)</td>
    </tr>
    <tr>
        <td>3</td>
        <td>Professeur</td>
        <td>Accueil, EDT, Préappel</td>
    </tr>
    <tr>
        <td>4</td>
        <td>Élève</td>
        <td>Accueil, EDT</td>
    </tr>
    <tr>
        <td>5</td>
        <td>Documentaliste</td>
        <td>À définir</td>
    </tr>
</table>

<h3>B. Variables de session</h3>
<pre>
$_SESSION['user_id']      // ID de l'utilisateur connecté
$_SESSION['id_statut']    // Rôle (1,2,3,4,5)
$_SESSION['identifiant']  // Identifiant de connexion
$_SESSION['nom']          // Nom
$_SESSION['prenom']       // Prénom
</pre>

<h3>C. Niveaux de logs</h3>
<ul>
    <li><strong>info</strong> - Actions normales (connexion, page vue)</li>
    <li><strong>warning</strong> - Incidents mineurs (tentatives échouées)</li>
    <li><strong>error</strong> - Erreurs système (SQL, fichiers)</li>
    <li><strong>critical</strong> - Problèmes majeurs (accès non autorisé)</li>
</ul>

<!-- CONCLUSION -->
<div style="margin-top: 60px; padding: 30px; background: #f0f8ff; border-radius: 10px; text-align: center;">
    <h3 style="color: #0e1f4c;">📌 Pour toute assistance</h3>
    <p>Documentation générée le <?= date('d/m/Y à H:i') ?></p>
    <p><strong>Mainteneur :</strong> Équipe technique - Lycée Bel Air</p>
    <p><em>"La documentation est comme l'assurance : on ne réalise son importance que quand on en a besoin."</em></p>
</div>

</body>
</html>
