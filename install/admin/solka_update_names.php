<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Localization\Loc;
use Solka\TestUpdater;

// Загружаем языковые файлы
Loc::loadMessages(__FILE__);

// Проверка прав администратора
global $USER, $APPLICATION;
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('SOLKA_ACCESS_DENIED'));
    die();
}

$result = null;
$mode = 'preview';
$error = null;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Создаем временную директорию
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/temp/solka_module/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = basename($_FILES['csv_file']['name']);
    $filePath = $uploadDir . $fileName;
    
    // Проверяем тип файла
    $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($fileType !== 'csv') {
        $error = Loc::getMessage('SOLKA_ERROR_CSV_ONLY');
    } elseif (move_uploaded_file($_FILES['csv_file']['tmp_name'], $filePath)) {
        // Определяем режим работы
        $mode = $_POST['mode'] ?? 'preview';
        $updateMode = ($mode === 'update');
        
        // Вызываем обработчик
        ob_start();
        TestUpdater::process($filePath, $updateMode);
        $output = ob_get_clean();
        
        // Парсим JSON результат
        $result = json_decode($output, true);
        
        // Удаляем временный файл
        unlink($filePath);
        
        // Удаляем пустую директорию
        if (count(glob($uploadDir . '*')) === 0) {
            rmdir($uploadDir);
        }
    } else {
        $error = Loc::getMessage('SOLKA_ERROR_UPLOAD');
    }
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

// Подключаем CSS для админки
$APPLICATION->SetAdditionalCSS('/bitrix/admin/interface/styles.css');
?>

