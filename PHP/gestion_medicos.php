<?php
session_start();
require_once 'conexion.php';

// solo el admin puede entrar
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 3) {
    header("Location: index.php");
    exit();
}

$mensaje = "";

// procesar formularios
// 1) aggregar medico
if (isset($_POST['agregar_medico'])) {
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre_completo']);
    $email = trim($_POST['email_institucional']);
    $contrasena_plana = $_POST['contrasena'];
    
    $especialidades_sel = isset($_POST['especialidades']) ? $_POST['especialidades'] : [];
    $centros_sel = isset($_POST['centros']) ? $_POST['centros'] : [];

    // validar cantidad de especializaciones y centros
    if (count($especialidades_sel) < 1 || count($especialidades_sel) > 3) {
        $mensaje = "<div class='alert alert-danger'>Debe seleccionar entre 1 y 3 especialidades.</div>";
    } elseif (count($centros_sel) < 1) {
        $mensaje = "<div class='alert alert-danger'>Debe seleccionar al menos 1 centro médico.</div>";
    } elseif (!preg_match('/^\d{7,8}-[\dkK]$/', $rut)) {
        $mensaje = "<div class='alert alert-danger'>El RUT no tiene el formato XXXXXXXX-X.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "<div class='alert alert-danger'>El correo electrónico no tiene un formato válido.</div>";
    } elseif (strlen($contrasena_plana) < 8 || !preg_match('/[A-Za-z]/', $contrasena_plana) || !preg_match('/\d/', $contrasena_plana)) {
        $mensaje = "<div class='alert alert-danger'>La contraseña debe tener al menos 8 caracteres, con letras y números.</div>";
    } else {
        // verificar si el RUT o correo ya existen
        $check = $pdo->prepare("SELECT 1 FROM Usuario WHERE email = ? UNION SELECT 1 FROM Medico WHERE rut = ? UNION SELECT 1 FROM Paciente WHERE rut = ?");
        $check->execute([$email, $rut, $rut]);
        if ($check->fetch()) {
            $mensaje = "<div class='alert alert-danger'>El RUT o Correo ya existen en el sistema.</div>";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO Usuario (email, password_hash, id_rol) VALUES (?, ?, 2)");
                $stmt->execute([$email, password_hash($contrasena_plana, PASSWORD_DEFAULT)]);
                $id_usuario_nuevo = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO Medico (id_usuario, rut, nombre_completo) VALUES (?, ?, ?)");
                $stmt->execute([$id_usuario_nuevo, $rut, $nombre]);
                $id_nuevo_medico = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO Medico_Especialidad (id_medico, id_especialidad) VALUES (?, ?)");
                foreach ($especialidades_sel as $id_esp) {
                    $stmt->execute([$id_nuevo_medico, $id_esp]);
                }

                $stmt = $pdo->prepare("INSERT INTO Medico_Centro (id_medico, id_centro) VALUES (?, ?)");
                foreach ($centros_sel as $id_cen) {
                    $stmt->execute([$id_nuevo_medico, $id_cen]);
                }

                $pdo->commit();
                $mensaje = "<div class='alert alert-success'>Médico creado exitosamente.</div>";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $mensaje = "<div class='alert alert-danger'>No se pudo crear el médico.</div>";
            }
        }
    }
}

// 2) eliminar medico
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    try {
        $stmt = $pdo->prepare("DELETE FROM Usuario WHERE id_usuario = (SELECT id_usuario FROM Medico WHERE id_medico = ?)");
        $stmt->execute([$id_eliminar]);
        $mensaje = "<div class='alert alert-success'>Médico eliminado correctamente.</div>";
    } catch (Exception $e) {
        $mensaje = "<div class='alert alert-danger'>No se puede eliminar al médico porque tiene historial de atenciones o citas.</div>";
    }
}

