<?php
session_start();
error_reporting(0);

// تنظیمات پایه
$baseDir = __DIR__ . '/databases/';
if (!file_exists($baseDir)) mkdir($baseDir, 0755, true);

// مسیریابی
$action = $_GET['action'] ?? 'home';
$dbName = $_GET['db'] ?? '';
$tableName = $_GET['table'] ?? '';

// پردازش POST برای ساخت دیتابیس
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_name'])) {
    $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name']);
    $dbFile = $baseDir . "{$dbName}.sqlite";
    
    try {
        $db = new SQLite3($dbFile);
        
        foreach ($_POST['tables'] as $table) {
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $table['name']);
            $columns = [];
            
            foreach ($table['fields'] as $field) {
                $fieldName = preg_replace('/[^a-zA-Z0-9_]/', '', $field['name']);
                $fieldType = strtoupper($field['type']);
                $constraints = [];
                
                if ($fieldType === 'VARCHAR' && !empty($field['length'])) {
                    $specifics = "({$field['length']})";
                } else {
                    $specifics = '';
                }
                
                $primary = isset($field['primary']) ? 'yes' : 'no';
                $autoIncrement = isset($field['auto_increment']) ? 'yes' : 'no';
                $notNull = isset($field['not_null']) ? 'yes' : 'no';
                
                if ($primary === 'yes') $constraints[] = 'PRIMARY KEY';
                if ($autoIncrement === 'yes' && $fieldType === 'INTEGER') $constraints[] = 'AUTOINCREMENT';
                if ($notNull === 'yes') $constraints[] = 'NOT NULL';
                if (!empty($field['default'])) $constraints[] = "DEFAULT '{$field['default']}'";
                
                $columns[] = "{$fieldName} {$fieldType}{$specifics} " . implode(' ', $constraints);
            }
            
            $query = "CREATE TABLE IF NOT EXISTS {$tableName} (" . implode(', ', $columns) . ")";
            $db->exec($query);
        }
        
        $_SESSION['message'] = "<div class='success'>✅ دیتابیس <strong>{$dbName}.sqlite</strong> ساخته شد! <a href='?action=manage&db={$dbName}'>مدیریت دیتابیس</a></div>";
        header("Location: ?");
        exit;
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// سیستم CRUD
function getDbConnection($dbName) {
    $dbFile = __DIR__ . "/databases/{$dbName}.sqlite";
    if (!file_exists($dbFile)) die("دیتابیس وجود ندارد!");
    return new SQLite3($dbFile);
}

