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
    $rut = $_POST['rut'];
    $nombre = $_POST['nombre_completo'];
    $email = $_POST['email_institucional'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    
    $especialidades_sel = isset($_POST['especialidades']) ? $_POST['especialidades'] : [];
    $centros_sel = isset($_POST['centros']) ? $_POST['centros'] : [];

    // validar cantidad de especializaciones y centros
    if (count($especialidades_sel) < 1 || count($especialidades_sel) > 3) {
        $mensaje = "<div class='alert alert-danger'>Debe seleccionar entre 1 y 3 especialidades.</div>";
    } elseif (count($centros_sel) < 1) {
        $mensaje = "<div class='alert alert-danger'>Debe seleccionar al menos 1 centro médico.</div>";
    } else {
        // verificar si el RUT o correo ya existen
        $check = $pdo->query("SELECT id FROM Medico WHERE rut = '$rut' OR email_institucional = '$email'")->fetch();
        if ($check) {
            $mensaje = "<div class='alert alert-danger'>El RUT o Correo ya existen en el sistema.</div>";
        } else {
            // insertar médico
            $sql = "INSERT INTO Medico (rut, nombre_completo, email_institucional, contrasena) 
                    VALUES ('$rut', '$nombre', '$email', '$contrasena')";
            $pdo->query($sql);
            $id_nuevo_medico = $pdo->lastInsertId();

            // insertar especialidades
            foreach ($especialidades_sel as $id_esp) {
                $pdo->query("INSERT INTO Medico_Especialidad (id_medico, id_especialidad) VALUES ($id_nuevo_medico, $id_esp)");
            }

            // insertar centros
            foreach ($centros_sel as $id_cen) {
                $pdo->query("INSERT INTO Medico_Centro (id_medico, id_centro) VALUES ($id_nuevo_medico, $id_cen)");
            }

            $mensaje = "<div class='alert alert-success'>Médico creado exitosamente.</div>";
        }
    }
}

// 2) eliminar medico
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    try {
        $pdo->query("DELETE FROM Medico WHERE id = $id_eliminar");
        $mensaje = "<div class='alert alert-success'>Médico eliminado correctamente.</div>";
    } catch (Exception $e) {
        $mensaje = "<div class='alert alert-danger'>No se puede eliminar al médico porque tiene historial de atenciones o citas.</div>";
    }
}

// 3) editar medico
if (isset($_POST['editar_medico'])) {
    $id_medico = $_POST['id_medico'];
    $nombre = $_POST['nombre_completo'];
    $email = $_POST['email_institucional'];
    
    $especialidades_sel = isset($_POST['especialidades']) ? $_POST['especialidades'] : [];
    $centros_sel = isset($_POST['centros']) ? $_POST['centros'] : [];

    // actualizar datos básicos
    $pdo->query("UPDATE Medico SET nombre_completo = '$nombre', email_institucional = '$email' WHERE id = $id_medico");

    // verificar si tiene citas a futuro
    $citas_futuras = $pdo->query("SELECT COUNT(*) FROM Cita WHERE id_medico = $id_medico AND fecha_hora > NOW()")->fetchColumn();

    if ($citas_futuras > 0) {
        $mensaje = "<div class='alert alert-warning'>Datos actualizados. <strong>Nota:</strong> Las especialidades y centros no fueron modificados porque el médico tiene $citas_futuras cita(s) futura(s) agendada(s).</div>";
    } else {
        // si no tiene, validamos especialidades y centros
        if (count($especialidades_sel) >= 1 && count($especialidades_sel) <= 3 && count($centros_sel) >= 1) {
            
            $pdo->query("DELETE FROM Medico_Especialidad WHERE id_medico = $id_medico");
            $pdo->query("DELETE FROM Medico_Centro WHERE id_medico = $id_medico");
            foreach ($especialidades_sel as $id_esp) {
                $pdo->query("INSERT INTO Medico_Especialidad (id_medico, id_especialidad) VALUES ($id_medico, $id_esp)");
            }
            foreach ($centros_sel as $id_cen) {
                $pdo->query("INSERT INTO Medico_Centro (id_medico, id_centro) VALUES ($id_medico, $id_cen)");
            }
            $mensaje = "<div class='alert alert-success'>Médico y asignaciones actualizados correctamente.</div>";
            
        } else {
            $mensaje = "<div class='alert alert-danger'>Los datos básicos se actualizaron, pero debe seleccionar entre 1-3 especialidades y al menos 1 centro.</div>";
        }
    }
}

//cargar datos
$catalogo_especialidades = $pdo->query("SELECT * FROM Especialidad")->fetchAll(PDO::FETCH_ASSOC);
$catalogo_centros = $pdo->query("SELECT * FROM CentroMedico")->fetchAll(PDO::FETCH_ASSOC);

// listado de médicos con sus centros y especialidades
$sql_lista = "SELECT m.*, 
                (SELECT GROUP_CONCAT(e.nombre SEPARATOR ', ') FROM Especialidad e JOIN Medico_Especialidad me ON e.id = me.id_especialidad WHERE me.id_medico = m.id) as especialidades,
                (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ') FROM CentroMedico c JOIN Medico_Centro mc ON c.id = mc.id_centro WHERE mc.id_medico = m.id) as centros
              FROM Medico m ORDER BY m.id DESC";
$medicos = $pdo->query($sql_lista)->fetchAll(PDO::FETCH_ASSOC);

// si se aprieta editar, se cargan los datos del medico.
$medico_a_editar = null;
$esp_actuales = [];
$cen_actuales = [];

if (isset($_GET['editar'])) {
    $id_edit = $_GET['editar'];
    $medico_a_editar = $pdo->query("SELECT * FROM Medico WHERE id = $id_edit")->fetch(PDO::FETCH_ASSOC);
    $esp_actuales = $pdo->query("SELECT id_especialidad FROM Medico_Especialidad WHERE id_medico = $id_edit")->fetchAll(PDO::FETCH_COLUMN);
    $cen_actuales = $pdo->query("SELECT id_centro FROM Medico_Centro WHERE id_medico = $id_edit")->fetchAll(PDO::FETCH_COLUMN);
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