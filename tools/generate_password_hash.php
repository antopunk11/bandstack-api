<?php
// =============================================================
// tools/generate_password_hash.php
// Ejecutar UNA VEZ desde CLI para generar el hash del admin:
//   php tools/generate_password_hash.php
// Luego pegar el hash resultante en el INSERT del schema.sql
// =============================================================

if (PHP_SAPI !== 'cli') {
    die("Solo ejecutar desde línea de comandos.\n");
}

$password = readline("Introduce la contraseña para el admin: ");

if (strlen($password) < 10) {
    die("Error: mínimo 10 caracteres.\n");
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "\nHash generado:\n{$hash}\n\n";
echo "Usa este UPDATE en tu BD o reemplázalo en el INSERT del schema.sql:\n";
echo "UPDATE users SET password_hash = '{$hash}' WHERE email = 'admin@bandstack.local';\n\n";
