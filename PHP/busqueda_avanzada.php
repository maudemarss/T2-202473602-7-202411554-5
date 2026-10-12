<?php
session_start();
require_once 'conexion.php';

// Validar que haya sesión iniciada (Cualquier rol puede entrar)
if (!isset($_SESSION['identificador_usuario'])) {
    header("Location: login.php");
    exit();
}

$rol_usuario = $_SESSION['rol_usuario'];
$id_usuario = $_SESSION['identificador_usuario'];

// cargar catalogos para los select
$centros = $pdo->query("SELECT id, nombre FROM CentroMedico")->fetchAll(PDO::FETCH_ASSOC);
// Obtenemos solo las regiones que existen en los centros
$regiones = $pdo->query("SELECT DISTINCT region FROM CentroMedico")->fetchAll(PDO::FETCH_ASSOC);
$especialidades = $pdo->query("SELECT id, nombre FROM Especialidad")->fetchAll(PDO::FETCH_ASSOC);
$medicos = $pdo->query("SELECT id, nombre_completo FROM Medico")->fetchAll(PDO::FETCH_ASSOC);
$estados = $pdo->query("SELECT id, nombre FROM EstadoCita")->fetchAll(PDO::FETCH_ASSOC);
$previsiones = $pdo->query("SELECT id, nombre FROM Prevision")->fetchAll(PDO::FETCH_ASSOC);

// consulta sql dinamica uniendo todas las tablas necesarias
$sql = "SELECT c.fecha_hora, p.nombre_completo AS paciente, m.nombre_completo AS medico, 
               e.nombre AS especialidad, cen.nombre AS centro, est.nombre AS estado
        FROM Cita c
        JOIN Paciente p ON c.id_paciente = p.id
        JOIN Medico m ON c.id_medico = m.id
        JOIN Especialidad e ON c.id_especialidad = e.id
        JOIN CentroMedico cen ON c.id_centro = cen.id
        JOIN EstadoCita est ON c.id_estado_cita = est.id
        JOIN Prevision prev ON p.id_prevision = prev.id
        WHERE 1=1 ";

// restriccion por rol
if ($rol_usuario == 1) {
    // el paciente ve sus citas
    $sql .= " AND c.id_paciente = $id_usuario ";
} elseif ($rol_usuario == 2) {
    // el medico ve sus citas
    $sql .= " AND c.id_medico = $id_usuario ";
}
// si es admin (rol 3), no agregamos nada y podrá ver todas las de la red
if (!empty($_GET['fecha_desde'])) {
    $sql .= " AND DATE(c.fecha_hora) >= '" . $_GET['fecha_desde'] . "' ";
}
if (!empty($_GET['fecha_hasta'])) {
    $sql .= " AND DATE(c.fecha_hora) <= '" . $_GET['fecha_hasta'] . "' ";
}
if (!empty($_GET['id_centro'])) {
    $sql .= " AND c.id_centro = " . $_GET['id_centro'] . " ";
}
if (!empty($_GET['region'])) {
    $sql .= " AND cen.region = '" . $_GET['region'] . "' ";
}
if (!empty($_GET['id_especialidad'])) {
    $sql .= " AND c.id_especialidad = " . $_GET['id_especialidad'] . " ";
}
if (!empty($_GET['id_medico'])) {
    $sql .= " AND c.id_medico = " . $_GET['id_medico'] . " ";
}
if (!empty($_GET['id_estado'])) {
    $sql .= " AND c.id_estado_cita = " . $_GET['id_estado'] . " ";
}
if (!empty($_GET['id_prevision'])) {
    $sql .= " AND p.id_prevision = " . $_GET['id_prevision'] . " ";
}

$sql .= " ORDER BY c.fecha_hora DESC";

$resultados = [];
$busqueda_activa = false;

if (isset($_GET['buscar'])) {
    $busqueda_activa = true;
    $resultados = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <h2>Búsqueda Avanzada de Citas</h2>
    <a href="index.php" class="btn btn-secondary mb-4">Volver al Inicio</a>

    <!-- filtros -->
    <div class="card shadow-sm mb-4 border-dark">
        <div class="card-header bg-dark text-white fw-bold">
            Filtros Combinables
        </div>
        <div class="card-body bg-light">
            <form action="busqueda_avanzada.php" method="GET">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Desde Fecha</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?php echo isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Hasta Fecha</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?php echo isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Centro Médico</label>
                        <select name="id_centro" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($centros as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php if(isset($_GET['id_centro']) && $_GET['id_centro'] == $c['id']) echo 'selected'; ?>><?php echo $c['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Región</label>
                        <select name="region" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php foreach($regiones as $r): ?>
                                <option value="<?php echo $r['region']; ?>" <?php if(isset($_GET['region']) && $_GET['region'] == $r['region']) echo 'selected'; ?>><?php echo $r['region']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Especialidad</label>
                        <select name="id_especialidad" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php foreach($especialidades as $e): ?>
                                <option value="<?php echo $e['id']; ?>" <?php if(isset($_GET['id_especialidad']) && $_GET['id_especialidad'] == $e['id']) echo 'selected'; ?>><?php echo $e['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- se oculta el filtro de medico si el logueado es el medico -->
                    <?php if($rol_usuario != 2): ?>
                    <div class="col-md-3">
                        <label class="form-label small">Médico</label>
                        <select name="id_medico" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($medicos as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php if(isset($_GET['id_medico']) && $_GET['id_medico'] == $m['id']) echo 'selected'; ?>><?php echo $m['nombre_completo']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-3">
                        <label class="form-label small">Estado Cita</label>
                        <select name="id_estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($estados as $est): ?>
                                <option value="<?php echo $est['id']; ?>" <?php if(isset($_GET['id_estado']) && $_GET['id_estado'] == $est['id']) echo 'selected'; ?>><?php echo $est['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- si es paciente, se oculta el filtro de prevision -->
                    <?php if($rol_usuario != 1): ?>
                    <div class="col-md-3">
                        <label class="form-label small">Previsión Paciente</label>
                        <select name="id_prevision" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php foreach($previsiones as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php if(isset($_GET['id_prevision']) && $_GET['id_prevision'] == $p['id']) echo 'selected'; ?>><?php echo $p['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="text-end">
                    <a href="busqueda_avanzada.php" class="btn btn-outline-danger btn-sm">Limpiar Filtros</a>
                    <button type="submit" name="buscar" value="1" class="btn btn-dark btn-sm px-4">Aplicar Filtros</button>
                </div>
            </form>
        </div>
    </div>

    <!-- resultados-->
    <?php if ($busqueda_activa): ?>
        <h4>Resultados de la búsqueda (<?php echo count($resultados); ?>)</h4>
        
        <?php if (count($resultados) > 0): ?>
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Paciente</th>
                                <th>Médico</th>
                                <th>Especialidad</th>
                                <th>Centro</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($resultados as $fila): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y H:i', strtotime($fila['fecha_hora'])); ?></td>
                                    <td><?php echo $fila['paciente']; ?></td>
                                    <td><?php echo $fila['medico']; ?></td>
                                    <td><?php echo $fila['especialidad']; ?></td>
                                    <td><?php echo $fila['centro']; ?></td>
                                    <td><strong><?php echo $fila['estado']; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No se encontraron citas con los filtros seleccionados.</div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>