function showTableData($db, $table) {
    $result = $db->query("SELECT * FROM $table");
    $columns = [];
    $rows = [];
    for ($i = 0; $i < $result->numColumns(); $i++) {
        $columns[] = $result->columnName($i);
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return ['columns' => $columns, 'rows' => $rows];
}

// لیست دیتابیس‌ها
function getDatabaseList() {
    $baseDir = __DIR__ . '/databases/';
    $databases = [];
    if (file_exists($baseDir)) {
        foreach (scandir($baseDir) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sqlite') {
                $databases[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }
    }
    return $databases;
}

// تابع برای بررسی نوع فایل
function isImage($data) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($data);
    return strpos($mime, 'image/') === 0;
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دیتابیس SQLite</title>
    <style>
        @import url('https://fonts.googleapis.com/css?family=Vazirmatn:400,500,700');
        * {
            font-family: 'Vazirmatn','Calibri';
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #f5f5f5;
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-title {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            #margin-bottom: 20px;
        }

        .page-title2 {
            #font-size: 24px;
            #font-weight: bold;
            text-align: center;
            #margin-bottom: 20px;
        }

        .form-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table {
            border: 1px solid #e0e0e0;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }

        .field {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
        }

        input[type=checkbox] {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 20%;
            font-size: 14px;
        }

        input, select {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 70%;
            font-size: 14px;
        }

        button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 14px;
            margin: 5px;
        }

        .add-btn {
            background:rgb(102, 179, 242);
            color: white;
        }

        .delete-btn {
            background:rgb(255, 84, 84);
            color: white;
        }

        .submit-btn {
            background: #4CAF50;
            color: white;
            padding: 12px 25px;
        }

        .success {
            background: #dff0d8;
            color: #3c763d;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .field-options {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .field-options label {
            display: block;
            margin: 8px 0;
        }

        .crud-container {
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .data-table th, .data-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: right;
        }

        .data-table th {
            background: #f8f9fa;
        }

        .action-links a {
            display: inline-block;
            margin: 0 5px;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
        }

        .edit-link { background: #ffc107; color: black; }
        .delete-link { background: #dc3545; color: white; }
        .add-link { background: #28a745; color: white; }

        .database-list {
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .database-list h2 {
            margin-bottom: 15px;
        }

        .database-list ul {
            list-style: none;
            padding: 0;
        }

        .database-list ul li {
            margin: 10px 0;
        }

        .database-list ul li a {
            display: inline-block;
            padding: 8px 15px;
            background:rgb(23, 96, 156);
            color: white;
            border-radius: 4px;
            text-decoration: none;
        }

        .database-list ul li a:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="page-title">مدیریت دیتابیس SQLite </div>
    <div class="page-title2"> SQLɪᴛᴇ ᴅᴀᴛᴀʙᴀsᴇ ᴍᴀɴᴀɢᴇᴍᴇɴᴛ  </div>

    <!-- لیست دیتابیس‌ها -->
    <div class="database-list">
        <div><b>📂 فهرست بانکهای اطلاعاتی -</b> Dᴀᴛᴀʙᴀsᴇ ʟɪsᴛ</div>
        
        <?php
        $databases = getDatabaseList();
        if (empty($databases)): ?>
            <p>هیچ دیتابیسی هنوز وجود ندارد.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($databases as $db): ?>
                    <li>
                        <a href="?action=manage&db=<?= $db ?>"><?= $db ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if($action === 'home'): ?>
        <!-- فرم اصلی ساخت دیتابیس -->
        <?= $_SESSION['message'] ?? '' ?>
        <?php unset($_SESSION['message']); ?>
        <form method="POST">
            <div class="form-section">
                <h2>⚙️ ساخت دیتابیس - Cʀᴇᴀᴛᴇ </h2>
                <input type="text" name="db_name" placeholder="نام دیتابیس - Dᴀᴛᴀʙᴀsᴇ ɴᴀᴍᴇ" required>
            </div>

            <div class="form-section" id="tables-section">
                <h2>📊 جداول - Tᴀʙʟᴇs</h2>
                <div class="table" data-table-index="0">
                    <div class="table-header">
                        <input type="text" name="tables[0][name]" placeholder="نام جدول - Tᴀʙʟᴇ ɴᴀᴍᴇ" required>
                        <button type="button" onclick="removeTable(this)" class="delete-btn">🗑️</button>
                    </div>

                    <div class="fields">
                        <div class="field" data-field-index="0">
                            <div class="field-header">
                                <input type="text" name="tables[0][fields][0][name]" placeholder="نام فیلد - Fɪᴇʟᴅ ɴᴀᴍᴇ" required>
                                <button type="button" onclick="removeField(this)" class="delete-btn">🗑️</button>
                            </div>

                            <select name="tables[0][fields][0][type]" class="field-type" required onchange="handleFieldType(this)">
                                <option value="INTEGER">INTEGER (عدد صحیح)</option>
                                <option value="VARCHAR">VARCHAR (متن کوتاه)</option>
                                <option value="TEXT">TEXT (متن بلند)</option>
                                <option value="REAL">REAL (اعشاری)</option>
                                <option value="BOOLEAN">BOOLEAN (منطقی)</option>
                                <option value="DATE">DATE (تاریخ)</option>
                                <option value="BLOB">BLOB (فایل/عکس)</option>
                            </select>

                            <div class="field-options">
                                <label>
                                    <input type="checkbox" name="tables[0][fields][0][primary]" value="yes">
                                    🔑 Pʀɪᴍᴀʀʏ ᴋᴇʏ
                                </label>
                                <label>
                                    <input type="checkbox" name="tables[0][fields][0][auto_increment]" value="yes">
                                    🔄 Aᴜᴛᴏ ɪɴᴄʀᴇᴍᴇɴᴛ
                                </label>
                                <label>
                                    <input type="checkbox" name="tables[0][fields][0][not_null]" value="yes">
                                    🚫 Nᴏᴛ Nᴜʟʟ
                                </label>
                                <div class="length-input" style="display: none;">
                                    <input type="number" name="tables[0][fields][0][length]" 
                                           min="1" max="65535" 
                                           placeholder="طول VARCHAR" 
                                           value="255">
                                </div>
                                <input type="text" name="tables[0][fields][0][default]" 
                                       placeholder="مقدار پیشفرض -  Dᴇғᴀᴜʟᴛ ᴠᴀʟᴜᴇ">
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addField(this)" class="add-btn">➕ فیلد جدید - Aᴅᴅ ғɪᴇʟᴅ</button>
                </div>
            </div>

            <div class="action-buttons">
                <button type="button" onclick="addTable()" class="add-btn">➕ جدول جدید - Aᴅᴅ ᴛᴀʙʟᴇ</button>
                <button type="submit" class="submit-btn">🚀 ساختن - Cʀᴇᴀᴛᴇ</button>
            </div>
        </form>

    <?php elseif($action === 'manage' && !empty($dbName)): ?>
        <!-- رابط مدیریت دیتابیس -->
        <div class="crud-container">
            <h2>📊 مدیریت دیتابیس: <?= $dbName ?></h2>
            <?php
            $db = getDbConnection($dbName);
            $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
            while ($table = $tables->fetchArray(SQLITE3_ASSOC)): 
                $tableName = $table['name'];
                $data = showTableData($db, $tableName);
            ?>
                <div class="table-section">
                    <h3>📌 جدول: <?= $tableName ?> 
                        <a href="?action=add&db=<?= $dbName ?>&table=<?= $tableName ?>" class="add-link">➕ افزودن رکورد</a>
                    </h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php foreach ($data['columns'] as $col): ?>
                                    <th><?= $col ?></th>
                                <?php endforeach; ?>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($row as $key => $val): ?>
                                        <td>
                                            <?php if ($key === 'id'): ?>
                                                <?= htmlspecialchars($val) ?>
                                            <?php else: ?>
                                                <?php
                                                $columnInfo = $db->querySingle("PRAGMA table_info($tableName)", true);
                                                $isBlob = false;
                                                $isBoolean = false;
                                                foreach ($columnInfo as $colInfo) {
                                                    if ($colInfo['name'] === $key && $colInfo['type'] === 'BLOB') {
                                                        $isBlob = true;
                                                    }
                                                    if ($colInfo['name'] === $key && $colInfo['type'] === 'BOOLEAN') {
                                                        $isBoolean = true;
                                                    }
                                                }
                                                if ($isBlob && isImage($val)): ?>
                                                    <img src="data:image/jpeg;base64,<?= base64_encode($val) ?>" alt="تصویر" style="max-width: 100px; max-height: 100px;">
                                                <?php elseif ($isBoolean): ?>
                                                    <?= $val ? 'true' : 'false' ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($val) ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="action-links">
                                        <a href="?action=edit&db=<?= $dbName ?>&table=<?= $tableName ?>&id=<?= $row['id'] ?>" class="edit-link">✏️ ویرایش</a>
                                        <a href="?action=delete&db=<?= $dbName ?>&table=<?= $tableName ?>&id=<?= $row['id'] ?>" class="delete-link" onclick="return confirm('آیا مطمئن هستید؟')">🗑️ حذف</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endwhile; ?>
            <a href="?" class="add-btn">🔙 بازگشت</a>
        </div>

    <?php elseif($action === 'add' && !empty($dbName) && !empty($tableName)): ?>
        <!-- فرم افزودن رکورد -->
        <div class="crud-container">
            <h2>➕ افزودن رکورد جدید به جدول <?= $tableName ?></h2>
            <form method="POST" action="?action=save&db=<?= $dbName ?>&table=<?= $tableName ?>" enctype="multipart/form-data">
                <?php
                $db = getDbConnection($dbName);
                $columns = $db->query("PRAGMA table_info($tableName)");
                while ($col = $columns->fetchArray(SQLITE3_ASSOC)):
                    if ($col['pk'] == 1) continue;
                ?>
                    <div class="form-group">
                        <label><?= $col['name'] ?> (<?= $col['type'] ?>)</label>
                        <?php if ($col['type'] === 'BLOB'): ?>
                            <input type="file" name="<?= $col['name'] ?>" <?= $col['notnull'] ? 'required' : '' ?>>
                        <?php elseif ($col['type'] === 'BOOLEAN'): ?>
                            <input type="checkbox" name="<?= $col['name'] ?>" value="1">
                        <?php elseif ($col['type'] === 'DATE'): ?>
                            <input type="date" name="<?= $col['name'] ?>" <?= $col['notnull'] ? 'required' : '' ?>>
                        <?php else: ?>
                            <input type="text" name="<?= $col['name'] ?>" <?= $col['notnull'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
                <button type="submit" class="submit-btn">💾 ذخیره</button>
                <a href="?action=manage&db=<?= $dbName ?>" class="delete-btn">لغو</a>
            </form>
        </div>

    <?php elseif($action === 'edit' && !empty($dbName) && !empty($tableName) && isset($_GET['id'])): ?>
        <!-- فرم ویرایش رکورد -->
        <div class="crud-container">
            <h2>✏️ ویرایش رکورد در جدول <?= $tableName ?></h2>
            <?php
            $db = getDbConnection($dbName);
            $id = $_GET['id'];
            $stmt = $db->prepare("SELECT * FROM $tableName WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            ?>
            <form method="POST" action="?action=save&db=<?= $dbName ?>&table=<?= $tableName ?>&id=<?= $id ?>" enctype="multipart/form-data">
                <?php
                $columns = $db->query("PRAGMA table_info($tableName)");
                while ($col = $columns->fetchArray(SQLITE3_ASSOC)):
                    if ($col['pk'] == 1) continue;
                ?>
                    <div class="form-group">
                        <label><?= $col['name'] ?></label>
                        <?php if ($col['type'] === 'BLOB'): ?>
                            <input type="file" name="<?= $col['name'] ?>" <?= $col['notnull'] ? 'required' : '' ?>>
                            <?php if (!empty($row[$col['name']]) && isImage($row[$col['name']])): ?>
                                <img src="data:image/jpeg;base64,<?= base64_encode($row[$col['name']]) ?>" alt="تصویر فعلی" style="max-width: 100px; max-height: 100px;">
                            <?php endif; ?>
                        <?php elseif ($col['type'] === 'BOOLEAN'): ?>
                            <input type="checkbox" name="<?= $col['name'] ?>" value="1" <?= $row[$col['name']] ? 'checked' : '' ?>>
                        <?php elseif ($col['type'] === 'DATE'): ?>
                            <input type="date" name="<?= $col['name'] ?>" value="<?= htmlspecialchars($row[$col['name']] ?? '') ?>" <?= $col['notnull'] ? 'required' : '' ?>>
                        <?php else: ?>
                            <input type="text" name="<?= $col['name'] ?>" value="<?= htmlspecialchars($row[$col['name']] ?? '') ?>" <?= $col['notnull'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
                <button type="submit" class="submit-btn">💾 ذخیره تغییرات</button>
                <a href="?action=manage&db=<?= $dbName ?>" class="delete-btn">لغو</a>
            </form>
        </div>

    <?php elseif($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <!-- پردازش ذخیره اطلاعات -->
        <?php
        $db = getDbConnection($dbName);
        $columns = [];
        $placeholders = [];
        $values = [];
        
        $tableInfo = $db->query("PRAGMA table_info($tableName)");
        while ($col = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
            if ($col['pk'] == 1) continue;
            $columns[] = $col['name'];
            $placeholders[] = ":{$col['name']}";
            if ($col['type'] === 'BLOB' && isset($_FILES[$col['name']]) && $_FILES[$col['name']]['error'] === UPLOAD_ERR_OK) {
                $values[":{$col['name']}"] = file_get_contents($_FILES[$col['name']]['tmp_name']);
            } elseif ($col['type'] === 'BOOLEAN') {
                $values[":{$col['name']}"] = isset($_POST[$col['name']]) ? 1 : 0;
            } else {
                $values[":{$col['name']}"] = $_POST[$col['name']] ?? null;
            }
        }
        
        if (isset($_GET['id'])) {
            // Update
            $setClause = implode(', ', array_map(fn($c) => "$c = :$c", $columns));
            $stmt = $db->prepare("UPDATE $tableName SET $setClause WHERE id = :id");
            $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO $tableName (" . implode(', ', $columns) . ") 
                                VALUES (" . implode(', ', $placeholders) . ")");
        }
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div class='success'>✅ تغییرات با موفقیت ذخیره شد!</div>";
        } else {
            $_SESSION['message'] = "<div class='error'>❌ خطا در ذخیره اطلاعات</div>";
        }
        header("Location: ?action=manage&db=$dbName");
        exit;
        ?>

    <?php elseif($action === 'delete' && isset($_GET['id'])): ?>
        <!-- پردازش حذف رکورد -->
        <?php
        $db = getDbConnection($dbName);
        $stmt = $db->prepare("DELETE FROM $tableName WHERE id = :id");
        $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div class='success'>✅ رکورد با موفقیت حذف شد!</div>";
        } else {
            $_SESSION['message'] = "<div class='error'>❌ خطا در حذف رکورد</div>";
        }
        header("Location: ?action=manage&db=$dbName");
        exit;
        ?>
    <?php endif; ?>

    <!-- اسکریپت‌های جاوااسکریپت -->
    <script>
        function handleFieldType(select) {
            const field = select.closest('.field');
            const lengthInput = field.querySelector('.length-input');
            const autoIncrement = field.querySelector('input[name*="auto_increment"]');

            lengthInput.style.display = (select.value === 'VARCHAR') ? 'block' : 'none';
            autoIncrement.disabled = (select.value !== 'INTEGER');
            if (select.value !== 'INTEGER') autoIncrement.checked = false;
        }

        function addTable() {
            const tablesSection = document.getElementById('tables-section');
            const newIndex = tablesSection.querySelectorAll('.table').length;
            
            const newTableHTML = `
                <div class="table" data-table-index="${newIndex}">
                    <div class="table-header">
                        <input type="text" name="tables[${newIndex}][name]" placeholder="نام جدول - Tᴀʙʟᴇ ɴᴀᴍᴇ" required>
                        <button type="button" onclick="removeTable(this)" class="delete-btn">🗑️</button>
                    </div>
                    <div class="fields">
                        <div class="field" data-field-index="0">
                            <div class="field-header">
                                <input type="text" name="tables[${newIndex}][fields][0][name]" placeholder="نام فیلد - Fɪᴇʟᴅ ɴᴀᴍᴇ" required>
                                <button type="button" onclick="removeField(this)" class="delete-btn">🗑️</button>
                            </div>
                            <select name="tables[${newIndex}][fields][0][type]" class="field-type" required onchange="handleFieldType(this)">
                                <option value="INTEGER">INTEGER</option>
                                <option value="VARCHAR">VARCHAR</option>
                                <option value="TEXT">TEXT</option>
                                <option value="REAL">REAL</option>
                                <option value="BOOLEAN">BOOLEAN</option>
                                <option value="DATE">DATE</option>
                                <option value="BLOB">BLOB</option>
                            </select>
                            <div class="field-options">
                                <label>
                                    <input type="checkbox" name="tables[${newIndex}][fields][0][primary]" value="yes">
                                    🔑 کلید اصلی
                                </label>
                                <label>
                                    <input type="checkbox" name="tables[${newIndex}][fields][0][auto_increment]" value="yes">
                                    🔄 افزایش خودکار
                                </label>
                                <label>
                                    <input type="checkbox" name="tables[${newIndex}][fields][0][not_null]" value="yes">
                                    🚫 خالی نباشد
                                </label>
                                <div class="length-input" style="display: none;">
                                    <input type="number" name="tables[${newIndex}][fields][0][length]" 
                                           min="1" max="65535" 
                                           placeholder="طول VARCHAR" 
                                           value="255">
                                </div>
                                <input type="text" name="tables[${newIndex}][fields][0][default]" 
                                       placeholder="مقدار پیشفرض (اختیاری)">
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addField(this)" class="add-btn">➕ فیلد جدید - Aᴅᴅ ғɪᴇʟᴅ</button>
                </div>
            `;
            
            tablesSection.insertAdjacentHTML('beforeend', newTableHTML);
        }

        function addField(button) {
            const table = button.closest('.table');
            const fieldsContainer = table.querySelector('.fields');
            const newFieldIndex = fieldsContainer.children.length;
            const tableIndex = table.dataset.tableIndex;

            const newFieldHTML = `
                <div class="field" data-field-index="${newFieldIndex}">
                    <div class="field-header">
                        <input type="text" name="tables[${tableIndex}][fields][${newFieldIndex}][name]" placeholder="نام فیلد - Fɪᴇʟᴅ ɴᴀᴍᴇ" required>
                        <button type="button" onclick="removeField(this)" class="delete-btn">🗑️</button>
                    </div>
                    <select name="tables[${tableIndex}][fields][${newFieldIndex}][type]" class="field-type" required onchange="handleFieldType(this)">
                        <option value="INTEGER">INTEGER</option>
                        <option value="VARCHAR">VARCHAR</option>
                        <option value="TEXT">TEXT</option>
                        <option value="REAL">REAL</option>
                        <option value="BOOLEAN">BOOLEAN</option>
                        <option value="DATE">DATE</option>
                        <option value="BLOB">BLOB</option>
                    </select>
                    <div class="field-options">
                        <label>
                            <input type="checkbox" name="tables[${tableIndex}][fields][${newFieldIndex}][primary]" value="yes">
                            🔑 کلید اصلی
                        </label>
                        <label>
                            <input type="checkbox" name="tables[${tableIndex}][fields][${newFieldIndex}][auto_increment]" value="yes">
                            🔄 افزایش خودکار
                        </label>
                        <label>
                            <input type="checkbox" name="tables[${tableIndex}][fields][${newFieldIndex}][not_null]" value="yes">
                            🚫 NOT NULL
                        </label>
                        <div class="length-input" style="display: none;">
                            <input type="number" name="tables[${tableIndex}][fields][${newFieldIndex}][length]" 
                                   min="1" max="65535" 
                                   placeholder="طول VARCHAR" 
                                   value="255">
                        </div>
                        <input type="text" name="tables[${tableIndex}][fields][${newFieldIndex}][default]" 
                               placeholder="مقدار پیشفرض (اختیاری)">
                    </div>
                </div>
            `;

            fieldsContainer.insertAdjacentHTML('beforeend', newFieldHTML);
        }

        function removeTable(button) {
            const tables = document.querySelectorAll('.table');
            if (tables.length <= 1) return alert("حداقل یک جدول باید وجود داشته باشد!");
            button.closest('.table').remove();
        }

        function removeField(button) {
            const fields = button.closest('.fields').querySelectorAll('.field');
            if (fields.length <= 1) return alert("حداقل یک فیلد باید وجود داشته باشد!");
            button.closest('.field').remove();
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.field-type').forEach(select => {
                select.addEventListener('change', () => handleFieldType(select));
                handleFieldType(select);
            });
        });
    </script>
</body>
</html>