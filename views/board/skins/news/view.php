<div id="board" class="unit">
    <?= load_asset('common/style.css') ?>
    <?= load_asset($skin_dir . '/board.css') ?>
    <?= load_asset('common/board.js') ?>

    <div class="layout-post">

        <div class="article-header">
            <? if (!empty($post['category'])): ?>
                <span class="article-category"><?= $post['category'] ?></span>
            <? else: ?>
                <span class="article-category">General</span>
            <? endif; ?>

            <h1 class="article-title"><?= $post['title'] ?></h1>

            <div class="article-meta">
                <span class="date"><?= $post['date_view'] ?></span>

                <div class="meta-right">
                    <button type="button" class="btn-share-icon" onclick="copyUrl()" title="URL 복사">
                        <i class="fas fa-share-square"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="article-body">

            <? if (!empty($files)): ?>
                <div class="view-img-wide">
                    <? foreach ($files as $file): ?>
                        <img src="<?= $file['file_path'] ?>" alt="첨부이미지">
                    <? endforeach; ?>
                </div>
            <? endif; ?>

            <div class="content">
                <?= $post['content'] ?>
            </div>
        </div>

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
