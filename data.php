<?php
require_once __DIR__ . '/persistent_storage.php';
require_once __DIR__ . '/auth.php';
require_authentication(true);
send_security_headers("default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function ($exception) {
    error_log('Dashboard API error: ' . $exception->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => 'Penyimpanan data tidak tersedia. Periksa konfigurasi DATABASE_URL.',
    ]);
    exit;
});

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'error' => 'Metode HTTP tidak diizinkan.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 5242880) {
        http_response_code(413);
        echo json_encode(['success' => false, 'error' => 'Ukuran permintaan terlalu besar.']);
        exit;
    }

    $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
    if (strpos($contentType, 'application/json') !== 0) {
        http_response_code(415);
        echo json_encode(['success' => false, 'error' => 'Content-Type harus application/json.']);
        exit;
    }

    require_api_csrf();
}

$baseDir = __DIR__ . '/';

$validFolders = ['csv_konsol', 'csv_kc_only', 'csv_kcp_only', 'csv_mikro', 'csv_ritel'];
$metrics = ['tabungan', 'giro', 'depo', 'casa', 'dpk'];
$defaultHeaders = ['Des-2025','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$maxRows = 31;

function readCsvFile($filepath) {
    $contents = persistent_storage_read($filepath);
    if ($contents === null) return null;
    $lines = preg_split('/\r\n|\r|\n/', trim($contents));
    $lines = array_values(array_filter($lines, function ($line) {
        return trim($line) !== '';
    }));
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
    persistent_storage_write($filepath, implode("\n", $lines) . "\n");
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
    $isKCP = ($folder === 'csv_kcp_only');
    $isMikro = ($folder === 'csv_mikro');

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
        // Mikro: Giro dalam Juta, perlu /1000 untuk konversi ke Miliar
        $giroForCalc = $isMikro ? $giroNum / 1000 : $giroNum;
        $casaNum = $tabNum + $giroForCalc;
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

    $isKCP = ($folder === 'csv_kcp_only');
    $isMikro = ($folder === 'csv_mikro');

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
            $giroForCalc = $isMikro ? $giroNum / 1000 : $giroNum;
            $casaNum = $tabNum + $giroForCalc;
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

// ---- READ BRILINK CSV ----
if ($action === 'read_brilink' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $file = isset($_GET['file']) ? $_GET['file'] : '';
    $validFiles = ['volume_trx', 'casa_agen', 'casa_uker'];
    if (!in_array($file, $validFiles)) {
        echo json_encode(['error' => 'Invalid file']);
        exit;
    }
    $filepath = $baseDir . 'csv_brilink/' . $file . '.csv';
    $csv = readCsvFile($filepath);
    if (!$csv) {
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $csv]);
    exit;
}

// ---- SAVE BRILINK: upsert values for a date column ----
if ($action === 'save_brilink' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
    $file    = isset($input['file'])    ? $input['file']    : '';
    $date    = isset($input['date'])    ? trim($input['date']) : '';
    $updates = isset($input['updates']) ? $input['updates']  : [];

    $validFiles = ['volume_trx', 'casa_agen', 'casa_uker'];
    if (!in_array($file, $validFiles)) {
        echo json_encode(['error' => 'Invalid file']);
        exit;
    }
    if (empty($date)) {
        echo json_encode(['error' => 'Date is required']);
        exit;
    }

    $filepath = $baseDir . 'csv_brilink/' . $file . '.csv';
    $csv = readCsvFile($filepath);
    if (!$csv) {
        echo json_encode(['error' => 'File not found: ' . $file . '.csv']);
        exit;
    }

    $headers = $csv['headers'];
    $data    = $csv['data'];

    // Add date column if it does not exist
    if (!in_array($date, $headers)) {
        $headers[] = $date;
        foreach ($data as &$row) {
            $row[$date] = '';
        }
        unset($row);
    }

    $keyCol = $headers[0]; // first column is the row identifier

    foreach ($updates as $u) {
        $name  = isset($u['name'])  ? $u['name']  : '';
        $value = isset($u['value']) ? $u['value'] : '';
        foreach ($data as &$row) {
            if ($row[$keyCol] === $name) {
                $row[$date] = $value;
                break;
            }
        }
        unset($row);
    }

    writeCsvFile($filepath, $headers, $data);
    echo json_encode(['success' => true, 'message' => "Saved $file for $date"]);
    exit;
}

// ---- DELETE BRILINK DATE: remove an entire date column ----
if ($action === 'delete_brilink_date' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
    $file = isset($input['file']) ? $input['file'] : '';
    $date = isset($input['date']) ? trim($input['date']) : '';

    $validFiles = ['volume_trx', 'casa_agen', 'casa_uker'];
    if (!in_array($file, $validFiles)) {
        echo json_encode(['error' => 'Invalid file']);
        exit;
    }
    if (empty($date)) {
        echo json_encode(['error' => 'Date is required']);
        exit;
    }

    $filepath = $baseDir . 'csv_brilink/' . $file . '.csv';
    $csv = readCsvFile($filepath);
    if (!$csv) {
        echo json_encode(['error' => 'File not found']);
        exit;
    }

    $headers = $csv['headers'];
    $data    = $csv['data'];

    if (!in_array($date, $headers)) {
        echo json_encode(['error' => "Date '$date' not found in $file"]);
        exit;
    }

    $headers = array_values(array_filter($headers, function($h) use ($date) { return $h !== $date; }));
    foreach ($data as &$row) {
        unset($row[$date]);
    }
    unset($row);

    writeCsvFile($filepath, $headers, $data);
    echo json_encode(['success' => true, 'message' => "Deleted date '$date' from $file"]);
    exit;
}

// ---- BULK SAVE BRILINK: save multiple rows x multiple dates at once ----
if ($action === 'bulk_save_brilink' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
    $file    = isset($input['file'])    ? $input['file']    : '';
    $entries = isset($input['entries']) ? $input['entries']  : [];

    $validFiles = ['volume_trx', 'casa_agen', 'casa_uker'];
    if (!in_array($file, $validFiles)) {
        echo json_encode(['error' => 'Invalid file']);
        exit;
    }
    if (empty($entries)) {
        echo json_encode(['error' => 'No entries provided']);
        exit;
    }

    $filepath = $baseDir . 'csv_brilink/' . $file . '.csv';
    $csv = readCsvFile($filepath);
    if (!$csv) {
        echo json_encode(['error' => 'File not found: ' . $file . '.csv']);
        exit;
    }

    $headers = $csv['headers'];
    $data    = $csv['data'];
    $keyCol  = $headers[0];
    $metaCols = [
        'volume_trx' => ['NAMA','JENIS'],
        'casa_agen'  => ['NAMA','UKER','NO_REK'],
        'casa_uker'  => ['NAMA_UKER','JUMLAH_AGEN']
    ];
    $meta = $metaCols[$file];
    $savedCount = 0;

    foreach ($entries as $entry) {
        $name  = isset($entry['name']) ? $entry['name'] : '';
        $dates = isset($entry['dates']) ? $entry['dates'] : [];
        if (empty($name) || empty($dates)) continue;

        // Find the row by key column
        $rowIdx = -1;
        foreach ($data as $i => $row) {
            if ($row[$keyCol] === $name) {
                $rowIdx = $i;
                break;
            }
        }
        if ($rowIdx === -1) continue; // skip unknown names

        foreach ($dates as $dateCol => $value) {
            $dateCol = trim($dateCol);
            if (empty($dateCol) || in_array($dateCol, $meta)) continue;

            // Add date column if it doesn't exist
            if (!in_array($dateCol, $headers)) {
                $headers[] = $dateCol;
                foreach ($data as &$r) {
                    $r[$dateCol] = '';
                }
                unset($r);
            }

            // Save the value (ensure it's not empty string being saved as empty)
            $trimmedValue = trim($value);
            $data[$rowIdx][$dateCol] = $trimmedValue;
            $savedCount++;
        }
    }

    writeCsvFile($filepath, $headers, $data);
    echo json_encode(['success' => true, 'message' => "Bulk saved $savedCount values for $file"]);
    exit;
}

echo json_encode(['error' => 'Invalid action. Use: read, save, delete, bulk_save, read_brilink, save_brilink, delete_brilink_date, bulk_save_brilink']);
