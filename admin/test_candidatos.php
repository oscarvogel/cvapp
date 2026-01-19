<?php
require_once __DIR__ . '/../init.php';

echo "<h2>Lista de Candidatos para Probar PDF</h2>";

try {
    $stmt = $pdo->query("SELECT id, nombre, email FROM candidatos ORDER BY id DESC LIMIT 10");
    $candidatos = $stmt->fetchAll();
    
    if (empty($candidatos)) {
        echo "<p>No hay candidatos en la base de datos.</p>";
    } else {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Acciones</th></tr>";
        foreach ($candidatos as $c) {
            echo "<tr>";
            echo "<td>{$c['id']}</td>";
            echo "<td>{$c['nombre']}</td>";
            echo "<td>{$c['email']}</td>";
            echo "<td>";
            echo "<a href='debug_pdf.php?id={$c['id']}'>Debug PDF</a> | ";
            echo "<a href='generar_pdf.php?id={$c['id']}'>Generar PDF</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
