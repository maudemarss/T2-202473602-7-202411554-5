<?php 
session_start();
// si ya está logeado, lo mandamos al index
if(isset($_SESSION['identificador_usuario'])){
    header("Location: index.php");
    exit();
}
include 'header.php'; 
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4>Iniciar Sesión - SaludUSM</h4>
            </div>
            <div class="card-body">
                <!--mensajes de error si es que existen -->
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                            if($_GET['error'] == 1) echo "Correo o contraseña incorrectos.";
                            if($_GET['error'] == 2) echo "Debe iniciar sesión primero.";
                        ?>
                    </div>
                <?php endif; ?>

                <form action="procesar_login.php" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
            </div>
            <div class="card-footer text-center">
                ¿Eres paciente y no tienes cuenta? <a href="registro.php">Regístrate aquí</a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>