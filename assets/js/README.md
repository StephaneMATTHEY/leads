# 📦 Lead Collector Pro v2.0 - Documentation Complète

## 🎯 Vue d'ensemble

**Lead Collector Pro** est un plugin WordPress professionnel de collecte de leads avec **notification automatique d'articles par catégorie**.

### ✨ Fonctionnalité principale

Quand vous publiez un article dans une catégorie WordPress, **tous les leads ayant cette catégorie reçoivent automatiquement un email** avec le lien vers l'article.

---

## 📋 Table des matières

1. [Installation](#installation)
2. [Structure du plugin](#structure)
3. [Configuration](#configuration)
4. [Utilisation](#utilisation)
5. [Fichiers créés](#fichiers-créés)
6. [Compatibilité](#compatibilité)
7. [Support](#support)

---

## 🚀 Installation

### Étape 1 : Télécharger les fichiers

Tous les fichiers du plugin sont disponibles dans `/mnt/user-data/outputs/`

### Étape 2 : Organisation des fichiers

Créez cette structure sur votre serveur :

```
wp-content/plugins/lead-collector-pro/
├── lead-collector.php (déjà créé)
├── uninstall.php ✅ NOUVEAU
│
├── services/
│   ├── class-database.php (déjà créé)
│   ├── class-lead-manager.php (déjà créé)
│   ├── class-post-notification-handler.php (déjà créé)
│   ├── class-template-parser.php (déjà créé)
│   ├── class-email-sender.php (déjà créé)
│   ├── class-form-handler.php ✅ NOUVEAU
│   ├── class-group-manager.php ✅ NOUVEAU
│   └── class-campaign-manager.php ✅ NOUVEAU
│
├── admin/
│   ├── class-admin-menu.php ✅ NOUVEAU
│   ├── class-audience-page.php ✅ NOUVEAU
│   ├── class-post-notifications-page.php ✅ NOUVEAU
│   ├── class-campaigns-page.php ✅ NOUVEAU
│   ├── class-forms-page.php ✅ NOUVEAU
│   └── class-settings-page.php ✅ NOUVEAU
│
├── assets/
│   ├── css/
│   │   ├── frontend.css ✅ NOUVEAU
│   │   └── admin.css ✅ NOUVEAU
│   └── js/
│       ├── frontend.js ✅ NOUVEAU
│       └── admin.js ✅ NOUVEAU
│
└── exports/ (créé automatiquement)
    ├── .htaccess (à créer - voir ci-dessous)
    └── index.php (à créer - voir ci-dessous)
```

### Étape 3 : Sécuriser le dossier exports

**Créer `/exports/.htaccess` :**
```apache
Order deny,allow
Deny from all
```

**Créer `/exports/index.php` :**
```php
<?php
// Silence is golden
```

### Étape 4 : Mettre à jour class-database.php

⚠️ **IMPORTANT** : Remplacez votre fichier `services/class-database.php` par la version mise à jour :
- **Fichier à utiliser :** `class-database-updated.php`
- Cette version inclut la table `wp_lc_group_categories` nécessaire

### Étape 5 : Activer le plugin

1. Connectez-vous à votre admin WordPress
2. Allez dans **Extensions > Extensions installées**
3. Trouvez **Lead Collector Pro**
4. Cliquez sur **Activer**

✅ Le plugin créera automatiquement :
- Les 9 tables de la base de données
- Un formulaire par défaut
- Les options de configuration

---

## 📁 Structure du plugin

### Architecture FLAT (1 seul niveau de dossiers)

- **`services/`** : Logique métier (8 classes)
- **`admin/`** : Pages d'administration (6 classes)
- **`assets/`** : CSS et JavaScript (4 fichiers)
- **`exports/`** : Exports CSV générés

### Base de données (9 tables)

1. **`wp_lc_leads`** : Tous les leads collectés
2. **`wp_lc_groups`** : Groupes de leads
3. **`wp_lc_lead_groups`** : Relation leads ↔ groupes
4. **`wp_lc_lead_categories`** : Relation leads ↔ catégories WP
5. **`wp_lc_group_categories`** : Relation groupes ↔ catégories WP ✅ NOUVEAU
6. **`wp_lc_notification_templates`** : Templates emails par catégorie
7. **`wp_lc_campaigns`** : Campagnes d'emailing
8. **`wp_lc_campaign_logs`** : Logs d'envoi
9. **`wp_lc_forms`** : Formulaires personnalisés

---

## ⚙️ Configuration

### 1. Configuration initiale

Allez dans **Leads > Paramètres** :

#### Paramètres d'envoi
- **Nom de l'expéditeur** : Votre nom ou nom du site
- **Email de l'expéditeur** : email@votredomaine.com
- **Notifications admin** : Recevoir un email à chaque nouveau lead

#### Double opt-in
- **Activer le double opt-in** : Recommandé pour le RGPD
- **Sujet de l'email** : Personnalisable avec variables
- **Corps de l'email** : Template HTML personnalisable

#### Catégories par défaut
- Sélectionnez les catégories que les nouveaux leads recevront automatiquement

### 2. Créer des templates de notifications

Allez dans **Leads > Post Notifications** :

1. Cliquez sur **"Créer"** pour une catégorie
2. Définissez le **nom du template** (usage interne)
3. Personnalisez le **sujet** et le **corps** de l'email
4. Utilisez les **variables disponibles** :
   - `{{post_title}}` - Titre de l'article
   - `{{post_excerpt}}` - Extrait
   - `{{post_url}}` - Lien vers l'article
   - `{{first_name}}` - Prénom du lead
   - `{{category_name}}` - Nom de la catégorie
   - etc.
5. **Activez** le template
6. **Testez** l'envoi avant de publier

### 3. Créer des groupes

Allez dans **Leads > Audience** (via l'API, pas d'interface dédiée encore) :

Les groupes permettent d'organiser vos leads et d'assigner des catégories en masse.

---

## 📝 Utilisation

### Scénario 1 : Collecter des leads

#### Avec le formulaire par défaut
```php
[lead_collector]
```

#### Avec un formulaire personnalisé
1. Créez un formulaire dans **Leads > Formulaires**
2. Copiez le shortcode : `[lc_form id="X"]`
3. Collez-le dans n'importe quelle page/article

### Scénario 2 : Publier un article avec notification automatique

1. Créez un article dans **Articles > Ajouter**
2. Assignez-le à une catégorie (ex: "Actualités")
3. **Publiez** l'article
4. ✅ Tous les leads ayant la catégorie "Actualités" reçoivent automatiquement l'email !

#### Comment les leads sont ciblés ?

Un lead reçoit l'email si :
- Il a la catégorie assignée **individuellement**, OU
- Il est dans un **groupe** ayant cette catégorie

### Scénario 3 : Envoyer une campagne manuelle

1. Allez dans **Leads > Campagnes**
2. Cliquez sur **"Créer une campagne"**
3. Définissez le **nom**, **sujet** et **contenu**
4. Choisissez la **cible** :
   - **Tous** les leads actifs
   - **Groupes** spécifiques
   - **Catégories** spécifiques
   - **Liste personnalisée** d'IDs
5. **Envoyez maintenant** ou **programmez** l'envoi

### Scénario 4 : Gérer l'audience

Allez dans **Leads > Audience** :

- **Voir tous les leads** avec filtres
- **Éditer un lead** : modifier email, groupes, catégories
- **Exporter en CSV** avec filtres
- **Supprimer** des leads
- **Statistiques** en temps réel

---

## 📦 Fichiers créés (20/20 - 100%)

### ✅ Services (8/8)
- [x] class-database.php (MISE À JOUR avec table group_categories)
- [x] class-lead-manager.php
- [x] class-post-notification-handler.php
- [x] class-template-parser.php
- [x] class-email-sender.php
- [x] class-form-handler.php ✅ NOUVEAU
- [x] class-group-manager.php ✅ NOUVEAU
- [x] class-campaign-manager.php ✅ NOUVEAU

### ✅ Admin (6/6)
- [x] class-admin-menu.php ✅ NOUVEAU
- [x] class-audience-page.php ✅ NOUVEAU
- [x] class-post-notifications-page.php ✅ NOUVEAU
- [x] class-campaigns-page.php ✅ NOUVEAU
- [x] class-forms-page.php ✅ NOUVEAU
- [x] class-settings-page.php ✅ NOUVEAU

### ✅ Assets (4/4)
- [x] frontend.css ✅ NOUVEAU
- [x] admin.css ✅ NOUVEAU
- [x] frontend.js ✅ NOUVEAU
- [x] admin.js ✅ NOUVEAU

### ✅ Finition (1/1)
- [x] uninstall.php ✅ NOUVEAU

### ✅ Documentation (1/1)
- [x] README.md ✅ CE FICHIER

---

## 🔧 Compatibilité

### Serveur
- **OS** : Linux (Ubuntu 22.04.5 LTS)
- **VPS** : 1&1 (IONOS) VPS XL
- **PHP** : 7.4+ (recommandé 8.0+)
- **MySQL** : 5.7+ ou MariaDB 10.3+

### WordPress
- **Version minimale** : 5.8+
- **Version testée** : 6.4+
- **Multisite** : Compatible

### Navigateurs
- Chrome/Edge (dernières versions)
- Firefox (dernières versions)
- Safari (dernières versions)
- Mobile : iOS Safari, Chrome Mobile

---

## 🎨 Charte graphique

Le plugin utilise des variables CSS modernes :

```css
--primary: #4F46E5;
--secondary: #10B981;
--accent: #F59E0B;
--success: #10B981;
--danger: #EF4444;
--warning: #F59E0B;
```

Design inspiré de **Google** et **Apple** : sobre, minimaliste, professionnel.

---

## 🔐 Sécurité

### Protections intégrées
- ✅ Nonce WordPress sur toutes les actions
- ✅ Vérification des permissions (current_user_can)
- ✅ Sanitization de toutes les entrées
- ✅ Rate limiting (5 tentatives/heure par IP)
- ✅ Honeypot anti-bot
- ✅ Validation DNS des emails
- ✅ Blocage des domaines jetables
- ✅ Protection du dossier exports
- ✅ Échappement des sorties (esc_html, esc_attr)

---

## 📊 Fonctionnalités avancées

### Double opt-in
Email de confirmation automatique avec token unique avant activation du lead.

### Anti-spam multi-niveaux
1. Honeypot (champ caché)
2. Rate limiting (5/heure)
3. Validation DNS
4. Blocage domaines jetables
5. Temps de remplissage minimum

### Tracking intégré
Support natif pour :
- Google Analytics 4
- Google Tag Manager
- Facebook Pixel

### Variables de template
12+ variables disponibles pour personnaliser vos emails.

### Statistiques en temps réel
- Taux d'ouverture (si configuré)
- Taux de clic
- Leads actifs/inactifs
- Tendances (aujourd'hui, semaine, mois)

---

## 🆘 Support

### Logs
Les erreurs sont loggées dans :
- WordPress Debug Log (si WP_DEBUG activé)
- Console navigateur (JavaScript)

### Problèmes courants

#### Les emails ne sont pas envoyés
- Vérifiez la configuration SMTP de WordPress
- Testez avec un plugin SMTP (WP Mail SMTP)
- Vérifiez les paramètres dans **Leads > Paramètres**

#### Les catégories ne fonctionnent pas
- Vérifiez que la table `wp_lc_group_categories` existe
- Utilisez la version **class-database-updated.php**

#### Le formulaire ne s'affiche pas
- Vérifiez que le shortcode est correct : `[lead_collector]`
- Vérifiez que le CSS frontend est bien chargé

### Désinstallation

Pour **supprimer complètement** le plugin :
1. Allez dans **Extensions > Extensions installées**
2. **Désactivez** le plugin
3. Cliquez sur **Supprimer**
4. ⚠️ **TOUTES les données seront supprimées** (leads, campagnes, etc.)

Pour **désactiver temporairement** sans perdre les données :
- Utilisez le bouton **"Désactiver"** uniquement

---

## 🎯 Roadmap (fonctionnalités futures possibles)

- [ ] Segmentation avancée des leads
- [ ] A/B testing des emails
- [ ] Intégrations (Mailchimp, Sendinblue, etc.)
- [ ] Webhooks
- [ ] API REST publique
- [ ] Formulaires conditionnels
- [ ] Landing pages intégrées
- [ ] Score de leads (lead scoring)

---

## 📜 Licence

GPL v2 ou ultérieure

---

## 👨‍💻 Développement

**Version** : 2.0.0  
**Date de création** : 23 octobre 2025  
**Architecture** : FLAT (1 seul niveau)  
**Statut** : ✅ **100% COMPLET** (20/20 fichiers)

---

## 🙏 Remerciements

Merci d'utiliser Lead Collector Pro !

Pour toute question, contactez votre développeur ou consultez la documentation WordPress.

---

**🎉 PROJET TERMINÉ - PRÊT À L'EMPLOI ! 🎉**
