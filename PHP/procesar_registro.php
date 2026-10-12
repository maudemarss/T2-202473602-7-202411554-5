<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // recibir todos los datos del formulario
    $rut = trim($_POST['rut']);
    $nombre_completo = trim($_POST['nombre_completo']);
    $correo_electronico = trim($_POST['correo_electronico']);
    $contrasena_plana = $_POST['contrasena'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $sexo = $_POST['sexo'];
    $id_prevision = $_POST['id_prevision'];
    $telefono = trim($_POST['telefono']);
    $comuna_residencia = trim($_POST['comuna_residencia']);

    if ($rut === '' || $nombre_completo === '' || $correo_electronico === '' || $contrasena_plana === '' ||
        $fecha_nacimiento === '' || $id_prevision === '' || $comuna_residencia === '' ||
        !in_array($sexo, ['M', 'F', 'Otro'])) {
        header("Location: registro.php?error=campos");
        exit();
    }
    if (!preg_match('/^\d{7,8}-[\dkK]$/', $rut)) {
        header("Location: registro.php?error=rut");
        exit();
    }
    if (!filter_var($correo_electronico, FILTER_VALIDATE_EMAIL)) {
        header("Location: registro.php?error=correo");
        exit();
    }
    if (strlen($contrasena_plana) < 8 || !preg_match('/[A-Za-z]/', $contrasena_plana) || !preg_match('/\d/', $contrasena_plana)) {
        header("Location: registro.php?error=contrasena");
        exit();
    }
    $fecha_valida = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
    if (!$fecha_valida || $fecha_valida->format('Y-m-d') !== $fecha_nacimiento || $fecha_nacimiento > date('Y-m-d')) {
        header("Location: registro.php?error=fecha");
        exit();
    }

    // 1) encritamos la contraseña con password_hash
    $contrasena_encriptada = password_hash($contrasena_plana, PASSWORD_DEFAULT);

    try {
        // 2) verificar que el rut o el correo no existan de antes
        $consulta_existencia = $pdo->prepare("SELECT 1 FROM Usuario WHERE email = :correo UNION SELECT 1 FROM Paciente WHERE rut = :rut");
        $consulta_existencia->execute([
            ':rut' => $rut,
            ':correo' => $correo_electronico
        ]);

        if ($consulta_existencia->rowCount() > 0) {
            // si el rut o correo ya existe
            header("Location: registro.php?error=duplicado");
            exit();
        }

        // 3) insertar el nuevo paciente en la base de datos
        $pdo->beginTransaction();

        $sentencia_usuario = $pdo->prepare("INSERT INTO Usuario (email, password_hash, id_rol) VALUES (:correo, :contrasena, 1)");
        $sentencia_usuario->execute([
            ':correo' => $correo_electronico,
            ':contrasena' => $contrasena_encriptada
        ]);
        $id_usuario = $pdo->lastInsertId();

        $sql_insertar = "INSERT INTO Paciente (
                            id_usuario, rut, nombre_completo, 
                            fecha_nacimiento, sexo, id_prevision, telefono, comuna_residencia
                        ) VALUES (
                            :id_usuario, :rut, :nombre_completo, 
                            :fecha_nacimiento, :sexo, :id_prevision, :telefono, :comuna_residencia
                        )";

        $sentencia_insertar = $pdo->prepare($sql_insertar);
        
        $registro_exitoso = $sentencia_insertar->execute([
            ':id_usuario' => $id_usuario,
            ':rut' => $rut,
            ':nombre_completo' => $nombre_completo,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':sexo' => $sexo,
            ':id_prevision' => $id_prevision,
            ':telefono' => empty($telefono) ? null : $telefono, //manejar opcional
            ':comuna_residencia' => $comuna_residencia
        ]);
        $id_paciente = $pdo->lastInsertId();

        $pdo->commit();

        if ($registro_exitoso) {
            // si se registró bien, lo logueamos automáticamente
            $_SESSION['id_usuario'] = $id_usuario;
            $_SESSION['identificador_usuario'] = $id_paciente;
            $_SESSION['nombre_usuario'] = $nombre_completo;
            $_SESSION['rol_usuario'] = 1; // 1 = Paciente
            
            // redirigir a la pagina principal
            header("Location: index.php");
            exit();
        }

    } catch (PDOException $excepcion) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: registro.php?error=sistema");
        exit();
    }
} else {
    header("Location: registro.php");
    exit();
}
?>