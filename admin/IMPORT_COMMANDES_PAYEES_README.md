# Import Commandes Payées - Guide d'Utilisation

## 🎯 Objectif

Cette fonctionnalité permet d'importer uniquement les commandes avec le statut **"delivered"** (livrées/payées) pour faciliter le paiement des commissions aux affiliés.

## 📍 Accès

1. **Page principale** : `admin/payments_received.php`
2. **Page avancée** : `admin/import_paid_orders.php`

## 🚀 Fonctionnalités

### 1. Export Simple (payments_received.php)

**Bouton vert** : "Exporter commandes payées via Excel"
- Export direct de toutes les commandes avec statut "delivered"
- Format CSV avec nom de fichier : `commandes_payees_YYYY-MM-DD.csv`
- Inclut les informations essentielles pour le paiement

### 2. Import Avancé (import_paid_orders.php)

**Page dédiée** avec fonctionnalités avancées :

#### 📊 Statistiques en Temps Réel
- **Nombre de commandes payées**
- **Montant total des commandes**
- **Total des commissions à payer**
- **Nombre d'affiliés concernés**

#### 🔍 Filtres Avancés
- **Période** : Date de début et fin
- **Affilié spécifique** : Filtrer par affilié
- **Format d'export** : CSV ou Excel (.xls)

#### 📋 Données Exportées

**Colonnes incluses :**
1. ID Commande
2. Numéro Commande
3. ID Affilié
4. Nom d'utilisateur Affilié
5. Nom complet Affilié
6. Nom du Client
7. Téléphone Client
8. Adresse Client
9. Ville Client
10. Montant Total
11. Commission Affilié
12. Date de Commande
13. Date de Livraison
14. Statut
15. Nombre d'Articles
16. Liste des Produits

## 🛠️ Utilisation

### Étape 1 : Accéder à la fonctionnalité
```
http://localhost/adnane1/admin/payments_received.php
```

### Étape 2 : Choisir l'option d'export

#### Option A - Export Simple
1. Cliquer sur le bouton vert **"Exporter commandes payées via Excel"**
2. Le fichier CSV se télécharge automatiquement

#### Option B - Import Avancé
1. Cliquer sur **"Import Avancé Commandes Payées"**
2. Utiliser les filtres si nécessaire
3. Choisir le format d'export (CSV ou Excel)
4. Cliquer sur **"Exporter CSV"** ou **"Exporter Excel"**

### Étape 3 : Utiliser les données exportées

Le fichier exporté contient toutes les informations nécessaires pour :
- ✅ Identifier les affiliés à payer
- ✅ Calculer les montants de commission
- ✅ Vérifier les commandes livrées
- ✅ Traçabilité complète

## 📈 Avantages

### Pour l'Administration
- **Gain de temps** : Export ciblé des commandes payées uniquement
- **Précision** : Données filtrées par statut "delivered"
- **Flexibilité** : Filtres par date et affilié
- **Traçabilité** : Historique complet des commandes

### Pour les Affiliés
- **Transparence** : Voir exactement quelles commandes sont payées
- **Justification** : Détails complets des commissions
- **Confiance** : Données vérifiables et détaillées

## 🔧 Configuration Technique

### Critères de Sélection
```sql
WHERE o.status = 'delivered'
```

### Tables Utilisées
- `orders` : Commandes principales
- `users` : Informations des affiliés
- `order_items` : Détails des articles

### Formats Supportés
- **CSV** : Séparateur virgule, encodage UTF-8
- **Excel** : Format .xls compatible avec Excel

## 📝 Notes Importantes

### Statut "delivered"
- Seules les commandes avec statut **"delivered"** sont incluses
- Ce statut indique que la commande a été livrée et payée
- Les autres statuts (new, confirmed, etc.) ne sont pas inclus

### Données Sensibles
- Les informations client sont incluses pour la traçabilité
- Respecter la confidentialité lors de l'utilisation
- Ne pas partager les fichiers avec des tiers

### Performance
- L'export peut prendre quelques secondes pour de gros volumes
- Utiliser les filtres pour réduire le temps de traitement
- Les statistiques sont calculées en temps réel

## 🆘 Support

En cas de problème :
1. Vérifier que la base de données est accessible
2. Contrôler les permissions d'écriture pour les exports
3. Vérifier que les tables `orders`, `users`, et `order_items` existent
4. Consulter les logs d'erreur si nécessaire

## 🔄 Mises à Jour

Cette fonctionnalité est régulièrement mise à jour pour :
- Améliorer les performances
- Ajouter de nouveaux filtres
- Optimiser les formats d'export
- Corriger les bugs éventuels

---

**Dernière mise à jour** : <?= date('d/m/Y') ?>
**Version** : 1.0
**Auteur** : Système d'Administration
