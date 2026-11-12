<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div id="file">

    <div class="form-group file-group">
        <label class="label-required">파일 첨부</label>

        <? if (!empty($files)): ?>
            <div class="existing-files-wrap">
                <span class="existing-label">
                    <i class="fas fa-exclamation-circle"></i> 기존 첨부파일
                </span>
                <div class="file-list-display">
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

            <div id="file_list_view" class="file-list-display"></div>
        </div>
    </div>

    <style>
        /* ========================================================= */
        /* File Uploader Component Style                             */
        /* ========================================================= */

        #file .file-group {
            border-top: 1px solid #333;
            padding-top: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        /* 1. 기존 파일 목록 */
        #file .existing-files-wrap {
            margin-bottom: 30px;
        }

        #file .existing-label {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 12px;
            display: block;
        }

        #file .existing-label i {
            margin-right: 5px;
            color: #f59e0b;
        }

        /* 2. 업로드 박스 */
        #file .file-upload-container {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        #file .upload-box {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: center !important;

            width: 100%;
            height: 70px;
            background-color: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #64748b;
            text-align: center !important;
            gap: 12px;
        }

        #file .upload-box:hover,
        #file .upload-box.drag-over {
            background-color: #eff6ff;
            border-color: #3b82f6;
            color: #3b82f6;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1);
        }

        #file .upload-box i {
            font-size: 20px;
            margin-bottom: 0;
            display: inline-block;
        }

        #file .text-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #file .upload-text {
            font-size: 15px;
            font-weight: 600;
            white-space: nowrap;
        }

        #file .upload-hint {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 2px;
            white-space: nowrap;
        }

        #file .upload-hint::before {
            content: '|';
            margin-right: 10px;
            color: #cbd5e1;
        }

        /* 3. 파일 리스트 그리드 */
        #file .file-list-display {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 25px;
        }

        #file .file-item {
            display: flex;
            align-items: center;
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.2s;
            position: relative;
        }

        #file .file-item:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        #file .icon-box {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-right: 12px;
            flex-shrink: 0;
        }

        /* 아이콘 색상 */
        #file .icon-box.pdf { color: #e11d48; background-color: #fff1f2; }
        #file .icon-box.xls { color: #059669; background-color: #ecfdf5; }
        #file .icon-box.ppt { color: #ea580c; background-color: #fff7ed; }
        #file .icon-box.doc, #file .icon-box.hwp { color: #2563eb; background-color: #eff6ff; }
        #file .icon-box.img { color: #7c3aed; background-color: #f5f3ff; }
        #file .icon-box.zip { color: #d97706; background-color: #fffbeb; }
        #file .icon-box.txt { color: #475569; background-color: #f1f5f9; }
        #file .icon-box.default { color: #64748b; background-color: #f8fafc; }

        #file .file-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        #file .file-name {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #file .file-size {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }

        #file .btn-remove {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #f1f5f9;
            color: #94a3b8;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            margin-left: 8px;
        }

        #file .btn-remove:hover {
            background-color: #fee2e2;
            color: #ef4444;
        }

        /* 모바일 반응형 */
        @media (max-width: 768px) {
            #file .upload-box {
                flex-direction: column !important;
                height: auto;
                padding: 15px 0;
                gap: 5px;
            }

            #file .text-group {
                flex-direction: column;
                gap: 2px;
            }

            #file .upload-hint::before {
                display: none;
            }

            #file .file-list-display {
                grid-template-columns: 1fr;
            }
        }
    </style>
</div>
