<?php
// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/../init.php';
require_login();

// Verificar si TCPDF está disponible
$tcpdf_available = false;

// Intentar cargar TCPDF - primero con Composer, luego manual
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('TCPDF')) {
        $tcpdf_available = true;
    }
} elseif (file_exists(__DIR__ . '/../libs/tcpdf/tcpdf.php')) {
    require_once __DIR__ . '/../libs/tcpdf/tcpdf.php';
    if (class_exists('TCPDF')) {
        $tcpdf_available = true;
    }
}

// Si no está disponible TCPDF, mostrar error
if (!$tcpdf_available) {
    die('TCPDF no está instalado. Por favor, consulta el archivo INSTALACION_IMPRESION_PDF.md para las instrucciones de instalación.');
}

class GeneradorPDFCandidato {
    private $pdo;
    private $candidato_id;
    private $candidato_data;
    
    // Configuración de colores corporativos (RGB)
    private $color_primario = [41, 128, 185];      // Azul corporativo
    private $color_secundario = [52, 73, 94];      // Gris oscuro
    private $color_acento = [46, 204, 113];        // Verde para destacados
    private $color_texto = [44, 62, 80];           // Gris texto
    private $color_fondo_claro = [248, 249, 250];  // Fondo claro
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function generarPDF($candidato_id) {
        $this->candidato_id = $candidato_id;
        
        // Obtener datos completos del candidato
        if (!$this->cargarDatosCandidato()) {
            die('Candidato no encontrado o sin permisos para acceder.');
        }
        
        // Crear instancia de TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurar metadatos del documento
        $pdf->SetCreator('Sistema de CVs');
        $pdf->SetAuthor('Sistema de Gestión de Candidatos');
        $pdf->SetTitle('CV - ' . $this->candidato_data['nombre']);
        $pdf->SetSubject('Curriculum Vitae');
        $pdf->SetKeywords('CV, Curriculum, Candidato, ' . $this->candidato_data['nombre']);
        
        // Configurar página
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Configurar fuentes
        $pdf->setFontSubsetting(true);
        
        // Establecer encabezado y pie de página personalizados
        $this->configurarEncabezadoPie($pdf);
        
        // Agregar primera página
        $pdf->AddPage();
        
        // Generar contenido del CV
        $this->generarContenidoCV($pdf);
        
        // Generar salida del PDF
        $nombre_archivo = 'CV_' . $this->limpiarNombreArchivo($this->candidato_data['nombre']) . '_' . date('Y-m-d') . '.pdf';
        $pdf->Output($nombre_archivo, 'D'); // 'D' = Descargar
        
        exit;
    }
    
    private function cargarDatosCandidato() {
        // Verificar qué columnas existen
        try {
            $columns_check = $this->pdo->query("SHOW COLUMNS FROM candidatos");
            $existing_columns = [];
            while ($col = $columns_check->fetch()) {
                $existing_columns[] = $col['Field'];
            }
        } catch (Exception $e) {
            $existing_columns = ['id', 'nombre', 'email', 'telefono', 'experiencia', 'foto_ruta', 'fecha_carga', 'observaciones'];
        }
        
        $has_demographic_fields = in_array('dni', $existing_columns) && 
                                 in_array('edad', $existing_columns) && 
                                 in_array('estado_civil', $existing_columns);
        
        // Construir SELECT dinámico
        $select_fields = [
            'c.id', 'c.nombre', 'c.email', 'c.telefono', 'c.experiencia',
            'c.foto_nombre_original', 'c.foto_ruta', 'c.fecha_carga', 'c.observaciones',
            'COALESCE(c.estado_id, 1) as estado_id',
            'COALESCE(e.nombre, "Pendiente") as estado_nombre',
            'COALESCE(e.color, "#f59e0b") as estado_color'
        ];
        
        if ($has_demographic_fields) {
            $select_fields = array_merge($select_fields, [
                'c.dni', 'c.edad', 'c.estado_civil', 'c.hijos', 'c.edad_hijos', 
                'c.nacionalidad', 'c.lugar_residencia', 'c.ocupacion_actual', 
                'c.ocupacion_padre', 'c.ocupacion_madre'
            ]);
        }
        
        $sql = 'SELECT ' . implode(', ', $select_fields) . '
                FROM candidatos c 
                LEFT JOIN estados_cv e ON c.estado_id = e.id 
                WHERE c.id = ?';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->candidato_id]);
        $candidato = $stmt->fetch();
        
        if (!$candidato) {
            return false;
        }
        
        $this->candidato_data = $candidato;
        $this->candidato_data['has_demographic_fields'] = $has_demographic_fields;
        
        // Cargar datos adicionales
        $this->cargarDatosAdicionales();
        
        return true;
    }
    
