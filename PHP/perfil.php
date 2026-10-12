<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['identificador_usuario'])) {
    header("Location: login.php?error=2");
    exit();
}

$rol = $_SESSION['rol_usuario'];
$id_usuario = $_SESSION['id_usuario'];
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_cuenta']) && $rol == 1) {
    $stmt = $pdo->prepare("DELETE FROM Usuario WHERE id_usuario = :id_usuario");
    $stmt->execute([':id_usuario' => $id_usuario]);
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_perfil'])) {
    $email = trim($_POST['email']);
    $nueva_contrasena = $_POST['nueva_contrasena'];
    $nombre = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : '';
    $error = "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no tiene un formato válido.";
    } elseif ($nueva_contrasena !== '' && (strlen($nueva_contrasena) < 8 || !preg_match('/[A-Za-z]/', $nueva_contrasena) || !preg_match('/\d/', $nueva_contrasena))) {
        $error = "La contraseña debe tener al menos 8 caracteres, con letras y números.";
    } elseif ($rol != 3 && $nombre === '') {
        $error = "El nombre completo es obligatorio.";
    }

    if ($rol == 1 && $error === "") {
        $fecha = $_POST['fecha_nacimiento'];
        $fecha_valida = DateTime::createFromFormat('Y-m-d', $fecha);
        if (trim($_POST['comuna_residencia']) === '' || $_POST['id_prevision'] === '' || !in_array($_POST['sexo'], ['M', 'F', 'Otro'])) {
            $error = "Todos los campos obligatorios deben estar completos.";
        } elseif (!$fecha_valida || $fecha_valida->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) {
            $error = "La fecha de nacimiento no es válida.";
        }
    }

    if ($error === "") {
        $stmt = $pdo->prepare("SELECT id_usuario FROM Usuario WHERE email = :email AND id_usuario <> :id_usuario");
        $stmt->execute([':email' => $email, ':id_usuario' => $id_usuario]);
        if ($stmt->fetch()) {
            $error = "Ese correo electrónico ya está registrado por otro usuario.";
        }
    }

    if ($error === "") {
        try {
            $pdo->beginTransaction();

            if ($nueva_contrasena !== '') {
                $stmt = $pdo->prepare("UPDATE Usuario SET email = :email, password_hash = :hash WHERE id_usuario = :id_usuario");
                $stmt->execute([':email' => $email, ':hash' => password_hash($nueva_contrasena, PASSWORD_DEFAULT), ':id_usuario' => $id_usuario]);
            } else {
                $stmt = $pdo->prepare("UPDATE Usuario SET email = :email WHERE id_usuario = :id_usuario");
                $stmt->execute([':email' => $email, ':id_usuario' => $id_usuario]);
            }

            if ($rol == 1) {
                $telefono = trim($_POST['telefono']);
                $stmt = $pdo->prepare("UPDATE Paciente SET nombre_completo = :nombre, fecha_nacimiento = :fecha, sexo = :sexo,
                                       id_prevision = :prevision, telefono = :telefono, comuna_residencia = :comuna
                                       WHERE id_usuario = :id_usuario");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':fecha' => $_POST['fecha_nacimiento'],
                    ':sexo' => $_POST['sexo'],
                    ':prevision' => $_POST['id_prevision'],
                    ':telefono' => $telefono === '' ? null : $telefono,
                    ':comuna' => trim($_POST['comuna_residencia']),
                    ':id_usuario' => $id_usuario
                ]);
            } elseif ($rol == 2) {
                $stmt = $pdo->prepare("UPDATE Medico SET nombre_completo = :nombre WHERE id_usuario = :id_usuario");
                $stmt->execute([':nombre' => $nombre, ':id_usuario' => $id_usuario]);
            }

            $pdo->commit();
            if ($rol != 3) {
                $_SESSION['nombre_usuario'] = $nombre;
            }
            $mensaje = "<div class='alert alert-success'>Datos actualizados correctamente.</div>";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = "<div class='alert alert-danger'>No se pudieron actualizar los datos. Intente nuevamente.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>";
    }
}

if ($rol == 1) {
    $stmt = $pdo->prepare("SELECT u.email, p.rut, p.nombre_completo, p.fecha_nacimiento, p.sexo, p.id_prevision, p.telefono, p.comuna_residencia
                           FROM Usuario u JOIN Paciente p ON p.id_usuario = u.id_usuario WHERE u.id_usuario = :id_usuario");
} elseif ($rol == 2) {
    $stmt = $pdo->prepare("SELECT u.email, m.rut, m.nombre_completo
                           FROM Usuario u JOIN Medico m ON m.id_usuario = u.id_usuario WHERE u.id_usuario = :id_usuario");
} else {
    $stmt = $pdo->prepare("SELECT email FROM Usuario WHERE id_usuario = :id_usuario");
}
$stmt->execute([':id_usuario' => $id_usuario]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

$previsiones = [];
if ($rol == 1) {
    $previsiones = $pdo->query("SELECT id_prevision, nombre FROM Prevision")->fetchAll(PDO::FETCH_ASSOC);
}

include 'header.php';
?>

<div class="row justify-content-center mb-5">
    <div class="col-md-8">
        <?php echo $mensaje; ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Mi Perfil</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="perfil.php">
                    <div class="row">
                        <?php if ($rol != 3): ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">RUT</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($datos['rut']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nombre_completo" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($datos['nombre_completo']); ?>" required>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($datos['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nueva_contrasena" class="form-label">Nueva contraseña (dejar vacío para no cambiarla)</label>
                            <input type="password" class="form-control" id="nueva_contrasena" name="nueva_contrasena" minlength="8">
                        </div>
                    </div>

                    <?php if ($rol == 1): ?>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($datos['fecha_nacimiento']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sexo" class="form-label">Sexo</label>
                                <select class="form-select" id="sexo" name="sexo" required>
                                    <?php foreach (['M' => 'Masculino', 'F' => 'Femenino', 'Otro' => 'Otro'] as $valor => $texto): ?>
                                        <option value="<?php echo $valor; ?>" <?php if ($datos['sexo'] == $valor) echo 'selected'; ?>><?php echo $texto; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="id_prevision" class="form-label">Previsión</label>
                                <select class="form-select" id="id_prevision" name="id_prevision" required>
                                    <?php foreach ($previsiones as $prev): ?>
                                        <option value="<?php echo $prev['id_prevision']; ?>" <?php if ($datos['id_prevision'] == $prev['id_prevision']) echo 'selected'; ?>><?php echo htmlspecialchars($prev['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono (Opcional)</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($datos['telefono'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="comuna_residencia" class="form-label">Comuna de Residencia</label>
                                <input type="text" class="form-control" id="comuna_residencia" name="comuna_residencia" value="<?php echo htmlspecialchars($datos['comuna_residencia']); ?>" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="guardar_perfil" class="btn btn-primary">Guardar cambios</button>
                </form>
            </div>
        </div>

        <?php if ($rol == 1): ?>
            <div class="card shadow border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Eliminar cuenta</h5>
                </div>
                <div class="card-body">
                    <p>Al eliminar tu cuenta se borrarán también todas tus citas, atenciones y recetas. Esta acción no se puede deshacer.</p>
                    <form method="POST" action="perfil.php" onsubmit="return confirm('¿Seguro que desea eliminar su cuenta? Se eliminarán también sus citas, atenciones y recetas.');">
                        <button type="submit" name="eliminar_cuenta" class="btn btn-danger">Eliminar mi cuenta</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