// 3) editar medico
if (isset($_POST['editar_medico'])) {
    $id_medico = $_POST['id_medico'];
    $nombre = trim($_POST['nombre_completo']);
    $email = trim($_POST['email_institucional']);
    
    $especialidades_sel = isset($_POST['especialidades']) ? $_POST['especialidades'] : [];
    $centros_sel = isset($_POST['centros']) ? $_POST['centros'] : [];

    $stmt = $pdo->prepare("SELECT id_usuario FROM Medico WHERE id_medico = ?");
    $stmt->execute([$id_medico]);
    $id_usuario_medico = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT 1 FROM Usuario WHERE email = ? AND id_usuario <> ?");
    $stmt->execute([$email, $id_usuario_medico]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "<div class='alert alert-danger'>El correo electrónico no tiene un formato válido.</div>";
    } elseif ($stmt->fetch()) {
        $mensaje = "<div class='alert alert-danger'>Ese correo ya está registrado por otro usuario.</div>";
    } elseif (count($especialidades_sel) < 1 || count($especialidades_sel) > 3 || count($centros_sel) < 1) {
        $mensaje = "<div class='alert alert-danger'>Debe seleccionar entre 1-3 especialidades y al menos 1 centro. No se guardó ningún cambio.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT id_especialidad FROM Medico_Especialidad WHERE id_medico = ?");
        $stmt->execute([$id_medico]);
        $esp_previas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("SELECT id_centro FROM Medico_Centro WHERE id_medico = ?");
        $stmt->execute([$id_medico]);
        $cen_previos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $esp_quitar = array_diff($esp_previas, $especialidades_sel);
        $cen_quitar = array_diff($cen_previos, $centros_sel);
        $esp_agregar = array_diff($especialidades_sel, $esp_previas);
        $cen_agregar = array_diff($centros_sel, $cen_previos);

        $bloqueo = "";
        $stmt_esp = $pdo->prepare("SELECT COUNT(*) FROM Cita WHERE id_medico = ? AND id_especialidad = ?");
        foreach ($esp_quitar as $id_esp) {
            $stmt_esp->execute([$id_medico, $id_esp]);
            if ($stmt_esp->fetchColumn() > 0) {
                $bloqueo = "No se puede quitar una especialidad que tiene citas asociadas al médico.";
            }
        }
        $stmt_cen = $pdo->prepare("SELECT COUNT(*) FROM Cita WHERE id_medico = ? AND id_centro = ?");
        foreach ($cen_quitar as $id_cen) {
            $stmt_cen->execute([$id_medico, $id_cen]);
            if ($stmt_cen->fetchColumn() > 0) {
                $bloqueo = "No se puede quitar un centro que tiene citas asociadas al médico.";
            }
        }

        if ($bloqueo !== "") {
            $mensaje = "<div class='alert alert-danger'>$bloqueo No se guardó ningún cambio.</div>";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE Medico SET nombre_completo = ? WHERE id_medico = ?");
                $stmt->execute([$nombre, $id_medico]);
                $stmt = $pdo->prepare("UPDATE Usuario SET email = ? WHERE id_usuario = ?");
                $stmt->execute([$email, $id_usuario_medico]);

                $stmt = $pdo->prepare("DELETE FROM Medico_Especialidad WHERE id_medico = ? AND id_especialidad = ?");
                foreach ($esp_quitar as $id_esp) {
                    $stmt->execute([$id_medico, $id_esp]);
                }
                $stmt = $pdo->prepare("DELETE FROM Medico_Centro WHERE id_medico = ? AND id_centro = ?");
                foreach ($cen_quitar as $id_cen) {
                    $stmt->execute([$id_medico, $id_cen]);
                }
                $stmt = $pdo->prepare("INSERT INTO Medico_Especialidad (id_medico, id_especialidad) VALUES (?, ?)");
                foreach ($esp_agregar as $id_esp) {
                    $stmt->execute([$id_medico, $id_esp]);
                }
                $stmt = $pdo->prepare("INSERT INTO Medico_Centro (id_medico, id_centro) VALUES (?, ?)");
                foreach ($cen_agregar as $id_cen) {
                    $stmt->execute([$id_medico, $id_cen]);
                }

                $pdo->commit();
                $mensaje = "<div class='alert alert-success'>Médico y asignaciones actualizados correctamente.</div>";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $mensaje = "<div class='alert alert-danger'>No se pudo actualizar el médico: " . htmlspecialchars(isset($e->errorInfo[2]) ? $e->errorInfo[2] : $e->getMessage()) . "</div>";
            }
        }
    }
}

//cargar datos
$catalogo_especialidades = $pdo->query("SELECT id_especialidad AS id, nombre FROM Especialidad")->fetchAll(PDO::FETCH_ASSOC);
$catalogo_centros = $pdo->query("SELECT id_centro AS id, nombre FROM CentroMedico")->fetchAll(PDO::FETCH_ASSOC);

