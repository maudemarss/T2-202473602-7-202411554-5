<?php
session_start();
require_once 'conexion.php';

// validamos si es medico
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 2) {
    echo "No tienes permiso para ver esto.";
    exit();
}

$id_medico = $_SESSION['identificador_usuario'];
$id_cita = $_GET['id_cita'];

$mensaje = "";

// si el medico envio el formulario de guardar atencion, procesamos los datos
if (isset($_POST['guardar_atencion'])) {
    $motivo = $_POST['motivo_consulta'];
    $obs = $_POST['observaciones'];

    // 1)guardar la atencion
    $sql_check = "SELECT id FROM Atencion WHERE id_cita = '$id_cita'";
    $res_check = $pdo->query($sql_check);
    $existe = $res_check->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        //actualizar
        $id_atencion = $existe['id'];
        $sql_upd = "UPDATE Atencion SET motivo_consulta = '$motivo', observaciones = '$obs' WHERE id = $id_atencion";
        $pdo->query($sql_upd);
    } else {
        //insertar nueva
        $sql_ins = "INSERT INTO Atencion (id_cita, motivo_consulta, observaciones) VALUES ('$id_cita', '$motivo', '$obs')";
        $pdo->query($sql_ins);
        $id_atencion = $pdo->lastInsertId();
    }

    // 2)guardar diagnosticos
    $pdo->query("DELETE FROM Atencion_Diagnostico WHERE id_atencion = $id_atencion");
    
    $diag1 = $_POST['diagnostico1'];
    $diag2 = $_POST['diagnostico2'];

    if ($diag1 != "") {
        $pdo->query("INSERT INTO Atencion_Diagnostico (id_atencion, codigo_cie10) VALUES ($id_atencion, '$diag1')");
    }
    if ($diag2 != "") {
        $pdo->query("INSERT INTO Atencion_Diagnostico (id_atencion, codigo_cie10) VALUES ($id_atencion, '$diag2')");
    }

    // 3) guardar receta 
    $pdo->query("DELETE FROM Receta WHERE id_atencion = $id_atencion");

    $med1 = $_POST['med1'];
    $dosis1 = $_POST['dosis1'];
    $dias1 = $_POST['dias1'];

    $med2 = $_POST['med2'];
    $dosis2 = $_POST['dosis2'];
    $dias2 = $_POST['dias2'];

    if ($med1 != "") {
        $pdo->query("INSERT INTO Receta (id_atencion, medicamento, dosis, dias_tratamiento) VALUES ($id_atencion, '$med1', '$dosis1', $dias1)");
    }
    if ($med2 != "") {
        $pdo->query("INSERT INTO Receta (id_atencion, medicamento, dosis, dias_tratamiento) VALUES ($id_atencion, '$med2', '$dosis2', $dias2)");
    }

    $mensaje = "Atención guardada correctamente.";
}

// cargar datos para mostrar en pantalla
$diagnosticos = $pdo->query("SELECT * FROM Diagnostico")->fetchAll(PDO::FETCH_ASSOC);

//cargar la atencion si ya existia
$atencion_previa = $pdo->query("SELECT * FROM Atencion WHERE id_cita = '$id_cita'")->fetch(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="container mt-4">
    <h2>Ficha Clínica</h2>
    <p>Registrando atención para la cita N° <?php echo $id_cita; ?></p>
    
    <a href="agenda_medico.php" class="btn btn-secondary mb-3">Volver a la Agenda</a>

    <?php if($mensaje != ""): ?>
        <div class="alert alert-success"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="registrar_atencion.php?id_cita=<?php echo $id_cita; ?>" method="POST">
                
                <div class="mb-3">
                    <label>Motivo de Consulta:</label>
                    <textarea class="form-control" name="motivo_consulta" required><?php if($atencion_previa) echo $atencion_previa['motivo_consulta']; ?></textarea>
                </div>

                <div class="mb-3">
                    <label>Observaciones:</label>
                    <textarea class="form-control" name="observaciones" required><?php if($atencion_previa) echo $atencion_previa['observaciones']; ?></textarea>
                </div>

                <hr>
                <h4>Diagnósticos</h4>
                <p class="text-muted small">Puede ingresar hasta 2 diagnósticos.</p>
                <div class="row mb-3">
                    <div class="col-6">
                        <label>Diagnóstico 1:</label>
                        <select name="diagnostico1" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($diagnosticos as $d): ?>
                                <option value="<?php echo $d['codigo_cie10']; ?>"><?php echo $d['descripcion']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label>Diagnóstico 2 (Opcional):</label>
                        <select name="diagnostico2" class="form-select">
                            <option value="">Ninguno</option>
                            <?php foreach($diagnosticos as $d): ?>
                                <option value="<?php echo $d['codigo_cie10']; ?>"><?php echo $d['descripcion']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr>
                <h4>Receta</h4>
                <p class="text-muted small">Puede recetar hasta 2 medicamentos.</p>
                
                <!--medicamento 1 -->
                <div class="row mb-2">
                    <div class="col-4">
                        <input type="text" name="med1" class="form-control" placeholder="Medicamento 1">
                    </div>
                    <div class="col-4">
                        <input type="text" name="dosis1" class="form-control" placeholder="Dosis">
                    </div>
                    <div class="col-4">
                        <input type="number" name="dias1" class="form-control" placeholder="Días">
                    </div>
                </div>

                <!--medicamento 2 -->
                <div class="row mb-4">
                    <div class="col-4">
                        <input type="text" name="med2" class="form-control" placeholder="Medicamento 2 (Opcional)">
                    </div>
                    <div class="col-4">
                        <input type="text" name="dosis2" class="form-control" placeholder="Dosis">
                    </div>
                    <div class="col-4">
                        <input type="number" name="dias2" class="form-control" placeholder="Días">
                    </div>
                </div>

                <button type="submit" name="guardar_atencion" class="btn btn-primary w-100">Guardar Ficha</button>

            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>