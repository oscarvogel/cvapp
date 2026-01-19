<?php 
require_once __DIR__ . '/init.php'; 

// Cargar áreas profesionales desde la base de datos
$areas_stmt = $pdo->query("SELECT id, nombre FROM areas_profesionales WHERE activa = 1 ORDER BY orden ASC, nombre ASC");
$areas_data = $areas_stmt->fetchAll(PDO::FETCH_ASSOC);
$areas_profesionales = array_column($areas_data, 'nombre');
$area_ids = [];
foreach ($areas_data as $area) {
    $area_ids[$area['nombre']] = $area['id'];
}

// --- Obtener datos anteriores si hay un error ---
$datos_anteriores = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); // Limpiar después de usar
$errores = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']); // Limpiar después de usar

// Función auxiliar para obtener valores anteriores
function valor_anterior($campo, $default = '') {
    global $datos_anteriores;
    $valor = $datos_anteriores[$campo] ?? $default;
    // Si el valor es un array, no procesarlo con htmlspecialchars
    if (is_array($valor)) {
        return $default;
    }
    return htmlspecialchars($valor);
}

// Función auxiliar para obtener valores anteriores de arrays (experiencia laboral)
function valor_anterior_array($campo_base, $indice, $subcampo, $default = '') {
    global $datos_anteriores;
    return htmlspecialchars($datos_anteriores[$campo_base][$indice][$subcampo] ?? $default);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Postulación - Formulario de Candidatos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/x-icon" href="assets/images/logo.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'brand': {
              50: '#eff6ff',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
            }
          }
        }
      }
    }
  </script>
  <style>
    /* Animaciones para el modal */
    #cameraModal {
      backdrop-filter: blur(4px);
    }
    
    #cameraModal.hidden {
      opacity: 0;
      pointer-events: none;
    }
    
    #cameraModal:not(.hidden) {
      opacity: 1;
      transition: opacity 0.3s ease-in-out;
    }
    
    /* Efecto de captura */
    @keyframes flash {
      0% { opacity: 1; }
      50% { opacity: 0.3; }
      100% { opacity: 1; }
    }
    
    .flash-effect {
      animation: flash 0.3s ease-in-out;
    }
    
    /* Responsive para el modal */
    @media (max-width: 768px) {
      #cameraModal .bg-white {
        margin: 1rem;
        max-height: 90vh;
        overflow-y: auto;
      }
    }
	
	/* Estilos para errores */
	.error-field {
		border-color: #ef4444 !important; /* Rojo Tailwind red-500 */
		background-color: #fef2f2 !important; /* Rojo Tailwind red-50 */
	}
	.error-text {
		color: #ef4444; /* Rojo Tailwind red-500 */
		font-size: 0.875rem; /* text-sm */
		margin-top: 0.25rem; /* mt-1 */
	}
  </style>
  <?= defined('UPPER_ASSETS_HTML') ? UPPER_ASSETS_HTML : '' ?>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