<div class="adm-detail-content-wrap">
    <div class="adm-detail-content">
        <div class="adm-detail-title">
            <?= Loc::getMessage('SOLKA_UPDATE_NAMES_TITLE') ?>
        </div>
        
        <?php if ($error): ?>
            <div class="adm-info-message-wrap adm-info-message-red">
                <div class="adm-info-message">
                    <div class="adm-info-message-title"><?= Loc::getMessage('SOLKA_ERROR_TITLE') ?></div>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" class="adm-detail-form">
            <div class="adm-detail-tabs-block">
                <div class="adm-detail-tabs">
                    <div class="adm-detail-tab active" style="padding: 20px;">
                        <div class="adm-input-wrap" style="margin-bottom: 20px;">
                            <label class="adm-input-label"><?= Loc::getMessage('SOLKA_CSV_FILE') ?></label>
                            <div class="adm-input-file">
                                <input type="file" name="csv_file" accept=".csv" required class="adm-designed-file">
                                <div class="adm-input-file-btn"><?= Loc::getMessage('SOLKA_CHOOSE_FILE') ?></div>
                                <div class="adm-input-file-text"><?= Loc::getMessage('SOLKA_NO_FILE') ?></div>
                            </div>
                            <div class="adm-input-hint"><?= Loc::getMessage('SOLKA_CSV_FORMAT') ?></div>
                        </div>
                        
                        <div class="adm-input-wrap" style="margin-bottom: 20px;">
                            <label class="adm-input-label"><?= Loc::getMessage('SOLKA_WORK_MODE') ?></label>
                            <div class="adm-radio-wrap">
                                <label class="adm-radio">
                                    <input type="radio" name="mode" value="preview" <?= ($mode === 'preview' ? 'checked' : '') ?>>
                                    <span class="adm-radio-icon"></span>
                                    <span class="adm-radio-label"><?= Loc::getMessage('SOLKA_MODE_PREVIEW') ?></span>
                                </label>
                                <label class="adm-radio" style="margin-left: 30px;">
                                    <input type="radio" name="mode" value="update">
                                    <span class="adm-radio-icon"></span>
                                    <span class="adm-radio-label"><?= Loc::getMessage('SOLKA_MODE_UPDATE') ?></span>
                                </label>
                            </div>
                            <div class="adm-input-hint" style="color: #ff0000;">
                                <strong><?= Loc::getMessage('SOLKA_WARNING') ?></strong> <?= Loc::getMessage('SOLKA_WARNING_TEXT') ?>
                            </div>
                        </div>
                        
                        <div class="adm-detail-content-btns-wrap">
                            <button type="submit" class="adm-btn <?= $mode === 'update' ? 'adm-btn-danger' : 'adm-btn-success' ?>" style="margin-right: 10px;">
                                <?= $mode === 'update' 
                                    ? Loc::getMessage('SOLKA_BUTTON_UPDATE') 
                                    : Loc::getMessage('SOLKA_BUTTON_PREVIEW') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <?php if ($result): ?>
            <div style="margin-top: 40px; border-top: 2px solid #e0e0e0; padding-top: 20px;">
                <h2 class="adm-title"><?= Loc::getMessage('SOLKA_RESULTS_TITLE') ?></h2>
                
                <?php if (isset($result['errors']) && count($result['errors']) > 0): ?>
                    <div class="adm-info-message-wrap adm-info-message-red" style="margin: 20px 0;">
                        <div class="adm-info-message">
                            <div class="adm-info-message-title"><?= Loc::getMessage('SOLKA_ERRORS_TITLE') ?></div>
                            <ul>
                                <?php foreach ($result['errors'] as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($result['summary'])): ?>
                    <div class="adm-info-message-wrap adm-info-message-green" style="margin: 20px 0;">
                        <div class="adm-info-message">
                            <div class="adm-info-message-title"><?= Loc::getMessage('SOLKA_SUMMARY_TITLE') ?></div>
                            <div><?= Loc::getMessage('SOLKA_AUTOS_UPDATED') ?>: <?= $result['summary']['autos_updated'] ?></div>
                            <div><?= Loc::getMessage('SOLKA_PARTS_UPDATED') ?>: <?= $result['summary']['parts_updated'] ?></div>
                            <div><?= Loc::getMessage('SOLKA_TOTAL_PARTS') ?>: <?= $result['summary']['total_parts'] ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <h3 class="adm-title-3"><?= Loc::getMessage('SOLKA_UPDATED_ITEMS') ?> (<?= count($result['updated'] ?? []) ?>)</h3>
                
                <?php foreach ($result['updated'] ?? [] as $item): ?>
                    <div class="adm-detail-block" style="margin: 15px 0; border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px;">
                        <div class="adm-detail-block-title">
                            <?= Loc::getMessage('SOLKA_AUTO_ID') ?>: <?= $item['AUTO_ID'] ?>
                        </div>
                        <div style="margin: 10px 0;">
                            <div><strong><?= Loc::getMessage('SOLKA_SECTION_NAME') ?>:</strong></div>
                            <div style="color: #666; text-decoration: line-through; margin-bottom: 5px;">
                                <?= htmlspecialchars($item['SECTION_OLD']) ?>
                            </div>
                            <div style="color: #00a300; font-weight: bold;">
                                <?= htmlspecialchars($item['SECTION_NEW']) ?>
                                <?php if ($item['AUTO_UPDATED']): ?>
                                    <span style="color: #00a300; margin-left: 10px;">✓ <?= Loc::getMessage('SOLKA_UPDATED') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="margin: 10px 0;">
                            <strong><?= Loc::getMessage('SOLKA_PARTS_COUNT') ?>:</strong> <?= $item['PARTS_COUNT'] ?>
                        </div>
                        
                        <?php if (count($item['PARTS']) > 0): ?>
                            <div class="adm-detail-block-toggle">
                                <a href="javascript:void(0)" class="adm-detail-block-link" onclick="toggleParts(<?= $item['AUTO_ID'] ?>)">
                                    <?= Loc::getMessage('SOLKA_SHOW_PARTS') ?> (<?= count($item['PARTS']) ?>)
                                </a>
                                <div id="parts_<?= $item['AUTO_ID'] ?>" style="display: none; margin-top: 10px;">
                                    <table class="adm-detail-content-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 80px;"><?= Loc::getMessage('SOLKA_PART_ID') ?></th>
                                                <th><?= Loc::getMessage('SOLKA_OLD_NAME') ?></th>
                                                <th><?= Loc::getMessage('SOLKA_NEW_NAME') ?></th>
                                                <th style="width: 100px;"><?= Loc::getMessage('SOLKA_STATUS') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($item['PARTS'] as $part): ?>
                                                <tr>
                                                    <td><?= $part['PART_ID'] ?></td>
                                                    <td style="color: #666;"><?= htmlspecialchars($part['OLD_NAME']) ?></td>
                                                    <td style="color: #00a300;"><?= htmlspecialchars($part['NEW_NAME']) ?></td>
                                                    <td>
                                                        <?php if ($part['UPDATED']): ?>
                                                            <span style="color: #00a300;">✓ <?= Loc::getMessage('SOLKA_UPDATED') ?></span>
                                                        <?php else: ?>
                                                            <span style="color: #666;"><?= Loc::getMessage('SOLKA_PENDING') ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <script>
            function toggleParts(autoId) {
                var element = document.getElementById('parts_' + autoId);
                if (element.style.display === 'none') {
                    element.style.display = 'block';
                } else {
                    element.style.display = 'none';
                }
            }
            </script>
        <?php endif; ?>
    </div>
</div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
?>