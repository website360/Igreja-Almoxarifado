<?php
echo "<h1>Teste de Conexão - Sistema Igreja</h1>";
echo "<p>PHP está funcionando: <strong style='color: green;'>✓ SIM</strong></p>";

// Testar conexão MySQL
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    echo "<p>MySQL está acessível: <strong style='color: green;'>✓ SIM</strong></p>";
    
    // Testar se banco existe
    $stmt = $pdo->query("SHOW DATABASES LIKE 'sistemaigreja2026'");
    $db = $stmt->fetch();
    
    if ($db) {
        echo "<p>Banco 'sistemaigreja2026' existe: <strong style='color: green;'>✓ SIM</strong></p>";
        
        // Conectar ao banco
        $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=sistemaigreja2026', 'root', '');
        echo "<p>Conexão com banco: <strong style='color: green;'>✓ SUCESSO</strong></p>";
        
        // Testar tabela users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $result = $stmt->fetch();
        echo "<p>Tabela 'users' acessível: <strong style='color: green;'>✓ SIM ({$result['total']} usuários)</strong></p>";
        
        echo "<hr>";
        echo "<h2 style='color: green;'>✓ Tudo funcionando!</h2>";
        echo "<p><a href='/dashboard/' style='padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px;'>Ir para Dashboard</a></p>";
        
    } else {
        echo "<p>Banco 'sistemaigreja2026' existe: <strong style='color: red;'>✗ NÃO</strong></p>";
        echo "<p style='color: red;'>ERRO: O banco de dados não existe. Execute o script de instalação.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>MySQL está acessível: <strong style='color: red;'>✗ NÃO</strong></p>";
    echo "<p style='color: red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<hr>";
    echo "<h3>Possíveis soluções:</h3>";
    echo "<ul>";
    echo "<li>Verifique se o MySQL está rodando no XAMPP Control Panel</li>";
    echo "<li>Verifique se a porta 3306 está correta</li>";
    echo "<li>Tente reiniciar o MySQL no XAMPP</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Informações do Sistema:</h3>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✓ Instalado' : '✗ Não instalado') . "</li>";
echo "</ul>";
?>
