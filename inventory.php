
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'inventory')) {
    header("Location: dashboard.php");
    exit();
}

$inventory = loadJson('data/inventory.json');
$items = $inventory['items'] ?? [];
$categories = $inventory['categories'] ?? [];

// Sorting parameters
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';
$filterCategory = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 50;

// Filter by category and search
$filteredItems = array_filter($items, function($item) use ($filterCategory, $searchQuery) {
    $matchesCategory = $filterCategory === 'all' || $item['category'] === $filterCategory;
    $matchesSearch = empty($searchQuery) || 
        stripos($item['name'], $searchQuery) !== false ||
        stripos($item['serial_number'], $searchQuery) !== false ||
        stripos($item['location'], $searchQuery) !== false;
    return $matchesCategory && $matchesSearch;
});

// Apply sorting
usort($filteredItems, function($a, $b) use ($sortBy, $sortOrder) {
    $result = strcmp($a[$sortBy], $b[$sortBy]);
    return $sortOrder === 'asc' ? $result : -$result;
});

// Pagination
$totalItems = count($filteredItems);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = min($page, $totalPages);
$offset = ($page - 1) * $itemsPerPage;
$paginatedItems = array_slice($filteredItems, $offset, $itemsPerPage);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $newItem = [
            'id' => generateId(),
            'name' => $_POST['name'],
            'category' => $_POST['category'],
            'serial_number' => $_POST['serial_number'],
            'location' => $_POST['location'],
            'status' => $_POST['status'],
            'responsible_person' => $_POST['responsible_person'],
            'notes' => $_POST['notes'],
            'created_at' => getClientAdjustedTime(),
            'updated_at' => getClientAdjustedTime()
        ];

        $inventory['items'][] = $newItem;
        saveJson('data/inventory.json', $inventory);
        logActivity($userId, 'add_inventory', "Добавлено новое оборудование: {$_POST['name']}");
        header("Location: inventory.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инвентаризация оборудования</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box {
            flex: 1;
            min-width: 200px;
        }
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .pagination a {
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
        }
        .pagination a.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .table-container {
            overflow-x: auto;
        }
        .button-group {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php renderMenu($userId, $userRole, $firstName, $lastName, 'inventory'); ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Инвентаризация оборудования</h1>
                <div class="button-group">
                    <button class="btn" onclick="openModal('add-item-modal')">Добавить оборудование</button>
                    <a href="export_inventory.php" class="btn">Экспорт в Excel</a>
                </div>
            </div>

            <div class="filters">
                <select onchange="updateFilters()" id="categoryFilter">
                    <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>Все категории</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $filterCategory === $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="searchInput" class="search-box" placeholder="Поиск..." value="<?= htmlspecialchars($searchQuery) ?>" onkeyup="debounceSearch()">
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>
                                <a href="?sort=name&order=<?= $sortBy === 'name' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $page ?>">
                                    Название <?= $sortBy === 'name' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=category&order=<?= $sortBy === 'category' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $page ?>">
                                    Категория <?= $sortBy === 'category' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=serial_number&order=<?= $sortBy === 'serial_number' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $page ?>">
                                    Серийный номер <?= $sortBy === 'serial_number' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=location&order=<?= $sortBy === 'location' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $page ?>">
                                    Местоположение <?= $sortBy === 'location' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars(array_values(array_filter($categories, function($cat) use ($item) { 
                                return $cat['id'] === $item['category']; 
                            }))[0]['name'] ?? 'Не указано') ?></td>
                            <td><?= htmlspecialchars($item['serial_number']) ?></td>
                            <td><?= htmlspecialchars($item['location']) ?></td>
                            <td><?= htmlspecialchars($item['status']) ?></td>
                            <td>
                                <button class="btn" onclick="editItem('<?= $item['id'] ?>')">Редактировать</button>
                                <button class="btn btn-danger" onclick="deleteItem('<?= $item['id'] ?>')">Удалить</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>">««</a>
                    <a href="?page=<?= $page - 1 ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>">«</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?= $i ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>">»</a>
                    <a href="?page=<?= $totalPages ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>&category=<?= $filterCategory ?>&search=<?= urlencode($searchQuery) ?>">»»</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно добавления оборудования -->
    <div id="add-item-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Добавить оборудование</h2>
                <button class="modal-close" onclick="closeModal('add-item-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="add-item-form" action="inventory.php" method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="name">Название</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Категория</label>
                        <select id="category" name="category" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="serial_number">Серийный номер</label>
                        <input type="text" id="serial_number" name="serial_number" required>
                    </div>
                    <div class="form-group">
                        <label for="location">Местоположение</label>
                        <input type="text" id="location" name="location" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Статус</label>
                        <select id="status" name="status" required>
                            <option value="active">В работе</option>
                            <option value="repair">В ремонте</option>
                            <option value="storage">На складе</option>
                            <option value="decommissioned">Списано</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="responsible_person">Ответственное лицо</label>
                        <input type="text" id="responsible_person" name="responsible_person" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Примечания</label>
                        <textarea id="notes" name="notes"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" onclick="closeModal('add-item-modal')">Отмена</button>
                        <button type="submit" class="btn btn-success">Добавить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let searchTimeout;

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateFilters, 300);
        }

        function updateFilters() {
            const category = document.getElementById('categoryFilter').value;
            const search = document.getElementById('searchInput').value;
            window.location.href = `inventory.php?category=${category}&search=${encodeURIComponent(search)}`;
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function deleteItem(itemId) {
            if (confirm('Вы уверены, что хотите удалить это оборудование?')) {
                window.location.href = 'delete_inventory.php?id=' + itemId;
            }
        }

        function editItem(itemId) {
            window.location.href = 'edit_inventory.php?id=' + itemId;
        }
    </script>
</body>
</html>