// listado de médicos con sus centros y especialidades
$sql_lista = "SELECT m.id_medico AS id, m.rut, m.nombre_completo, 
                (SELECT GROUP_CONCAT(e.nombre SEPARATOR ', ') FROM Especialidad e JOIN Medico_Especialidad me ON e.id_especialidad = me.id_especialidad WHERE me.id_medico = m.id_medico) as especialidades,
                (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ') FROM CentroMedico c JOIN Medico_Centro mc ON c.id_centro = mc.id_centro WHERE mc.id_medico = m.id_medico) as centros
              FROM Medico m ORDER BY m.id_medico DESC";
$medicos = $pdo->query($sql_lista)->fetchAll(PDO::FETCH_ASSOC);

// si se aprieta editar, se cargan los datos del medico.
$medico_a_editar = null;
$esp_actuales = [];
$cen_actuales = [];

if (isset($_GET['editar'])) {
    $id_edit = $_GET['editar'];
    $stmt = $pdo->prepare("SELECT m.id_medico AS id, m.rut, m.nombre_completo, u.email AS email_institucional FROM Medico m JOIN Usuario u ON u.id_usuario = m.id_usuario WHERE m.id_medico = ?");
    $stmt->execute([$id_edit]);
    $medico_a_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT id_especialidad FROM Medico_Especialidad WHERE id_medico = ?");
    $stmt->execute([$id_edit]);
    $esp_actuales = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare("SELECT id_centro FROM Medico_Centro WHERE id_medico = ?");
    $stmt->execute([$id_edit]);
    $cen_actuales = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <h2>Gestión de Médicos</h2>
    <a href="index.php" class="btn btn-secondary mb-3">Volver al Panel</a>

    <?php echo $mensaje; ?>

    <div class="row">
        <!--ingreso/edición-->
        <div class="col-md-4">
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <?php echo $medico_a_editar ? 'Editar Médico' : 'Nuevo Médico'; ?>
                </div>
                <div class="card-body">
                    <form action="gestion_medicos.php" method="POST">
                        
                        <?php if($medico_a_editar): ?>
                            <input type="hidden" name="id_medico" value="<?php echo $medico_a_editar['id']; ?>">
                            <div class="mb-2">
                                <label class="small fw-bold">RUT</label>
                                <input type="text" class="form-control form-control-sm" value="<?php echo $medico_a_editar['rut']; ?>" disabled>
                            </div>
                        <?php else: ?>
                            <div class="mb-2">
                                <label class="small fw-bold">RUT (12345678-9)</label>
                                <input type="text" name="rut" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Contraseña Acceso</label>
                                <input type="password" name="contrasena" class="form-control form-control-sm" required>
                            </div>
                        <?php endif; ?>

                        <div class="mb-2">
                            <label class="small fw-bold">Nombre Completo</label>
                            <input type="text" name="nombre_completo" class="form-control form-control-sm" value="<?php echo $medico_a_editar ? $medico_a_editar['nombre_completo'] : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Correo Institucional</label>
                            <input type="email" name="email_institucional" class="form-control form-control-sm" value="<?php echo $medico_a_editar ? $medico_a_editar['email_institucional'] : ''; ?>" required>
                        </div>

                        <hr>
                        <h6 class="fw-bold text-primary">Especialidades (Mín 1, Máx 3)</h6>
                        <div class="mb-3" style="max-height: 120px; overflow-y: auto; border: 1px solid #ccc; padding: 5px;">
                            <?php foreach($catalogo_especialidades as $esp): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="especialidades[]" value="<?php echo $esp['id']; ?>" 
                                        <?php if(in_array($esp['id'], $esp_actuales)) echo 'checked'; ?>>
                                    <label class="form-check-label small"><?php echo $esp['nombre']; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h6 class="fw-bold text-primary">Centros (Al menos 1)</h6>
                        <div class="mb-3" style="max-height: 120px; overflow-y: auto; border: 1px solid #ccc; padding: 5px;">
                            <?php foreach($catalogo_centros as $cen): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="centros[]" value="<?php echo $cen['id']; ?>"
                                        <?php if(in_array($cen['id'], $cen_actuales)) echo 'checked'; ?>>
                                    <label class="form-check-label small"><?php echo $cen['nombre']; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if($medico_a_editar): ?>
                            <button type="submit" name="editar_medico" class="btn btn-warning w-100 mb-2">Actualizar Datos</button>
                            <a href="gestion_medicos.php" class="btn btn-outline-secondary w-100">Cancelar</a>
                        <?php else: ?>
                            <button type="submit" name="agregar_medico" class="btn btn-primary w-100">Registrar Médico</button>
                        <?php endif; ?>

                    </form>
                </div>
            </div>
        </div>

        <!--tabla de médicos-->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0" style="font-size: 0.9rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>Médico</th>
                                <th>RUT</th>
                                <th>Especialidades</th>
                                <th>Centros</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($medicos as $m): ?>
                                <tr>
                                    <td><?php echo $m['nombre_completo']; ?></td>
                                    <td><?php echo $m['rut']; ?></td>
                                    <td><?php echo $m['especialidades'] ? $m['especialidades'] : '<span class="text-danger">Ninguna</span>'; ?></td>
                                    <td><?php echo $m['centros'] ? $m['centros'] : '<span class="text-danger">Ninguno</span>'; ?></td>
                                    <td>
                                        <a href="gestion_medicos.php?editar=<?php echo $m['id']; ?>" class="btn btn-sm btn-warning mb-1">Editar</a>
                                        <a href="gestion_medicos.php?eliminar=<?php echo $m['id']; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('¿Seguro que desea eliminar a este médico?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>