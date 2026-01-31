<?php
/**
 * SCRIPT DE TEST EMAIL - DIAGNOSTIC ANTI-SPAM
 * Envoie un email de test et vérifie la configuration
 */

// À CONFIGURER
$email_test = 'test-ca430il3i@srv1.mail-tester.com'; // Remplace par ton email
$domaine = 'entraide-plus-iroise.fr'; // Remplace par ton vrai domaine

echo "<h2>🔍 Diagnostic Email Anti-Spam</h2>";

// 1. Vérifier la configuration PHP
echo "<h3>1. Configuration PHP mail()</h3>";
echo "Fonction mail() disponible : " . (function_exists('mail') ? '✅ Oui' : '❌ Non') . "<br>";
echo "Serveur : " . $_SERVER['SERVER_NAME'] . "<br>";
echo "IP Serveur : " . $_SERVER['SERVER_ADDR'] . "<br><br>";

// 2. Construire un email de test
$subject = "Test anti-spam - " . date('Y-m-d H:i:s');

$headers = "From: Entraide Plus Iroise <noreply@{$domaine}>\r\n";
$headers .= "Reply-To: contact@{$domaine}\r\n";
$headers .= "Return-Path: noreply@{$domaine}\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "X-Priority: 3\r\n";
$headers .= "Message-ID: <" . time() . "-" . md5(uniqid()) . "@{$domaine}>\r\n";

$message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
</head>
<body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center;'>
        <h1>🧪 Email de Test</h1>
    </div>
    
    <div style='padding: 20px; background: #f8f9fa;'>
        <h2>Ceci est un test de délivrabilité</h2>
        <p>Si vous recevez cet email, vérifiez :</p>
        <ul>
            <li>Est-il dans votre boîte de réception ? ✅</li>
            <li>Est-il dans les spams ? ❌</li>
            <li>Est-il dans les promotions ? ⚠️</li>
        </ul>
        
        <h3>Informations techniques :</h3>
        <p><strong>Date d'envoi :</strong> " . date('d/m/Y H:i:s') . "</p>
        <p><strong>Serveur :</strong> " . $_SERVER['SERVER_NAME'] . "</p>
        <p><strong>IP :</strong> " . $_SERVER['SERVER_ADDR'] . "</p>
        <p><strong>Domaine :</strong> {$domaine}</p>
    </div>
    
    <div style='padding: 20px; background: white; border-top: 2px solid #e0e0e0;'>
        <h3>📊 Pour tester la qualité de l'email :</h3>
        <ol>
            <li>Transfère cet email à : <strong>check@mail-tester.com</strong></li>
            <li>Va sur <a href='https://www.mail-tester.com'>https://www.mail-tester.com</a></li>
            <li>Regarde ton score et les recommandations</li>
        </ol>
    </div>
    
    <div style='padding: 15px; background: #f0f0f0; text-align: center; font-size: 12px; color: #666;'>
        <p>Email envoyé par Entraide Plus Iroise</p>
        <p><a href='mailto:contact@{$domaine}'>Nous contacter</a></p>
    </div>
</body>
</html>
";

// Version texte
$message_txt = "Ceci est un email de test pour vérifier la délivrabilité.
Si vous recevez cet email, vérifiez s'il est dans les spams ou dans la boîte de réception.
Pour tester : transférez cet email à check@mail-tester.com puis allez sur mail-tester.com";

// 3. Envoyer l'email
echo "<h3>2. Envoi de l'email de test</h3>";
$result = mail($email_test, $subject, $message, $headers);

if ($result) {
    echo "✅ <strong>Email envoyé avec succès à {$email_test}</strong><br><br>";
    echo "⚠️ <strong>IMPORTANT</strong> : Vérifie ta boîte email ET ton dossier spam !<br><br>";
} else {
    echo "❌ <strong>Échec de l'envoi</strong><br><br>";
}

// 4. Instructions pour le test mail-tester
echo "<h3>3. Test de qualité email (mail-tester.com)</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>";
echo "<strong>📧 Pour tester la qualité de tes emails :</strong><br><br>";
echo "1. Va sur <a href='https://www.mail-tester.com' target='_blank'>https://www.mail-tester.com</a><br>";
echo "2. Copie l'adresse email affichée (exemple: test-xxxxx@mail-tester.com)<br>";
echo "3. Envoie un email de test à cette adresse<br>";
echo "4. Retourne sur mail-tester.com pour voir ton score<br>";
echo "5. <strong>Objectif : Score de 9/10 ou 10/10</strong><br>";
echo "</div><br>";

// 5. Vérifications DNS à faire
echo "<h3>4. Vérifications DNS nécessaires</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #667eea;'>";
echo "<strong>Vérifie ces enregistrements DNS :</strong><br><br>";

echo "<strong>SPF :</strong><br>";
echo "<a href='https://mxtoolbox.com/spf.aspx?domain={$domaine}' target='_blank'>Vérifier SPF pour {$domaine}</a><br><br>";

echo "<strong>DKIM :</strong><br>";
echo "<a href='https://mxtoolbox.com/dkim.aspx?domain={$domaine}' target='_blank'>Vérifier DKIM pour {$domaine}</a><br><br>";

echo "<strong>DMARC :</strong><br>";
echo "<a href='https://mxtoolbox.com/dmarc.aspx?domain={$domaine}' target='_blank'>Vérifier DMARC pour {$domaine}</a><br><br>";

echo "<strong>Blacklist :</strong><br>";
echo "<a href='https://mxtoolbox.com/blacklists.aspx?ip={$_SERVER['SERVER_ADDR']}' target='_blank'>Vérifier si l'IP {$_SERVER['SERVER_ADDR']} est blacklistée</a><br>";
echo "</div><br>";

// 6. Checklist
echo "<h3>5. ✅ Checklist de configuration</h3>";
echo "<ul>";
echo "<li>[ ] Remplacer 'tondomaine.fr' par ton vrai domaine</li>";
echo "<li>[ ] Configurer SPF dans les DNS</li>";
echo "<li>[ ] Activer DKIM chez ton hébergeur</li>";
echo "<li>[ ] Configurer DMARC dans les DNS</li>";
echo "<li>[ ] Tester sur mail-tester.com (objectif 9-10/10)</li>";
echo "<li>[ ] Vérifier que l'IP n'est pas blacklistée</li>";
echo "<li>[ ] Demander aux bénévoles de marquer comme 'Non spam'</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>📝 Prochaines étapes :</h3>";
echo "<ol>";
echo "<li>Lance ce script et vérifie si l'email arrive</li>";
echo "<li>Envoie un email de test à check@mail-tester.com</li>";
echo "<li>Corrige les problèmes indiqués par mail-tester</li>";
echo "<li>Répète jusqu'à avoir un score de 9-10/10</li>";
echo "</ol>";

?>
