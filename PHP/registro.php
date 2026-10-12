<?php 
include 'header.php'; 
require_once 'conexion.php';

//obtener las previsiones para el select
$consulta_prevision = $pdo->query("SELECT id_prevision, nombre FROM Prevision");
$previsiones = $consulta_prevision->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="row justify-content-center mt-4 mb-5">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h4>Registro de Nuevo Paciente</h4>
            </div>
            <div class="card-body">
                <!-- error si el rut o correo ya existen -->
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                            if($_GET['error'] == 'duplicado') echo "El RUT o correo electrónico ya se encuentran registrados.";
                            if($_GET['error'] == 'sistema') echo "Ocurrió un error en el sistema. Intente nuevamente.";
                            if($_GET['error'] == 'campos') echo "Todos los campos obligatorios deben estar completos y ser válidos.";
                            if($_GET['error'] == 'rut') echo "El RUT debe tener el formato XXXXXXXX-X (con guión y dígito verificador).";
                            if($_GET['error'] == 'correo') echo "El correo electrónico no tiene un formato válido.";
                            if($_GET['error'] == 'contrasena') echo "La contraseña debe tener al menos 8 caracteres, con letras y números.";
                            if($_GET['error'] == 'fecha') echo "La fecha de nacimiento no es válida.";
                        ?>
                    </div>
                <?php endif; ?>

                <form action="procesar_registro.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="rut" class="form-label">RUT (Ej: 12345678-9)</label>
                            <input type="text" class="form-control" id="rut" name="rut" required pattern="\d{7,8}-[\dkK]" title="Debe ingresar el RUT con guión. Ej: 12345678-9">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nombre_completo" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="correo_electronico" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contrasena" class="form-label">Contraseña (Mín. 8, con letras y números)</label>
                            <input type="password" class="form-control" id="contrasena" name="contrasena" required minlength="8">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sexo" class="form-label">Sexo</label>
                            <select class="form-select" id="sexo" name="sexo" required>
                                <option value="">Seleccione...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="id_prevision" class="form-label">Previsión</label>
                            <select class="form-select" id="id_prevision" name="id_prevision" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($previsiones as $prev): ?>
                                    <option value="<?php echo $prev['id_prevision']; ?>"><?php echo $prev['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono (Opcional)</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="comuna_residencia" class="form-label">Comuna de Residencia</label>
                            <input type="text" class="form-control" id="comuna_residencia" name="comuna_residencia" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100 mt-3">Completar Registro</button>
                    <div class="text-center mt-3">
                        <a href="login.php">Volver al inicio de sesión</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>