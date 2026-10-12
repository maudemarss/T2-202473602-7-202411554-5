<?php
session_start();
require_once 'conexion.php';

//validamos que solo entre el medico (rol 2).
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 2) {
    header("Location: index.php");
    exit();
}

$id_medico = $_SESSION['identificador_usuario'];
$mensaje_exito = "";
$mensaje_error = "";

// cambiar el estado de la cita
if (isset($_POST['cambiar_estado']) && isset($_POST['id_cita']) && isset($_POST['nuevo_estado_id'])) {
    $id_cita = $_POST['id_cita'];
    $nuevo_estado_id = $_POST['nuevo_estado_id'];

    try {
        //validamos que la cita sea de este medico antes de actualizarla
        $sql_update = "UPDATE Cita SET id_estado = :nuevo_estado WHERE id_cita = :id_cita AND id_medico = :id_medico";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            ':nuevo_estado' => $nuevo_estado_id,
            ':id_cita' => $id_cita,
            ':id_medico' => $id_medico
        ]);

        if ($stmt_update->rowCount() > 0) {
            $mensaje_exito = "El estado de la cita se actualizó correctamente.";
        } else {
            $mensaje_error = "No se pudo actualizar el estado. la cita no existe o no te pertenece";
        }
    } catch (PDOException $e) {
        $mensaje_error = isset($e->errorInfo[2]) && $e->errorInfo[0] == '45000' ? $e->errorInfo[2] : "Error al actualizar el estado de la cita.";
    }
}

// obtener la fecha para mostrar la agenda
// por defecto seria el dia de hoy, pero con un get permite cambiarlo
$fecha_agenda = isset($_GET['fecha_agenda']) ? $_GET['fecha_agenda'] : date('Y-m-d');

// consultas para cagar los datos
try {
    // 1)obtenemos todas las citas del medico para la fecha elegida
    $sql_agenda = "SELECT c.id_cita AS id, c.fecha_hora, p.nombre_completo AS paciente, p.rut AS rut_paciente, 
                          prev.nombre AS prevision, cen.nombre AS centro, est.nombre AS estado, est.id_estado AS id_estado
                   FROM Cita c
                   JOIN Paciente p ON c.id_paciente = p.id_paciente
                   JOIN Prevision prev ON p.id_prevision = prev.id_prevision
                   JOIN CentroMedico cen ON c.id_centro = cen.id_centro
                   JOIN EstadoCita est ON c.id_estado = est.id_estado
                   WHERE c.id_medico = :id_medico 
                     AND DATE(c.fecha_hora) = :fecha_agenda
                   ORDER BY c.fecha_hora ASC";
                   
    $stmt_agenda = $pdo->prepare($sql_agenda);
    $stmt_agenda->execute([
        ':id_medico' => $id_medico,
        ':fecha_agenda' => $fecha_agenda
    ]);
    $citas_del_dia = $stmt_agenda->fetchAll(PDO::FETCH_ASSOC);

    // 2) obtenemos los estados permitidos para el medico, o sea confirmada, atendida o no asistio. 
    $sql_estados = "SELECT id_estado AS id, nombre FROM EstadoCita WHERE nombre IN ('Confirmada', 'Atendida', 'No Asistió')";
    $estados_permitidos = $pdo->query($sql_estados)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al cargar la agenda: " . $e->getMessage());
}

include 'header.php';
?>

<div class="row mt-3 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>Agenda Diaria </h2>
        <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
    </div>
</div>

<?php if(!empty($mensaje_exito)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $mensaje_exito; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if(!empty($mensaje_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $mensaje_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- buscador de fechas -->
<div class="card shadow-sm border-info mb-4">
    <div class="card-body bg-light py-3">
        <form action="agenda_medico.php" method="GET" class="row g-3 align-items-center justify-content-center">
            <div class="col-auto">
                <label for="fecha_agenda" class="col-form-label fw-bold">Seleccionar Día:</label>
            </div>
            <div class="col-auto">
                <input type="date" name="fecha_agenda" id="fecha_agenda" class="form-control" value="<?php echo $fecha_agenda; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-info text-white">Ver Agenda</button>
            </div>
            <div class="col-auto">
                <a href="agenda_medico.php" class="btn btn-outline-secondary">Ir a Hoy</a>
            </div>
        </form>
    </div>
</div>

<!-- listado de citas -->
<div class="card shadow-sm border-primary mb-5">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Citas para el <?php echo date('d-m-Y', strtotime($fecha_agenda)); ?></h5>
    </div>
    <div class="card-body">
        <?php if (count($citas_del_dia) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th>RUT</th>
                            <th>Previsión</th>
                            <th>Centro Médico</th>
                            <th>Estado Actual</th>
                            <th>Actualizar Estado</th>
                            <th>Acciones Clínicas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas_del_dia as $cita): ?>
                            <tr>
                                <td><strong><?php echo date('H:i', strtotime($cita['fecha_hora'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($cita['paciente']); ?></td>
                                <td><?php echo htmlspecialchars($cita['rut_paciente']); ?></td>
                                <td><?php echo htmlspecialchars($cita['prevision']); ?></td>
                                <td><small><?php echo htmlspecialchars($cita['centro']); ?></small></td>
                                
                                <!-- estado actual  -->
                                <td>
                                    <?php 
                                        $color = 'bg-secondary';
                                        if ($cita['estado'] == 'Reservada') $color = 'bg-primary';
                                        if ($cita['estado'] == 'Confirmada') $color = 'bg-info text-dark';
                                        if ($cita['estado'] == 'Atendida') $color = 'bg-success';
                                        if ($cita['estado'] == 'No Asistió' || $cita['estado'] == 'Cancelada') $color = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $color; ?>"><?php echo htmlspecialchars($cita['estado']); ?></span>
                                </td>

                                <!--formulario para cambiar el estado-->
                                <td>
                                    <?php if ($cita['estado'] != 'Cancelada'): ?>
                                        <form action="agenda_medico.php" method="POST" class="d-flex">
                                            <input type="hidden" name="id_cita" value="<?php echo $cita['id']; ?>">
                                            <input type="hidden" name="fecha_agenda" value="<?php echo $fecha_agenda; ?>">
                                            <select name="nuevo_estado_id" class="form-select form-select-sm me-2" required>
                                                <option value="">Cambiar a:</option>
                                                <?php foreach ($estados_permitidos as $est): ?>
                                                    <option value="<?php echo $est['id']; ?>" <?php if($cita['id_estado'] == $est['id']) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($est['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="cambiar_estado" class="btn btn-sm btn-outline-primary">Ok</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Cancelada por paciente</span>
                                    <?php endif; ?>
                                </td>

                                <!--registrar atención -->
                                <td>
                                    <?php if ($cita['estado'] == 'Atendida'): ?>
                                        <a href="registrar_atencion.php?id_cita=<?php echo $cita['id']; ?>" class="btn btn-sm btn-success fw-bold">Registrar Ficha Clínica</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="Debe marcar la cita como 'Atendida' primero">Registrar Ficha Clínica</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-3">
                No tienes citas agendadas para este día
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
