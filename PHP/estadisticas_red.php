<?php
session_start();
require_once 'conexion.php';

//validar si es admin (rol 3)
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 3) {
    header("Location: index.php");
    exit();
}

$total_citas = 0;
$inasistencias = [];
$top_diagnosticos = [];

try {
    // obtener el total de citas
    $stmt_total = $pdo->query("SELECT COUNT(*) AS total FROM Cita");
    $total_citas = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

    // obtener el porcentaje de inasistencia por centro
    $stmt_inasistencia = $pdo->query("SELECT cm.nombre AS centro, COUNT(c.id_cita) AS total_citas,
                                             COALESCE(SUM(c.id_estado = 4), 0) AS total_inasistencias,
                                             fn_pct_inasistencia(cm.id_centro) AS porcentaje_inasistencia
                                      FROM CentroMedico cm
                                      LEFT JOIN Cita c ON c.id_centro = cm.id_centro
                                      GROUP BY cm.id_centro, cm.nombre
                                      ORDER BY cm.nombre");
    $inasistencias = $stmt_inasistencia->fetchAll(PDO::FETCH_ASSOC);

    // los 5 diagnosticos mas frecuentes
    $sql_top_diag = "SELECT d.descripcion, COUNT(ad.codigo_cie10) as cantidad 
                     FROM Atencion_Diagnostico ad
                     JOIN Diagnostico d ON ad.codigo_cie10 = d.codigo_cie10
                     GROUP BY ad.codigo_cie10, d.descripcion
                     ORDER BY cantidad DESC 
                     LIMIT 5";
    $stmt_top = $pdo->query($sql_top_diag);
    $top_diagnosticos = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_bd = "Error al cargar el panel: " . $e->getMessage();
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <h2>Panel de Gestión: Estadísticas de la Red</h2>
    <a href="index.php" class="btn btn-secondary mb-4">Volver al Panel</a>

    <?php if(isset($error_bd)): ?>
        <div class="alert alert-danger fw-bold">
            <?php echo $error_bd; ?>
            <br><small>Sigue las instrucciones para ejecutar el script SQL final en tu phpMyAdmin.</small>
        </div>
    <?php else: ?>

        <div class="row">
            <!--total citas -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-primary h-100">
                    <div class="card-header bg-primary text-white text-center fw-bold">
                        Total de Citas
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <h1 class="display-1 text-primary mb-0"><?php echo $total_citas; ?></h1>
                    </div>
                </div>
            </div>

            <!-- top 5 diagnosticos -->
            <div class="col-md-8 mb-4">
                <div class="card shadow-sm border-danger h-100">
                    <div class="card-header bg-danger text-white fw-bold">
                        Top 5 diagnosticos mas frecuentes
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Diagnóstico (CIE-10)</th>
                                    <th>Cantidad de Casos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($top_diagnosticos) > 0): ?>
                                    <?php $contador = 1; foreach($top_diagnosticos as $diag): ?>
                                        <tr>
                                            <td><strong><?php echo $contador++; ?></strong></td>
                                            <td><?php echo $diag['descripcion']; ?></td>
                                            <td><span class="badge bg-danger"><?php echo $diag['cantidad']; ?> casos</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center">Aún no hay diagnósticos registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- inasistencias por centro -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark fw-bold">
                        Porcentaje de inasistencia por centro médico
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0 text-center">
                            <thead class="table-dark">
                                <tr>
                                    <th>Centro Médico</th>
                                    <th>Total Citas (Históricas)</th>
                                    <th>Citas "No Asistió"</th>
                                    <th>Tasa de Inasistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($inasistencias) > 0): ?>
                                    <?php foreach($inasistencias as $ina): ?>
                                        <tr>
                                            <td class="text-start fw-bold"><?php echo $ina['centro']; ?></td>
                                            <td><?php echo $ina['total_citas']; ?></td>
                                            <td><?php echo $ina['total_inasistencias']; ?></td>
                                            <td>
                                                <?php 
                                                    // si el porcentaje es muy alto se le cambia el color
                                                    $porcentaje = $ina['porcentaje_inasistencia']; 
                                                    $color = 'success';
                                                    if ($porcentaje > 15) $color = 'warning text-dark';
                                                    if ($porcentaje > 30) $color = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?> fs-6">
                                                    <?php echo $porcentaje; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">No hay datos suficientes para calcular inasistencias.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>