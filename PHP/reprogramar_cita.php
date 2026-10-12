<?php
session_start();
require_once 'conexion.php';

// validar que sea paciente (eol 1)
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 1) {
    header("Location: index.php");
    exit();
}

$id_paciente = $_SESSION['identificador_usuario'];

// validar que nos hayan enviado el id de la cita a reprogramar
if (!isset($_GET['id']) && !isset($_POST['id_cita'])) {
    header("Location: mis_citas.php");
    exit();
}

$id_cita = isset($_GET['id']) ? $_GET['id'] : $_POST['id_cita'];

// 1) obtener y validar la cita actual
try {
    // buscamos la cita asegurándonos que sea del paciente y este en estado valido
    $sql_cita = "SELECT c.*, m.nombre_completo as medico, e.nombre as especialidad, cen.nombre as centro 
                 FROM Cita c
                 JOIN Medico m ON c.id_medico = m.id
                 JOIN Especialidad e ON c.id_especialidad = e.id
                 JOIN CentroMedico cen ON c.id_centro = cen.id
                 JOIN EstadoCita est ON c.id_estado_cita = est.id
                 WHERE c.id = :id_cita 
                   AND c.id_paciente = :id_paciente 
                   AND c.fecha_hora > NOW() 
                   AND est.nombre IN ('Reservada', 'Confirmada')";
                   
    $stmt_cita = $pdo->prepare($sql_cita);
    $stmt_cita->execute([':id_cita' => $id_cita, ':id_paciente' => $id_paciente]);
    $cita_actual = $stmt_cita->fetch(PDO::FETCH_ASSOC);

    if (!$cita_actual) {
        die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
                <h2>Acceso Denegado</h2>
                <p>La cita no existe, ya ocurrió, está cancelada o no te pertenece.</p>
                <a href='mis_citas.php'>Volver a Mis Citas</a>
             </div>");
    }
} catch (PDOException $e) {
    die("Error al cargar la cita: " . $e->getMessage());
}

// variables para el formulario
$fecha_seleccionada = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d', strtotime($cita_actual['fecha_hora']));

// 2) buscar bloques horarios disponibles en la fecha seleccionada
$bloques_disponibles = [];
if (!empty($fecha_seleccionada)) {
    // horario de atencion y bloques igual que en el otro archivo que no me acuerdo
    $hora_inicio = strtotime('09:00');
    $hora_fin = strtotime('17:00');
    $duracion_bloque = 30 * 60; 
    
    // buscar horas ocupadas del medico en esa nueva fecha
    $sql_ocupadas = "SELECT DATE_FORMAT(fecha_hora, '%H:%i') 
                     FROM Cita 
                     WHERE id_medico = :id_medico 
                       AND DATE(fecha_hora) = :fecha 
                       AND id != :id_cita_actual
                       AND id_estado_cita != (SELECT id FROM EstadoCita WHERE nombre = 'Cancelada')";
                       
    $stmt_ocupadas = $pdo->prepare($sql_ocupadas);
    $stmt_ocupadas->execute([
        ':id_medico' => $cita_actual['id_medico'], 
        ':fecha' => $fecha_seleccionada,
        ':id_cita_actual' => $id_cita // evita que la misma cita bloquee su propio horario si la elegimos el mismo día
    ]);
    $horas_ocupadas = $stmt_ocupadas->fetchAll(PDO::FETCH_COLUMN);

    //generar horas y descartar las ocupadas
    for ($i = $hora_inicio; $i < $hora_fin; $i += $duracion_bloque) {
        $hora_actual = date('H:i', $i);
        if (!in_array($hora_actual, $horas_ocupadas)) {
            $bloques_disponibles[] = $hora_actual;
        }
    }
}

// 3) procesar la actualizacion si el paciente eligio un horario nuevo.
if (isset($_POST['hora_nueva'])) {
    $nueva_fecha_hora = $fecha_seleccionada . ' ' . $_POST['hora_nueva'] . ':00';
    
    try {
        // hacemos el update de la cita sin sobreagendar
        $sql_update = "UPDATE Cita SET fecha_hora = :nueva_fecha_hora WHERE id = :id_cita";
        $stmt_update = $pdo->prepare($sql_update);
        
        if ($stmt_update->execute([':nueva_fecha_hora' => $nueva_fecha_hora, ':id_cita' => $id_cita])) {
            // redirigimos a mis citas
            header("Location: mis_citas.php");
            exit();
        }
    } catch (PDOException $e) {
        $error_update = "Error al reprogramar. Es posible que el horario ya se haya ocupado.";
    }
}

include 'header.php';
?>

<div class="row justify-content-center mt-4 mb-5">
    <div class="col-md-8">
        <div class="card shadow border-warning">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">Reprogramar Cita</h4>
            </div>
            <div class="card-body">
                
                <?php if(isset($error_update)): ?>
                    <div class="alert alert-danger"><?php echo $error_update; ?></div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>Datos de la cita a reprogramar:</strong><br>
                    Médico: <?php echo htmlspecialchars($cita_actual['medico']); ?><br>
                    Especialidad: <?php echo htmlspecialchars($cita_actual['especialidad']); ?><br>
                    Centro Médico: <?php echo htmlspecialchars($cita_actual['centro']); ?><br>
                    <em>Fecha Original: <?php echo date('d-m-Y H:i', strtotime($cita_actual['fecha_hora'])); ?></em>
                </div>

                <!--elegir nueva fecha -->
                <form action="reprogramar_cita.php" method="POST" id="formReprogramar" class="mb-4">
                    <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                    
                    <div class="mb-3">
                        <label for="fecha" class="form-label fw-bold">1. Seleccione la Nueva Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="<?php echo $fecha_seleccionada; ?>" min="<?php echo date('Y-m-d'); ?>" onchange="document.getElementById('formReprogramar').submit();" required>
                    </div>
                </form>

                <!--elegir el nuevo bloque horario -->
                <h5 class="mb-3">2. Seleccione un Nuevo Horario Disponible</h5>
                <form action="reprogramar_cita.php" method="POST">
                    <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                    <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                    
                    <div class="row row-cols-3 row-cols-md-4 g-2 mb-4">
                        <?php if(empty($bloques_disponibles)): ?>
                            <div class="col-12 text-center text-danger">No hay horas disponibles para el Dr/a. en esta fecha.</div>
                        <?php else: ?>
                            <?php foreach($bloques_disponibles as $bloque): ?>
                                <div class="col">
                                    <input type="radio" class="btn-check" name="hora_nueva" id="hora_<?php echo $bloque; ?>" value="<?php echo $bloque; ?>" required>
                                    <label class="btn btn-outline-warning text-dark w-100" for="hora_<?php echo $bloque; ?>"><?php echo $bloque; ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="mis_citas.php" class="btn btn-secondary">Cancelar y Volver</a>
                        <?php if(!empty($bloques_disponibles)): ?>
                            <button type="submit" class="btn btn-warning fw-bold">Confirmar Reprogramación</button>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>