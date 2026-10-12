<?php
session_start();
// si no ha iniciado sesion, lo manda al login
if (!isset($_SESSION['identificador_usuario'])) {
    header("Location: login.php?error=2");
    exit();
}

require_once 'conexion.php';
include 'header.php'; 

$rol_actual = $_SESSION['rol_usuario'];
$nombre_usuario = $_SESSION['nombre_usuario'];
?>

<div class="row mb-4 mt-3">
    <div class="col-12">
        <div class="alert alert-info d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Bienvenido/a, <?php echo htmlspecialchars($nombre_usuario); ?></h4>
            <span class="badge bg-secondary fs-6">
                <?php 
                    if($rol_actual == 1) echo "Portal Paciente";
                    if($rol_actual == 2) echo "Portal Médico";
                    if($rol_actual == 3) echo "Panel Administrador";
                ?>
            </span>
        </div>
    </div>
</div>

<!--barra de búsqueda-->
<div class="card mb-4 shadow-sm border-primary">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Buscar Médicos</h5>
    </div>
    <div class="card-body bg-light">
        <!-- el formulario enviará los datos a buscar_medico.php -->
        <form action="buscar_medico.php" method="GET" class="row g-3 align-items-center">
            <div class="col-md-9">
                <input type="text" class="form-control" name="termino_busqueda" placeholder="Ingrese nombre del médico o especialidad..." required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Buscar</button>
            </div>
        </form>
    </div>
</div>

<?php 
//vista del paciente
if ($rol_actual == 1): 
?>
    <div class="row">
        <!-- opciones rápidas-->
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title">Nueva Cita</h5>
                    <p class="card-text text-muted">Agenda una hora médica seleccionando especialidad, centro y médico.</p>
                    <a href="agendar_cita.php" class="btn btn-success mt-auto">Agendar Hora</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title">Mis Citas</h5>
                    <p class="card-text text-muted">Revisa, reprograma o cancela tus horas agendadas.</p>
                    <a href="mis_citas.php" class="btn btn-info mt-auto text-white">Ver Mis Citas</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title">Mi Historial Clínico</h5>
                    <p class="card-text text-muted">Consulta tus atenciones anteriores y recetas médicas.</p>
                    <a href="historial_paciente.php" class="btn btn-secondary mt-auto">Ver Historial</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
//vista del médico
if ($rol_actual == 2): 
?>
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Mi Agenda Diaria</h5>
                </div>
                <div class="card-body">
                    <p>Revisa tus citas agendadas para hoy y registra la atención de tus pacientes.</p>
                    <a href="agenda_medico.php" class="btn btn-info text-white w-100">Abrir Agenda del Día</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title">Historial de Pacientes</h5>
                    <p class="card-text text-muted">Busca atenciones anteriores de los pacientes que tratas.</p>
                    <a href="buscar_historial.php" class="btn btn-secondary mt-auto">Consultar Historial</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
// vista del administrador
if ($rol_actual == 3): 
?>
    <div class="row">
        <div class="col-md-4 mb-4">
            <!--menu de gestion administrativa-->
            <div class="list-group shadow-sm">
                <a href="gestion_centros.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Gestión de Centros Médicos <span class="badge bg-primary rounded-pill">></span>
                </a>
                <a href="gestion_medicos.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Gestión de Médicos <span class="badge bg-primary rounded-pill">></span>
                </a>
                <a href="estadisticas_red.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Panel Estadístico General <span class="badge bg-primary rounded-pill">></span>
                </a>
            </div>
        </div>
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm h-100 border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Panel de Gestión - Resumen de la Red</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">A continuación se debe mostrar el reporte de inasistencia por centro y los 5 diagnósticos más frecuentes.</p>
                    <div class="text-center mt-4">
                        <a href="estadisticas_red.php" class="btn btn-warning">Generar Reporte Ahora</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- búsqueda avanzada de citas -->
<div class="text-center mt-2 mb-5">
    <a href="busqueda_avanzada.php" class="btn btn-outline-dark">Ir a Búsqueda Avanzada de Citas</a>
</div>

<?php include 'footer.php'; ?>