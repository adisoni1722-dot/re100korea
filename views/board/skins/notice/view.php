<div id="board">
    <?= load_asset('common/style.css') ?>
    <?= load_asset($skin_dir . '/board.css') ?>
    <?= load_asset('common/board.js') ?>
    <link rel="stylesheet" href="/assets/common/style.css">

    <div class="view-head">
        <div class="view-head-inner">
            <h1 class="subject"><?= $post['title'] ?></h1>
            <div class="meta">
                <span class="date"><?= $post['date_view'] ?></span>
            </div>
        </div>
    </div>

    <div class="view-body">
        <div class="content">
            <?= $post['content'] ?>
        </div>

        <? if (!empty($files)): ?>
            <div id="file" style="margin-top: 60px;">
                <div class="existing-files-wrap">
                    <span class="existing-label">
                        <i class="fas fa-paperclip"></i>
                        첨부파일 <span style="color:#334155; margin-left:4px;"><?= count($files) ?></span>개
                    </span>

                    <div class="file-list">
                        <? foreach ($files as $file): ?>
                            <div class="file-item">
                                <div class="icon-box <?= $file['color_class'] ?>">
                                    <i class="fas <?= $file['icon_class'] ?>"></i>
                                </div>

                                <div class="file-info">
                                    <a href="/board/download/<?= $file['file_id'] ?>" class="file-name" title="<?= $file['safe_name'] ?>">
                                        <?= $file['safe_name'] ?>
                                    </a>
                                    <span class="file-size"><?= $file['size_text'] ?></span>
                                </div>

                                <a href="/board/download/<?= $file['file_id'] ?>" class="btn-download" title="내 PC 저장">
                                    <i class="fas fa-arrow-down"></i>
                                </a>
                            </div>
                        <? endforeach; ?>
                    </div>
                </div>
            </div>
        <? endif; ?>

        <div class="view-action">
            <div class="left">
                <button type="button" class="btn-copy" onclick="copyContent()">
                    텍스트 복사 <i class="far fa-copy"></i>
                </button>
            </div>
            <div class="right">
                <? if ($can_modify): ?>
                    <a href="<?= $links['modify'] ?>" class="btn-circle" title="글 수정"><i class="fas fa-pen"></i></a>
                    <form action="<?= $links['delete'] ?>" method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-circle" title="글 삭제" onclick="return confirm('정말 삭제하시겠습니까?');"><i class="fas fa-trash-alt"></i></button>
                    </form>
                <? endif; ?>
                <a href="<?= $links['list'] ?>" class="btn-circle list" title="목록으로"><i class="fas fa-list"></i></a>
            </div>
        </div>

        <div class="view-nav">
            <? $nav_items = array_filter([$next_post, $prev_post]); ?>
            <? foreach ($nav_items as $nav): ?>
                <a href="<?= $nav['link'] ?>" class="nav-item">
                    <span class="date"><?= $nav['date'] ?></span>
                    <span class="title">
                        <span style="font-weight:bold; margin-right:5px; color:var(--primary);">[<?= $nav['label'] ?>]</span>
                        <?= $nav['title'] ?>
                    </span>

                    <i class="fas fa-chevron-right"></i>

                </a>
            <? endforeach; ?>
        </div>
    </div>
</div>
