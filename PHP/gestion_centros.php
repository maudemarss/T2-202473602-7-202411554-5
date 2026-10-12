<?php
session_start();
require_once 'conexion.php';

// slo el admin puede entrar
if (!isset($_SESSION['identificador_usuario']) || $_SESSION['rol_usuario'] != 3) {
    header("Location: index.php");
    exit();
}

$mensaje = "";

// procesar los formularios
// 1)agregar centro
if (isset($_POST['agregar_centro'])) {
    $codigo = $_POST['codigo_interno'];
    $nombre = $_POST['nombre'];
    $comuna = $_POST['comuna'];
    $region = $_POST['region'];

    //verificamos si el codigo ya existe
    $check = $pdo->prepare("SELECT id_centro FROM CentroMedico WHERE codigo_interno = ?");
    $check->execute([$codigo]);
    $check = $check->fetch();
    
    if ($check) {
        $mensaje = "<div class='alert alert-danger'>El código interno ya existe.</div>";
    } else {
        $sql = $pdo->prepare("INSERT INTO CentroMedico (codigo_interno, nombre, comuna, region) VALUES (?, ?, ?, ?)");
        if ($sql->execute([$codigo, $nombre, $comuna, $region])) {
            $mensaje = "<div class='alert alert-success'>Centro agregado correctamente.</div>";
        }
    }
}

// 2) borrar centro
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    
    // por si acaso un try-catch por si el centro tiene medicos o citas
    try {
        $unico = $pdo->prepare("SELECT COUNT(*) FROM Medico_Centro mc WHERE mc.id_centro = ? AND NOT EXISTS (SELECT 1 FROM Medico_Centro otro WHERE otro.id_medico = mc.id_medico AND otro.id_centro <> mc.id_centro)");
        $unico->execute([$id_eliminar]);
        if ($unico->fetchColumn() > 0) {
            throw new Exception("unico centro de un medico");
        }
        $del = $pdo->prepare("DELETE FROM CentroMedico WHERE id_centro = ?");
        $del->execute([$id_eliminar]);
        $mensaje = "<div class='alert alert-success'>Centro eliminado.</div>";
    } catch (Exception $e) {
        $mensaje = "<div class='alert alert-danger'>No se puede eliminar porque tiene datos asociados (citas o médicos que atienden solo en este centro).</div>";
    }
}

// 3)editar centro
if (isset($_POST['editar_centro'])) {
    $id = $_POST['id_centro'];
    $nombre = $_POST['nombre'];
    $comuna = $_POST['comuna'];
    $region = $_POST['region'];

    $sql = $pdo->prepare("UPDATE CentroMedico SET nombre = ?, comuna = ?, region = ? WHERE id_centro = ?");
    if ($sql->execute([$nombre, $comuna, $region, $id])) {
        $mensaje = "<div class='alert alert-success'>Centro actualizado.</div>";
    }
}

$centros = $pdo->query("SELECT *, id_centro AS id FROM CentroMedico ORDER BY id_centro DESC")->fetchAll(PDO::FETCH_ASSOC);

// si nos piden editar un centro en especifico, se cargan sus datos en el formulario
$centro_a_editar = null;
if (isset($_GET['editar'])) {
    $id_edit = $_GET['editar'];
    $stmt_edit = $pdo->prepare("SELECT *, id_centro AS id FROM CentroMedico WHERE id_centro = ?");
    $stmt_edit->execute([$id_edit]);
    $centro_a_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <h2>Gestión de Centros Médicos</h2>
    <a href="index.php" class="btn btn-secondary mb-3">Volver al Panel</a>

    <?php echo $mensaje; ?>

    <div class="row">
        <!-- agregar o editar-->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <?php echo $centro_a_editar ? 'Editar Centro' : 'Nuevo Centro'; ?>
                </div>
                <div class="card-body">
                    <form action="gestion_centros.php" method="POST">
                        <?php if($centro_a_editar): ?>
                            <!-- editar -->
                            <input type="hidden" name="id_centro" value="<?php echo $centro_a_editar['id']; ?>">
                            <div class="mb-2">
                                <label>Código Interno</label>
                                <input type="text" class="form-control" value="<?php echo $centro_a_editar['codigo_interno']; ?>" disabled>
                            </div>
                        <?php else: ?>
                            <!-- nuevo -->
                            <div class="mb-2">
                                <label>Código Interno (Único)</label>
                                <input type="text" class="form-control" name="codigo_interno" required>
                            </div>
                        <?php endif; ?>

                        <div class="mb-2">
                            <label>Nombre del Centro</label>
                            <input type="text" class="form-control" name="nombre" value="<?php echo $centro_a_editar ? $centro_a_editar['nombre'] : ''; ?>" required>
                        </div>
                        <div class="mb-2">
                            <label>Comuna</label>
                            <input type="text" class="form-control" name="comuna" value="<?php echo $centro_a_editar ? $centro_a_editar['comuna'] : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Región</label>
                            <input type="text" class="form-control" name="region" value="<?php echo $centro_a_editar ? $centro_a_editar['region'] : ''; ?>" required>
                        </div>

                        <?php if($centro_a_editar): ?>
                            <button type="submit" name="editar_centro" class="btn btn-warning w-100 mb-2">Actualizar</button>
                            <a href="gestion_centros.php" class="btn btn-outline-secondary w-100">Cancelar</a>
                        <?php else: ?>
                            <button type="submit" name="agregar_centro" class="btn btn-primary w-100">Guardar Nuevo Centro</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- tabla con el listado -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Comuna</th>
                                <th>Región</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($centros as $c): ?>
                                <tr>
                                    <td><?php echo $c['codigo_interno']; ?></td>
                                    <td><?php echo $c['nombre']; ?></td>
                                    <td><?php echo $c['comuna']; ?></td>
                                    <td><?php echo $c['region']; ?></td>
                                    <td>
                                        <a href="gestion_centros.php?editar=<?php echo $c['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <a href="gestion_centros.php?eliminar=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que desea eliminar este centro?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>