    private function cargarDatosAdicionales() {
        // Áreas del candidato
        $stmt = $this->pdo->prepare('SELECT ap.id, ap.nombre 
            FROM candidato_areas ca 
            INNER JOIN areas_profesionales ap ON ca.area_profesional_id = ap.id 
            WHERE ca.candidato_id = ?');
        $stmt->execute([$this->candidato_id]);
        $this->candidato_data['areas'] = $stmt->fetchAll();
        
        // Especialidades con niveles
        $stmt = $this->pdo->prepare('SELECT 
            ce.id, ce.especialidad_id, ce.nivel_id,
            ne.nombre as nivel_nombre, ne.descripcion as nivel_descripcion,
            ea.nombre as especialidad_nombre,
            ea.area_profesional_id, ap.nombre as area_nombre
            FROM candidato_especialidades ce
            INNER JOIN especialidades_areas ea ON ce.especialidad_id = ea.id
            INNER JOIN areas_profesionales ap ON ea.area_profesional_id = ap.id
            LEFT JOIN niveles_especialidades ne ON ce.nivel_id = ne.id
            WHERE ce.candidato_id = ?
            ORDER BY ap.nombre, ea.nombre');
        $stmt->execute([$this->candidato_id]);
        $especialidades = $stmt->fetchAll();
        
        // Agrupar especialidades por área
        $especialidades_por_area = [];
        foreach ($especialidades as $esp) {
            $area_id = $esp['area_profesional_id'];
            if (!isset($especialidades_por_area[$area_id])) {
                $especialidades_por_area[$area_id] = [
                    'area_nombre' => $esp['area_nombre'],
                    'especialidades' => []
                ];
            }
            $especialidades_por_area[$area_id]['especialidades'][] = $esp;
        }
        $this->candidato_data['especialidades_por_area'] = $especialidades_por_area;
        
        // Experiencia laboral
        $stmt = $this->pdo->prepare('SELECT * FROM experiencia_laboral WHERE candidato_id = ? ORDER BY fecha_desde DESC');
        $stmt->execute([$this->candidato_id]);
        $this->candidato_data['experiencias_laborales'] = $stmt->fetchAll();
        
        // Formación profesional
        if ($this->candidato_data['has_demographic_fields']) {
            try {
                $stmt = $this->pdo->prepare('SELECT * FROM formacion_profesional WHERE candidato_id = ?');
                $stmt->execute([$this->candidato_id]);
                $this->candidato_data['formacion_profesional'] = $stmt->fetch();
            } catch (Exception $e) {
                $this->candidato_data['formacion_profesional'] = null;
            }
        }
        
        // Habilidades y disponibilidad
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM habilidades_disponibilidad WHERE candidato_id = ?');
            $stmt->execute([$this->candidato_id]);
            $this->candidato_data['habilidades_disponibilidad'] = $stmt->fetch();
        } catch (Exception $e) {
            $this->candidato_data['habilidades_disponibilidad'] = null;
        }
    }
    
    private function configurarEncabezadoPie($pdf) {
        // TCPDF maneja encabezados y pies mediante extensión de clase
        // Por ahora deshabilitamos encabezados automáticos y los haremos manualmente
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
    }
    
    private function generarEncabezado($pdf) {
        // Información de la empresa
        $empresa_info = [
            'nombre' => 'Gestión de Candidatos',
            'telefono' => '+54 3743667526',
            'email' => 'oscarvogel@gmail.com',
            'direccion' => 'Garuhapé, Argentina'
        ];
        
        $pdf->SetY(5);
        
        // Fondo del encabezado
        $pdf->SetFillColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
        $pdf->Rect(0, 0, $pdf->getPageWidth(), 25, 'F');
        
        // Logo de la empresa SVG
        $logo_path = __DIR__ . '/../assets/images/logo_empresa.svg';
        
        if (file_exists($logo_path)) {
            try {
                // Incluir logo SVG real de la empresa
                $pdf->ImageSVG($logo_path, 8, 6, 20, 13, '', '', '', 0, true);
                
                // Nombre de la empresa junto al logo
                $pdf->SetXY(32, 8);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 4, 'GESTIÓN DE CANDIDATOS', 0, 1, 'L');
                
                // Información de contacto debajo del nombre
                $pdf->SetXY(32, 13);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->Cell(0, 4, $empresa_info['telefono'] . ' | ' . $empresa_info['email'], 0, 1, 'L');
                
            } catch (Exception $e) {
                // Fallback: Logo con texto si falla el SVG
                $pdf->SetXY(10, 8);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 4, 'GESTIÓN DE CANDIDATOS', 0, 1, 'L');
                
                $pdf->SetXY(10, 13);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->Cell(0, 4, $empresa_info['telefono'] . ' | ' . $empresa_info['email'], 0, 1, 'L');
                
                // Log del error para debugging
                error_log("PDF: Error cargando logo SVG: " . $e->getMessage());
            }
        } else {
            // Fallback: Logo con texto si no existe el archivo
            $pdf->SetXY(10, 8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 4, 'GESTIÓN DE CANDIDATOS', 0, 1, 'L');
            
            $pdf->SetXY(10, 13);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(0, 4, $empresa_info['telefono'] . ' | ' . $empresa_info['email'], 0, 1, 'L');
        }
        
