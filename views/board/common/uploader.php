<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div id="file">
    <div class="file-group">

        <? if (!empty($files)): ?>
            <div class="existing-files-wrap">
                <span class="existing-label">
                    <i class="fas fa-exclamation-circle"></i> 기존 첨부파일
                </span>

                <div class="file-list">
                    <? foreach ($files as $file): ?>
                        <div class="file-item" id="existing_file_<?= $file['file_id'] ?>">
                            <div class="icon-box <?= $file['color_class'] ?>">
                                <i class="fas <?= $file['icon_class'] ?>"></i>
                            </div>
                            <div class="file-info">
                                <span class="file-name"><?= $file['safe_name'] ?></span>
                                <span class="file-size"><?= $file['size_text'] ?></span>
                            </div>
                            <button type="button" class="btn-remove" onclick="deleteExistingFile(<?= $file['file_id'] ?>)" title="삭제">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <? endforeach; ?>
                </div>
            </div>
        <? endif; ?>

        <div class="file-upload-container">
            <input type="file" id="real_file_upload" name="file_upload[]" multiple style="display:none;">
            <input type="file" id="file_selector" multiple style="display:none;" onchange="handleFileSelect(this)">

            <label for="file_selector" class="upload-box" id="drop_zone">
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="text-group">
                    <span class="upload-text">여기를 클릭하거나 파일을 드래그하세요</span>
                    <span class="upload-hint">최대 5개, 개당 10MB 이하</span>
                </div>
            </label>

            <div id="new_file_label" class="new-file-label" style="display:none;">
                <i class="fas fa-plus-circle"></i> 신규 첨부파일
            </div>

            <div id="file_list_view" class="file-list"></div>
        </div>
    </div>
</div>
