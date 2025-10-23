# 🚀 Lead Collector Pro v2.0

## Plugin WordPress de collecte de leads avec notification automatique d'articles par catégorie

---

## ✨ NOUVELLE FONCTIONNALITÉ PRINCIPALE

### 📬 Notifications automatiques d'articles par catégorie

**Comment ça fonctionne :**

1. Vous créez un **template d'email** pour une catégorie WordPress (ex: "Actualités")
2. Vous **assignez des catégories** à vos leads (individuellement ou par groupes)
3. Quand vous **publiez un article** dans cette catégorie → **tous les leads associés reçoivent automatiquement un email** avec le lien vers l'article

**Exemple concret :**
```
📝 Vous publiez un article dans "Adhérents"
   ↓
👥 Tous les leads du groupe "Adhérents" reçoivent un email automatique
   avec le titre, l'extrait et le lien vers l'article
```

---

## 📦 CONTENU DU PACKAGE

### Fichiers créés (prêts à l'emploi) ✅
- `lead-collector.php` - Fichier principal du plugin
- `services/class-database.php` - 8 tables BDD complètes
- `services/class-lead-manager.php` - Gestion CRUD des leads
- `services/class-post-notification-handler.php` - Notifications automatiques
- `services/class-template-parser.php` - Parser de variables
- `services/class-email-sender.php` - Envoi d'emails

### Fichiers à créer (20 fichiers) ⚠️
Voir le document **ARCHITECTURE-COMPLETE-v2.md** pour les spécifications détaillées de chaque fichier.

---

## 🏗️ ARCHITECTURE FLAT

```
lead-collector-pro/
├── lead-collector.php          ✅ Créé
├── services/                   📁 6/9 créés
├── admin/                      📁 0/6 créés
├── views/                      📁 0/6 créés
└── assets/                     📁 0/4 créés
```

**Principe** : 1 seul niveau de dossiers maximum. Chaque fichier est indépendant et peut être modifié par l'IA sans dépendances complexes.

---

## 📊 BASE DE DONNÉES (8 tables)

### Tables principales
1. `wp_lc_leads` - Leads collectés
2. `wp_lc_groups` - Groupes de leads
3. `wp_lc_lead_groups` - Relations leads ↔ groupes
4. `wp_lc_lead_categories` - Relations leads ↔ catégories WordPress
5. `wp_lc_notification_templates` - Templates emails par catégorie
6. `wp_lc_campaigns` - Campagnes d'emailing
7. `wp_lc_campaign_logs` - Logs d'envoi
8. `wp_lc_forms` - Formulaires d'inscription

⚠️ **Table manquante à ajouter** : `wp_lc_group_categories` (voir ARCHITECTURE-COMPLETE-v2.md)

---

## 🎯 MODULES DU PLUGIN

### 1. Audience (Leads)
- Gestion complète des leads
- Statuts : actif, en attente, désinscrit, bounced
- Tags, champs personnalisés
- Import/Export CSV
- Attribution de catégories et groupes

### 2. Formulaires
- Formulaires d'inscription personnalisables
- Shortcode `[lc_form id="1"]`
- Double opt-in optionnel
- Champs personnalisés

### 3. Campagnes
- Envoi d'emails manuels (newsletters)
- Brouillon / Programmé / Envoyé
- Statistiques : ouvertures, clics, erreurs
- Ciblage : tous, groupes, catégories

### 4. Post Notifications (NOUVEAU) ⭐
- Templates d'emails par catégorie WordPress
- Variables dynamiques : `{{post_title}}`, `{{post_url}}`, etc.
- Catégories par défaut pour nouveaux leads
- Groupes avec catégories assignées
- Envoi automatique lors de la publication

### 5. Paramètres
- Configuration de l'expéditeur
- Double opt-in
- Catégories par défaut
- Maintenance

---

## 🚀 SCÉNARIOS D'UTILISATION

### Scenario 1 : Blog avec plusieurs rubriques
```
Catégories WordPress : Actualités, Tutoriels, Offres
↓
Template "Actualités" : Email sobre avec extrait
Template "Offres" : Email promotionnel avec CTA
↓
Leads groupe "VIP" → reçoivent toutes les catégories
Leads groupe "Gratuit" → reçoivent uniquement "Actualités"
```

### Scenario 2 : Association avec adhérents
```
Groupes : Adhérents, Bureau, Public
Catégories : Info Adhérents, Assemblée Générale, Actualités publiques
↓
Groupe "Adhérents" → catégories "Info Adhérents" + "Actualités publiques"
Groupe "Bureau" → toutes les catégories
Groupe "Public" → uniquement "Actualités publiques"
↓
Publication d'un article "Assemblée Générale"
→ Seuls les membres du Bureau reçoivent l'email
```

