<div id="board" class="unit">
    <?= load_asset('common/style.css') ?>
    <?= load_asset($skin_dir . '/board.css') ?>

    <div class="layout">
        <div class="header search-header">
            <h1 class="title"><?= $board['title'] ?></h1>

            <div class="simple-search">
                <form method="get" action="<?= $links['list'] ?>">
                    <input type="hidden" name="field" value="all">

                    <div class="search-input-box">
                        <input type="text" name="keyword" value="<?= $keyword ?>" placeholder="제목, 내용 검색">
                        <button type="submit" class="btn-icon">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="list-head">
            <div class="item-date">작성일</div>
            <div class="item-subject">제목</div>
        </div>

        <ul class="list-body">
            <? if (empty($posts)): ?>
                <li class="list-row empty-msg">등록된 글이 없습니다.</li>
            <? else: ?>
                <? foreach ($posts as $row): ?>
                    <li class="list-row">
                        <a href="<?= $row['link'] ?>" class="item-link">

                            <div class="item-date">
                                <?= $row['date'] ?>
                            </div>

                            <div class="item-subject">
                                <?= $row['secret_icon'] ?>
                                <? if (!empty($row['category'])): ?>
                                    <span class="category">[<?= $row['category'] ?>]</span>
                                <? endif; ?>
                                <?= $row['title'] ?>

                                <? if (($row['comment_count'] ?? 0) > 0): ?>
                                    <span class="cnt">(<?= $row['comment_count'] ?>)</span>
                                <? endif; ?>

                                <?= $row['new_icon'] ?>
                            </div>
                        </a>
                    </li>
                <? endforeach; ?>
            <? endif; ?>
        </ul>

        <div class="pagination">
            <?= $pager ?? '<a href="#" class="page-link active">1</a>' ?>
        </div>

        <? if ($can_write): ?>
            <div style="display:flex; justify-content:flex-end; margin-top:30px;">
                <a href="<?= $links['write'] ?>" class="btn primary">글쓰기</a>
            </div>
        <? endif; ?>

    </div>
</div>
