<?php
session_start();
require_once 'conexion.php';

// validar que solo entre el paciente (rol 1)
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 1) {
    header("Location: index.php");
    exit();
}

$id_paciente = $_SESSION['identificador_usuario'];
$mensaje_exito = "";
$mensaje_error = "";

// para cancelar una cita
if (isset($_GET['cancelar_id'])) {
    $id_cita_cancelar = $_GET['cancelar_id'];
    
    try {
        // obtenemos el id del estado cancelada
        $consulta_estado_cancelada = $pdo->query("SELECT id FROM EstadoCita WHERE nombre = 'Cancelada'");
        $estado_cancelada = $consulta_estado_cancelada->fetch(PDO::FETCH_ASSOC)['id'];

        // obtenemos los ids de reservada y confirmada.
        $consulta_estados_validos = $pdo->query("SELECT id FROM EstadoCita WHERE nombre IN ('Reservada', 'Confirmada')");
        $estados_validos = $consulta_estados_validos->fetchAll(PDO::FETCH_COLUMN);

        if ($estado_cancelada && count($estados_validos) > 0) {
            // actualizamos la cita a cancelada solo si pertenece al paciente
            $sql_cancelar = "UPDATE Cita 
                             SET id_estado_cita = :id_cancelada 
                             WHERE id = :id_cita 
                               AND id_paciente = :id_paciente 
                               AND fecha_hora > NOW() 
                               AND id_estado_cita IN (" . implode(',', $estados_validos) . ")";
            
            $sentencia_cancelar = $pdo->prepare($sql_cancelar);
            $sentencia_cancelar->execute([
                ':id_cancelada' => $estado_cancelada,
                ':id_cita' => $id_cita_cancelar,
                ':id_paciente' => $id_paciente
            ]);

            if ($sentencia_cancelar->rowCount() > 0) {
                $mensaje_exito = "La cita ha sido cancelada exitosamente.";
            } else {
                $mensaje_error = "No se pudo cancelar la cita. Es posible que la fecha ya haya pasado o no tenga el estado permitido.";
            }
        }
    } catch (PDOException $e) {
        $mensaje_error = "Error al intentar cancelar la cita.";
    }
}

// obtener las citas
try {
    // 1) citas próximas, fecha >= hoy
    $sql_proximas = "SELECT c.id, c.fecha_hora, m.nombre_completo AS medico, e.nombre AS especialidad, 
                            cen.nombre AS centro, est.nombre AS estado 
                     FROM Cita c
                     JOIN Medico m ON c.id_medico = m.id
                     JOIN Especialidad e ON c.id_especialidad = e.id
                     JOIN CentroMedico cen ON c.id_centro = cen.id
                     JOIN EstadoCita est ON c.id_estado_cita = est.id
                     WHERE c.id_paciente = :id_paciente AND c.fecha_hora >= NOW()
                     ORDER BY c.fecha_hora ASC";
    
    $sentencia_proximas = $pdo->prepare($sql_proximas);
    $sentencia_proximas->execute([':id_paciente' => $id_paciente]);
    $citas_proximas = $sentencia_proximas->fetchAll(PDO::FETCH_ASSOC);

    // 2) citas históricas, fecha < hoy
    $sql_historicas = "SELECT c.id, c.fecha_hora, m.nombre_completo AS medico, e.nombre AS especialidad, 
                              cen.nombre AS centro, est.nombre AS estado 
                       FROM Cita c
                       JOIN Medico m ON c.id_medico = m.id
                       JOIN Especialidad e ON c.id_especialidad = e.id
                       JOIN CentroMedico cen ON c.id_centro = cen.id
                       JOIN EstadoCita est ON c.id_estado_cita = est.id
                       WHERE c.id_paciente = :id_paciente AND c.fecha_hora < NOW()
                       ORDER BY c.fecha_hora DESC";
    
    $sentencia_historicas = $pdo->prepare($sql_historicas);
    $sentencia_historicas->execute([':id_paciente' => $id_paciente]);
    $citas_historicas = $sentencia_historicas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al cargar las citas: " . $e->getMessage());
}

include 'header.php';
?>

<div class="row mt-3 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>Mis Citas Médicas</h2>
        <a href="agendar_cita.php" class="btn btn-success">Agendar Nueva Hora</a>
    </div>
</div>

<?php if(!empty($mensaje_exito)): ?>
    <div class="alert alert-success"><?php echo $mensaje_exito; ?></div>
<?php endif; ?>
<?php if(!empty($mensaje_error)): ?>
    <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
<?php endif; ?>

<!-- citas próximas -->
<div class="card border-primary mb-5 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Próximas Citas</h5>
    </div>
    <div class="card-body">
        <?php if (count($citas_proximas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Médico</th>
                            <th>Especialidad</th>
                            <th>Centro Médico</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas_proximas as $cita): ?>
                            <tr>
                                <td><strong><?php echo date('d-m-Y H:i', strtotime($cita['fecha_hora'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($cita['medico']); ?></td>
                                <td><?php echo htmlspecialchars($cita['especialidad']); ?></td>
                                <td><?php echo htmlspecialchars($cita['centro']); ?></td>
                                <td>
                                    <?php 
                                        $color_badge = 'bg-secondary';
                                        if ($cita['estado'] == 'Reservada' || $cita['estado'] == 'Confirmada') $color_badge = 'bg-info text-dark';
                                        if ($cita['estado'] == 'Cancelada') $color_badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $color_badge; ?>"><?php echo htmlspecialchars($cita['estado']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    // solo modificable si es reservada o confirmada
                                    if ($cita['estado'] == 'Reservada' || $cita['estado'] == 'Confirmada'): ?>
                                        <a href="reprogramar_cita.php?id=<?php echo $cita['id']; ?>" class="btn btn-sm btn-warning">Reprogramar</a>
                                        <a href="mis_citas.php?cancelar_id=<?php echo $cita['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro que desea cancelar esta cita?');">Cancelar</a>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center mb-0">No tienes citas médicas próximas agendadas.</p>
        <?php endif; ?>
    </div>
</div>

<!-- citas históricas -->
<div class="card border-secondary shadow-sm mb-5">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0">Historial de Citas</h5>
    </div>
    <div class="card-body">
        <?php if (count($citas_historicas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle text-muted">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Médico</th>
                            <th>Especialidad</th>
                            <th>Centro Médico</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas_historicas as $cita): ?>
                            <tr>
                                <td><?php echo date('d-m-Y H:i', strtotime($cita['fecha_hora'])); ?></td>
                                <td><?php echo htmlspecialchars($cita['medico']); ?></td>
                                <td><?php echo htmlspecialchars($cita['especialidad']); ?></td>
                                <td><?php echo htmlspecialchars($cita['centro']); ?></td>
                                <td>
                                    <?php 
                                        $color_badge = 'bg-secondary';
                                        if ($cita['estado'] == 'Atendida') $color_badge = 'bg-success';
                                        if ($cita['estado'] == 'No Asistió' || $cita['estado'] == 'Cancelada') $color_badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $color_badge; ?>"><?php echo htmlspecialchars($cita['estado']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center mb-0">No tienes citas históricas registradas.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>