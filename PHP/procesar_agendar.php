<?php
session_start();
require_once 'conexion.php';

// validar que el usuario sea paciente rol=1.
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 1) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1) recibir los datos del formulario de agendar_cita.php
    $id_paciente = $_SESSION['identificador_usuario'];
    $id_especialidad = $_POST['id_especialidad'];
    $id_centro = $_POST['id_centro'];
    $id_medico = $_POST['id_medico'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];

    // 2) formatear la fecha y hora para que calce con fecha_hora
    $fecha_hora = $fecha . ' ' . $hora . ':00';

    try {
        // 3) obtener el id del estado "reservada" 
        $stmt_estado = $pdo->prepare("SELECT id_estado FROM EstadoCita WHERE nombre = 'Reservada'");
        $stmt_estado->execute();
        $estado = $stmt_estado->fetch(PDO::FETCH_ASSOC);
        
        if (!$estado) {
            die("Error crítico: No existe el estado 'Reservada' en la base de datos.");
        }
        $id_estado_cita = $estado['id_estado'];

        if (strtotime($fecha_hora) === false || strtotime($fecha_hora) <= time()) {
            header("Location: agendar_cita.php?error=" . urlencode("No se puede agendar una cita en una fecha u hora que ya pasó."));
            exit();
        }

        // 4) Intentar insertar la cita
        $sql_insertar = "INSERT INTO Cita (fecha_hora, id_paciente, id_medico, id_centro, id_especialidad, id_estado) 
                         VALUES (:fecha_hora, :id_paciente, :id_medico, :id_centro, :id_especialidad, :id_estado_cita)";
        
        $stmt_insertar = $pdo->prepare($sql_insertar);
        
        $exito = $stmt_insertar->execute([
            ':fecha_hora' => $fecha_hora,
            ':id_paciente' => $id_paciente,
            ':id_medico' => $id_medico,
            ':id_centro' => $id_centro,
            ':id_especialidad' => $id_especialidad,
            ':id_estado_cita' => $id_estado_cita
        ]);

        if ($exito) {
            // cita guardada correctamente
            header("Location: agendar_cita.php?exito=1");
            exit();
        }

    } catch (PDOException $e) {
        $detalle = $e->getMessage();
        if (strpos($detalle, 'unq_medico_hora') !== false) {
            $msg = "El médico ya tiene una cita agendada en ese horario.";
        } elseif (strpos($detalle, 'unq_paciente_hora') !== false) {
            $msg = "Usted ya tiene una cita agendada a esa misma fecha y hora.";
        } elseif (strpos($detalle, 'foreign key') !== false) {
            $msg = "El médico no posee la especialidad solicitada o no atiende en el centro seleccionado.";
        } else {
            $msg = "Ocurrió un error al agendar la cita.";
        }
        header("Location: agendar_cita.php?error=" . urlencode($msg));
        exit();
    }
} else {
    // Si entran directo por URL, devolver al index
    header("Location: index.php");
    exit();
}
?>