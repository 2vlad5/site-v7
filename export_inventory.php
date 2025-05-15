
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];

if (!hasTabAccess($userId, 'inventory')) {
    header("Location: dashboard.php");
    exit();
}

$inventory = loadJson('data/inventory.json');
$items = $inventory['items'] ?? [];
$categories = $inventory['categories'] ?? [];

// Prepare data for export
$data = [
    [
        'Название',
        'Категория',
        'Серийный номер',
        'Местоположение', 
        'Статус',
        'Ответственное лицо',
        'Примечания',
        'Дата создания',
        'Последнее обновление'
    ]
];

foreach ($items as $item) {
    $categoryName = '';
    foreach ($categories as $category) {
        if ($category['id'] === $item['category']) {
            $categoryName = $category['name'];
            break;
        }
    }

    $status = match($item['status']) {
        'active' => 'В работе',
        'repair' => 'В ремонте', 
        'storage' => 'На складе',
        'decommissioned' => 'Списано',
        default => $item['status']
    };

    $data[] = [
        $item['name'],
        $categoryName,
        $item['serial_number'],
        $item['location'],
        $status,
        $item['responsible_person'],
        $item['notes'] ?? '',
        date('d.m.Y H:i', $item['created_at']),
        date('d.m.Y H:i', $item['updated_at'])
    ];
}

// Create temporary CSV file
$tempFile = tempnam(sys_get_temp_dir(), 'inventory');
$fp = fopen($tempFile, 'w');

// Add BOM for Excel
fputs($fp, "\xEF\xBB\xBF");

// Write data
foreach ($data as $row) {
    fputcsv($fp, $row, ';');
}

fclose($fp);

// Send file to browser
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="inventory_' . date('Y-m-d') . '.csv"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: no-cache');

readfile($tempFile);
unlink($tempFile);

// Log export
logActivity($userId, 'export_inventory', 'Экспортировал инвентаризацию в CSV');
exit;
