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

    // 1) encritamos la contraseña con password_hash
    $contrasena_encriptada = password_hash($contrasena_plana, PASSWORD_DEFAULT);

    try {
        // 2) verificar que el rut o el correo no existan de antes
        $consulta_existencia = $pdo->prepare("SELECT id FROM Paciente WHERE rut = :rut OR correo_electronico = :correo");
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
        $sql_insertar = "INSERT INTO Paciente (
                            rut, nombre_completo, correo_electronico, contrasena, 
                            fecha_nacimiento, sexo, id_prevision, telefono, comuna_residencia
                        ) VALUES (
                            :rut, :nombre_completo, :correo_electronico, :contrasena, 
                            :fecha_nacimiento, :sexo, :id_prevision, :telefono, :comuna_residencia
                        )";

        $sentencia_insertar = $pdo->prepare($sql_insertar);
        
        $registro_exitoso = $sentencia_insertar->execute([
            ':rut' => $rut,
            ':nombre_completo' => $nombre_completo,
            ':correo_electronico' => $correo_electronico,
            ':contrasena' => $contrasena_encriptada,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':sexo' => $sexo,
            ':id_prevision' => $id_prevision,
            ':telefono' => empty($telefono) ? null : $telefono, //manejar opcional
            ':comuna_residencia' => $comuna_residencia
        ]);

        if ($registro_exitoso) {
            // si se registró bien, lo logueamos automáticamente
            $_SESSION['identificador_usuario'] = $pdo->lastInsertId();
            $_SESSION['nombre_usuario'] = $nombre_completo;
            $_SESSION['rol_usuario'] = 1; // 1 = Paciente
            
            // redirigir a la pagina principal
            header("Location: index.php");
            exit();
        }

    } catch (PDOException $excepcion) {
        header("Location: registro.php?error=sistema");
        exit();
    }
} else {
    header("Location: registro.php");
    exit();
}
?>