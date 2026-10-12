<?php
session_start();
require_once 'conexion.php';

// validamos que sea medico  (rol 2)
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 2) {
    echo "Acceso denegado. Solo para médicos.";
    exit();
}

$atenciones = [];
$rut_buscado = "";
$mensaje_error = "";
$paciente_info = null;

// si el medico envio el formulario con un rut, se busca el paciente y sus atenciones
if (isset($_GET['rut_paciente'])) {
    $rut_buscado = trim($_GET['rut_paciente']);
    
    // busca si el paciente existe en la base de datos
    $sql_paciente = "SELECT id_paciente AS id, nombre_completo, fecha_nacimiento FROM Paciente WHERE rut = '$rut_buscado'";
    $res_paciente = $pdo->query($sql_paciente);
    $paciente_info = $res_paciente->fetch(PDO::FETCH_ASSOC);

    if ($paciente_info) {
        $id_paciente = $paciente_info['id'];
        
        // busca todas las atenciones del paciente
        $sql_atenciones = "SELECT a.id_atencion as id_atencion, a.motivo_consulta, a.observaciones, 
                                  c.fecha_hora, m.nombre_completo as medico, e.nombre as especialidad
                           FROM Atencion a
                           JOIN Cita c ON a.id_cita = c.id_cita
                           JOIN Medico m ON c.id_medico = m.id_medico
                           JOIN Especialidad e ON c.id_especialidad = e.id_especialidad
                           WHERE c.id_paciente = $id_paciente
                           ORDER BY c.fecha_hora DESC";

        $atenciones = $pdo->query($sql_atenciones)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $mensaje_error = "No se encontró ningún paciente con el RUT ingresado.";
    }
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <h2>Consultar Historial Clínico</h2>
    <a href="index.php" class="btn btn-secondary mb-4">Volver al Inicio</a>

    <!--formulario de busqueda-->
    <div class="card mb-4 shadow-sm">
        <div class="card-body bg-light">
            <form action="buscar_historial.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-9">
                    <label for="rut_paciente" class="form-label fw-bold">Ingrese RUT del Paciente (Ej: 12345678-9):</label>
                    <input type="text" class="form-control" name="rut_paciente" id="rut_paciente" value="<?php echo $rut_buscado; ?>" required>
                </div>
                <div class="col-md-3 mt-auto">
                    <button type="submit" class="btn btn-primary w-100">Buscar Historial</button>
                </div>
            </form>
        </div>
    </div>

    <?php if($mensaje_error != ""): ?>
        <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
    <?php endif; ?>

    <!-- resultados de la busqueda anterior -->
    <?php if($paciente_info): ?>
        <div class="alert alert-success">
            <strong>Paciente Encontrado:</strong> <?php echo $paciente_info['nombre_completo']; ?> | 
            <strong>Fecha Nacimiento:</strong> <?php echo date('d-m-Y', strtotime($paciente_info['fecha_nacimiento'])); ?>
        </div>

        <?php if(count($atenciones) == 0): ?>
            <div class="alert alert-info">Este paciente no tiene atenciones médicas registradas.</div>
        <?php else: ?>
            
            <h4>Fichas Anteriores</h4>
            <?php foreach($atenciones as $atencion): ?>
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <strong>Fecha:</strong> <?php echo date('d-m-Y H:i', strtotime($atencion['fecha_hora'])); ?> | 
                        <strong>Atendido por:</strong> <?php echo $atencion['medico']; ?> (<?php echo $atencion['especialidad']; ?>)
                    </div>
                    <div class="card-body">
                        <p><strong>Motivo de Consulta:</strong> <?php echo $atencion['motivo_consulta']; ?></p>
                        <p><strong>Observaciones:</strong> <?php echo $atencion['observaciones']; ?></p>
                        
                        <hr>
                        
                        <div class="row">
                            <!-- diagnósticos -->
                            <div class="col-md-6">
                                <h5>Diagnósticos Registrados</h5>
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
                                            echo "<li>No hay diagnósticos.</li>";
                                        }
                                    ?>
                                </ul>
                            </div>
                            
                            <!-- recetas -->
                            <div class="col-md-6">
                                <h5>Recetas Prescritas</h5>
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
                                            echo "<li>No hay recetas.</li>";
                                        }
                                    ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>