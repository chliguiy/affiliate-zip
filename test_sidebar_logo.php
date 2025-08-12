<?php
// Test de la sidebar avec logo
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Sidebar Logo - SCAR AFFILIATE</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        
        .test-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .test-info h2 {
            color: #007bff;
            margin-bottom: 15px;
        }
        
        .test-info ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .test-info li {
            margin-bottom: 8px;
        }
        
        .success {
            color: #28a745;
            font-weight: bold;
        }
        
        .info {
            color: #17a2b8;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="test-info">
            <h2>🧪 Test de la Sidebar avec Logo</h2>
            <p><strong>Objectif :</strong> Vérifier que le logo SCAR AFFILIATE s'affiche correctement dans la sidebar.</p>
            
                               <h3>✅ Ce qui devrait être visible :</h3>
                   <ul>
                       <li><span class="success">✓</span> Le logo SCAR AFFILIATE en haut de la sidebar (150x150px)</li>
                       <li><span class="success">✓</span> Effet de survol sur le logo (zoom + ombre)</li>
                       <li><span class="success">✓</span> Menu de navigation en dessous</li>
                   </ul>
            
                               <h3>🔍 Vérifications à faire :</h3>
                   <ul>
                       <li><span class="info">ℹ</span> Le logo est-il bien centré en haut de la sidebar ?</li>
                       <li><span class="info">ℹ</span> Le logo a-t-il une taille appropriée (150x150px) ?</li>
                       <li><span class="info">ℹ</span> L'effet de survol fonctionne-t-il ?</li>
                       <li><span class="info">ℹ</span> La sidebar est-elle responsive sur mobile ?</li>
                   </ul>
            
            <h3>📱 Test Mobile :</h3>
            <ul>
                <li><span class="info">ℹ</span> Redimensionnez la fenêtre pour tester le responsive</li>
                <li><span class="info">ℹ</span> Le bouton menu mobile devrait apparaître</li>
                <li><span class="info">ℹ</span> Cliquez sur le bouton pour ouvrir/fermer la sidebar</li>
            </ul>
        </div>
        
        <div class="test-info">
                           <h3>🎯 Résultat attendu :</h3>
               <p>La sidebar devrait maintenant afficher :</p>
               <ol>
                   <li><strong>Logo SCAR AFFILIATE</strong> (image du logo, 150x150px)</li>
                   <li><strong>Menu de navigation</strong> (Dashboard, Commandes, etc.)</li>
               </ol>
        </div>
    </div>
</body>
</html> 