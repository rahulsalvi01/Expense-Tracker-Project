<?php
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    $amount = $_POST['amount'] ?? '';
    $category = trim($_POST['category'] ?? '');

    if (empty($name) || empty($amount) || empty($category)) {
        $response['message'] = 'Missing required fields: name, amount, category';
        echo json_encode($response);
        exit;
    }

    // Validate amount is numeric
    if (!is_numeric($amount)) {
        $response['message'] = 'Amount must be a number';
        echo json_encode($response);
        exit;
    }

    // Sanitize CSV data (escape commas, quotes, newlines)
    $name = str_replace(['"', "\n", "\r"], ['""', '', ''], $name);
    $category = str_replace(['"', "\n", "\r"], ['""', '', ''], $category);
    $amount = number_format((float)$amount, 2, '.', '');

    $csvLine = '"' . $name . '",' . $amount . ',"' . $category . '"' . "\n";

    // Ensure data.csv exists
    $csvFile = __DIR__ . '/data.csv';
    if (!file_exists($csvFile)) {
        file_put_contents($csvFile, "name,amount,category\n");
    }

    if (file_put_contents($csvFile, $csvLine, FILE_APPEND) !== false) {
        $response = ['status' => 'success', 'message' => 'Data appended successfully'];
    } else {
        $response['message'] = 'Failed to write to data.csv';
    }

} elseif ($action === 'calculate') {
    $calcPath = __DIR__ . '/calc.exe';

    if (!file_exists($calcPath)) {
        $response['message'] = 'calc.exe not found';
        echo json_encode($response);
        exit;
    }

    // Use absolute path and escape for security
    $command = escapeshellarg($calcPath);
    $output = shell_exec($command);

    if ($output === null) {
        $response['message'] = 'Failed to execute calc.exe';
    } else {
        $output = trim($output);
        $response = ['status' => 'success', 'total' => $output];
    }
} else {
    $response['message'] = 'Invalid action';
}

echo json_encode($response);
?>