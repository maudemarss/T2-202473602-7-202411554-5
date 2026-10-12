<?php
session_start();
require_once 'conexion.php';

// validar que sea paciente
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 1) {
    echo "Acceso denegado. Solo pacientes.";
    exit();
}

$id_paciente = $_SESSION['identificador_usuario'];

// buscar todas las atenciones del paciente
$sql_atenciones = "SELECT a.id as id_atencion, a.motivo_consulta, a.observaciones, 
                          c.fecha_hora, m.nombre_completo as medico, e.nombre as especialidad
                   FROM Atencion a
                   JOIN Cita c ON a.id_cita = c.id
                   JOIN Medico m ON c.id_medico = m.id
                   JOIN Especialidad e ON c.id_especialidad = e.id
                   WHERE c.id_paciente = '$id_paciente'
                   ORDER BY c.fecha_hora DESC";

$atenciones = $pdo->query($sql_atenciones)->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <h2>Mi Historial Clínico</h2>
    <a href="index.php" class="btn btn-secondary mb-4">Volver al Inicio</a>

    <?php if(count($atenciones) == 0): ?>
        <div class="alert alert-info">No tienes atenciones médicas registradas en tu historial.</div>
    <?php else: ?>
        
        <!-- recorremos cada atención encontrada-->
        <?php foreach($atenciones as $atencion): ?>
            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-white">
                    <strong>Fecha:</strong> <?php echo date('d-m-Y H:i', strtotime($atencion['fecha_hora'])); ?> | 
                    <strong>Especialidad:</strong> <?php echo $atencion['especialidad']; ?> | 
                    <strong>Médico:</strong> <?php echo $atencion['medico']; ?>
                </div>
                <div class="card-body">
                    <p><strong>Motivo de Consulta:</strong> <?php echo $atencion['motivo_consulta']; ?></p>
                    <p><strong>Observaciones:</strong> <?php echo $atencion['observaciones']; ?></p>
                    
                    <hr>
                    
                    <div class="row">
                        <!-- diagnósticos-->
                        <div class="col-md-6">
                            <h5>Diagnósticos</h5>
                            <ul>
                                <?php
                                    $id_atencion = $atencion['id_atencion'];
                                    $sql_diag = "SELECT d.codigo_cie10, d.descripcion 
                                                 FROM Atencion_Diagnostico ad
                                                 JOIN Diagnostico d ON ad.codigo_cie10 = d.codigo_cie10
                                                 WHERE ad.id_atencion = $id_atencion";
                                    $diagnosticos = $pdo->query($sql_diag)->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if(count($diagnosticos) > 0){
                                        foreach($diagnosticos as $d) {
                                            echo "<li>[".$d['codigo_cie10']."] ".$d['descripcion']."</li>";
                                        }
                                    } else {
                                        echo "<li>No hay diagnósticos registrados.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                        
                        <!--recetas -->
                        <div class="col-md-6">
                            <h5>Recetas Médicas</h5>
                            <ul>
                                <?php
                                    $sql_receta = "SELECT medicamento, dosis, dias_tratamiento 
                                                   FROM Receta 
                                                   WHERE id_atencion = $id_atencion";
                                    $recetas = $pdo->query($sql_receta)->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if(count($recetas) > 0){
                                        foreach($recetas as $r) {
                                            echo "<li><strong>".$r['medicamento'].":</strong> ".$r['dosis']." (por ".$r['dias_tratamiento']." días)</li>";
                                        }
                                    } else {
                                        echo "<li>No se emitieron recetas.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>