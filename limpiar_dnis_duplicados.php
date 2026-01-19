<?php
/**
 * Script para limpiar DNIs duplicados antes de la migración
 */

require_once __DIR__ . '/init.php';

try {
    global $pdo;
    
    echo "========================================\n";
    echo "Limpieza de DNIs antes de migración\n";
    echo "========================================\n\n";
    
    // Verificar DNIs duplicados
    echo "1. Buscando DNIs duplicados o vacíos...\n";
    $stmt = $pdo->query("SELECT dni, COUNT(*) as count FROM candidatos GROUP BY dni HAVING count > 1 OR dni = ''");
    $duplicados = $stmt->fetchAll();
    
    if (count($duplicados) > 0) {
        echo "   ⚠ Se encontraron " . count($duplicados) . " DNIs con problemas:\n";
        foreach ($duplicados as $dup) {
            $dni_display = $dup['dni'] === '' ? '(vacío)' : $dup['dni'];
            echo "      - DNI: {$dni_display} - Cantidad: {$dup['count']}\n";
        }
        echo "\n";
        
        // Actualizar DNIs vacíos o duplicados con un valor temporal único
        echo "2. Actualizando DNIs problemáticos...\n";
        
        $stmt = $pdo->query("SELECT id, dni, nombre FROM candidatos WHERE dni = '' OR dni IS NULL ORDER BY id");
        $candidatos_vacios = $stmt->fetchAll();
        
        if (count($candidatos_vacios) > 0) {
            echo "   - Actualizando " . count($candidatos_vacios) . " candidatos con DNI vacío/NULL...\n";
            foreach ($candidatos_vacios as $cand) {
                $nuevo_dni = 'TEMP_' . $cand['id'] . '_' . time();
                $pdo->prepare("UPDATE candidatos SET dni = ? WHERE id = ?")->execute([$nuevo_dni, $cand['id']]);
                echo "      ✓ Candidato ID {$cand['id']} ({$cand['nombre']}): DNI temporal asignado: {$nuevo_dni}\n";
            }
            echo "\n";
        }
        
        // Buscar otros duplicados (no vacíos)
        $stmt = $pdo->query("SELECT dni, COUNT(*) as count FROM candidatos WHERE dni != '' AND dni IS NOT NULL GROUP BY dni HAVING count > 1");
        $otros_duplicados = $stmt->fetchAll();
        
        if (count($otros_duplicados) > 0) {
            echo "   - Actualizando " . count($otros_duplicados) . " grupos de DNIs duplicados...\n";
            foreach ($otros_duplicados as $dup) {
                // Obtener todos los candidatos con este DNI
                $stmt = $pdo->prepare("SELECT id, nombre FROM candidatos WHERE dni = ? ORDER BY id");
                $stmt->execute([$dup['dni']]);
                $candidatos_con_dni = $stmt->fetchAll();
                
                // Dejar el primero, actualizar el resto
                $primero = true;
                foreach ($candidatos_con_dni as $cand) {
                    if ($primero) {
                        echo "      ✓ Candidato ID {$cand['id']} ({$cand['nombre']}): Mantiene DNI {$dup['dni']}\n";
                        $primero = false;
                    } else {
                        $nuevo_dni = $dup['dni'] . '_DUP_' . $cand['id'];
                        $pdo->prepare("UPDATE candidatos SET dni = ? WHERE id = ?")->execute([$nuevo_dni, $cand['id']]);
                        echo "      ✓ Candidato ID {$cand['id']} ({$cand['nombre']}): DNI actualizado a {$nuevo_dni}\n";
                    }
                }
            }
            echo "\n";
        }
        
    } else {
        echo "   ✓ No se encontraron DNIs duplicados\n\n";
    }
    
    // Verificar que todos los DNIs sean únicos ahora
    echo "3. Verificando que todos los DNIs sean únicos...\n";
    $stmt = $pdo->query("SELECT dni, COUNT(*) as count FROM candidatos GROUP BY dni HAVING count > 1");
    $aun_duplicados = $stmt->fetchAll();
    
    if (count($aun_duplicados) > 0) {
        echo "   ✗ ERROR: Aún hay DNIs duplicados:\n";
        foreach ($aun_duplicados as $dup) {
            echo "      - DNI: {$dup['dni']} - Cantidad: {$dup['count']}\n";
        }
        exit(1);
    } else {
        echo "   ✓ Todos los DNIs son únicos\n\n";
    }
    
    echo "========================================\n";
    echo "✓ Limpieza completada exitosamente\n";
    echo "========================================\n\n";
    
    echo "NOTA: Los candidatos con DNIs temporales (TEMP_* o *_DUP_*) deben ser\n";
    echo "revisados manualmente desde el panel de administración para asignarles\n";
    echo "sus DNIs reales.\n\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "✗ ERROR en la limpieza\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    exit(1);
}
?>
