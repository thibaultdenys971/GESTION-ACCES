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
        .admin-section {
            background: #e8f4fd;
            border-left: 4px solid #0e1f4c;
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
        }
        .admin-section h3 {
            margin-top: 0;
            color: #0e1f4c;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-admin { background: #0e1f4c; color: white; }
        .badge-feature { background: #28a745; color: white; }
    </style>
</head>
<body>

<!-- PAGE DE GARDE -->
<div style="text-align: center; margin-bottom: 60px;">
    <h1 style="font-size: 48px; border-bottom: none;">📚 GESTION ACCÈS</h1>
    <h2 style="border-left: none; font-size: 32px;">Documentation Technique & Guide d'utilisation</h2>
    <p style="font-size: 18px; color: #666;">Lycée Bel Air - Système de gestion des accès et des présences</p>
    <hr style="margin: 40px 0;">
    <p><strong>Version :</strong> 3.0</p>
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
        <li><a href="#interface-admin">Interface Administrateur Global</a></li>
        <li><a href="#utilisation">Guide d'utilisation par rôle</a></li>
        <li><a href="#securite">Sécurité et logs</a></li>
        <li><a href="#maintenance">Maintenance</a></li>
        <li><a href="#depannage">Dépannage</a></li>
        <li><a href="#annexes">Annexes</a></li>
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
    <li><strong>NOUVEAU :</strong> Interface d'administration globale centralisée</li>
    <li><strong>NOUVEAU :</strong> Scan QR code pour recherche rapide de livres</li>
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
        <td>Chart.js, html5-qrcode, FontAwesome</td>
        <td>-</td>
    </tr>
</table>

<h3>2.2 Structure MVC simplifiée</h3>
<pre>
gestion_acces/
├── index.php                  
├── includes/                  
│   ├── db.php                
│   └── sidebar.php           
├── pages/                     
│   ├── accueil.php           
│   ├── admin.php              
│   ├── admin_global.php       
│   ├── edt.php              
│   ├── preappel.php           
│   ├── logs.php             
│   ├── gestion_edt.php        
│   ├── gestion_bibliotheque.php 
│   └── *.php                
├── assets/                    
│   └── css/                 
│       ├── style.css          
│       └── img/                
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
        <td><code>utilisateurs</code></td>
        <td>Stocke tous les utilisateurs</td>
        <td>→ statut, classe, badge</td>
    </tr>
    <tr>
        <td><code>statut</code></td>
        <td>Définit les rôles (1=Admin, 2=Technicien, 3=Professeur, 4=Élève, 5=Documentaliste)</td>
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
        <td><code>ouvrage</code></td>
        <td>Catalogue des livres</td>
        <td>← exemplaire, etre</td>
    </tr>
    <tr>
        <td><code>pret</code></td>
        <td>Gestion des emprunts</td>
        <td>→ etre, realiser</td>
    </tr>
</table>

<!-- 4. INSTALLATION -->
<h2 id="installation">4. Installation et configuration</h2>

<h3>4.1 Prérequis</h3>
<ul>
    <li>WampServer, XAMPP ou MAMP</li>
    <li>PHP 8.0 ou supérieur</li>
    <li>MySQL 5.7 ou supérieur</li>
    <li>Navigateur web moderne avec support caméra (pour scan QR)</li>
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

<!-- 5. FONCTIONNALITÉS -->
<h2 id="fonctionnalites">5. Fonctionnalités détaillées</h2>

<h3>5.1 Gestion des élèves (admin.php)</h3>
<ul>
    <li><strong>CRUD complet :</strong> Ajouter, modifier, supprimer des élèves</li>
    <li><strong>Recherche :</strong> Par nom, prénom, identifiant, classe</li>
    <li><strong>Filtres :</strong> Par classe (toutes les classes affichées, même vides)</li>
    <li><strong>Export CSV :</strong> Téléchargement de la liste</li>
</ul>

<h3>5.2 Emploi du temps (edt.php)</h3>
<ul>
    <li><strong>Élève :</strong> Affiche l'EDT de sa classe</li>
    <li><strong>Professeur :</strong> Affiche ses cours selon ses matières</li>
    <li><strong>Admin :</strong> Vue complète avec salles</li>
    <li><strong>Cours en cours :</strong> Mise en évidence du cours actuel</li>
</ul>

<h3>5.3 Préappel automatisé (preappel.php)</h3>
<ul>
    <li><strong>Détection badges :</strong> Affiche les badges scannés récemment</li>
    <li><strong>Cours du jour :</strong> Liste des cours du professeur</li>
    <li><strong>Appel :</strong> Interface de validation des présences</li>
    <li><strong>Timer Arduino :</strong> Simulation des 2 minutes de validation</li>
</ul>

<h3>5.4 Logs système (logs.php)</h3>
<ul>
    <li><strong>Visualisation :</strong> Tableau avec tous les logs</li>
    <li><strong>Filtres :</strong> Par date, niveau, type, recherche texte</li>
    <li><strong>Graphiques :</strong> Activité par jour, top actions</li>
    <li><strong>Export CSV :</strong> Sauvegarde des logs</li>
</ul>

<h3>5.5 Bibliothèque avec scan QR (gestion_bibliotheque.php)</h3>
<ul>
    <li><strong>Scan QR code :</strong> Recherche instantanée par code barre</li>
    <li><strong>Gestion des prêts :</strong> Emprunt, retour, prolongation</li>
    <li><strong>Réservations :</strong> Mise de côté des livres</li>
    <li><strong>Statistiques :</strong> Livres disponibles, prêts en retard</li>
</ul>

<!-- 6. INTERFACE ADMINISTRATEUR GLOBAL -->
<h2 id="interface-admin">6. Interface Administrateur Global</h2>

<div class="admin-section">
    <h3>👑 admin_global.php - Vue d'ensemble</h3>
    <p><span class="badge badge-admin">Admin uniquement</span> <span class="badge badge-feature">Nouveauté v3.0</span></p>
    
    <h4>6.1 Tableau de bord</h4>
    <ul>
        <li><strong>Statistiques globales :</strong> Utilisateurs, livres, badges, cours</li>
        <li><strong>Graphiques :</strong> Activité récente, répartition des utilisateurs</li>
        <li><strong>Effectifs :</strong> Nombre d'élèves par classe</li>
        <li><strong>Derniers inscrits :</strong> Liste des 5 derniers utilisateurs</li>
    </ul>

    <h4>6.2 Gestion des utilisateurs</h4>
    <ul>
        <li><strong>Ajout :</strong> Formulaire complet avec choix du statut et de la classe</li>
        <li><strong>Liste :</strong> Tableau avec tous les utilisateurs et leurs droits</li>
        <li><strong>Actions :</strong> Modifier, supprimer (sauf son propre compte)</li>
        <li><strong>Filtres :</strong> Par statut, par classe, recherche</li>
    </ul>

    <h4>6.3 Gestion des badges</h4>
    <ul>
        <li><strong>Attribution :</strong> Lier un badge à un utilisateur</li>
        <li><strong>Expiration :</strong> Suivi des dates de validité</li>
        <li><strong>État :</strong> Actif, inactif, perdu</li>
        <li><strong>Statut :</strong> Badges valides/expirés</li>
    </ul>

    <h4>6.4 Gestion des classes</h4>
    <ul>
        <li><strong>Liste :</strong> Toutes les classes avec effectifs</li>
        <li><strong>Niveaux :</strong> Association année (1ère/2ème année)</li>
        <li><strong>Actions rapides :</strong> Voir l'emploi du temps de la classe</li>
    </ul>

    <h4>6.5 Logs système</h4>
    <ul>
        <li><strong>Vue condensée :</strong> 50 derniers logs</li>
        <li><strong>Accès rapide :</strong> Lien vers la page complète des logs</li>
        <li><strong>Filtrage :</strong> Par niveau, type, utilisateur</li>
    </ul>

    <h4>6.6 Configuration générale</h4>
    <ul>
        <li><strong>Paramètres :</strong> Durée des prêts, validité des badges, délai de retard</li>
        <li><strong>Sécurité :</strong> Journalisation, mots de passe forts, expiration session</li>
        <li><strong>Informations système :</strong> Versions PHP/MySQL, espace disque</li>
    </ul>
</div>

<!-- 7. GUIDE D'UTILISATION PAR RÔLE -->
<h2 id="utilisation">7. Guide d'utilisation par rôle</h2>

<h3>7.1 Administrateur</h3>
<div class="info-box">
    <strong>Accès :</strong> Toutes les pages
</div>
<table>
    <tr>
        <th>Page</th>
        <th>Action principale</th>
        <th>Fréquence</th>
    </tr>
    <tr>
        <td>admin_global.php</td>
        <td>Vue d'ensemble, gestion utilisateurs, configuration</td>
        <td>Quotidien</td>
    </tr>
    <tr>
        <td>admin.php</td>
        <td>Gestion détaillée des élèves</td>
        <td>Hebdomadaire</td>
    </tr>
    <tr>
        <td>gestion_edt.php</td>
        <td>Configuration des emplois du temps et salles</td>
        <td>Mensuel</td>
    </tr>
    <tr>
        <td>logs.php</td>
        <td>Surveillance des accès et incidents</td>
        <td>Quotidien</td>
    </tr>
    <tr>
        <td>gestion_bibliotheque.php</td>
        <td>Supervision des prêts</td>
        <td>Hebdomadaire</td>
    </tr>
</table>

<h3>7.2 Professeur</h3>
<table>
    <tr>
        <th>Page</th>
        <th>Action principale</th>
    </tr>
    <tr>
        <td>accueil.php</td>
        <td>Tableau de bord personnel</td>
    </tr>
    <tr>
        <td>edt.php</td>
        <td>Consulter ses cours et salles</td>
    </tr>
    <tr>
        <td>preappel.php</td>
        <td>Faire l'appel de ses classes</td>
    </tr>
</table>

<h3>7.3 Élève</h3>
<table>
    <tr>
        <th>Page</th>
        <th>Action principale</th>
    </tr>
    <tr>
        <td>accueil.php</td>
        <td>Voir ses informations, sa classe</td>
    </tr>
    <tr>
        <td>edt.php</td>
        <td>Consulter l'emploi du temps de sa classe</td>
    </tr>
</table>

<h3>7.4 Documentaliste</h3>
<table>
    <tr>
        <th>Page</th>
        <th>Action principale</th>
    </tr>
    <tr>
        <td>gestion_bibliotheque.php</td>
        <td>Gérer les prêts, retours, réservations</td>
    </tr>
    <tr>
        <td>accueil.php</td>
        <td>Tableau de bord</td>
    </tr>
</table>

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
        <td>Tentative échouée</td>
        <td>warning</td>
    </tr>
    <tr>
        <td><code>AJOUT_UTILISATEUR</code></td>
        <td>Création d'un utilisateur</td>
        <td>info</td>
    </tr>
    <tr>
        <td><code>SUPPR_UTILISATEUR</code></td>
        <td>Suppression d'un utilisateur</td>
        <td>warning</td>
    </tr>
    <tr>
        <td><code>AJOUT_BADGE</code></td>
        <td>Attribution d'un badge</td>
        <td>info</td>
    </tr>
    <tr>
        <td><code>PRET_LIVRE</code></td>
        <td>Emprunt de livre</td>
        <td>info</td>
    </tr>
</table>

<h3>8.2 Fonction addLog()</h3>
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

<h3>8.3 Sécurité des mots de passe</h3>
<ul>
    <li>Utilisation de <code>password_hash()</code> et <code>password_verify()</code></li>
    <li>Protection contre les injections SQL via PDO</li>
    <li>Sessions PHP avec régénération d'ID</li>
    <li>Validation des droits d'accès sur chaque page</li>
</ul>

<!-- 9. MAINTENANCE -->
<h2 id="maintenance">9. Maintenance</h2>

<h3>9.1 Tâches régulières</h3>
<table>
    <tr>
        <th>Fréquence</th>
        <th>Action</th>
        <th>Outil</th>
    </tr>
    <tr>
        <td>Quotidien</td>
        <td>Vérifier les logs</td>
        <td>logs.php</td>
    </tr>
    <tr>
        <td>Hebdomadaire</td>
        <td>Vérifier les prêts en retard</td>
        <td>gestion_bibliotheque.php</td>
    </tr>
    <tr>
        <td>Mensuel</td>
        <td>Purger les logs anciens (90 jours)</td>
        <td>logs.php</td>
    </tr>
    <tr>
        <td>Trimestriel</td>
        <td>Sauvegarde BDD</td>
        <td>phpMyAdmin</td>
    </tr>
</ul>

<h3>9.2 Sauvegarde</h3>
<pre>
# Sauvegarde MySQL
mysqldump -u root -p projet_bts > backup_$(date +%Y%m%d).sql

# Sauvegarde fichiers
tar -czf gestion_acces_$(date +%Y%m%d).tar.gz /path/to/gestion_acces/
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
        <td>Garder uniquement dans db.php</td>
    </tr>
    <tr>
        <td>SQLSTATE[42S02]: Table not found</td>
        <td>Table manquante</td>
        <td>Importer projet_bts.sql</td>
    </tr>
    <tr>
        <td>Connection failed</td>
        <td>Paramètres BDD incorrects</td>
        <td>Vérifier db.php</td>
    </tr>
    <tr>
        <td>QR Code ne scanne pas</td>
        <td>Permission caméra refusée</td>
        <td>Autoriser la caméra dans le navigateur</td>
    </tr>
</table>

<!-- 11. ANNEXES -->
<h2 id="annexes">11. Annexes</h2>

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
        <td>Pages techniques</td>
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
        <td>Bibliothèque, Accueil</td>
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

<h3>C. Structure de la table logs_systeme</h3>
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

<h3>D. Nouveautés version 3.0</h3>
<ul>
    <li>✅ Interface d'administration globale (admin_global.php)</li>
    <li>✅ Scan QR code pour recherche rapide de livres</li>
    <li>✅ Statistiques globales avec graphiques Chart.js</li>
    <li>✅ Gestion centralisée des badges</li>
    <li>✅ Configuration générale du système</li>
    <li>✅ Dashboard personnalisé pour admin</li>
</ul>

<!-- CONCLUSION -->
<div style="margin-top: 60px; padding: 30px; background: #f0f8ff; border-radius: 10px; text-align: center;">
    <h3 style="color: #0e1f4c;">📌 Pour toute assistance</h3>
    <p>Documentation générée le <?= date('d/m/Y à H:i') ?></p>
    <p><strong>Mainteneur :</strong> Équipe technique - Lycée Bel Air</p>
    <p><strong>Version actuelle :</strong> 3.0 (avec administration globale et scan QR)</p>
    <p><em>"Une bonne administration est la clé d'un système fiable."</em></p>
</div>

</body>
</html>
