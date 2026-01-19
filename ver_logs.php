<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🔍 Visor de Logs - Debug Upload</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            margin: 0;
        }
        h1 {
            color: #ffff00;
            border-bottom: 2px solid #ffff00;
            padding-bottom: 10px;
        }
        .log-container {
            background: #2d2d2d;
            padding: 20px;
            border-left: 4px solid #00ff00;
            margin: 20px 0;
            max-height: 600px;
            overflow-y: auto;
        }
        .log-line {
            margin: 5px 0;
            padding: 5px;
            border-bottom: 1px solid #444;
        }
        .error { color: #ff0000; }
        .warning { color: #ffaa00; }
        .info { color: #00aaff; }
        .success { color: #00ff00; }
        button {
            background: #00ff00;
            color: #000;
            border: none;
            padding: 10px 20px;
            font-family: 'Courier New', monospace;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #00cc00;
        }
        .instructions {
            background: #2d2d2d;
            padding: 15px;
            border-left: 4px solid #ffaa00;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 Visor de Logs en Tiempo Real</h1>
    
    <div class="instructions">
        <h2 style="color:#ffaa00">📋 INSTRUCCIONES:</h2>
        <ol>
            <li>Deja esta ventana abierta</li>
            <li>En otra pestaña, abre: <code style="color:#00ff00">http://localhost/cvapp/index.php</code></li>
            <li>Llena el formulario y haz clic en "Enviar Datos"</li>
            <li>Vuelve a esta ventana para ver los logs detallados</li>
            <li>Copia los logs y envíamelos para analizarlos</li>
        </ol>
    </div>
    
    <button onclick="location.reload()">🔄 Actualizar Logs</button>
    <button onclick="clearLogs()">🗑️ Limpiar Pantalla</button>
    <button onclick="copyLogs()">📋 Copiar Logs</button>
    
    <div class="log-container" id="logContainer">
        <?php
        // Intentar leer el log de errores de PHP
        $logFiles = [
            'C:/xampp/php/logs/php_error_log',
            'C:/wamp64/logs/php_error.log',
            'C:/laragon/data/logs/php_error.log',
            '/var/log/php_errors.log',
            ini_get('error_log')
        ];
        
        $logFound = false;
        $logContent = '';
        
        foreach ($logFiles as $logFile) {
            if ($logFile && file_exists($logFile)) {
                echo "<div class='success'>✅ Log encontrado: $logFile</div>";
                // Leer las últimas 100 líneas
                $lines = file($logFile);
                $lastLines = array_slice($lines, -100);
                
                foreach ($lastLines as $line) {
                    $line = htmlspecialchars($line);
                    $class = 'info';
                    if (stripos($line, 'error') !== false) $class = 'error';
                    if (stripos($line, 'warning') !== false) $class = 'warning';
                    if (stripos($line, 'UPLOAD.PHP DEBUG') !== false) $class = 'success';
                    
                    echo "<div class='log-line $class'>$line</div>";
                    $logContent .= $line . "\n";
                }
                $logFound = true;
                break;
            }
        }
        
        if (!$logFound) {
            echo "<div class='warning'>⚠️ No se encontró el archivo de log de PHP</div>";
            echo "<div class='info'>Archivos buscados:</div>";
            foreach ($logFiles as $logFile) {
                if ($logFile) {
                    echo "<div class='info'>  - $logFile</div>";
                }
            }
            echo "<div class='info'>error_log configurado en: " . ini_get('error_log') . "</div>";
        }
        ?>
    </div>
    
    <script>
        function clearLogs() {
            document.getElementById('logContainer').innerHTML = '<div class="info">Logs limpiados. Presiona "Actualizar Logs" para recargar.</div>';
        }
        
        function copyLogs() {
            const logText = document.getElementById('logContainer').innerText;
            navigator.clipboard.writeText(logText).then(() => {
                alert('✅ Logs copiados al portapapeles');
            }).catch(err => {
                alert('❌ Error al copiar: ' + err);
            });
        }
    </script>
</body>
</html>
