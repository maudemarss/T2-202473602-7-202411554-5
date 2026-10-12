<?php
session_start();
// validar que el usuario haya iniciado sesion
if (!isset($_SESSION['identificador_usuario'])) {
    header("Location: login.php?error=2");
    exit();
}

require_once 'conexion.php';
include 'header.php';

$termino_busqueda = "";
$resultados = [];

// verificar si se envio un termino de busqueda desde el index
if (isset($_GET['termino_busqueda'])) {
    $termino_busqueda = trim($_GET['termino_busqueda']);
    
    if (!empty($termino_busqueda)) {
        try {
            // consulta sql para buscar por nombre o especialidad.
            // usamos GROUP_CONCAT, ya que si usaramos JOIN, un medico con mas de una especialidad apareceria repetido
            $sql = "SELECT 
                        m.id, 
                        m.nombre_completo,
                        (SELECT GROUP_CONCAT(e.nombre SEPARATOR ', ') 
                         FROM Especialidad e 
                         JOIN Medico_Especialidad me ON e.id = me.id_especialidad 
                         WHERE me.id_medico = m.id) as especialidades,
                        (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ') 
                         FROM CentroMedico c 
                         JOIN Medico_Centro mc ON c.id = mc.id_centro 
                         WHERE mc.id_medico = m.id) as centros
                    FROM Medico m
                    WHERE m.nombre_completo LIKE :termino 
                       OR m.id IN (
                           SELECT me2.id_medico 
                           FROM Medico_Especialidad me2 
                           JOIN Especialidad e2 ON me2.id_especialidad = e2.id 
                           WHERE e2.nombre LIKE :termino
                       )";
            
            $consulta = $pdo->prepare($sql);
            // % para buscar por un trozo del nombre o especialidad
            $consulta->execute([':termino' => '%' . $termino_busqueda . '%']);
            $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $excepcion) {
            die("Error en la búsqueda: " . $excepcion->getMessage());
        }
    }
}
?>

<div class="row mt-4 mb-5">
    <div class="col-12">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Resultados de Búsqueda</h4>
                <a href="index.php" class="btn btn-sm btn-light text-primary">Volver al Inicio</a>
            </div>
            <div class="card-body">
                
                <!-- mantener la barra de búsqueda por si acaso -->
                <form action="buscar_medico.php" method="GET" class="row g-3 mb-4">
                    <div class="col-md-10">
                        <input type="text" class="form-control" name="termino_busqueda" value="<?php echo htmlspecialchars($termino_busqueda); ?>" placeholder="Ingrese nombre del médico o especialidad..." required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Buscar</button>
                    </div>
                </form>

                <hr>

                <?php if (!empty($termino_busqueda)): ?>
                    <h5 class="mb-3">Buscando: "<strong><?php echo htmlspecialchars($termino_busqueda); ?></strong>"</h5>
                    
                    <?php if (count($resultados) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nombre del Médico</th>
                                        <th>Especialidades</th>
                                        <th>Centros de Atención</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resultados as $medico): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($medico['nombre_completo']); ?></td>
                                            <td>
                                                <?php 
                                                    // Si es que no tiene especialidad asignada
                                                    echo $medico['especialidades'] ? htmlspecialchars($medico['especialidades']) : '<span class="text-muted">No registradas</span>'; 
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    echo $medico['centros'] ? htmlspecialchars($medico['centros']) : '<span class="text-muted">No registrados</span>'; 
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            No se encontraron médicos que coincidan con su búsqueda.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        Utilice la barra de arriba para buscar médicos por nombre o especialidad.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>