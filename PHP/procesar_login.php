<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rut = $_POST['rut'];
    $password = $_POST['password'];

    try {
        // 1) verificar si es un paciente (rol 1)
        $consulta_paciente = $pdo->prepare("SELECT id, nombre_completo, password FROM Paciente WHERE rut = :rut");
        $consulta_paciente->execute([':rut' => $rut]);
        $datos_paciente = $consulta_paciente->fetch(PDO::FETCH_ASSOC);

        // verificamos si existe el paciente y si la contraseña coincide
        if ($datos_paciente && password_verify($password, $datos_paciente['password'])) {
            $_SESSION['identificador_usuario'] = $datos_paciente['id'];
            $_SESSION['nombre_usuario'] = $datos_paciente['nombre_completo'];
            $_SESSION['rol_usuario'] = 1;
            header("Location: index.php");
            exit();
        }

        // 2) si no es paciente, verificar si es médico (rol 2)
        $consulta_medico = $pdo->prepare("SELECT id, nombre_completo, password FROM Medico WHERE rut = :rut");
        $consulta_medico->execute([':rut' => $rut]);
        $datos_medico = $consulta_medico->fetch(PDO::FETCH_ASSOC);

        if ($datos_medico && password_verify($password, $datos_medico['password'])) {
            $_SESSION['identificador_usuario'] = $datos_medico['id'];
            $_SESSION['nombre_usuario'] = $datos_medico['nombre_completo'];
            $_SESSION['rol_usuario'] = 2; // 2 = Médico
            header("Location: index.php");
            exit();
        }

        // 3)v erificar administrador. 
        if ($rut === 'admin' && $password === 'admin123') {
            $_SESSION['identificador_usuario'] = 0;
            $_SESSION['nombre_usuario'] = 'Administrador SaludUSM';
            $_SESSION['rol_usuario'] = 3; // 3 = Admin
            header("Location: index.php");
            exit();
        }

        // si llegó hasta aquí, el RUT o contraseña están mal
        header("Location: login.php?error=1");
        exit();

    } catch (PDOException $e) {
        die("Error en la consulta: " . $e->getMessage());
    }
}
?>