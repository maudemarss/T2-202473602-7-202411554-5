<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaludUSM - Sistema de Gestión Clínica</title>
    <!--bootstrap-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">SaludUSM</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if(isset($_SESSION['identificador_usuario'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Inicio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="perfil.php">Mi Perfil</a>
                        </li>
                        
                        <!-- enlaces para pacientes -->
                        <?php if($_SESSION['rol_usuario'] == 1): ?>
                            <li class="nav-item"><a class="nav-link" href="agendar_cita.php">Agendar Hora</a></li>
                            <li class="nav-item"><a class="nav-link" href="mis_citas.php">Mis Citas</a></li>
                        <?php endif; ?>

                        <!-- para medicos -->
                        <?php if($_SESSION['rol_usuario'] == 2): ?>
                            <li class="nav-item"><a class="nav-link" href="agenda_medico.php">Mi Agenda</a></li>
                        <?php endif; ?>

                        <!-- para admins -->
                        <?php if($_SESSION['rol_usuario'] == 3): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    Gestión
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="gestion_centros.php">Centros Médicos</a></li>
                                    <li><a class="dropdown-item" href="gestion_medicos.php">Médicos</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="estadisticas_red.php">Panel de Gestión</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <!-- a la derecha de la barra van el nombre del usuario y boton de cerrar sesion-->
                <span class="navbar-text">
                    <?php if(isset($_SESSION['identificador_usuario'])): ?>
                        <span class="me-3 text-white">Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></span>
                        <a href="logout.php" class="btn btn-sm btn-danger text-white">Cerrar Sesión</a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </nav>
    <div class="container">