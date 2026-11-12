<div id="board" class="unit">
    <?= load_asset('common/style.css') ?>
    <?= load_asset($skin_dir . '/board.css') ?>

    <div class="write-wrap">
        <div class="layout-narrow">
            <h1 class="main-title"><?= $config['title'] ?></h1>

            <form action="<?= $config['action_url'] ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" id="hidden_content" name="content" value="">

                <? if (!empty($categories)): ?>
                    <div class="form-group">
                        <label for="category" class="form-label req">말머리</label>
                        <select name="category" id="category" class="form-input" required>
                            <option value="">말머리를 선택해주세요</option>
                            <? foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= set_select('category', $cat, ($cat === $form['category'])) ?>>
                                    <?= $cat ?>
                                </option>
                            <? endforeach; ?>
                        </select>
                    </div>
                <? endif; ?>

                <div class="form-group">
                    <label for="title" class="form-label req">제목</label>
                    <input type="text" id="title" name="title" class="form-input"
                        value="<?= set_value('title', $form['title']) ?>"
                        required placeholder="제목을 입력해 주세요">
                </div>

                <? if (!$config['is_login']): ?>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="writer" class="form-label req">작성자</label>
                                <input type="text" id="writer" name="writer" class="form-input"
                                    value="<?= set_value('writer', $form['writer']) ?>"
                                    required placeholder="이름">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="password" class="form-label req">비밀번호</label>
                                <input type="password" id="password" name="password" class="form-input"
                                    required placeholder="비밀번호">
                            </div>
                        </div>
                    </div>
                <? endif; ?>

                <? if (validation_errors()): ?>
                    <div class="msg-error">
                        <?= validation_errors(); ?>
                    </div>
                <? endif; ?>

                <div class="form-group">
                    <label for="content" class="form-label req">내용</label>
                    <div style="border:1px solid #eee; border-radius:4px; overflow:hidden;">
                        <? if ($config['use_editor'] === 'minici'): ?>
                            <?= $this->include('board/common/minici_editor', ['content' => $form['content']]) ?>
                        <? else: ?>
                            <link rel="stylesheet" href="https://uicdn.toast.com/editor/3.2.2/toastui-editor.min.css" />
                            <script src="https://uicdn.toast.com/editor/3.2.2/toastui-editor-all.min.js"></script>
                            <script src="https://uicdn.toast.com/editor/3.2.2/i18n/ko-kr.min.js"></script>
                            <div id="toast_editor"></div>
                            <?= load_asset('common/toast-init.js') ?>
                            <script>
                                const TOAST_CONFIG = {
                                    initialValue: <?= json_encode((string)($form['content'] ?? '')) ?>,
                                    csrfName: '<?= csrf_token_name() ?>',
                                    actionUrl: '<?= $config['action_url'] ?>',
                                    uploadUrl: '/board/upload_image'
                                };
                            </script>
                        <? endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="title" class="form-label req">파일 첨부</label>
                    <?= $this->include('board/common/uploader', ['files' => $files]) ?>
                </div>

                <? if ($config['use_secret']): ?>
                    <div class="form-group" style="margin-top:10px;">
                        <label style="cursor:pointer; display:inline-flex; align-items:center;">
                            <input type="checkbox" name="is_secret" value="1" <?= set_checkbox('is_secret', '1', ($form['is_secret'] == 1)) ?> style="width:18px; height:18px; margin-right:8px;">
                            <span style="font-size:15px; color:#555;">이 글을 비밀글로 설정합니다.</span>
                        </label>
                    </div>
                <? endif; ?>

                <div style="display:flex; justify-content:center; gap:10px; margin-top:60px; border-top:1px solid #eee; padding-top:40px;">
                    <button type="button" class="btn basic" onclick="history.back()">취소</button>
                    <button type="submit" class="btn primary" style="min-width:140px; height:50px;"><?= $config['submit_text'] ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BOARD_CONFIG = {
        MAX_FILES: 5,
        CSRF_NAME: '<?= csrf_token_name() ?>'
    };
</script>
<?= load_asset('common/board.js') ?>
