<?php
// /local/modules/solka_module/install/admin/autoparts_updater_admin.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

// Проверяем авторизацию
global $USER, $APPLICATION;
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm("Требуются права администратора");
}

// Подключаем модуль
if (!CModule::IncludeModule('solka_module')) {
    $APPLICATION->AuthForm('Модуль не установлен');
}

// Устанавливаем заголовок
$APPLICATION->SetTitle('Обновление авто из CSV');

// Обработка загрузки файла
$messageType = '';
$messageText = '';
$messageDetails = '';
$logContent = SolkaAutoPartsUpdater::getUpdateLog();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv']) && check_bitrix_sessid()) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            // Создаем директорию для загрузки
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/solka_updater/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Сохраняем файл
            $fileName = 'update_' . date('Ymd_His') . '.csv';
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // ИСПРАВЛЕНО: вызываем правильный метод updateFromCsv
                $result = SolkaAutoPartsUpdater::updateFromCsv($filePath);
                
                if ($result['success']) {
                    $messageType = 'OK';
                    $messageText = $result['message'];
                    $messageDetails = "
                        Обработано записей: {$result['updates_count']}<br>
                        Обновлено разделов: {$result['stats']['sections']}<br>
                        Обновлено свойств: {$result['stats']['properties']}<br>
                        Обновлено элементов: {$result['stats']['elements']}<br>
                        Обновлено значений: {$result['stats']['enum_values']}
                    ";
                    
                    // Обновляем лог на странице
                    $logContent = SolkaAutoPartsUpdater::getUpdateLog();
                    
                } else {
                    $messageType = 'ERROR';
                    $messageText = 'Ошибка обновления';
                    $messageDetails = $result['message'];
                }
                
                // Удаляем временный файл
                unlink($filePath);
                
            } else {
                $messageType = 'ERROR';
                $messageText = 'Ошибка сохранения файла';
                $messageDetails = '';
            }
        } else {
            $messageType = 'ERROR';
            $messageText = 'Неверный формат файла';
            $messageDetails = 'Загрузите файл в формате CSV';
        }
    } else {
        $messageType = 'ERROR';
        $messageText = 'Файл не загружен';
        $messageDetails = 'Выберите CSV файл для загрузки';
    }
}

// Тестируем подключение
$testResult = SolkaAutoPartsUpdater::testConnection();

