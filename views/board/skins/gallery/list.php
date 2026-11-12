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

        <div class="gallery-list">
            <? if (empty($posts)): ?>
                <div class="empty-msg">등록된 글이 없습니다.</div>
            <? else: ?>
                <? foreach ($posts as $row): ?>
                    <div class="gallery-item">
                        <a href="<?= $row['link'] ?>" class="card-link">
                            <div class="card-thumb">
                                <?
                                // 썸네일 로직: $row['thumb'] 혹은 첨부파일 이미지가 있다면 출력
                                // 데이터에 썸네일 경로가 없다면 아래 주석 처리된 부분 등을 활용해야 합니다.
                                $thumb_url = $row['thumb_url'] ?? ''; // 실제 썸네일 변수명으로 변경 필요
                                ?>

                                <? if ($thumb_url): ?>
                                    <img src="<?= $thumb_url ?>" alt="<?= $row['title'] ?>">
                                <? else: ?>
                                    <div class="no-img">
                                        <i class="far fa-image"></i>
                                    </div>
                                <? endif; ?>
                            </div>

                            <div class="card-body">
                                <div class="card-meta">
                                    <? if (!empty($row['category'])): ?>
                                        <span class="card-cat"><?= $row['category'] ?></span>
                                    <? else: ?>
                                        <span class="card-cat">General</span>
                                    <? endif; ?>
                                    <span class="card-date"><?= $row['date'] ?></span>
                                </div>

                                <h3 class="card-title">
                                    <?= $row['title'] ?>
                                    <? if (($row['comment_count'] ?? 0) > 0): ?>
                                        <span class="cnt">(<?= $row['comment_count'] ?>)</span>
                                    <? endif; ?>
                                    <?= $row['secret_icon'] ?>
                                    <?= $row['new_icon'] ?>
                                </h3>

                                <div class="card-tags">
                                    <? if (!empty($row['category'])): ?>
                                        <span class="tag-badge"><?= $row['category'] ?></span>
                                    <? endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <? endforeach; ?>
            <? endif; ?>
        </div>

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
