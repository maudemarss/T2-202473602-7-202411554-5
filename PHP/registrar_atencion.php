<?php
session_start();
require_once 'conexion.php';

// validamos si es medico
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 2) {
    echo "No tienes permiso para ver esto.";
    exit();
}

$id_medico = $_SESSION['identificador_usuario'];
$id_cita = isset($_GET['id_cita']) ? $_GET['id_cita'] : 0;

$mensaje = "";
$mensaje_error = "";

// la cita debe existir y pertenecer al medico que registra la atencion
$stmt = $pdo->prepare("SELECT id_cita, id_estado FROM Cita WHERE id_cita = ? AND id_medico = ?");
$stmt->execute([$id_cita, $id_medico]);
$cita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
    echo "No tienes permiso para ver esto. La cita no existe o no te pertenece.";
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM Atencion WHERE id_cita = ?");
$stmt->execute([$id_cita]);
$atencion_previa = $stmt->fetch(PDO::FETCH_ASSOC);

$diag_sel = [];
$recetas_sel = [];
if ($atencion_previa) {
    $stmt = $pdo->prepare("SELECT codigo_cie10 FROM Atencion_Diagnostico WHERE id_atencion = ?");
    $stmt->execute([$atencion_previa['id_atencion']]);
    $diag_sel = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare("SELECT medicamento, dosis, dias_tratamiento FROM Receta WHERE id_atencion = ? ORDER BY id_receta");
    $stmt->execute([$atencion_previa['id_atencion']]);
    $recetas_sel = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$motivo_form = $atencion_previa ? $atencion_previa['motivo_consulta'] : '';
$obs_form = $atencion_previa ? $atencion_previa['observaciones'] : '';

// si el medico envio el formulario de guardar atencion, procesamos los datos
if (isset($_POST['guardar_atencion'])) {
    $motivo = trim($_POST['motivo_consulta']);
    $obs = trim($_POST['observaciones']);
    $motivo_form = $motivo;
    $obs_form = $obs;

    $diag_post = isset($_POST['diagnostico']) ? array_values(array_unique(array_filter($_POST['diagnostico'], function ($d) { return $d !== ''; }))) : [];
    $nuevo_codigo = strtoupper(trim(isset($_POST['nuevo_codigo']) ? $_POST['nuevo_codigo'] : ''));
    $nueva_desc = trim(isset($_POST['nueva_descripcion']) ? $_POST['nueva_descripcion'] : '');
    if ($nuevo_codigo !== '' && !in_array($nuevo_codigo, $diag_post)) {
        $diag_post[] = $nuevo_codigo;
    }
    $recetas_post = [];
    $meds = isset($_POST['med']) ? $_POST['med'] : [];
    foreach ($meds as $k => $m) {
        $m = trim($m);
        $dosis = isset($_POST['dosis'][$k]) ? trim($_POST['dosis'][$k]) : '';
        $dias = isset($_POST['dias'][$k]) ? trim($_POST['dias'][$k]) : '';
        if ($m === '' && $dosis === '' && $dias === '') {
            continue;
        }
        $recetas_post[] = ['medicamento' => $m, 'dosis' => $dosis, 'dias_tratamiento' => $dias];
    }
    $diag_sel = $diag_post;
    $recetas_sel = $recetas_post;

    if ($cita['id_estado'] != 3) {
        $mensaje_error = "Solo se puede registrar una atención si la cita está en estado Atendida.";
    } elseif ($motivo === '' || $obs === '') {
        $mensaje_error = "El motivo de consulta y las observaciones son obligatorios.";
    } elseif (($nuevo_codigo !== '' || $nueva_desc !== '') && ($nueva_desc === '' || !preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/', $nuevo_codigo))) {
        $mensaje_error = "Para crear un diagnóstico nuevo ingrese un código CIE-10 válido (ej: J06.9) y su descripción.";
    } elseif (count($diag_post) < 1) {
        $mensaje_error = "Debe ingresar al menos un diagnóstico.";
    } else {
        foreach ($recetas_post as $r) {
            if ($r['medicamento'] === '' || $r['dosis'] === '' || !ctype_digit($r['dias_tratamiento']) || (int)$r['dias_tratamiento'] < 1) {
                $mensaje_error = "Cada línea de receta debe tener medicamento, dosis y días de tratamiento (mayor a 0).";
            }
        }
    }

    if ($mensaje_error === "") {
        try {
            $pdo->beginTransaction();

            // 1)guardar la atencion
            if ($atencion_previa) {
                $id_atencion = $atencion_previa['id_atencion'];
                $stmt = $pdo->prepare("UPDATE Atencion SET motivo_consulta = ?, observaciones = ? WHERE id_atencion = ?");
                $stmt->execute([$motivo, $obs, $id_atencion]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO Atencion (id_cita, motivo_consulta, observaciones) VALUES (?, ?, ?)");
                $stmt->execute([$id_cita, $motivo, $obs]);
                $id_atencion = $pdo->lastInsertId();
            }

            // 2)guardar diagnosticos
            if ($nuevo_codigo !== '') {
                $stmt = $pdo->prepare("INSERT INTO Diagnostico (codigo_cie10, descripcion) VALUES (?, ?) ON DUPLICATE KEY UPDATE codigo_cie10 = codigo_cie10");
                $stmt->execute([$nuevo_codigo, $nueva_desc]);
            }
            $stmt = $pdo->prepare("DELETE FROM Atencion_Diagnostico WHERE id_atencion = ?");
            $stmt->execute([$id_atencion]);
            $stmt = $pdo->prepare("INSERT INTO Atencion_Diagnostico (id_atencion, codigo_cie10) VALUES (?, ?)");
            foreach ($diag_post as $codigo) {
                $stmt->execute([$id_atencion, $codigo]);
            }

            // 3) guardar receta 
            $stmt = $pdo->prepare("DELETE FROM Receta WHERE id_atencion = ?");
            $stmt->execute([$id_atencion]);
            $stmt = $pdo->prepare("INSERT INTO Receta (id_atencion, medicamento, dosis, dias_tratamiento) VALUES (?, ?, ?, ?)");
            foreach ($recetas_post as $r) {
                $stmt->execute([$id_atencion, $r['medicamento'], $r['dosis'], (int)$r['dias_tratamiento']]);
            }

            $pdo->commit();
            $atencion_previa = ['id_atencion' => $id_atencion];
            $mensaje = "Atención guardada correctamente.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje_error = isset($e->errorInfo[0]) && $e->errorInfo[0] == '45000' ? $e->errorInfo[2] : "No se pudo guardar la atención.";
        }
    }
}

// cargar datos para mostrar en pantalla
$diagnosticos = $pdo->query("SELECT * FROM Diagnostico ORDER BY codigo_cie10")->fetchAll(PDO::FETCH_ASSOC);

if (count($diag_sel) == 0) {
    $diag_sel = [''];
}
if (count($recetas_sel) == 0) {
    $recetas_sel = [['medicamento' => '', 'dosis' => '', 'dias_tratamiento' => '']];
}

include 'header.php';
?>

<div class="container mt-4">
    <h2>Ficha Clínica</h2>
    <p>Registrando atención para la cita N° <?php echo htmlspecialchars($id_cita); ?></p>
    
    <a href="agenda_medico.php" class="btn btn-secondary mb-3">Volver a la Agenda</a>

    <?php if($mensaje != ""): ?>
        <div class="alert alert-success"><?php echo $mensaje; ?></div>
    <?php endif; ?>
    <?php if($mensaje_error != ""): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="registrar_atencion.php?id_cita=<?php echo htmlspecialchars($id_cita); ?>" method="POST">
                
                <div class="mb-3">
                    <label>Motivo de Consulta:</label>
                    <textarea class="form-control" name="motivo_consulta" required><?php echo htmlspecialchars($motivo_form); ?></textarea>
                </div>

                <div class="mb-3">
                    <label>Observaciones:</label>
                    <textarea class="form-control" name="observaciones" required><?php echo htmlspecialchars($obs_form); ?></textarea>
                </div>

                <hr>
                <h4>Diagnósticos</h4>
                <p class="text-muted small">Agregue, cambie o quite los diagnósticos que necesite (mínimo 1).</p>
                <div id="lista_diagnosticos">
                    <?php foreach($diag_sel as $sel): ?>
                        <div class="row mb-2 fila-diagnostico">
                            <div class="col-10">
                                <select name="diagnostico[]" class="form-select">
                                    <option value="">Seleccione...</option>
                                    <?php foreach($diagnosticos as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d['codigo_cie10']); ?>" <?php if($sel == $d['codigo_cie10']) echo 'selected'; ?>><?php echo htmlspecialchars($d['codigo_cie10'] . ' - ' . $d['descripcion']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-outline-danger w-100 quitar-fila">Quitar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="agregar_diagnostico">+ Agregar diagnóstico</button>
                <div class="row mb-3">
                    <div class="col-3">
                        <input type="text" name="nuevo_codigo" class="form-control" placeholder="Nuevo código CIE-10" maxlength="10" value="<?php echo htmlspecialchars(isset($_POST['nuevo_codigo']) ? $_POST['nuevo_codigo'] : ''); ?>">
                    </div>
                    <div class="col-9">
                        <input type="text" name="nueva_descripcion" class="form-control" placeholder="Descripción del nuevo diagnóstico" maxlength="255" value="<?php echo htmlspecialchars(isset($_POST['nueva_descripcion']) ? $_POST['nueva_descripcion'] : ''); ?>">
                    </div>
                </div>

                <hr>
                <h4>Receta</h4>
                <p class="text-muted small">Agregue, cambie o quite las líneas de receta (puede dejarla vacía).</p>
                <div id="lista_recetas">
                    <?php foreach($recetas_sel as $r): ?>
                        <div class="row mb-2 fila-receta">
                            <div class="col-4">
                                <input type="text" name="med[]" class="form-control" placeholder="Medicamento" value="<?php echo htmlspecialchars($r['medicamento']); ?>">
                            </div>
                            <div class="col-3">
                                <input type="text" name="dosis[]" class="form-control" placeholder="Dosis" value="<?php echo htmlspecialchars($r['dosis']); ?>">
                            </div>
                            <div class="col-3">
                                <input type="number" min="1" name="dias[]" class="form-control" placeholder="Días" value="<?php echo htmlspecialchars($r['dias_tratamiento']); ?>">
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-outline-danger w-100 quitar-fila">Quitar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mb-4" id="agregar_receta">+ Agregar medicamento</button>

                <button type="submit" name="guardar_atencion" class="btn btn-primary w-100">Guardar Ficha</button>

            </form>
        </div>
    </div>
</div>

<script>
function clonarFila(contenedor, selector) {
    var filas = document.querySelectorAll(selector);
    var nueva = filas[0].cloneNode(true);
    nueva.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    nueva.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    document.getElementById(contenedor).appendChild(nueva);
}
document.getElementById('agregar_diagnostico').addEventListener('click', function () {
    clonarFila('lista_diagnosticos', '.fila-diagnostico');
});
document.getElementById('agregar_receta').addEventListener('click', function () {
    clonarFila('lista_recetas', '.fila-receta');
});
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('quitar-fila')) {
        var fila = e.target.closest('.row');
        var contenedor = fila.parentNode;
        if (contenedor.querySelectorAll('.row').length > 1) {
            fila.remove();
        } else {
            fila.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            fila.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
        }
    }
});
</script>

<?php include 'footer.php'; ?>