### Scenario 3 : Site e-commerce
```
Catégories : Nouveautés, Promotions, Blog
↓
Tout nouveau lead → reçoit automatiquement "Nouveautés" + "Promotions"
↓
Publication d'un produit dans "Nouveautés"
→ Tous les leads actifs reçoivent la notification
```

---

## 📋 INSTALLATION

### Prérequis
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+

### Étapes
1. Uploadez le dossier `lead-collector-pro` dans `/wp-content/plugins/`
2. Activez le plugin dans WordPress
3. Les 8 tables seront créées automatiquement
4. Configurez dans **Leads > Paramètres**

---

## 🔧 COMPLÉTER LE PLUGIN

Le plugin est actuellement **complété à 23% (6/26 fichiers)**.

### Pour terminer le développement :

**Consultez le document `ARCHITECTURE-COMPLETE-v2.md`** qui contient :
- Spécifications détaillées de chaque fichier manquant
- Code de référence pour chaque méthode
- Ordre recommandé de création
- Instructions pour IA

### Ordre de développement recommandé :

**Phase 1 : Services** (priorité haute)
1. `services/class-form-handler.php`
2. `services/class-group-manager.php`
3. `services/class-campaign-manager.php`

**Phase 2 : Admin** (priorité haute)
4. `admin/class-admin-menu.php`
5. `admin/class-audience-page.php`
6. `admin/class-post-notifications-page.php`

**Phase 3 : Views** (priorité moyenne)
7. Tous les fichiers `views/`

**Phase 4 : Assets** (priorité basse)
8. CSS et JavaScript

---

## 💬 UTILISATION

### Shortcode formulaire
```
[lc_form id="1"]
```

### Variables disponibles dans les templates
```
{{post_title}}       - Titre de l'article
{{post_url}}         - URL de l'article
{{post_excerpt}}     - Extrait
{{post_content}}     - Contenu complet
{{post_date}}        - Date de publication
{{post_author}}      - Auteur
{{category_name}}    - Nom de la catégorie
{{site_name}}        - Nom du site
{{site_url}}         - URL du site
{{lead_email}}       - Email du lead
{{lead_first_name}}  - Prénom du lead
{{lead_last_name}}   - Nom du lead
```

### Exemple de template
```html
<h2>Nouvel article : {{post_title}}</h2>
<p>Bonjour {{lead_first_name}},</p>
<p>Un nouvel article vient d'être publié dans la catégorie {{category_name}} :</p>
<blockquote>{{post_excerpt}}</blockquote>
<p><a href="{{post_url}}">Lire l'article complet</a></p>
<p>Cordialement,<br>L'équipe {{site_name}}</p>
```

---

## 🔐 SÉCURITÉ

- ✅ Nonces WordPress sur toutes les requêtes AJAX
- ✅ Échappement de toutes les sorties
- ✅ Requêtes SQL préparées (`$wpdb->prepare`)
- ✅ Limitation de taux (anti-spam)
- ✅ Protection du dossier exports
- ✅ Vérification des permissions (`manage_options`)

---

## 📞 SUPPORT

- **Documentation** : ARCHITECTURE-COMPLETE-v2.md
- **VPS 1&1** : Configuration optimisée pour VPS IONOS
- **Architecture** : Flat, modulaire, évolutive par IA

---

## 📜 LICENCE

GPL v2 ou ultérieure

---

## 🎯 ROADMAP

### Version 2.0.0 (actuelle - 23% complété)
- ✅ Base de données (8 tables)
- ✅ Gestion des leads
- ✅ Notifications automatiques (logique)
- ⚠️ Interface admin à créer
- ⚠️ Formulaires à créer
- ⚠️ Campagnes à créer

### Version 2.1.0 (future)
- [ ] Statistiques avancées
- [ ] Tracking des ouvertures/clics
- [ ] Intégrations (Mailchimp, SendinBlue)
- [ ] A/B testing des templates

### Version 2.2.0 (future)
- [ ] Éditeur visuel de templates (drag & drop)
- [ ] Automatisations (si X alors Y)
- [ ] Segmentation avancée
- [ ] Webhooks

---

**Version** : 2.0.0  
**Date** : 23 octobre 2025  
**Statut** : En développement (23% complété)  
**Architecture** : FLAT (1 niveau max)  
**Compatibilité** : WordPress 5.0+ | PHP 7.4+ | VPS 1&1 IONOS

---

💡 **Conseil** : Commencez par lire `ARCHITECTURE-COMPLETE-v2.md` pour comprendre l'architecture complète !
