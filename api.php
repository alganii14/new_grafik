<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$baseDir = __DIR__ . '/';

$validFolders = ['csv konsol', 'csv kc only', 'csv kcp only', 'csv mikro', 'csv ritel'];
$metrics = ['tabungan', 'giro', 'depo', 'casa', 'dpk'];
$defaultHeaders = ['Des-2025','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$maxRows = 31;

function readCsvFile($filepath) {
    if (!file_exists($filepath)) return null;
    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) < 1) return null;
    $headers = str_getcsv($lines[0]);
    $headers = array_map('trim', $headers);
    $data = [];
    for ($i = 1; $i < count($lines); $i++) {
        $vals = str_getcsv($lines[$i]);
        $row = [];
        foreach ($headers as $j => $h) {
            $v = isset($vals[$j]) ? trim($vals[$j]) : '';
            $row[$h] = $v;
        }
        $data[] = $row;
    }
    return ['headers' => $headers, 'data' => $data];
}

function writeCsvFile($filepath, $headers, $data) {
    $lines = [];
    $lines[] = implode(',', $headers);
    foreach ($data as $row) {
        $vals = [];
        foreach ($headers as $h) {
            $vals[] = isset($row[$h]) ? $row[$h] : '';
        }
        $lines[] = implode(',', $vals);
    }
    file_put_contents($filepath, implode("\n", $lines) . "\n");
}

function ensureRows(&$data, $headers, $count) {
    while (count($data) < $count) {
        $emptyRow = [];
        foreach ($headers as $h) {
            $emptyRow[$h] = '';
        }
        $data[] = $emptyRow;
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ---- READ: Get all data for a folder ----
if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $folder = isset($_GET['folder']) ? $_GET['folder'] : '';
    if (!in_array($folder, $validFolders)) {
        echo json_encode(['error' => 'Invalid folder']);
        exit;
    }
    $result = [];
    foreach ($metrics as $m) {
        $filepath = $baseDir . $folder . '/' . $m . '.csv';
        $csv = readCsvFile($filepath);
        $result[$m] = $csv;
    }
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

// ---- SAVE: Save tabungan, giro, depo for a specific day; auto-calc casa & dpk ----
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }

    $folder = isset($input['folder']) ? $input['folder'] : '';
    $month = isset($input['month']) ? $input['month'] : '';
    $day = isset($input['day']) ? intval($input['day']) : 0;
    $tabungan = isset($input['tabungan']) ? $input['tabungan'] : '';
    $giro = isset($input['giro']) ? $input['giro'] : '';
    $depo = isset($input['depo']) ? $input['depo'] : '';

    if (!in_array($folder, $validFolders)) {
        echo json_encode(['error' => 'Invalid folder']);
        exit;
    }
    if (!in_array($month, $defaultHeaders)) {
        echo json_encode(['error' => 'Invalid month']);
        exit;
    }
    if ($day < 1 || $day > 31) {
        echo json_encode(['error' => 'Invalid day (1-31)']);
        exit;
    }

    $rowIdx = $day - 1; // 0-indexed
    $isKCP = ($folder === 'csv kcp only');

    // Parse numeric values
    // For KCP: tabungan uses x.xxx format (dot = thousands), giro & depo are integers
    // For others: all use decimal format like 35.832
    $tabVal = str_replace(',', '', $tabungan);
    $giroVal = str_replace(',', '', $giro);
    $depoVal = str_replace(',', '', $depo);

    // Calculate CASA and DPK
    if ($isKCP) {
        // KCP: tabungan = "2.070" (dot=thousands → 2070), giro = "589", depo = "758"
        $tabNum = floatval(str_replace('.', '', $tabVal)); // remove dots → integer
        $giroNum = floatval($giroVal);
        $depoNum = floatval($depoVal);
        $casaNum = $tabNum + $giroNum;
        $dpkNum = $casaNum + $depoNum;

        // Format back: casa & dpk use x.xxx format same as tabungan
        $casaStr = number_format($casaNum, 0, '', '.');
        $dpkStr = number_format($dpkNum, 0, '', '.');
    } else {
        // Others: tabungan = "35.832" (decimal), giro = "3.551", depo = "16.753"
        $tabNum = floatval($tabVal);
        $giroNum = floatval($giroVal);
        $depoNum = floatval($depoVal);
        $casaNum = $tabNum + $giroNum;
        $dpkNum = $casaNum + $depoNum;

        // Format with 3 decimal places
        $casaStr = number_format($casaNum, 3, '.', '');
        $dpkStr = number_format($dpkNum, 3, '.', '');
    }

    $valuesToSave = [
        'tabungan' => $tabungan,
        'giro' => $giro,
        'depo' => $depo,
        'casa' => $casaStr,
        'dpk' => $dpkStr
    ];

    foreach ($metrics as $m) {
        $filepath = $baseDir . $folder . '/' . $m . '.csv';
        $csv = readCsvFile($filepath);
        if (!$csv) {
            // Create new file with default headers
            $csv = ['headers' => $defaultHeaders, 'data' => []];
        }
        $headers = $csv['headers'];
        $data = $csv['data'];

        // Ensure month column exists
        if (!in_array($month, $headers)) {
            echo json_encode(['error' => "Month $month not found in $m.csv headers"]);
            exit;
        }

        // Ensure enough rows
        ensureRows($data, $headers, $rowIdx + 1);

        // Set value
        $data[$rowIdx][$month] = $valuesToSave[$m];

        writeCsvFile($filepath, $headers, $data);
    }

    echo json_encode([
        'success' => true,
        'saved' => $valuesToSave,
        'message' => "Data saved for {$folder}, {$month}, day {$day}"
    ]);
    exit;
}