        // Línea decorativa vertical elegante
        $pdf->SetDrawColor(255, 255, 255, 50);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(155, 8, 155, 17);
        
        // Fecha de generación (esquina derecha)
        $pdf->SetXY(-60, 8);
        $pdf->SetFont('helvetica', '', 9);
        // Asegurar zona horaria correcta
        $fecha_generacion = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
        $pdf->Cell(50, 5, 'Generado: ' . $fecha_generacion->format('d/m/Y H:i'), 0, 1, 'R');
        
        // Línea separadora
        $pdf->SetY(25);
    }
    
    private function generarPie($pdf) {
        // ESTRATEGIA ULTRA-CONSERVADORA: ELIMINAR COMPLETAMENTE EL PIE
        // Esta es la solución más segura para evitar páginas en blanco
        
        // NO hacer nada - eliminar completamente el pie de página
        // para evitar cualquier manipulación del cursor Y que pueda 
        // causar saltos de página no deseados
        
        // Opcionalmente, solo agregar un pequeño espacio al final
        // si el contenido no está muy cerca del margen inferior
        $currentY = $pdf->GetY();
        $pageHeight = $pdf->getPageHeight();
        
        // Solo agregar espacio mínimo si estamos muy arriba en la página
        if ($currentY < $pageHeight - 80) {
            $pdf->Ln(3); // Espacio muy pequeño
        }
        
        // FIN - Sin pie de página para garantizar una sola página
    }
    
    private function generarContenidoCV($pdf) {
        // Generar encabezado manual en la primera página
        $this->generarEncabezado($pdf);
        
        // Título del documento
        $pdf->SetY(35);
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
        $pdf->Cell(0, 12, 'CURRICULUM VITAE', 0, 1, 'C');
        
        $pdf->Ln(5);
        
        // Sección de datos personales con foto
        $this->generarSeccionDatosPersonales($pdf);
        
        // Información demográfica
        if ($this->candidato_data['has_demographic_fields']) {
            $this->generarSeccionDemografica($pdf);
        }
        
        // Áreas profesionales
        if (!empty($this->candidato_data['areas'])) {
            $this->generarSeccionAreas($pdf);
        }
        
        // Especialidades
        if (!empty($this->candidato_data['especialidades_por_area'])) {
            $this->generarSeccionEspecialidades($pdf);
        }
        
        // Experiencia laboral
        if (!empty($this->candidato_data['experiencias_laborales'])) {
            $this->generarSeccionExperiencia($pdf);
        }
        
        // Formación profesional
        if (!empty($this->candidato_data['formacion_profesional'])) {
            $this->generarSeccionFormacion($pdf);
        }
        
        // Habilidades y disponibilidad
        if (!empty($this->candidato_data['habilidades_disponibilidad'])) {
            $this->generarSeccionHabilidades($pdf);
        }
        
        // Observaciones
        if (!empty($this->candidato_data['observaciones'])) {
            $this->generarSeccionObservaciones($pdf);
        }
        
        // Generar pie de página al final
        $this->generarPie($pdf);
    }
    
    private function generarSeccionDatosPersonales($pdf) {
        // Título de sección
        $this->generarTituloSeccion($pdf, 'DATOS PERSONALES');
        
        $y_inicial = $pdf->GetY();
        
        // Foto del candidato (lado derecho)
        $this->incluirFotoCandidato($pdf, $pdf->getPageWidth() - 50, $y_inicial);
        
        // Datos personales (lado izquierdo)
        $pdf->SetXY(15, $y_inicial + 5);
        
        // Nombre
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
        $pdf->Cell(0, 8, mb_strtoupper($this->candidato_data['nombre'], 'UTF-8'), 0, 1, 'L');
        
        $pdf->Ln(3);
        
        // Estado del candidato
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor($this->color_acento[0], $this->color_acento[1], $this->color_acento[2]);
        $pdf->Cell(0, 6, 'Estado: ' . $this->candidato_data['estado_nombre'], 0, 1, 'L');
        
        $pdf->Ln(2);
        
        // Información de contacto
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
        
        $contacto_info = [
            ['Email:', $this->candidato_data['email']],
            ['Teléfono:', $this->candidato_data['telefono']],
            ['Experiencia:', $this->candidato_data['experiencia'] . ' años']
        ];
        
        // Agregar datos demográficos básicos si existen
        if ($this->candidato_data['has_demographic_fields']) {
            if (!empty($this->candidato_data['dni'])) {
                $contacto_info[] = ['DNI:', $this->candidato_data['dni']];
            }
            if (!empty($this->candidato_data['edad'])) {
                $contacto_info[] = ['Edad:', $this->candidato_data['edad'] . ' años'];
            }
            if (!empty($this->candidato_data['nacionalidad'])) {
                $contacto_info[] = ['Nacionalidad:', $this->candidato_data['nacionalidad']];
            }
        }
        
        foreach ($contacto_info as $info) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, $info[0], 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $info[1], 0, 1, 'L');
        }
        
        $pdf->SetY(max($pdf->GetY(), $y_inicial + 40)); // Asegurar espacio mínimo para la foto
        $pdf->Ln(5);
    }
    
    private function generarSeccionDemografica($pdf) {
        $this->generarTituloSeccion($pdf, 'INFORMACIÓN DEMOGRÁFICA');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
        
        $info_demografica = [];
        
        if (!empty($this->candidato_data['estado_civil'])) {
            $info_demografica[] = ['Estado Civil:', $this->candidato_data['estado_civil']];
        }
        
        if (!empty($this->candidato_data['hijos']) && $this->candidato_data['hijos'] > 0) {
            $hijos_text = $this->candidato_data['hijos'] . ' hijo' . ($this->candidato_data['hijos'] > 1 ? 's' : '');
            if (!empty($this->candidato_data['edad_hijos'])) {
                $hijos_text .= ' (edades: ' . $this->candidato_data['edad_hijos'] . ')';
            }
            $info_demografica[] = ['Hijos:', $hijos_text];
        } elseif (isset($this->candidato_data['hijos'])) {
            $info_demografica[] = ['Hijos:', 'No tiene'];
        }
        
        if (!empty($this->candidato_data['lugar_residencia'])) {
            $info_demografica[] = ['Residencia:', $this->candidato_data['lugar_residencia']];
        }
        
        if (!empty($this->candidato_data['ocupacion_actual'])) {
            $info_demografica[] = ['Ocupación Actual:', $this->candidato_data['ocupacion_actual']];
        }
        
        if (!empty($this->candidato_data['ocupacion_padre'])) {
            $info_demografica[] = ['Ocupación Padre:', $this->candidato_data['ocupacion_padre']];
        }
        
        if (!empty($this->candidato_data['ocupacion_madre'])) {
            $info_demografica[] = ['Ocupación Madre:', $this->candidato_data['ocupacion_madre']];
        }
        
        // Mostrar en dos columnas
        $columna_actual = 0;
        $y_inicial = $pdf->GetY();
        
        foreach ($info_demografica as $index => $info) {
            if ($columna_actual == 0) {
                $pdf->SetXY(15, $y_inicial + ($index / 2) * 5);
            } else {
                $pdf->SetXY(105, $y_inicial + (($index - 1) / 2) * 5);
            }
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(35, 5, $info[0], 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $info[1], 0, 1, 'L');
            
            $columna_actual = 1 - $columna_actual; // Alternar entre 0 y 1
        }
        
        $pdf->SetY($pdf->GetY() + 5);
        $pdf->Ln(3);
    }
    
    private function generarSeccionAreas($pdf) {
        $this->generarTituloSeccion($pdf, 'ÁREAS PROFESIONALES');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
        
        $areas_texto = [];
        foreach ($this->candidato_data['areas'] as $area) {
            $areas_texto[] = $area['nombre'];
        }
        
        $pdf->Cell(0, 6, '• ' . implode(' • ', $areas_texto), 0, 1, 'L');
        $pdf->Ln(3);
    }
    
    private function generarSeccionEspecialidades($pdf) {
        $this->generarTituloSeccion($pdf, 'ESPECIALIDADES Y COMPETENCIAS');
        
        foreach ($this->candidato_data['especialidades_por_area'] as $area_data) {
            // Título del área
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor($this->color_acento[0], $this->color_acento[1], $this->color_acento[2]);
            $pdf->Cell(0, 6, $area_data['area_nombre'], 0, 1, 'L');
            
            // Especialidades del área
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
            
            foreach ($area_data['especialidades'] as $esp) {
                $texto_esp = '   • ' . $esp['especialidad_nombre'];
                if (!empty($esp['nivel_nombre'])) {
                    $texto_esp .= ' (' . $esp['nivel_nombre'] . ')';
                }
                $pdf->Cell(0, 5, $texto_esp, 0, 1, 'L');
            }
            
            $pdf->Ln(2);
        }
        
        $pdf->Ln(1);
    }
    
    private function generarSeccionExperiencia($pdf) {
        $this->generarTituloSeccion($pdf, 'EXPERIENCIA LABORAL');
        
        foreach ($this->candidato_data['experiencias_laborales'] as $exp) {
            // Verificar si necesitamos nueva página
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                $this->generarEncabezado($pdf);
                $pdf->SetY(35);
            }
            
            // Puesto y empresa
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
            $pdf->Cell(0, 6, $exp['puesto'], 0, 1, 'L');
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor($this->color_acento[0], $this->color_acento[1], $this->color_acento[2]);
            $pdf->Cell(0, 5, $exp['empresa'], 0, 1, 'L');
            
            // Fechas
            $fecha_desde = date('m/Y', strtotime($exp['fecha_desde']));
            $fecha_hasta = $exp['fecha_hasta'] ? date('m/Y', strtotime($exp['fecha_hasta'])) : 'Actual';
            
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
            $pdf->Cell(0, 5, 'Período: ' . $fecha_desde . ' - ' . $fecha_hasta, 0, 1, 'L');
            
            // Empleador si existe
            if (!empty($exp['empleador'])) {
                $pdf->Cell(0, 5, 'Empleador: ' . $exp['empleador'], 0, 1, 'L');
            }
            
            // Tareas/descripción
            if (!empty($exp['tareas'])) {
                $pdf->Ln(1);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->MultiCell(0, 4, 'Tareas: ' . $exp['tareas'], 0, 'L');
            }
            
            $pdf->Ln(3);
        }
    }
    
    private function generarSeccionFormacion($pdf) {
        $formacion = $this->candidato_data['formacion_profesional'];
        
        $this->generarTituloSeccion($pdf, 'FORMACIÓN PROFESIONAL');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
        
        if (!empty($formacion['nivel_educativo'])) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Nivel Educativo:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $formacion['nivel_educativo'], 0, 1, 'L');
        }
        
        if (!empty($formacion['carreras_titulos'])) {
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Carreras y Títulos:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 4, $formacion['carreras_titulos'], 0, 'L');
        }
        
        if (!empty($formacion['cursos_capacitaciones'])) {
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Cursos y Capacitaciones:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 4, $formacion['cursos_capacitaciones'], 0, 'L');
        }
        
        $pdf->Ln(3);
    }
    
    private function generarSeccionHabilidades($pdf) {
        $hab = $this->candidato_data['habilidades_disponibilidad'];
        
        $this->generarTituloSeccion($pdf, 'HABILIDADES Y DISPONIBILIDAD');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
        
        // Antecedentes penales
        if (!empty($hab['antecedentes_penales'])) {
            $antecedentes_text = strtolower($hab['antecedentes_penales']) === 'no' ? 'Sin antecedentes penales' : 'Con antecedentes penales';
            $pdf->Cell(0, 5, '• Antecedentes: ' . $antecedentes_text, 0, 1, 'L');
        }
        
        // Licencias de conducir
        if (!empty($hab['licencia_conducir'])) {
            $pdf->Cell(0, 5, '• Licencia de conducir: ' . $hab['licencia_conducir'], 0, 1, 'L');
        }
        
        if (!empty($hab['otras_licencias'])) {
            $pdf->MultiCell(0, 4, '• Otras licencias: ' . $hab['otras_licencias'], 0, 'L');
        }
        
        // Disponibilidad
        if (!empty($hab['disponibilidad'])) {
            $disponibilidad_text = '';
            switch($hab['disponibilidad']) {
                case 'inmediata': $disponibilidad_text = 'Inmediata'; break;
                case '15_dias': $disponibilidad_text = '15 días'; break;
                case '30_dias': $disponibilidad_text = '30 días'; break;
                default: $disponibilidad_text = $hab['disponibilidad'];
            }
            $pdf->Cell(0, 5, '• Disponibilidad: ' . $disponibilidad_text, 0, 1, 'L');
        }
        
        $pdf->Ln(3);
    }
    
    private function generarSeccionObservaciones($pdf) {
        $this->generarTituloSeccion($pdf, 'OBSERVACIONES');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor($this->color_texto[0], $this->color_texto[1], $this->color_texto[2]);
        $pdf->MultiCell(0, 4, $this->candidato_data['observaciones'], 0, 'L');
        
        $pdf->Ln(3);
    }
    
    private function generarTituloSeccion($pdf, $titulo) {
        // Verificar si necesitamos nueva página
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            $this->generarEncabezado($pdf);
            $pdf->SetY(35); // Posición después del encabezado
        }
        
        $pdf->Ln(2);
        
        // Fondo del título
        $pdf->SetFillColor($this->color_fondo_claro[0], $this->color_fondo_claro[1], $this->color_fondo_claro[2]);
        $pdf->Rect(15, $pdf->GetY(), $pdf->getPageWidth() - 30, 8, 'F');
        
        // Texto del título
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
        $pdf->Cell(0, 8, $titulo, 0, 1, 'L');
        
        $pdf->Ln(2);
    }
    
    private function incluirFotoCandidato($pdf, $x, $y) {
        $foto_incluida = false;
        $foto_error = null;
        
        // Intentar cargar foto real del candidato
        if (!empty($this->candidato_data['foto_ruta'])) {
            
            // Definir rutas posibles donde puede estar la foto
            $rutas_posibles = [
                // Ruta directa desde uploads
                __DIR__ . '/../uploads/' . $this->candidato_data['foto_ruta'],
                // Ruta relativa desde el campo
                __DIR__ . '/../' . $this->candidato_data['foto_ruta'],
                // Ruta absoluta si está completa
                $this->candidato_data['foto_ruta']
            ];
            
            $foto_path = null;
            
            // Buscar el archivo en las rutas posibles
            foreach ($rutas_posibles as $ruta) {
                if (file_exists($ruta)) {
                    $foto_path = $ruta;
                    break;
                }
            }
            
            if ($foto_path) {
                try {
                    // Verificar si es una imagen válida
                    $image_info = @getimagesize($foto_path);
                    if ($image_info !== false) {
                        
                        // Determinar el tipo de imagen y verificar compatibilidad
                        $tipo_imagen = '';
                        $mime_type = $image_info['mime'];
                        $puede_cargar = true;
                        
                        switch ($mime_type) {
                            case 'image/jpeg':
                            case 'image/jpg':
                                $tipo_imagen = 'JPEG';
                                // JPEG siempre es compatible
                                break;
                            case 'image/png':
                                $tipo_imagen = 'PNG';
                                // Verificar si PNG es problemático (con alpha channel)
                                // Sin GD, los PNGs con transparencia fallan
                                if (!extension_loaded('gd') && !extension_loaded('imagick')) {
                                    // Para PNGs sin GD, usar detección heurística
                                    $file_size = filesize($foto_path);
                                    $dimensions = $image_info[0] * $image_info[1];
                                    
                                    // Si el archivo es "grande" para sus dimensiones, probablemente tiene alpha
                                    $ratio = $file_size / ($dimensions / 1000);
                                    if ($ratio > 25) { // Threshold empírico
                                        $puede_cargar = false;
                                        $foto_error = "PNG con canal alpha no soportado sin extensión GD";
                                    }
                                }
                                break;
                            case 'image/gif':
                                $tipo_imagen = 'GIF';
                                break;
                            default:
                                $tipo_imagen = ''; // Auto-detectar
                        }
                        
                        if ($puede_cargar) {
                            // Intentar incluir la imagen con TCPDF
                            // TCPDF puede manejar JPEGs y algunos PNGs sin GD
                            $pdf->Image(
                                $foto_path,      // Ruta de la imagen
                                $x,              // Posición X
                                $y,              // Posición Y 
                                35,              // Ancho
                                35,              // Alto
                                $tipo_imagen,    // Tipo específico
                                '',              // Enlace
                                'T',             // Alineación
                                false,           // Redimensionar
                                300,             // DPI
                                '',              // Palette
                                false,           // Transparencia
                                false,           // Reducir imagen
                                1,               // Border
                                false,           // FitBox
                                false,           // Hidden
                                false            // Fitonpage
                            );
                        } else {
                            // No intentar cargar, usar placeholder
                            throw new Exception($foto_error);
                        }
                        $foto_incluida = true;
                        
                        // Log de éxito interno
                        error_log("PDF: Foto incluida exitosamente - Candidato {$this->candidato_data['id']}, archivo: " . basename($foto_path) . ", tipo: $tipo_imagen");
                        
                    } else {
                        $foto_error = "Formato de imagen no válido";
                    }
                } catch (Exception $e) {
                    $foto_error = "Error al procesar imagen: " . $e->getMessage();
                    error_log("PDF: Error incluyendo foto - Candidato {$this->candidato_data['id']}: " . $e->getMessage());
                }
            } else {
                $foto_error = "Archivo no encontrado en ninguna ruta";
                error_log("PDF: Foto no encontrada - Candidato {$this->candidato_data['id']}, foto_ruta: {$this->candidato_data['foto_ruta']}");
            }
        } else {
            $foto_error = "No hay foto asignada";
        }
        
        // Si no se pudo cargar la foto, crear placeholder profesional
        if (!$foto_incluida) {
            // Fondo del placeholder
            $pdf->SetFillColor(245, 250, 255); // Azul muy claro
            $pdf->Rect($x, $y, 35, 35, 'F');
            
            // Borde decorativo interno
            $pdf->SetDrawColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($x + 2, $y + 2, 31, 31, 'D');
            
            // Círculo para simular cabeza
            $centerX = $x + 17.5;
            $centerY = $y + 12;
            $pdf->SetFillColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
            $pdf->Circle($centerX, $centerY, 4, 0, 360, 'F');
            
            // Rectángulo para simular cuerpo
            $pdf->SetFillColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
            $pdf->RoundedRect($x + 12, $y + 18, 11, 8, 2, '1111', 'F');
            
            // Texto descriptivo
            $pdf->SetXY($x + 2, $y + 28);
            $pdf->SetFont('helvetica', 'B', 5);
            $pdf->SetTextColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
            
            if ($foto_error === "No hay foto asignada") {
                $pdf->Cell(31, 3, 'SIN FOTO', 0, 0, 'C');
            } else {
                $pdf->Cell(31, 3, 'FOTO NO DISPONIBLE', 0, 0, 'C');
            }
        }
        
        // Marco principal alrededor de la foto o placeholder
        $pdf->SetDrawColor($this->color_primario[0], $this->color_primario[1], $this->color_primario[2]);
        $pdf->SetLineWidth(1);
        $pdf->Rect($x, $y, 35, 35, 'D');
        
        // Log interno para debug (no visible en PDF)
        if ($foto_error && !$foto_incluida) {
            // Se podría registrar en log para debugging
            // error_log("PDF: Foto candidato {$this->candidato_data['id']}: $foto_error");
        }
    }
    
    private function limpiarNombreArchivo($nombre) {
        // Reemplazar caracteres especiales para nombre de archivo
        $nombre = mb_strtolower($nombre, 'UTF-8');
        $nombre = preg_replace('/[^a-z0-9\-_]/', '_', $nombre);
        $nombre = preg_replace('/_{2,}/', '_', $nombre);
        $nombre = trim($nombre, '_');
        return $nombre;
    }
}

