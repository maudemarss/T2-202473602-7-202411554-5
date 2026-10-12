<?php
// SUPUESTO: bloques de media hora y la atencion es desde las 9 am hasta las 6 pm
session_start();
// solo los pacientes pueden agendar citas (rol 1)
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 1) {
    header("Location: index.php");
    exit();
}

require_once 'conexion.php';
include 'header.php';

$paciente_id = $_SESSION['identificador_usuario'];

// variables para mantener la selección del usuario
$especialidad_seleccionada = isset($_POST['id_especialidad']) ? $_POST['id_especialidad'] : '';
$centro_seleccionado = isset($_POST['id_centro']) ? $_POST['id_centro'] : '';
$medico_seleccionado = isset($_POST['id_medico']) ? $_POST['id_medico'] : '';
$fecha_seleccionada = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');

// obtener las especialidades
$stmt_especialidades = $pdo->query("SELECT id_especialidad AS id, nombre FROM Especialidad ORDER BY nombre");
$especialidades = $stmt_especialidades->fetchAll(PDO::FETCH_ASSOC);

// si se eligió especialidad, obtener los centros donde atienden los medicos con esta especialidad. 
$centros = [];
if (!empty($especialidad_seleccionada)) {
    $sql_centros = "SELECT DISTINCT c.id_centro AS id, c.nombre 
                    FROM CentroMedico c
                    JOIN Medico_Centro mc ON c.id_centro = mc.id_centro
                    JOIN Medico_Especialidad me ON mc.id_medico = me.id_medico
                    WHERE me.id_especialidad = :id_esp";
    $stmt = $pdo->prepare($sql_centros);
    $stmt->execute([':id_esp' => $especialidad_seleccionada]);
    $centros = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// si se selecciono un centro y una especialidad, arroja los medicos disponibles
if (!empty($centro_seleccionado) && !empty($especialidad_seleccionada)) {
    $sql_medicos = "SELECT m.id_medico AS id, m.nombre_completo 
                    FROM Medico m
                    JOIN Medico_Centro mc ON m.id_medico = mc.id_medico
                    JOIN Medico_Especialidad me ON m.id_medico = me.id_medico
                    WHERE mc.id_centro = :id_cen AND me.id_especialidad = :id_esp";
    $stmt = $pdo->prepare($sql_medicos);
    $stmt->execute([':id_cen' => $centro_seleccionado, ':id_esp' => $especialidad_seleccionada]);
    $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// logica para generar los bloques horarios si se selecciono todo
$bloques_disponibles = [];
if (!empty($medico_seleccionado) && !empty($fecha_seleccionada)) {
    //horario de atencion
    $hora_inicio = strtotime('09:00');
    $hora_fin = strtotime('18:00');
    $duracion_bloque = 30 * 60; // 30 minutos en los segundos
    
    // obtener las horas que ya estan ocupadas por el médico en esa fecha
    $sql_ocupadas = "SELECT DATE_FORMAT(fecha_hora, '%H:%i') as hora_ocupada 
                     FROM Cita 
                     WHERE (id_medico = :id_med OR id_paciente = :id_pac) AND DATE(fecha_hora) = :fecha";
    $stmt_ocupadas = $pdo->prepare($sql_ocupadas);
    $stmt_ocupadas->execute([':id_med' => $medico_seleccionado, ':id_pac' => $paciente_id, ':fecha' => $fecha_seleccionada]);
    $horas_ocupadas = $stmt_ocupadas->fetchAll(PDO::FETCH_COLUMN);

    // generar la lista de horas y descartasr las ocupadas
    for ($i = $hora_inicio; $i < $hora_fin; $i += $duracion_bloque) {
        $hora_actual = date('H:i', $i);
        if (!in_array($hora_actual, $horas_ocupadas) && strtotime($fecha_seleccionada . ' ' . $hora_actual) > time()) {
            $bloques_disponibles[] = $hora_actual;
        }
    }
}
?>

<div class="row justify-content-center mt-4 mb-5">
    <div class="col-md-8">
        <div class="card shadow border-success">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Agendar Nueva Hora Médica</h4>
            </div>
            <div class="card-body">
                
                <?php if(isset($_GET['exito'])): ?>
                    <div class="alert alert-success">Su cita ha sido agendada con éxito!!!</div>
                <?php endif; ?>
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error'] != '1' ? $_GET['error'] : 'Ocurrió un error al agendar. Puede que el horario ya esté ocupado.'); ?></div>
                <?php endif; ?>

                <!--formulario-->
                <form action="agendar_cita.php" method="POST" id="formAgenda">
                    
                    <!--especialidad-->
                    <div class="mb-3">
                        <label for="id_especialidad" class="form-label fw-bold">1. Seleccione Especialidad</label>
                        <select name="id_especialidad" id="id_especialidad" class="form-select" onchange="document.getElementById('formAgenda').submit();" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($especialidades as $esp): ?>
                                <option value="<?php echo $esp['id']; ?>" <?php if($especialidad_seleccionada == $esp['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($esp['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- centro-->
                    <?php if(!empty($especialidad_seleccionada)): ?>
                    <div class="mb-3">
                        <label for="id_centro" class="form-label fw-bold">2. Seleccione Centro Médico</label>
                        <select name="id_centro" id="id_centro" class="form-select" onchange="document.getElementById('formAgenda').submit();" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($centros as $cen): ?>
                                <option value="<?php echo $cen['id']; ?>" <?php if($centro_seleccionado == $cen['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cen['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- médico -->
                    <?php if(!empty($centro_seleccionado)): ?>
                    <div class="mb-3">
                        <label for="id_medico" class="form-label fw-bold">3. Seleccione Médico</label>
                        <select name="id_medico" id="id_medico" class="form-select" onchange="document.getElementById('formAgenda').submit();" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($medicos as $med): ?>
                                <option value="<?php echo $med['id']; ?>" <?php if($medico_seleccionado == $med['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($med['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- fecha -->
                    <?php if(!empty($medico_seleccionado)): ?>
                    <div class="mb-3">
                        <label for="fecha" class="form-label fw-bold">4. Seleccione Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="<?php echo $fecha_seleccionada; ?>" min="<?php echo date('Y-m-d'); ?>" onchange="document.getElementById('formAgenda').submit();" required>
                    </div>
                    <?php endif; ?>

                </form>

                <!-- confirmar cita -->
                <?php if(!empty($medico_seleccionado) && !empty($fecha_seleccionada)): ?>
                    <hr>
                    <h5 class="mb-3">5. Seleccione un Horario Disponible</h5>
                    <form action="procesar_agendar.php" method="POST">
                        <input type="hidden" name="id_especialidad" value="<?php echo $especialidad_seleccionada; ?>">
                        <input type="hidden" name="id_centro" value="<?php echo $centro_seleccionado; ?>">
                        <input type="hidden" name="id_medico" value="<?php echo $medico_seleccionado; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                        
                        <div class="row row-cols-3 row-cols-md-4 g-2 mb-4">
                            <?php if(empty($bloques_disponibles)): ?>
                                <div class="col-12 text-center text-danger">No hay horas disponibles para este día.</div>
                            <?php else: ?>
                                <?php foreach($bloques_disponibles as $bloque): ?>
                                    <div class="col">
                                        <input type="radio" class="btn-check" name="hora" id="hora_<?php echo $bloque; ?>" value="<?php echo $bloque; ?>" required>
                                        <label class="btn btn-outline-success w-100" for="hora_<?php echo $bloque; ?>"><?php echo $bloque; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($bloques_disponibles)): ?>
                            <button type="submit" class="btn btn-success w-100 fw-bold">Confirmar Reserva de Hora</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