// ---- DELETE: Clear data for a specific day ----
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }

    $folder = isset($input['folder']) ? $input['folder'] : '';
    $month = isset($input['month']) ? $input['month'] : '';
    $day = isset($input['day']) ? intval($input['day']) : 0;

    if (!in_array($folder, $validFolders)) {
        echo json_encode(['error' => 'Invalid folder']);
        exit;
    }
    if (!in_array($month, $defaultHeaders)) {
        echo json_encode(['error' => 'Invalid month']);
        exit;
    }
    if ($day < 1 || $day > 31) {
        echo json_encode(['error' => 'Invalid day (1-31)']);
        exit;
    }

    $rowIdx = $day - 1;

    foreach ($metrics as $m) {
        $filepath = $baseDir . $folder . '/' . $m . '.csv';
        $csv = readCsvFile($filepath);
        if (!$csv) continue;
        $headers = $csv['headers'];
        $data = $csv['data'];

        if ($rowIdx < count($data)) {
            $data[$rowIdx][$month] = '';
            writeCsvFile($filepath, $headers, $data);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Data deleted for {$folder}, {$month}, day {$day}"
    ]);
    exit;
}

// ---- BULK SAVE: Save multiple days at once ----
if ($action === 'bulk_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['folder']) || !isset($input['month']) || !isset($input['entries'])) {
        echo json_encode(['error' => 'Invalid input. Need folder, month, entries[]']);
        exit;
    }

    $folder = $input['folder'];
    $month = $input['month'];
    $entries = $input['entries']; // array of {day, tabungan, giro, depo}

    if (!in_array($folder, $validFolders)) {
        echo json_encode(['error' => 'Invalid folder']);
        exit;
    }
    if (!in_array($month, $defaultHeaders)) {
        echo json_encode(['error' => 'Invalid month']);
        exit;
    }

    $isKCP = ($folder === 'csv kcp only');

    // Load all CSVs first
    $csvData = [];
    foreach ($metrics as $m) {
        $filepath = $baseDir . $folder . '/' . $m . '.csv';
        $csv = readCsvFile($filepath);
        if (!$csv) {
            $csv = ['headers' => $defaultHeaders, 'data' => []];
        }
        $csvData[$m] = $csv;
    }

    foreach ($entries as $entry) {
        $day = intval($entry['day']);
        if ($day < 1 || $day > 31) continue;
        $rowIdx = $day - 1;

        $tabVal = str_replace(',', '', $entry['tabungan']);
        $giroVal = str_replace(',', '', $entry['giro']);
        $depoVal = str_replace(',', '', $entry['depo']);

        if ($isKCP) {
            $tabNum = floatval(str_replace('.', '', $tabVal));
            $giroNum = floatval($giroVal);
            $depoNum = floatval($depoVal);
            $casaNum = $tabNum + $giroNum;
            $dpkNum = $casaNum + $depoNum;
            $casaStr = number_format($casaNum, 0, '', '.');
            $dpkStr = number_format($dpkNum, 0, '', '.');
        } else {
            $tabNum = floatval($tabVal);
            $giroNum = floatval($giroVal);
            $depoNum = floatval($depoVal);
            $casaNum = $tabNum + $giroNum;
            $dpkNum = $casaNum + $depoNum;
            $casaStr = number_format($casaNum, 3, '.', '');
            $dpkStr = number_format($dpkNum, 3, '.', '');
        }

        $values = [
            'tabungan' => $entry['tabungan'],
            'giro' => $entry['giro'],
            'depo' => $entry['depo'],
            'casa' => $casaStr,
            'dpk' => $dpkStr
        ];

        foreach ($metrics as $m) {
            ensureRows($csvData[$m]['data'], $csvData[$m]['headers'], $rowIdx + 1);
            $csvData[$m]['data'][$rowIdx][$month] = $values[$m];
        }
    }

    // Write all CSVs
    foreach ($metrics as $m) {
        $filepath = $baseDir . $folder . '/' . $m . '.csv';
        writeCsvFile($filepath, $csvData[$m]['headers'], $csvData[$m]['data']);
    }

    echo json_encode([
        'success' => true,
        'message' => "Bulk saved " . count($entries) . " entries for {$folder}, {$month}"
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid action. Use: read, save, delete, bulk_save']);