<header class="bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm sticky top-0 z-10">
  <div class="max-w-4xl mx-auto px-4 py-6">
    <div class="flex items-center gap-4 mb-4">
      <img src="assets/images/logo_fg.png" alt="Logo" class="h-12 w-auto">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Formulario de Postulación</h1>
        <p class="text-gray-600 mt-1">Completa tus datos para registrarte como candidato.</p>
      </div>
    </div>
  </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-8">
  <!-- Mostrar errores generales si los hay -->
  <?php if (!empty($errores) && !isset($errores['general'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl mb-6 shadow-sm">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium">Por favor, corrige los errores en el formulario.</span>
      </div>
    </div>
  <?php elseif (!empty($errores['general'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl mb-6 shadow-sm">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium"><?= e($errores['general']) ?></span>
      </div>
    </div>
  <?php endif; ?>

  <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-6">
      <h2 class="text-2xl font-bold text-white mb-2">Formulario de Postulación</h2>
      <p class="text-blue-100">Completa la información solicitada para registrarte como candidato</p>
    </div>

    <form id="cvForm" action="upload.php" method="post" enctype="multipart/form-data" novalidate class="p-8">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <!-- Honeypot contra bots -->
      <input type="text" name="website" tabindex="-1" autocomplete="off" class="absolute -left-9999 w-px h-px opacity-0">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Nombre -->
        <div class="space-y-2">
          <label for="nombre" class="block text-sm font-semibold text-gray-700">
            Nombre completo
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="text" 
            id="nombre" 
            name="nombre" 
            maxlength="100" 
            placeholder="Ej: Ana Pérez"
			value="<?= valor_anterior('nombre') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['nombre']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['nombre'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['nombre']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="space-y-2">
          <label for="email" class="block text-sm font-semibold text-gray-700">
            Email
            <span class="text-gray-400 text-xs">(opcional)</span>
          </label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            maxlength="150" 
            placeholder="ejemplo@correo.com"
			value="<?= valor_anterior('email') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['email']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['email'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['email']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Teléfono -->
        <div class="space-y-2">
          <label for="telefono" class="block text-sm font-semibold text-gray-700">
            Teléfono
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="text" 
            id="telefono" 
            name="telefono" 
            maxlength="30" 
            placeholder="Ej: 1123456789"
			value="<?= valor_anterior('telefono') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['telefono']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['telefono'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['telefono']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Áreas -->
        <div class="space-y-2 md:col-span-2">
          <label class="block text-sm font-semibold text-gray-700">
            Área / familia de puesto (podés elegir hasta 2)
            <span class="text-red-500">*</span>
          </label>
          <p class="text-sm text-gray-600 mb-3">Marcá las áreas donde tenés experiencia o te gustaría postularte.</p>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-4 bg-gray-50">
            <?php foreach ($areas_profesionales as $area): ?>
              <label class="flex items-center space-x-3 cursor-pointer hover:bg-white p-2 rounded-lg transition-colors duration-200 area-checkbox-label">
                <input 
                  type="checkbox" 
                  name="areas[]" 
                  value="<?= e($area) ?>"
                  data-area-id="<?= e($area_ids[$area] ?? '') ?>"
                  class="area-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                >
                <span class="text-sm text-gray-700 select-none"><?= e($area) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          
          <!-- Contenedor para especialidades dinámicas -->
          <div id="especialidades-container" class="mt-4 space-y-4"></div>
          
		  <?php if (isset($errores['areas'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['areas']) ?>
			</div>
		  <?php endif; ?>
          <div id="areasValidation" class="text-sm hidden"></div>
        </div>

        <!-- Experiencia -->
        <div class="space-y-2">
          <label for="anios_experiencia" class="block text-sm font-semibold text-gray-700">
            Años de experiencia
          </label>
          <input 
            type="number" 
            id="anios_experiencia" 
            name="anios_experiencia" 
            min="0" 
            max="50" 
            step="1" 
            placeholder="Ej: 0 (si no tienes experiencia)"
            value="<?= valor_anterior('anios_experiencia') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['anios_experiencia']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['anios_experiencia'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['anios_experiencia']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- INFORMACIÓN PERSONAL ADICIONAL -->
        <div class="md:col-span-2 mt-8">
          <div class="bg-gradient-to-r from-purple-500 to-indigo-600 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-bold text-white mb-1">Información Personal</h3>
            <p class="text-purple-100 text-sm">Completa los siguientes datos personales</p>
          </div>
        </div>

        <!-- DNI -->
        <div class="space-y-2">
          <label for="dni" class="block text-sm font-semibold text-gray-700">
            DNI / Documento de Identidad
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="text" 
            id="dni" 
            name="dni" 
            maxlength="20" 
            placeholder="Ej: 12345678"
			value="<?= valor_anterior('dni') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['dni']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['dni'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['dni']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Edad -->
        <div class="space-y-2">
          <label for="edad" class="block text-sm font-semibold text-gray-700">
            Edad
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
    type="number" 
    id="edad" 
    name="edad" 
    min="19" 
    max="79" 
            step="1" 
            placeholder="Ej: 28"
			value="<?= valor_anterior('edad') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['edad']) ? 'error-field' : '' ?>"
          >
          <p class="text-xs text-gray-500 mt-1">La edad debe ser mayor de 18 y menor de 80 años.</p>
		  <?php if (isset($errores['edad'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['edad']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Estado Civil -->
        <div class="space-y-2">
          <label for="estado_civil" class="block text-sm font-semibold text-gray-700">
            Estado Civil
            <span class="text-red-500">*</span>
          </label>
          <select 
            required 
            id="estado_civil" 
            name="estado_civil"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['estado_civil']) ? 'error-field' : '' ?>"
          >
            <option value="">Selecciona...</option>
            <option value="Soltero" <?= valor_anterior('estado_civil') === 'Soltero' ? 'selected' : '' ?>>Soltero/a</option>
            <option value="Casado" <?= valor_anterior('estado_civil') === 'Casado' ? 'selected' : '' ?>>Casado/a</option>
            <option value="Divorciado" <?= valor_anterior('estado_civil') === 'Divorciado' ? 'selected' : '' ?>>Divorciado/a</option>
            <option value="Viudo" <?= valor_anterior('estado_civil') === 'Viudo' ? 'selected' : '' ?>>Viudo/a</option>
            <option value="Unión libre" <?= valor_anterior('estado_civil') === 'Unión libre' ? 'selected' : '' ?>>Unión libre</option>
          </select>
		  <?php if (isset($errores['estado_civil'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['estado_civil']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Hijos -->
        <div class="space-y-2">
          <label for="hijos" class="block text-sm font-semibold text-gray-700">
            Número de hijos
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="number" 
            id="hijos" 
            name="hijos" 
            min="0" 
            max="20" 
            step="1" 
			value="<?= valor_anterior('hijos', '0') ?>"
            placeholder="0"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['hijos']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['hijos'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['hijos']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Edad Hijos -->
        <div class="space-y-2" id="edad_hijos_container" style="<?= (valor_anterior('hijos', 0) > 0) ? 'display: block;' : 'display: none;'; ?>">
          <label for="edad_hijos" class="block text-sm font-semibold text-gray-700">
            Edades de los hijos
          </label>
          <input 
            type="text" 
            id="edad_hijos" 
            name="edad_hijos" 
            maxlength="255" 
            placeholder="Ej: 5, 8, 12"
			value="<?= valor_anterior('edad_hijos') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['edad_hijos']) ? 'error-field' : '' ?>"
          >
          <p class="text-sm text-gray-500">Separa las edades con comas</p>
		  <?php if (isset($errores['edad_hijos'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['edad_hijos']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Nacionalidad (hasta 2) -->
        <div class="space-y-2">
          <label class="block text-sm font-semibold text-gray-700">
            Nacionalidad(es)
            <span class="text-red-500">*</span>
            <span class="text-xs text-gray-500 font-normal">(Máximo 2)</span>
          </label>
          
          <!-- Datalist con todas las nacionalidades -->
          <datalist id="nacionalidades-list">
            <option value="AFGANA">
            <option value="ALBANESA">
            <option value="ALEMANA">
            <option value="ANDORRANA">
            <option value="ANGOLEÑA">
            <option value="ARGELINA">
            <option value="ARGENTINA">
            <option value="ARMENIA">
            <option value="AUSTRALIANA">
            <option value="AUSTRÍACA">
            <option value="AZERBAIYANA">
            <option value="BAHAMEÑA">
            <option value="BANGLADESÍ">
            <option value="BARBADENSE">
            <option value="BAREINÍ">
            <option value="BELGA">
            <option value="BELICEÑA">
            <option value="BENINESA">
            <option value="BIELORRUSA">
            <option value="BIRMANA">
            <option value="BOLIVIANA">
            <option value="BOSNIA">
            <option value="BOTSUANESA">
            <option value="BRASILEÑA">
            <option value="BRITÁNICA">
            <option value="BRUNEANA">
            <option value="BÚLGARA">
            <option value="BURKINESA">
            <option value="BURUNDESA">
            <option value="BUTANESA">
            <option value="CABOVERDIANA">
            <option value="CAMBOYANA">
            <option value="CAMERUNESA">
            <option value="CANADIENSE">
            <option value="CATARÍ">
            <option value="CHADIANA">
            <option value="CHECA">
            <option value="CHILENA">
            <option value="CHINA">
            <option value="CHIPRIOTA">
            <option value="COLOMBIANA">
            <option value="COMORENSE">
            <option value="CONGOLEÑA">
            <option value="NORCOREANA">
            <option value="SURCOREANA">
            <option value="MARFILEÑA">
            <option value="COSTARRICENSE">
            <option value="CROATA">
            <option value="CUBANA">
            <option value="DANESA">
            <option value="DOMINICANA">
            <option value="ECUATORIANA">
            <option value="EGIPCIA">
            <option value="EMIRATÍ">
            <option value="ERITREA">
            <option value="ESLOVACA">
            <option value="ESLOVENA">
            <option value="ESPAÑOLA">
            <option value="ESTADOUNIDENSE">
            <option value="ESTONIA">
            <option value="ETÍOPE">
            <option value="FILIPINA">
            <option value="FINLANDESA">
            <option value="FIYIANA">
            <option value="FRANCESA">
            <option value="GABONESA">
            <option value="GAMBIANA">
            <option value="GEORGIANA">
            <option value="GHANESA">
            <option value="GRANADINA">
            <option value="GRIEGA">
            <option value="GUATEMALTECA">
            <option value="ECUATOGUINEANA">
            <option value="GUINEANA">
            <option value="GUINEANA-BISAUANA">
            <option value="GUYANESA">
            <option value="HAITIANA">
            <option value="HONDUREÑA">
            <option value="HÚNGARA">
            <option value="INDIA">
            <option value="INDONESIA">
            <option value="IRAQUÍ">
            <option value="IRANÍ">
            <option value="IRLANDESA">
            <option value="ISLANDESA">
            <option value="ISRAELÍ">
            <option value="ITALIANA">
            <option value="JAMAIQUINA">
            <option value="JAPONESA">
            <option value="JORDANA">
            <option value="KAZAJA">
            <option value="KENIANA">
            <option value="KIRGUISA">
            <option value="KIRIBATIANA">
            <option value="KUWAITÍ">
            <option value="LAOSIANA">
            <option value="LESOTENSE">
            <option value="LETONA">
            <option value="LIBANESA">
            <option value="LIBERIANA">
            <option value="LIBIA">
            <option value="LIECHTENSTEINIANA">
            <option value="LITUANA">
            <option value="LUXEMBURGUESA">
            <option value="MACEDONIA">
            <option value="MADAGASCARENSE">
            <option value="MALASIA">
            <option value="MALAUÍ">
            <option value="MALDIVA">
            <option value="MALIENSE">
            <option value="MALTESA">
            <option value="MARROQUÍ">
            <option value="MAURICIANA">
            <option value="MAURITANA">
            <option value="MEXICANA">
            <option value="MICRONESIA">
            <option value="MOLDAVA">
            <option value="MONEGASCA">
            <option value="MONGOLA">
            <option value="MONTENEGRINA">
            <option value="MOZAMBIQUEÑA">
            <option value="NAMIBIA">
            <option value="NAURUANA">
            <option value="NEPALESA">
            <option value="NICARAGÜENSE">
            <option value="NIGERINA">
            <option value="NIGERIANA">
            <option value="NORUEGA">
            <option value="NEOZELANDESA">
            <option value="OMANÍ">
            <option value="NEERLANDESA">
            <option value="PAKISTANÍ">
            <option value="PALAUANA">
            <option value="PALESTINA">
            <option value="PANAMEÑA">
            <option value="PAPÚ">
            <option value="PARAGUAYA">
            <option value="PERUANA">
            <option value="POLACA">
            <option value="PORTUGUESA">
            <option value="PUERTORRIQUEÑA">
            <option value="RUANDESA">
            <option value="RUMANA">
            <option value="RUSA">
            <option value="SALOMONENSE">
            <option value="SALVADOREÑA">
            <option value="SAMOANA">
            <option value="SANMARINENSE">
            <option value="SANTALUCENSE">
            <option value="SANTOTOMENSE">
            <option value="SAUDÍ">
            <option value="SENEGALESA">
            <option value="SERBIA">
            <option value="SEYCHELLENSE">
            <option value="SIERRALEONESA">
            <option value="SINGAPURENSE">
            <option value="SIRIA">
            <option value="SOMALÍ">
            <option value="CEILANESA">
            <option value="SUAZI">
            <option value="SUDAFRICANA">
            <option value="SUDANESA">
            <option value="SUDSUDANESA">
            <option value="SUECA">
            <option value="SUIZA">
            <option value="SURINAMESA">
            <option value="TAILANDESA">
            <option value="TANZANA">
            <option value="TAYIKA">
            <option value="TIMORENSE">
            <option value="TOGOLESA">
            <option value="TONGANA">
            <option value="TRINITENSE">
            <option value="TUNECINA">
            <option value="TURCOMANA">
            <option value="TURCA">
            <option value="TUVALUANA">
            <option value="UCRANIANA">
            <option value="UGANDESA">
            <option value="URUGUAYA">
            <option value="UZBEKA">
            <option value="VANUATUENSE">
            <option value="VATICANA">
            <option value="VENEZOLANA">
            <option value="VIETNAMITA">
            <option value="YEMENÍ">
            <option value="YIBUTIANA">
            <option value="ZAMBIANA">
            <option value="ZIMBABUENSE">
          </datalist>

          <div id="nacionalidades-container" class="space-y-2">
            <div class="nacionalidad-item flex gap-2">
              <input 
                required 
                type="text" 
                name="nacionalidades[]" 
                list="nacionalidades-list"
                maxlength="100" 
                placeholder="Ej: Argentina"
                value="<?= is_array(valor_anterior('nacionalidades')) ? e(valor_anterior('nacionalidades')[0] ?? '') : e(valor_anterior('nacionalidad', '')) ?>"
                class="nacionalidad-input flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white"
                autocomplete="off"
              >
              <button 
                type="button" 
                onclick="eliminarNacionalidad(this)"
                class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors duration-200 hidden"
                title="Eliminar nacionalidad"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>
          
          <button 
            type="button" 
            id="agregar-nacionalidad-btn"
            onclick="agregarNacionalidad()"
            class="mt-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition-colors duration-200 flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Agregar otra nacionalidad
          </button>
          
          <?php if (isset($errores['nacionalidades'])): ?>
            <div class="error-text flex items-center gap-1">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
              <?= e($errores['nacionalidades']) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Lugar Residencia -->
        <div class="space-y-2">
          <label for="lugar_residencia" class="block text-sm font-semibold text-gray-700">
            Lugar de Residencia
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="text" 
            id="lugar_residencia" 
            name="lugar_residencia" 
            maxlength="255" 
            placeholder="Ej: Buenos Aires, Capital Federal"
            value="<?= valor_anterior('lugar_residencia') ?>"
            list="localidades-list"
            autocomplete="off"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['lugar_residencia']) ? 'error-field' : '' ?>"
          >
          <datalist id="localidades-list"></datalist>
		  <?php if (isset($errores['lugar_residencia'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['lugar_residencia']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Ocupación Actual -->
        <div class="space-y-2">
          <label for="ocupacion_actual" class="block text-sm font-semibold text-gray-700">
            Ocupación Actual
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="text" 
            id="ocupacion_actual" 
            name="ocupacion_actual" 
            maxlength="100" 
            placeholder="Ej: Desarrollador Web"
			value="<?= valor_anterior('ocupacion_actual') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['ocupacion_actual']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['ocupacion_actual'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['ocupacion_actual']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Ocupación Padre -->
        <div class="space-y-2">
          <label for="ocupacion_padre" class="block text-sm font-semibold text-gray-700">
            Ocupación del Padre
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="text" 
            id="ocupacion_padre" 
            name="ocupacion_padre" 
            maxlength="100" 
            placeholder="Ej: Contador"
			value="<?= valor_anterior('ocupacion_padre') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['ocupacion_padre']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['ocupacion_padre'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['ocupacion_padre']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Ocupación Madre -->
        <div class="space-y-2">
          <label for="ocupacion_madre" class="block text-sm font-semibold text-gray-700">
            Ocupación de la Madre
            <span class="text-red-500">*</span>
          </label>
          <input 
            required 
            type="text" 
            id="ocupacion_madre" 
            name="ocupacion_madre" 
            maxlength="100" 
            placeholder="Ej: Maestra"
			value="<?= valor_anterior('ocupacion_madre') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['ocupacion_madre']) ? 'error-field' : '' ?>"
          >
		  <?php if (isset($errores['ocupacion_madre'])): ?>
			<div class="error-text flex items-center gap-1">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['ocupacion_madre']) ?>
			</div>
		  <?php endif; ?>
        </div>

        <!-- Foto -->
        <div class="space-y-2 md:col-span-2">
          <label for="foto" class="block text-sm font-semibold text-gray-700">
            Foto Personal (JPG, JPEG, PNG) Máx 2MB
            <span class="text-red-500">*</span>
          </label>
          
          <!-- Opciones de foto -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Subir archivo -->
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors duration-200 bg-gray-50">
              <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <input 
                type="file" 
                id="foto" 
                name="foto" 
                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                class="block w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"
              >
              <p class="mt-1 text-xs text-gray-500">Subir archivo</p>
            </div>
            
            <!-- Tomar foto -->
            <div class="border-2 border-dashed border-blue-300 rounded-lg p-4 text-center hover:border-blue-400 transition-colors duration-200 bg-blue-50">
              <svg class="mx-auto h-8 w-8 text-blue-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <button 
                type="button" 
                id="openCamera" 
                class="w-full bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-200"
              >
                Tomar Foto
              </button>
              <p class="mt-1 text-xs text-gray-500">Usar cámara</p>
            </div>
          </div>
          
		  <?php if (isset($errores['foto'])): ?>
			<div class="error-text flex items-center gap-1 mt-2">
				<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<?= e($errores['foto']) ?>
			</div>
		  <?php endif; ?>
		  
          <!-- Preview de foto seleccionada/capturada -->
          <div id="photoPreview" class="hidden mt-4 text-center">
            <img id="previewImage" class="mx-auto h-32 w-32 object-cover rounded-full border-4 border-green-500 shadow-lg" alt="Vista previa">
            <p class="mt-2 text-sm text-green-600 font-medium">Foto lista para enviar</p>
            <button type="button" id="removePhoto" class="mt-2 text-red-500 hover:text-red-700 text-sm underline">
              Cambiar foto
            </button>
          </div>
        </div>

        <!-- Modal de cámara -->
        <div id="cameraModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
          <div class="bg-white rounded-2xl max-w-md w-full">
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Tomar Foto</h3>
                <button type="button" id="closeCamera" class="text-gray-400 hover:text-gray-600">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
              
              <div class="space-y-4">
                <!-- Video preview -->
                <div class="relative">
                  <video id="camera" autoplay playsinline class="w-full h-64 object-cover rounded-lg bg-gray-100"></video>
                  <div id="cameraError" class="hidden absolute inset-0 flex items-center justify-center bg-gray-100 rounded-lg">
                    <div class="text-center">
                      <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                      </svg>
                      <p class="text-gray-500 text-sm">No se pudo acceder a la cámara</p>
                      <p class="text-gray-400 text-xs mt-1">Verifica los permisos del navegador</p>
                    </div>
                  </div>
                </div>
                
                <!-- Canvas oculto para captura -->
                <canvas id="canvas" class="hidden"></canvas>
                
                <!-- Botones -->
                <div class="flex gap-3">
                  <button 
                    type="button" 
                    id="capture" 
                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center gap-2"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Capturar
                  </button>
                  <button 
                    type="button" 
                    id="cancelCamera" 
                    class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors duration-200"
                  >
                    Cancelar
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- SECCIÓN DE FORMACIÓN PROFESIONAL -->
        <div class="md:col-span-2 mt-8 pt-6 border-t border-gray-200">
          <div class="bg-gradient-to-r from-cyan-500 to-blue-500 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-bold text-white mb-1">Formación Profesional</h3>
            <p class="text-cyan-100 text-sm">Detalla tu nivel educativo y formación complementaria</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Nivel Educativo -->
            <div class="space-y-2 md:col-span-2">
              <label for="nivel_educativo" class="block text-sm font-semibold text-gray-700">
                Nivel Educativo Alcanzado
                <span class="text-red-500">*</span>
              </label>
              <select
                required
                id="nivel_educativo"
                name="nivel_educativo"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['nivel_educativo']) ? 'error-field' : '' ?>"
              >
                <option value="">Selecciona...</option>
                <option value="Primaria" <?= valor_anterior('nivel_educativo') === 'Primaria' ? 'selected' : '' ?>>Primaria</option>
                <option value="Secundaria" <?= valor_anterior('nivel_educativo') === 'Secundaria' ? 'selected' : '' ?>>Secundaria</option>
                <option value="Terciario" <?= valor_anterior('nivel_educativo') === 'Terciario' ? 'selected' : '' ?>>Terciario</option>
                <option value="Universitaria" <?= valor_anterior('nivel_educativo') === 'Universitaria' ? 'selected' : '' ?>>Universitaria</option>
              </select>
			  <?php if (isset($errores['nivel_educativo'])): ?>
				<div class="error-text flex items-center gap-1">
					<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
					<?= e($errores['nivel_educativo']) ?>
				</div>
			  <?php endif; ?>
            </div>

            <!-- Carreras y Títulos -->
            <div class="space-y-2 md:col-span-2">
              <label for="carreras_titulos" class="block text-sm font-semibold text-gray-700">
                Carreras y Títulos Obtenidos
              </label>
              <textarea
                id="carreras_titulos"
                name="carreras_titulos"
                rows="3"
                maxlength="1000"
                placeholder="Ej: Ingeniería en Sistemas, Licenciatura en Administración..."
				class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['carreras_titulos']) ? 'error-field' : '' ?>"
			  ><?= valor_anterior('carreras_titulos') ?></textarea>
              <p class="text-xs text-gray-500">Separa las carreras y títulos con comas si es necesario.</p>
			  <?php if (isset($errores['carreras_titulos'])): ?>
				<div class="error-text flex items-center gap-1">
					<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
					<?= e($errores['carreras_titulos']) ?>
				</div>
			  <?php endif; ?>
            </div>

            <!-- Cursos y Capacitaciones -->
            <div class="space-y-2 md:col-span-2">
              <label for="cursos_capacitaciones" class="block text-sm font-semibold text-gray-700">
                Cursos y Capacitaciones Relevantes
              </label>
              <textarea
                id="cursos_capacitaciones"
                name="cursos_capacitaciones"
                rows="3"
                maxlength="1000"
                placeholder="Ej: Curso de Marketing Digital, Certificación en Python..."
				class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['cursos_capacitaciones']) ? 'error-field' : '' ?>"
			  ><?= valor_anterior('cursos_capacitaciones') ?></textarea>
              <p class="text-xs text-gray-500">Menciona cursos, talleres o certificaciones que hayas realizado.</p>
			  <?php if (isset($errores['cursos_capacitaciones'])): ?>
				<div class="error-text flex items-center gap-1">
					<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
					<?= e($errores['cursos_capacitaciones']) ?>
				</div>
			  <?php endif; ?>
            </div>

          </div>
        </div>
        <!-- FIN SECCIÓN DE FORMACIÓN PROFESIONAL -->

        <!-- SECCIÓN DE EXPERIENCIA LABORAL -->
        <div class="md:col-span-2 mt-8 pt-6 border-t border-gray-200">
          <div class="bg-gradient-to-r from-green-500 to-teal-600 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-bold text-white mb-1">Experiencia Laboral (Opcional)</h3>
            <p class="text-green-100 text-sm">Si tienes experiencia laboral, agrégala aquí. Si no has trabajado antes, puedes dejar esta sección vacía. Puedes agregar múltiples trabajos haciendo clic en "+ Agregar Experiencia".</p>
          </div>

          <div id="experiencia-laboral-container">
            <!-- Conjunto de campos para una experiencia laboral -->
            <?php 
            $experiencias_anteriores = $datos_anteriores['experiencia'] ?? [[]]; // Al menos un conjunto vacío
            $exp_index = 0;
            foreach ($experiencias_anteriores as $exp_data): 
            ?>
            <div class="experiencia-item bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2 md:col-span-2">
                  <label class="block text-sm font-semibold text-gray-700">
                    Nombre de la Empresa
                  </label>
                  <input
                    type="text"
                    name="experiencia[<?= $exp_index ?>][empresa]"
                    maxlength="255"
                    placeholder="Ej: Tech Solutions S.A."
                    value="<?= valor_anterior_array('experiencia', $exp_index, 'empresa') ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 <?= isset($errores["experiencia_{$exp_index}_empresa"]) ? 'error-field' : '' ?>"
                  >
                  <?php if (isset($errores["experiencia_{$exp_index}_empresa"])): ?>
                    <div class="error-text flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                      <?= e($errores["experiencia_{$exp_index}_empresa"]) ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700">
                    Puesto
                  </label>
                  <input
                    type="text"
                    name="experiencia[<?= $exp_index ?>][puesto]"
                    maxlength="100"
                    placeholder="Ej: Desarrollador Web"
                    value="<?= valor_anterior_array('experiencia', $exp_index, 'puesto') ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 <?= isset($errores["experiencia_{$exp_index}_puesto"]) ? 'error-field' : '' ?>"
                  >
                  <?php if (isset($errores["experiencia_{$exp_index}_puesto"])): ?>
                    <div class="error-text flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                      <?= e($errores["experiencia_{$exp_index}_puesto"]) ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700">
                    Empleador/Contacto
                  </label>
                  <input
                    type="text"
                    name="experiencia[<?= $exp_index ?>][empleador]"
                    maxlength="255"
                    placeholder="Ej: Juan Pérez (Supervisor)"
                    value="<?= valor_anterior_array('experiencia', $exp_index, 'empleador') ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 <?= isset($errores["experiencia_{$exp_index}_empleador"]) ? 'error-field' : '' ?>"
                  >
                  <?php if (isset($errores["experiencia_{$exp_index}_empleador"])): ?>
                    <div class="error-text flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                      <?= e($errores["experiencia_{$exp_index}_empleador"]) ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700">
                    Fecha de Inicio
                  </label>
                  <input
                    type="date"
                    name="experiencia[<?= $exp_index ?>][fecha_desde]"
                    value="<?= valor_anterior_array('experiencia', $exp_index, 'fecha_desde') ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 <?= isset($errores["experiencia_{$exp_index}_fecha_desde"]) ? 'error-field' : '' ?>"
                  >
                  <?php if (isset($errores["experiencia_{$exp_index}_fecha_desde"])): ?>
                    <div class="error-text flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                      <?= e($errores["experiencia_{$exp_index}_fecha_desde"]) ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700">
                    Fecha de Finalización
                  </label>
                  <input
                    type="date"
                    name="experiencia[<?= $exp_index ?>][fecha_hasta]"
                    value="<?= valor_anterior_array('experiencia', $exp_index, 'fecha_hasta') ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                  >
                  <p class="text-xs text-gray-500">Deja en blanco si es tu trabajo actual.</p>
                </div>

                <div class="space-y-2 md:col-span-2">
                  <label class="block text-sm font-semibold text-gray-700">
                    Tareas Principales
                  </label>
                  <textarea
                    name="experiencia[<?= $exp_index ?>][tareas]"
                    rows="3"
                    maxlength="1000"
                    placeholder="Describe brevemente tus responsabilidades y logros en este puesto..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 <?= isset($errores["experiencia_{$exp_index}_tareas"]) ? 'error-field' : '' ?>"
                  ><?= valor_anterior_array('experiencia', $exp_index, 'tareas') ?></textarea>
                  <?php if (isset($errores["experiencia_{$exp_index}_tareas"])): ?>
                    <div class="error-text flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                      <?= e($errores["experiencia_{$exp_index}_tareas"]) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              <!-- Botón para eliminar este bloque (solo si hay más de uno) -->
              <?php if ($exp_index > 0 || count($experiencias_anteriores) > 1): ?>
              <div class="flex justify-end mt-2">
                 <button type="button" class="remove-experiencia text-red-500 hover:text-red-700 text-sm font-medium flex items-center gap-1">
                   <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                   Eliminar
                 </button>
              </div>
              <?php endif; ?>
            </div>
            <?php 
            $exp_index++;
            endforeach; 
            ?>
          </div>

          <!-- Botón para agregar más experiencia -->
          <div class="flex justify-start mt-2">
            <button type="button" id="add-experiencia" class="text-blue-600 hover:text-blue-800 font-medium text-sm flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
              Agregar Experiencia
            </button>
          </div>
        </div>
        <!-- FIN SECCIÓN DE EXPERIENCIA LABORAL -->

        <!-- SECCIÓN DE HABILIDADES Y DISPONIBILIDAD -->
        <div class="md:col-span-2 mt-8 pt-6 border-t border-gray-200">
          <div class="bg-gradient-to-r from-amber-500 to-orange-600 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-bold text-white mb-1">Habilidades y Disponibilidad</h3>
            <p class="text-amber-100 text-sm">Completa información sobre tus habilidades y disponibilidad laboral</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Antecedentes Penales -->
            <div class="space-y-2 md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700">
                ¿Tiene antecedentes penales?
                <span class="text-red-500">*</span>
              </label>
              <div class="flex items-center space-x-6">
                <label class="inline-flex items-center">
                  <input 
                    type="radio" 
                    name="antecedentes_penales" 
                    value="Si" 
                    <?= (valor_anterior('antecedentes_penales') === 'Si') ? 'checked' : '' ?>
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500"
                    required
                  >
                  <span class="ml-2 text-gray-700">Sí</span>
                </label>
                <label class="inline-flex items-center">
                  <input 
                    type="radio" 
                    name="antecedentes_penales" 
                    value="No" 
                    <?= (valor_anterior('antecedentes_penales') === 'No') ? 'checked' : '' ?>
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500"
                    required
                  >
                  <span class="ml-2 text-gray-700">No</span>
                </label>
              </div>
              <?php if (isset($errores['antecedentes_penales'])): ?>
                <div class="error-text flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  <?= e($errores['antecedentes_penales']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Certificado de Antecedentes -->
            <div class="space-y-2 md:col-span-2">
              <label for="certificado_antecedentes" class="block text-sm font-semibold text-gray-700">
                Certificado de Antecedentes (PDF, DOC, DOCX) Máx 2MB
              </label>
              <input 
                type="file" 
                id="certificado_antecedentes" 
                name="certificado_antecedentes" 
                accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              >
              <p class="text-xs text-gray-500 mt-1">Adjunta el certificado si seleccionaste "Sí" en antecedentes penales.</p>
              <?php if (isset($errores['certificado_antecedentes'])): ?>
                <div class="error-text flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  <?= e($errores['certificado_antecedentes']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Licencia de Conducir -->
            <div class="space-y-2 md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700">
                Licencia de Conducir
              </label>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php 
                $licencias_disponibles = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
                $licencias_seleccionadas = explode(',', valor_anterior('licencia_conducir', ''));
                foreach ($licencias_disponibles as $licencia): 
                ?>
                  <label class="flex items-center space-x-2 cursor-pointer bg-gray-50 hover:bg-white p-3 rounded-lg border border-gray-200">
                    <input 
                      type="checkbox" 
                      name="licencia_conducir[]" 
                      value="<?= $licencia ?>" 
                      <?= in_array($licencia, $licencias_seleccionadas) ? 'checked' : '' ?>
                      class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                    >
                    <span class="text-sm text-gray-700"><?= e($licencia) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="text-xs text-gray-500 mt-1">Selecciona todas las licencias que posees.</p>
            </div>

            <!-- Otras Licencias -->
            <div class="space-y-2 md:col-span-2">
              <label for="otras_licencias" class="block text-sm font-semibold text-gray-700">
                Otras Licencias o Certificaciones
              </label>
              <textarea
                id="otras_licencias"
                name="otras_licencias"
                rows="3"
                maxlength="500"
                placeholder="Ej: Certificado de primeros auxilios, Licencia de manejo de maquinaria pesada..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['otras_licencias']) ? 'error-field' : '' ?>"
              ><?= valor_anterior('otras_licencias') ?></textarea>
              <p class="text-xs text-gray-500">Separa las licencias con comas si es necesario.</p>
              <?php if (isset($errores['otras_licencias'])): ?>
                <div class="error-text flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  <?= e($errores['otras_licencias']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Disponibilidad -->
            <div class="space-y-2 md:col-span-2">
              <label for="disponibilidad" class="block text-sm font-semibold text-gray-700">
                Disponibilidad para Incorporarse
                <span class="text-red-500">*</span>
              </label>
              <select
                required
                id="disponibilidad"
                name="disponibilidad"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white <?= isset($errores['disponibilidad']) ? 'error-field' : '' ?>"
              >
                <option value="">Selecciona...</option>
                <option value="Inmediata" <?= (valor_anterior('disponibilidad') === 'Inmediata') ? 'selected' : '' ?>>Inmediata</option>
                <option value="15 días" <?= (valor_anterior('disponibilidad') === '15 días') ? 'selected' : '' ?>>15 días</option>
                <option value="30 días" <?= (valor_anterior('disponibilidad') === '30 días') ? 'selected' : '' ?>>30 días</option>
              </select>
              <?php if (isset($errores['disponibilidad'])): ?>
                <div class="error-text flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  <?= e($errores['disponibilidad']) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <!-- FIN SECCIÓN DE HABILIDADES Y DISPONIBILIDAD -->

      </div>

      <div class="mt-8 flex justify-center">
        <button 
          type="submit"
          class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-4 px-8 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-3"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
          Enviar Datos
        </button>
      </div>
    </form>
  </div>

  <div class="text-center mt-8">
    <p class="text-gray-600">
      ¿Eres de RRHH? 
      <a href="admin/login.php" class="text-blue-600 hover:text-blue-800 font-medium underline decoration-2 underline-offset-2 hover:decoration-blue-800 transition-colors">
        Entrar al panel
      </a>
    </p>
  </div>
</main>

<script src="assets/js/app.js"></script>
<script>
// Esperar a que el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando formulario...');
    
    // Variables globales para la cámara
    let stream = null;
    let capturedPhotoBlob = null;
    let experienciaIndex = <?= $exp_index ?>; // Comenzar desde el siguiente índice disponible

    // Elementos del DOM
    const openCameraBtn = document.getElementById('openCamera');
    const cameraModal = document.getElementById('cameraModal');
    const closeCameraBtn = document.getElementById('closeCamera');
    const cancelCameraBtn = document.getElementById('cancelCamera');
    const video = document.getElementById('camera');
    const canvas = document.getElementById('canvas');
    const captureBtn = document.getElementById('capture');
    const cameraError = document.getElementById('cameraError');
    const photoInput = document.getElementById('foto');
    const photoPreview = document.getElementById('photoPreview');
    const previewImage = document.getElementById('previewImage');
    const removePhotoBtn = document.getElementById('removePhoto');
    const form = document.getElementById('cvForm');
    const submitButton = document.querySelector('button[type="submit"]');
    const emailInput = document.getElementById('email');
    
    console.log('✅ Elementos del DOM cargados:', {
        form: !!form,
        emailInput: !!emailInput,
        submitButton: !!submitButton,
        openCameraBtn: !!openCameraBtn,
        photoInput: !!photoInput
    });
    
    // Verificar si faltan elementos críticos
    if (!form) {
        console.error('❌ ERROR CRÍTICO: No se encontró el formulario #cvForm');
        return;
    }
    if (!submitButton) {
        console.error('❌ ERROR CRÍTICO: No se encontró el botón submit');
        return;
    }

    // Función auxiliar para mostrar alertas
    function showAlert(message, type) {
        console.log('🔔 Mostrando alerta:', message, 'Tipo:', type);
        // Mapear tipos simples a iconos de SweetAlert
        const icon = type === 'red' ? 'error' : (type === 'warning' ? 'warning' : 'success');
        Swal.fire({
            text: message,
            icon: icon,
            timer: 4000,
            toast: true,
            position: 'top',
            showConfirmButton: false,
            timerProgressBar: true
        });
    }

    // Función para cerrar la cámara
    function closeCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        cameraModal.classList.add('hidden');
        video.srcObject = null;
    }

// Abrir cámara
openCameraBtn.addEventListener('click', async () => {
    try {
        // Solicitar permisos de cámara
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'user', // Cámara frontal en móviles
                width: { ideal: 640 },
                height: { ideal: 480 }
            } 
        });
        
        video.srcObject = stream;
        cameraModal.classList.remove('hidden');
        cameraError.classList.add('hidden');
        video.classList.remove('hidden');
        
    } catch (error) {
        console.error('Error al acceder a la cámara:', error);
        cameraModal.classList.remove('hidden');
        video.classList.add('hidden');
        cameraError.classList.remove('hidden');
    }
});

closeCameraBtn.addEventListener('click', closeCamera);
cancelCameraBtn.addEventListener('click', closeCamera);

// Capturar foto
captureBtn.addEventListener('click', () => {
    if (!stream) return;
    
    // Efecto visual de flash
    video.classList.add('flash-effect');
    setTimeout(() => video.classList.remove('flash-effect'), 300);
    
    // Configurar canvas con las dimensiones del video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Dibujar frame actual del video en el canvas
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    
    // Convertir canvas a blob
    canvas.toBlob((blob) => {
        if (blob) {
            capturedPhotoBlob = blob;
            
            // Mostrar preview
            const url = URL.createObjectURL(blob);
            previewImage.src = url;
            photoPreview.classList.remove('hidden');
            
            // Limpiar el input de archivo
            photoInput.value = '';
            
            // Feedback visual
            captureBtn.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                ¡Capturada!
            `;
            captureBtn.classList.add('bg-green-500', 'hover:bg-green-600');
            captureBtn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
            
            setTimeout(() => {
                closeCamera();
                // Restaurar botón
                captureBtn.innerHTML = `
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Capturar
                `;
                captureBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                captureBtn.classList.add('bg-blue-500', 'hover:bg-blue-600');
            }, 1000);
        }
    }, 'image/jpeg', 0.8);
});

// Manejar selección de archivo
photoInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        capturedPhotoBlob = null; // Limpiar foto capturada
        
        // Mostrar preview del archivo
        const url = URL.createObjectURL(file);
        previewImage.src = url;
        photoPreview.classList.remove('hidden');
    }
});

// Remover foto
removePhotoBtn.addEventListener('click', () => {
    photoInput.value = '';
    capturedPhotoBlob = null;
    photoPreview.classList.add('hidden');
    URL.revokeObjectURL(previewImage.src);
});

// Validar antes de enviar
form.addEventListener('submit', (e) => {
    // SIEMPRE PREVENIR EL ENVÍO AUTOMÁTICO PARA DEBUG
    e.preventDefault();
    
    console.log('═══════════════════════════════════════════════════');
    console.log('🔍 EVENTO SUBMIT DISPARADO - Iniciando validación');
    console.log('═══════════════════════════════════════════════════');
    console.log(' Timestamp:', new Date().toLocaleTimeString());
    
    // Verificar que hay al menos una área seleccionada
    const selectedAreas = form.querySelectorAll('input[name="areas[]"]:checked');
    console.log('🔍 Verificando áreas seleccionadas...');
    if (selectedAreas.length === 0) {
        console.error('❌ VALIDACIÓN FALLÓ: No hay áreas seleccionadas');
        showAlert('Debes seleccionar al menos un área profesional', 'red');
        document.querySelector('label[class*="Área"]').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
        console.log('═══════════════════════════════════════════════════');
        console.log('⛔ FORMULARIO DETENIDO - No se enviará');
        console.log('═══════════════════════════════════════════════════');
        return;
    }
    console.log('✅ Áreas seleccionadas:', selectedAreas.length);
    
    // Verificar que hay al menos una experiencia laboral completa
    const experienciaItems = document.querySelectorAll('.experiencia-item');
    let hayExperienciaValida = false;
    let primerErrorExperiencia = null;
    
    console.log('───────────────────────────────────────────────────');
    console.log('🔍 Validando experiencias laborales');
    console.log('📊 Total de bloques de experiencia:', experienciaItems.length);
    console.log('───────────────────────────────────────────────────');
    
    experienciaItems.forEach((item, index) => {
        const empresa = item.querySelector('input[name*="[empresa]"]');
        const puesto = item.querySelector('input[name*="[puesto]"]');
        const empleador = item.querySelector('input[name*="[empleador]"]');
        const fechaDesde = item.querySelector('input[name*="[fecha_desde]"]');
        const tareas = item.querySelector('textarea[name*="[tareas]"]');
        
        // Verificar si hay datos en esta experiencia
        const empresaLlena = empresa && empresa.value.trim() !== '';
        const puestoLleno = puesto && puesto.value.trim() !== '';
        const empleadorLleno = empleador && empleador.value.trim() !== '';
        const fechaDesdeLlena = fechaDesde && fechaDesde.value.trim() !== '';
        const tareasLlenas = tareas && tareas.value.trim() !== '';
        
        const algunCampoLleno = empresaLlena || puestoLleno || empleadorLleno || fechaDesdeLlena || tareasLlenas;
        const todosCamposLlenos = empresaLlena && puestoLleno && empleadorLleno && fechaDesdeLlena && tareasLlenas;
        
        console.log(`📋 Experiencia ${index + 1}:`, {
            empresa: empresaLlena ? '✅' : '❌',
            puesto: puestoLleno ? '✅' : '❌',
            empleador: empleadorLleno ? '✅' : '❌',
            fechaDesde: fechaDesdeLlena ? '✅' : '❌',
            tareas: tareasLlenas ? '✅' : '❌',
            algunCampoLleno,
            todosCamposLlenos
        });
        
        // Si hay algún campo lleno pero no todos, es un error
        if (algunCampoLleno && !todosCamposLlenos) {
            if (!primerErrorExperiencia) {
                primerErrorExperiencia = item;
            }
            console.error(`❌ Experiencia ${index + 1} está incompleta`);
        }
        
        // Si todos los campos están llenos, hay al menos una experiencia válida
        if (todosCamposLlenos) {
            hayExperienciaValida = true;
        }
    });
    
    console.log('───────────────────────────────────────────────────');
    console.log('📊 Resumen de validación de experiencias:');
    console.log('   - Hay experiencia válida:', hayExperienciaValida);
    console.log('   - Hay errores:', !!primerErrorExperiencia);
    console.log('───────────────────────────────────────────────────');
    
    // Si hay un error en alguna experiencia, detener el envío
    if (primerErrorExperiencia) {
        console.error('❌ VALIDACIÓN FALLÓ: Hay experiencias laborales incompletas');
        showAlert('Por favor completa todos los campos de las experiencias laborales que iniciaste, o elimínalas si no deseas agregarlas.', 'red');
        primerErrorExperiencia.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
        // Resaltar campos vacíos
        primerErrorExperiencia.querySelectorAll('input[required], textarea[required], input[name*="[empresa]"], input[name*="[puesto]"], input[name*="[empleador]"], input[name*="[fecha_desde]"], textarea[name*="[tareas]"]').forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error-field');
                setTimeout(() => field.classList.remove('error-field'), 3000);
            }
        });
        console.log('═══════════════════════════════════════════════════');
        console.log('⛔ FORMULARIO DETENIDO - No se enviará');
        console.log('═══════════════════════════════════════════════════');
        return;
    }
    
    // Validar experiencias laborales: son opcionales, pero si se inicia una, debe estar completa
    // Solo mostrar error si NO hay experiencia válida Y hay campos parcialmente llenos
    if (!hayExperienciaValida && primerErrorExperiencia) {
        console.error('❌ VALIDACIÓN FALLÓ: Hay experiencias laborales iniciadas pero incompletas');
        showAlert('Por favor completa todos los campos de las experiencias laborales que iniciaste, o elimínalas si no deseas agregarlas.', 'red');
        document.querySelector('#experiencia-laboral-container').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
        console.log('═══════════════════════════════════════════════════');
        console.log('⛔ FORMULARIO DETENIDO - No se enviará');
        console.log('═══════════════════════════════════════════════════');
        return;
    }
    
    // Si no hay experiencia válida pero tampoco hay errores (todo vacío), está bien
    if (hayExperienciaValida) {
        console.log('✅ Al menos una experiencia laboral válida encontrada');
    } else {
        console.log('ℹ️  No hay experiencias laborales (opcional - está bien)');
    }
    
    // Verificar que hay una foto (archivo o capturada)
    console.log('🔍 Verificando foto...');
    console.log('   - Archivos en input:', photoInput.files.length);
    console.log('   - Foto capturada:', !!capturedPhotoBlob);
    if (!photoInput.files.length && !capturedPhotoBlob) {
        console.error('❌ VALIDACIÓN FALLÓ: No hay foto');
        showAlert('Debes subir una foto o tomar una con la cámara', 'red');
        document.querySelector('label[for="foto"]').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
        console.log('═══════════════════════════════════════════════════');
        console.log('⛔ FORMULARIO DETENIDO - No se enviará');
        console.log('═══════════════════════════════════════════════════');
        return;
    }
    console.log('✅ Foto presente');
    
    // Si hay foto capturada, convertirla a archivo
    if (capturedPhotoBlob && !photoInput.files.length) {
        console.log('🔄 Convirtiendo foto capturada a archivo...');
        const dt = new DataTransfer();
        const file = new File([capturedPhotoBlob], 'foto_capturada.jpg', { 
            type: 'image/jpeg' 
        });
        dt.items.add(file);
        photoInput.files = dt.files;
        console.log('✅ Foto convertida correctamente');
    }
    
    console.log('═══════════════════════════════════════════════════');
    console.log('✅ TODAS LAS VALIDACIONES PASADAS');
    console.log('═══════════════════════════════════════════════════');
    console.log('📤 Enviando formulario al servidor...');
    
    // Mostrar indicador de carga
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Enviando...
    `;
    
    // Enviar el formulario
    form.submit();
});

console.log('✅ Listener de submit registrado correctamente');

// --- Funcionalidad para Experiencia Laboral Dinámica ---
const addExperienciaButton = document.getElementById('add-experiencia');
const experienciaContainer = document.getElementById('experiencia-laboral-container');

if (addExperienciaButton && experienciaContainer) {
    console.log('🎯 Elementos de experiencia encontrados - Registrando listener');
    
    addExperienciaButton.addEventListener('click', function(e) {
        console.log('🚀 ¡CLICK EN AGREGAR EXPERIENCIA DETECTADO!');
        e.preventDefault();
        e.stopPropagation();
        
        const newItem = document.createElement('div');
        newItem.className = 'experiencia-item bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200';
        
        newItem.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        Nombre de la Empresa
                    </label>
                    <input
                        type="text"
                        name="experiencia[${experienciaIndex}][empresa]"
                        maxlength="255"
                        placeholder="Ej: Tech Solutions S.A."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    >
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        Puesto
                    </label>
                    <input
                        type="text"
                        name="experiencia[${experienciaIndex}][puesto]"
                        maxlength="100"
                        placeholder="Ej: Desarrollador Web"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    >
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        Empleador/Contacto
                    </label>
                    <input
                        type="text"
                        name="experiencia[${experienciaIndex}][empleador]"
                        maxlength="255"
                        placeholder="Ej: Juan Pérez (Supervisor)"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    >
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        Fecha de Inicio
                    </label>
                    <input
                        type="date"
                        name="experiencia[${experienciaIndex}][fecha_desde]"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    >
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        Fecha de Finalización
                    </label>
                    <input
                        type="date"
                        name="experiencia[${experienciaIndex}][fecha_hasta]"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    >
                    <p class="text-xs text-gray-500">Deja en blanco si es tu trabajo actual.</p>
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        Tareas Principales
                    </label>
                    <textarea
                        name="experiencia[${experienciaIndex}][tareas]"
                        rows="3"
                        maxlength="1000"
                        placeholder="Describe brevemente tus responsabilidades y logros en este puesto..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    ></textarea>
                </div>
            </div>
            <div class="flex justify-end mt-2">
                <button type="button" class="remove-experiencia text-red-500 hover:text-red-700 text-sm font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Eliminar
                </button>
            </div>
        `;
        
        experienciaContainer.appendChild(newItem);
        experienciaIndex++;
        
        console.log('✅ Nueva experiencia agregada. Nuevo índice:', experienciaIndex);

        // Agregar listener al botón de eliminar del nuevo item
        newItem.querySelector('.remove-experiencia').addEventListener('click', function() {
            experienciaContainer.removeChild(newItem);
            console.log('🗑️ Experiencia eliminada');
        });
    });

    // Agregar listeners a los botones de eliminar existentes
    document.querySelectorAll('.remove-experiencia').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.experiencia-item').remove();
            console.log('🗑️ Experiencia existente eliminada');
        });
    });
    
    console.log('✅ Funcionalidad de experiencias registrada correctamente');
} else {
    console.error('❌ No se encontraron elementos de experiencia:', {
        addButton: !!addExperienciaButton,
        container: !!experienciaContainer
    });
}
// --- Fin Funcionalidad para Experiencia Laboral Dinámica ---

// Validación en tiempo real para áreas (máximo 2)
const areaCheckboxes = document.querySelectorAll('input[name="areas[]"]');
const areasValidation = document.getElementById('areasValidation');

areaCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkedBoxes = document.querySelectorAll('input[name="areas[]"]:checked');
        const checkedCount = checkedBoxes.length;
        
        if (checkedCount === 0) {
            areasValidation.className = 'text-sm text-red-600 flex items-center gap-1';
            areasValidation.innerHTML = `
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                Selecciona al menos un área
            `;
        } else if (checkedCount === 1) {
            areasValidation.className = 'text-sm text-blue-600 flex items-center gap-1';
            areasValidation.innerHTML = `
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5-5a1 1 0 00-1 1v3H9a1 1 0 100 2h3v3a1 1 0 102 0v-3h3a1 1 0 100-2h-3V9a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                Podés seleccionar una área más
            `;
        } else if (checkedCount === 2) {
            areasValidation.className = 'text-sm text-green-600 flex items-center gap-1';
            areasValidation.innerHTML = `
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Perfecto! Has seleccionado 2 áreas
            `;
        } else {
            // Más de 2 seleccionados - desmarcar el último
            this.checked = false;
            areasValidation.className = 'text-sm text-red-600 flex items-center gap-1';
            areasValidation.innerHTML = `
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                Máximo 2 áreas permitidas
            `;
            
            setTimeout(() => {
                const newCheckedCount = document.querySelectorAll('input[name="areas[]"]:checked').length;
                if (newCheckedCount === 2) {
                    areasValidation.className = 'text-sm text-green-600 flex items-center gap-1';
                    areasValidation.innerHTML = `
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Perfecto! Has seleccionado 2 áreas
                    `;
                }
            }, 100);
        }
    });
});

// Mostrar/ocultar campo de edades de hijos
const hijosInput = document.getElementById('hijos');
const edadHijosContainer = document.getElementById('edad_hijos_container');
const edadHijosInput = document.getElementById('edad_hijos');

hijosInput.addEventListener('change', function() {
    const numHijos = parseInt(this.value) || 0;
    
    if (numHijos > 0) {
        edadHijosContainer.style.display = 'block';
        edadHijosInput.required = true;
    } else {
        edadHijosContainer.style.display = 'none';
        edadHijosInput.required = false;
        edadHijosInput.value = '';
    }
});

// Cerrar modal al hacer clic fuera
cameraModal.addEventListener('click', (e) => {
    if (e.target === cameraModal) {
        closeCamera();
    }
});

// El email ya no se valida en tiempo real - es opcional
// Si el usuario lo ingresa, se validará solo el formato al enviar el formulario

// Validación en tiempo real de contraseñas (del código anterior)
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');

if (newPassword && confirmPassword) {
    function validatePasswords() {
        if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Las contraseñas no coinciden');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
}

// --- Funcionalidad para Experiencia Laboral Dinámica ---
// MOVIDO AL BLOQUE PRINCIPAL DE DOMContentLoaded - NO DUPLICAR
// --- Fin Funcionalidad para Experiencia Laboral Dinámica ---

// --- Funcionalidad para Especialidades por Área ---
async function cargarNiveles(especialidadId) {
    try {
        const response = await fetch(`obtener_niveles.php?especialidad_id=${especialidadId}`);
        const niveles = await response.json();
        return niveles;
    } catch (error) {
        console.error('Error al cargar niveles:', error);
        return [];
    }
}

async function cargarEspecialidades(areaId, areaNombre) {
    try {
        const response = await fetch(`obtener_especialidades.php?area_id=${areaId}`);
        const especialidades = await response.json();
        
        if (especialidades.length > 0) {
            // Crear contenedor para esta área
            const container = document.createElement('div');
            container.className = 'especialidades-area bg-blue-50 p-4 rounded-lg border border-blue-200';
            container.id = `especialidades-${areaId}`;
            
            // Crear HTML base
            let html = `
                <h4 class="font-semibold text-blue-800 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Habilidades en ${areaNombre}
                </h4>
                <p class="text-xs text-blue-600 mb-3">Selecciona las herramientas/habilidades que dominas y su nivel</p>
                <div class="space-y-3">
            `;
            
            // Cargar cada especialidad con sus niveles
            for (const esp of especialidades) {
                const niveles = await cargarNiveles(esp.id);
                const tipoSeleccion = esp.tipo_seleccion || 'multiple'; // Por defecto 'multiple'
                
                if (niveles.length > 0) {
                    html += `
                        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                            <div class="font-medium text-gray-800 mb-3 text-sm flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                ${esp.nombre}
                                ${tipoSeleccion === 'unica' ? '<span class="text-xs text-purple-600 ml-2">(Selecciona uno)</span>' : '<span class="text-xs text-blue-600 ml-2">(Selecciona uno o más)</span>'}
                            </div>
                    `;
                    
                    if (tipoSeleccion === 'unica') {
                        // Renderizar como radio buttons
                        html += `<div class="space-y-2 ml-6">`;
                        niveles.forEach(nivel => {
                            const radioId = `nivel_${esp.id}_${nivel.id}`;
                            html += `
                                <label for="${radioId}" class="flex items-center gap-2 p-2 rounded hover:bg-purple-50 cursor-pointer group transition-colors">
                                    <input 
                                        type="radio" 
                                        id="${radioId}"
                                        name="especialidades[${esp.id}][niveles][]"
                                        value="${nivel.id}"
                                        class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500"
                                    />
                                    <span class="text-sm text-gray-700 group-hover:text-purple-700 flex-1" title="${nivel.descripcion || ''}">
                                        ${nivel.nombre}
                                    </span>
                                    ${nivel.descripcion ? `
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="${nivel.descripcion}">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    ` : ''}
                                </label>
                            `;
                        });
                        html += `</div>`;
                    } else {
                        // Renderizar como checkboxes (múltiple)
                        html += `<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 ml-6">`;
                        niveles.forEach(nivel => {
                            const checkboxId = `nivel_${esp.id}_${nivel.id}`;
                            html += `
                                <label for="${checkboxId}" class="flex items-center gap-2 p-2 rounded hover:bg-blue-50 cursor-pointer group transition-colors">
                                    <input 
                                        type="checkbox" 
                                        id="${checkboxId}"
                                        name="especialidades[${esp.id}][niveles][]"
                                        value="${nivel.id}"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-gray-700 group-hover:text-blue-700 flex-1" title="${nivel.descripcion || ''}">
                                        ${nivel.nombre}
                                    </span>
                                    ${nivel.descripcion ? `
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="${nivel.descripcion}">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    ` : ''}
                                </label>
                            `;
                        });
                        html += `</div>`;
                    }
                    
                    html += `
                        </div>
                    `;
                } else {
                    // Si no hay niveles configurados
                    html += `
                        <div class="bg-gray-50 p-3 rounded border border-gray-200">
                            <span class="text-sm text-gray-600">${esp.nombre}</span>
                            <span class="text-xs text-gray-400 ml-2">(Sin niveles configurados)</span>
                        </div>
                    `;
                }
            }
            
            html += `
                </div>
            `;
            
            container.innerHTML = html;
            return container;
        }
        return null;
    } catch (error) {
        console.error('Error al cargar especialidades:', error);
        return null;
    }
}

// Manejar selección de áreas
document.querySelectorAll('.area-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', async function() {
        const areaId = this.dataset.areaId;
        const areaNombre = this.nextElementSibling.textContent.trim();
        const containerId = `especialidades-${areaId}`;
        
        if (this.checked) {
            // Cargar y mostrar especialidades
            const especialidadesContainer = await cargarEspecialidades(areaId, areaNombre);
            if (especialidadesContainer) {
                document.getElementById('especialidades-container').appendChild(especialidadesContainer);
            }
        } else {
            // Remover contenedor de especialidades
            const container = document.getElementById(containerId);
            if (container) {
                container.remove();
            }
        }
    });
});
// --- Fin Funcionalidad para Especialidades por Área ---

// --- Scroll automático al primer error cuando hay errores del servidor ---
<?php if (!empty($errores)): ?>
    console.log('⚠️ Se detectaron errores en el formulario del servidor');
    console.log('Errores:', <?= json_encode($errores) ?>);
    
    // Buscar el primer campo con error
    const primerCampoConError = document.querySelector('.error-field');
    
    if (primerCampoConError) {
        console.log('🎯 Haciendo scroll al primer campo con error');
        setTimeout(() => {
            primerCampoConError.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            // Hacer focus en el campo si es posible
            if (primerCampoConError.tagName === 'INPUT' || primerCampoConError.tagName === 'TEXTAREA' || primerCampoConError.tagName === 'SELECT') {
                primerCampoConError.focus();
            }
        }, 500);
    } else {
        console.log('⚠️ No se encontró ningún campo con la clase error-field');
        // Intentar hacer scroll al mensaje de error general
        const mensajeError = document.querySelector('.bg-red-50');
        if (mensajeError) {
            setTimeout(() => {
                mensajeError.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 500);
        }
    }
<?php endif; ?>
// --- Fin Scroll automático al primer error ---

// --- Funcionalidad para múltiples nacionalidades (máx 2) ---
window.agregarNacionalidad = function() {
    const container = document.getElementById('nacionalidades-container');
    const items = container.querySelectorAll('.nacionalidad-item');
    
    if (items.length >= 2) {
        alert('Solo puedes agregar hasta 2 nacionalidades');
        return;
    }
    
    const nuevoItem = document.createElement('div');
    nuevoItem.className = 'nacionalidad-item flex gap-2';
    nuevoItem.innerHTML = `
        <input 
            type="text" 
            name="nacionalidades[]" 
            list="nacionalidades-list"
            maxlength="100" 
            placeholder="Ej: Peruana"
            autocomplete="off"
            class="nacionalidad-input flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white"
        >
        <button 
            type="button" 
            onclick="eliminarNacionalidad(this)"
            class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors duration-200"
            title="Eliminar nacionalidad"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>
    `;
    
    container.appendChild(nuevoItem);
    
    // Aplicar conversión a mayúsculas al nuevo input
    const nuevoInput = nuevoItem.querySelector('.nacionalidad-input');
    aplicarConversionMayusculas(nuevoInput);
    
    actualizarBotonesNacionalidad();
};

window.eliminarNacionalidad = function(button) {
    const container = document.getElementById('nacionalidades-container');
    const items = container.querySelectorAll('.nacionalidad-item');
    
    if (items.length <= 1) {
        alert('Debe mantener al menos una nacionalidad');
        return;
    }
    
    button.closest('.nacionalidad-item').remove();
    actualizarBotonesNacionalidad();
};

function actualizarBotonesNacionalidad() {
    const container = document.getElementById('nacionalidades-container');
    const items = container.querySelectorAll('.nacionalidad-item');
    const agregarBtn = document.getElementById('agregar-nacionalidad-btn');
    
    if (!agregarBtn) return; // Validación por si no existe el botón
    
    // Mostrar/ocultar botón de agregar
    if (items.length >= 2) {
        agregarBtn.style.display = 'none';
    } else {
        agregarBtn.style.display = 'flex';
    }
    
    // Mostrar/ocultar botones de eliminar
    items.forEach((item, index) => {
        const deleteBtn = item.querySelector('button[onclick^="eliminarNacionalidad"]');
        if (deleteBtn) {
            if (items.length === 1) {
                deleteBtn.classList.add('hidden');
            } else {
                deleteBtn.classList.remove('hidden');
            }
        }
    });
}

// Función para convertir input a mayúsculas
function aplicarConversionMayusculas(input) {
    if (!input) return;
    input.addEventListener('input', function() {
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(start, end);
    });
}

// Inicializar estado de botones y conversión a mayúsculas al cargar
document.addEventListener('DOMContentLoaded', function() {
    actualizarBotonesNacionalidad();
    
    // Aplicar conversión a mayúsculas a todos los inputs de nacionalidad existentes
    document.querySelectorAll('.nacionalidad-input').forEach(input => {
        aplicarConversionMayusculas(input);
    });
});
// --- Fin funcionalidad múltiples nacionalidades ---

// Autocomplete para lugar de residencia usando la tabla localidades_nea
(function() {
  const input = document.getElementById('lugar_residencia');
  const datalist = document.getElementById('localidades-list');
  if (!input || !datalist) return;

  let timer = null;
  input.addEventListener('input', function() {
    const q = this.value.trim();
    if (timer) clearTimeout(timer);
    if (q.length < 2) {
      datalist.innerHTML = '';
      return;
    }
    timer = setTimeout(async () => {
      try {
        const res = await fetch(`obtener_localidades.php?q=${encodeURIComponent(q)}`);
        if (!res.ok) return;
        const items = await res.json();
        datalist.innerHTML = '';
        items.forEach(it => {
          const opt = document.createElement('option');
          opt.value = it.label; // 'Nombre, Provincia'
          datalist.appendChild(opt);
        });
      } catch (err) {
        console.error('Error cargando localidades:', err);
      }
    }, 250);
  });
})();

console.log('✅ Formulario completamente inicializado');

}); // FIN DOMContentLoaded
</script>
</body>
</html>