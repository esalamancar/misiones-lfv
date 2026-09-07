<?php
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

function executePythonScript($sheet_id, $sheet_name, $column1, $column2) {
    $command = 'python3 /var/www/html/extract_key_value.py --sheet_id "' . $sheet_id . '" --sheet_name "' . $sheet_name . '" --columns "' . $column1 . ',' . $column2 . '" 2>&1';
    $output = shell_exec($command);
    if (empty($output)) {
        return null;
    }
    $data = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    return $data;
}

$actor = $_GET['actor'] ?? '';
$sheet_id = "1kiUjnIrdlN6wnFDETshi3Qr8unFgPP4CypWtNQX0Aek";
$column1 = "1";
$column2 = "4";

if ($actor) {
    $dataNombres = executePythonScript($sheet_id, $actor, $column1, $column2);
    if (
        isset($dataNombres['data']) &&
        is_array($dataNombres['data']) &&
        count($dataNombres['data']) > 1
    ) {
        $data = $dataNombres['data'];
        $data = array_slice($data, 1);
        if (empty($data)) {
            echo json_encode(['data' => []]);
            exit;
        }
        usort($data, function($a, $b) {
            return strcmp(mb_strtolower($a['columna_1'] ?? ''), mb_strtolower($b['columna_1'] ?? ''));
        });
        echo json_encode(['data' => $data]);
        exit;
    }
}
echo json_encode(['data' => []]);
