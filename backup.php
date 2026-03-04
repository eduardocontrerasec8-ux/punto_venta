<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Verificar permisos
if ($_SESSION['puesto'] != 'Administrador') {
    die('No tienes permisos');
}

$backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$tables = ['usuario', 'proveedor', 'producto', 'venta', 'detalle_venta', 'corte_caja'];

if (isset($_GET['download'])) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup_file . '"');
    
    echo "-- Backup del Sistema Punto de Venta\n";
    echo "-- Generado: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Usuario: " . $_SESSION['nombre'] . "\n\n";
    
    foreach ($tables as $table) {
        // Exportar estructura
        $stmt = $conn->query("SELECT column_name, data_type, character_maximum_length 
                             FROM information_schema.columns 
                             WHERE table_name = '$table' 
                             ORDER BY ordinal_position");
        $columns = $stmt->fetchAll();
        
        echo "DROP TABLE IF EXISTS $table;\n";
        echo "CREATE TABLE $table (\n";
        
        $column_defs = [];
        foreach ($columns as $col) {
            $def = "  " . $col['column_name'] . " " . $col['data_type'];
            if ($col['character_maximum_length']) {
                $def .= "(" . $col['character_maximum_length'] . ")";
            }
            $column_defs[] = $def;
        }
        echo implode(",\n", $column_defs);
        echo "\n);\n\n";
        
        // Exportar datos
        $stmt = $conn->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "INSERT INTO $table VALUES\n";
            $values = [];
            foreach ($rows as $row) {
                $row_values = array_map(function($val) use ($conn) {
                    if ($val === null) return 'NULL';
                    return $conn->quote($val);
                }, array_values($row));
                $values[] = "  (" . implode(", ", $row_values) . ")";
            }
            echo implode(",\n", $values) . ";\n\n";
        }
    }
    
    exit;
}
?>
<div class="container">
    <h4>Backup del Sistema</h4>
    
    <div class="card">
        <div class="card-content">
            <span class="card-title">Generar Backup</span>
            <p>Este proceso generará un archivo SQL con todos los datos del sistema.</p>
            
            <div class="center-align" style="margin: 30px 0;">
                <a href="backup.php?download=1" class="btn-large green waves-effect waves-light">
                    <i class="material-icons left">cloud_download</i>
                    Descargar Backup
                </a>
            </div>
            
            <div class="card blue lighten-5">
                <div class="card-content blue-text">
                    <i class="material-icons left">info</i>
                    <strong>Recomendaciones:</strong>
                    <ul>
                        <li>Realice backups regularmente</li>
                        <li>Guarde los archivos en un lugar seguro</li>
                        <li>Verifique que el backup se haya descargado correctamente</li>
                        <li>En producción, configure backups automáticos</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>