// Показываем сообщение если есть
if (!empty($messageType)) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => $messageText,
        'TYPE' => $messageType,
        'DETAILS' => $messageDetails,
        'HTML' => true
    ]);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="adm-detail-content">
    
    <!-- Заголовок -->
    <div class="adm-detail-title">Обновление наименований авто из CSV</div>
    
    <!-- Форма загрузки -->
    <div class="adm-detail-content-item-block">
        <div class="adm-detail-content-item">
            <div class="adm-detail-title">Загрузите CSV файл</div>
            
            <form method="post" enctype="multipart/form-data" class="adm-detail-form">
                <?php echo bitrix_sessid_post(); ?>
                
                <div class="adm-input-wrap" style="margin: 20px 0;">
                    <div class="adm-input-file">
                        <input type="file" name="csv_file" accept=".csv" required 
                               style="padding: 10px; border: 2px dashed #0066cc; width: 100%;">
                    </div>
                </div>
                
                <div class="adm-info-message-wrap" style="margin: 20px 0;">
                    <div class="adm-info-message">
                        <strong>📋 Формат CSV файла:</strong><br>
                        1. Разделитель - запятая<br>
                        2. Кодировка - UTF-8<br>
                        3. Колонки:<br>
                           &nbsp;&nbsp;• A: "Как сейчас" (старое название)<br>
                           &nbsp;&nbsp;• B: (пустая колонка)<br>
                           &nbsp;&nbsp;• C: "Как нужно" (новое название)<br>
                        <br>
                        <strong>Пример:</strong><br>
                        <code style="display:block;background:#f5f5f5;padding:10px;margin:5px 0;">
                        Как сейчас,,Как нужно<br>
                        Audi[1216],,Audi[1216]<br>
                        . . . 100[1217],,. . . 100[1217]<br>
                        . . . . . . 100 2 (С2) 1978 - 1982г[1218],,. . . . . . 100 С2 1978 - 1982 [1218]
                        </code>
                    </div>
                </div>
                
                <div class="adm-detail-content-btns-wrap">
                    <input type="submit" name="upload_csv" value="📤 Загрузить и обновить" 
                           class="adm-btn adm-btn-save" style="padding: 12px 30px;">
                </div>
            </form>
        </div>
    </div>
    
    <!-- Проверка системы -->
    <div class="adm-detail-content-item-block">
        <div class="adm-detail-content-item">
            <div class="adm-detail-title">✅ Проверка системы</div>
            
            <table class="adm-detail-content-table" style="width: 100%;">
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">Модуль iblock</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">
                        <?php echo $testResult['iblock_module'] ? '✅' : '❌'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">Модуль main</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">
                        <?php echo $testResult['main_module'] ? '✅' : '❌'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">Директория /upload/</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">
                        <?php echo $testResult['upload_dir'] ? '✅' : '❌'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">ID свойства "применимость"</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">
                        <?php echo $testResult['property_id'] ? '✅ ' . $testResult['property_id'] : '❌ не найден'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">ID инфоблока</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">
                        <?php echo $testResult['iblock_id'] ? '✅ ' . $testResult['iblock_id'] : '❌ не определен'; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- История обновлений -->
    <?php if (!empty($logContent)): ?>
    <div class="adm-detail-content-item-block">
        <div class="adm-detail-content-item">
            <div class="adm-detail-title">📝 История обновлений</div>
            
            <div class="adm-input-wrap" style="margin: 15px 0;">
                <textarea style="width: 100%; height: 300px; font-family: 'Courier New', monospace; 
                              padding: 10px; font-size: 12px; background: #f9f9f9; border: 1px solid #ddd;"
                          readonly><?php echo htmlspecialchars($logContent); ?></textarea>
            </div>
            
            <div class="adm-detail-content-btns-wrap">
                <a href="/upload/solka_updater/update_log.txt" class="adm-btn" download>📥 Скачать полный лог</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Инструкция по созданию CSV -->
    <div class="adm-detail-content-item-block">
        <div class="adm-detail-content-item">
            <div class="adm-detail-title">📖 Как создать CSV из Excel</div>
            
            <div class="adm-info-message-wrap">
                <div class="adm-info-message">
                    <strong>Шаг 1: Откройте Excel файл</strong><br>
                    - Откройте ваш файл "КАТАЛОГ АВТО АМС.xlsx"<br><br>
                    
                    <strong>Шаг 2: Сохраните как CSV</strong><br>
                    - Нажмите "Файл" → "Сохранить как"<br>
                    - Выберите "CSV (разделители - запятые)"<br>
                    - Сохраните файл (например: update.csv)<br><br>
                    
                    <strong>Шаг 3: Проверьте файл</strong><br>
                    - Откройте CSV в Блокноте или Notepad++<br>
                    - Убедитесь что колонки разделены запятыми<br>
                    - Пример правильного формата:<br>
                    <code style="display:block;background:#f5f5f5;padding:10px;margin:5px 0;">
                    Как сейчас,,Как нужно<br>
                    Audi[1216],,Audi[1216]<br>
                    . . . 100[1217],,. . . 100[1217]<br>
                    . . . . . . 100 2 (С2) 1978 - 1982г[1218],,. . . . . . 100 С2 1978 - 1982 [1218]
                    </code>
                </div>
            </div>
            
            <div class="adm-detail-content-btns-wrap" style="margin-top: 20px;">
                <a href="/create_test_csv.php" class="adm-btn" target="_blank">📋 Скачать тестовый CSV</a>
            </div>
        </div>
    </div>

</div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';