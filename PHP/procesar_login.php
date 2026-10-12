<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $consulta_usuario = $pdo->prepare("SELECT id_usuario, password_hash, id_rol FROM Usuario WHERE email = :email");
        $consulta_usuario->execute([':email' => $email]);
        $datos_usuario = $consulta_usuario->fetch(PDO::FETCH_ASSOC);

        if ($datos_usuario && password_verify($password, $datos_usuario['password_hash'])) {
            $rol = (int)$datos_usuario['id_rol'];
            $identificador = 0;
            $nombre = 'Administrador SaludUSM';

            if ($rol == 1) {
                $consulta = $pdo->prepare("SELECT id_paciente AS id, nombre_completo FROM Paciente WHERE id_usuario = :id_usuario");
                $consulta->execute([':id_usuario' => $datos_usuario['id_usuario']]);
                $datos = $consulta->fetch(PDO::FETCH_ASSOC);
                $identificador = $datos['id'];
                $nombre = $datos['nombre_completo'];
            } elseif ($rol == 2) {
                $consulta = $pdo->prepare("SELECT id_medico AS id, nombre_completo FROM Medico WHERE id_usuario = :id_usuario");
                $consulta->execute([':id_usuario' => $datos_usuario['id_usuario']]);
                $datos = $consulta->fetch(PDO::FETCH_ASSOC);
                $identificador = $datos['id'];
                $nombre = $datos['nombre_completo'];
            }

            $_SESSION['id_usuario'] = $datos_usuario['id_usuario'];
            $_SESSION['identificador_usuario'] = $identificador;
            $_SESSION['nombre_usuario'] = $nombre;
            $_SESSION['rol_usuario'] = $rol;

            $pdo->exec("CALL sp_cancelar_citas_vencidas(@citas_canceladas)");

            header("Location: index.php");
            exit();
        }

        header("Location: login.php?error=1");
        exit();

    } catch (PDOException $e) {
        die("Error en la consulta: " . $e->getMessage());
    }
}
?>