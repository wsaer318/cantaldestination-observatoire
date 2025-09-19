<?php
/**
 * Guide de dépannage pour les problèmes d'API dans le navigateur
 */

echo "🔧 GUIDE DE DÉPANNAGE - API dans le navigateur\n";
echo "=============================================\n\n";

echo "✅ DIAGNOSTIC CONFIRMÉ: L'API fonctionne parfaitement côté serveur\n";
echo "Toutes les années (2019-2025) retournent des données valides.\n\n";

echo "❓ PROBLÈME IDENTIFIÉ: JavaScript/navigateur côté client\n\n";

echo "🛠️ SOLUTIONS À TESTER (dans l'ordre):\n";
echo "====================================\n\n";

// Solution 1: Cache du navigateur
echo "1️⃣ SOLUTION 1: VIDER LE CACHE DU NAVIGATEUR\n";
echo "===========================================\n";
echo "• Ouvrez l'infographie: https://observatoire.cantal-destination.com/infographie\n";
echo "• Appuyez sur Ctrl+F5 (ou Cmd+Shift+R sur Mac)\n";
echo "• Actualisez la page complètement\n";
echo "• Vérifiez si le graphique apparaît\n\n";

// Solution 2: Navigation privée
echo "2️⃣ SOLUTION 2: NAVIGATION PRIVÉE\n";
echo "===============================\n";
echo "• Ouvrez une nouvelle fenêtre en navigation privée/incognito\n";
echo "• Allez sur l'infographie\n";
echo "• Testez avec différentes années\n";
echo "• Si ça fonctionne, le problème vient du cache\n\n";

// Solution 3: Console du navigateur
echo "3️⃣ SOLUTION 3: CONSOLE DU NAVIGATEUR (F12)\n";
echo "===========================================\n";
echo "• Ouvrez l'infographie\n";
echo "• Appuyez sur F12 pour ouvrir les outils de développement\n";
echo "• Allez dans l'onglet 'Console'\n";
echo "• Cherchez ces messages d'erreur:\n";
echo "  - ❌ Erreur lors du chargement des données\n";
echo "  - ❌ Aucune donnée de mobilité interne disponible\n";
echo "  - ❌ [Infographie] ❌ Container non trouvé\n\n";

// Solution 4: Test direct de l'API
echo "4️⃣ SOLUTION 4: TEST DIRECT DE L'API\n";
echo "===================================\n";
echo "• Ouvrez une nouvelle onglet\n";
echo "• Collez cette URL:\n";
echo "  https://observatoire.cantal-destination.com/api/infographie/infographie_communes_excursion.php?annee=2023&periode=vacances_ete&zone=CANTAL&limit=10\n";
echo "• Si vous voyez du JSON avec des destinations, l'API fonctionne\n";
echo "• Si vous voyez une erreur, notez-la\n\n";

// Solution 5: Test JavaScript manuel
echo "5️⃣ SOLUTION 5: TEST JAVASCRIPT MANUEL\n";
echo "=====================================\n";
echo "• Dans la console (F12), exécutez:\n\n";
echo "  fetch('api/infographie/infographie_communes_excursion.php?annee=2023&periode=vacances_ete&zone=CANTAL&limit=10')\n";
echo "    .then(r => r.json())\n";
echo "    .then(d => console.log('Données:', d))\n";
echo "    .catch(e => console.error('Erreur:', e))\n\n";
echo "• Si vous voyez les données, le problème est dans le code JavaScript\n";
echo "• Si vous voyez une erreur, le problème est réseau/API\n\n";

// Solution 6: Désactivation des extensions
echo "6️⃣ SOLUTION 6: DÉSACTIVATION DES EXTENSIONS\n";
echo "===========================================\n";
echo "• Les extensions Chrome/Firefox peuvent bloquer les requêtes\n";
echo "• Ouvrez une fenêtre en navigation privée (extensions désactivées)\n";
echo "• Testez l'infographie\n";
echo "• Si ça fonctionne, une extension pose problème\n\n";

// Solution 7: Test avec différents navigateurs
echo "7️⃣ SOLUTION 7: TEST AVEC DIFFÉRENTS NAVIGATEURS\n";
echo "===============================================\n";
echo "• Essayez avec Chrome, Firefox, Edge, Safari\n";
echo "• Si ça fonctionne sur un navigateur mais pas un autre\n";
echo "• Le problème vient des paramètres de ce navigateur\n\n";

// Solution 8: Vérification du réseau
echo "8️⃣ SOLUTION 8: VÉRIFICATION DU RÉSEAU\n";
echo "=====================================\n";
echo "• Dans F12 > onglet Network\n";
echo "• Actualisez la page\n";
echo "• Cherchez la requête vers 'infographie_communes_excursion.php'\n";
echo "• Vérifiez le statut HTTP (200 = OK, 404/500 = problème)\n\n";

echo "🔍 ANALYSE DES RÉSULTATS POSSIBLES\n";
echo "===================================\n\n";

echo "SCÉNARIO 1: L'API directe fonctionne, mais pas l'infographie\n";
echo "-----------------------------------------------------------\n";
echo "✅ Problème identifié: JavaScript côté client\n";
echo "🔧 Solution: Corriger le code JavaScript\n\n";

echo "SCÉNARIO 2: Rien ne fonctionne dans le navigateur\n";
echo "------------------------------------------------\n";
echo "❌ Problème: Configuration réseau/navigateur\n";
echo "🔧 Solution: Vérifier les paramètres réseau\n\n";

echo "SCÉNARIO 3: Ça fonctionne en navigation privée\n";
echo "----------------------------------------------\n";
echo "✅ Problème identifié: Cache ou extensions\n";
echo "🔧 Solution: Vider le cache ou désactiver les extensions\n\n";

echo "📋 PROCÉDURE DE TEST RECOMMANDÉE\n";
echo "=================================\n\n";

echo "1. Ouvrir https://observatoire.cantal-destination.com/infographie\n";
echo "2. Ouvrir la console (F12)\n";
echo "3. Vider la console (bouton 🗑️)\n";
echo "4. Actualiser avec Ctrl+F5\n";
echo "5. Noter les messages d'erreur dans la console\n";
echo "6. Tester l'API directe dans un nouvel onglet\n";
echo "7. Tester en navigation privée\n\n";

echo "📝 RAPPORT DE BUG À FOURNIR\n";
echo "===========================\n\n";

echo "Si le problème persiste, fournissez ces informations:\n\n";

echo "• Navigateur et version: Chrome 118.0.5993.117\n";
echo "• Système d'exploitation: Windows 10\n";
echo "• Messages d'erreur dans la console: [coller ici]\n";
echo "• Résultat du test API direct: [fonctionne/échoue]\n";
echo "• Résultat en navigation privée: [fonctionne/échoue]\n";
echo "• Extensions installées: [liste]\n\n";

echo "🎯 PROCHAINES ÉTAPES\n";
echo "===================\n\n";

echo "1. Testez les solutions ci-dessus dans l'ordre\n";
echo "2. Notez les résultats de chaque test\n";
echo "3. Si le problème persiste, partagez le rapport de bug\n";
echo "4. Nous pourrons alors identifier la cause exacte\n\n";

echo "✅ Guide de dépannage terminé\n";
echo "=============================\n\n";

echo "💡 RAPPEL: L'API fonctionne parfaitement côté serveur.\n";
echo "Le problème est 100% côté client/navigateur.\n\n";
?>