// Procesamiento de la solicitud
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $candidato_id = (int) $_GET['id'];
    
    // Verificar token CSRF si se envía por POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf'] ?? null)) {
            die('Token de seguridad inválido.');
        }
    }
    
    try {
        // Agregar más debugging
        error_log("Iniciando generación de PDF para candidato ID: $candidato_id");
        
        $generador = new GeneradorPDFCandidato();
        $generador->generarPDF($candidato_id);
        
    } catch (Exception $e) {
        // Log detallado del error
        error_log("Error al generar PDF para candidato $candidato_id: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Mostrar error al usuario
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Error</title></head><body>";
        echo "<h2>Error al generar el PDF</h2>";
        echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
        echo "<p><strong>Línea:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
        echo "<h3>Stack Trace:</h3>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "<p><a href='javascript:history.back()'>Volver</a></p>";
        echo "</body></html>";
        exit;
    } catch (Error $e) {
        // Capturar errores fatales como Error
        error_log("Error fatal al generar PDF para candidato $candidato_id: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Error Fatal</title></head><body>";
        echo "<h2>Error Fatal al generar el PDF</h2>";
        echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
        echo "<p><strong>Línea:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
        echo "<h3>Stack Trace:</h3>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "<p><a href='javascript:history.back()'>Volver</a></p>";
        echo "</body></html>";
        exit;
    }
} else {
    die('ID de candidato no válido.');
}
?>