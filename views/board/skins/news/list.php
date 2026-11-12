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
                        <input type="text" name="keyword" value="<?= $keyword ?>" placeholder="검색어 입력">
                        <button type="submit" class="btn-icon">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="news-list">
            <? if (empty($posts)): ?>
                <div class="empty-msg">등록된 글이 없습니다.</div>
            <? else: ?>
                <? foreach ($posts as $row): ?>
                    <div class="news-item">
                        <a href="<?= $row['link'] ?>" class="news-link">

                            <div class="news-thumb">
                                <?
                                $thumb_url = $row['thumb_url'] ?? '';
                                ?>
                                <? if ($thumb_url): ?>
                                    <img src="<?= $thumb_url ?>" alt="<?= $row['title'] ?>">
                                <? else: ?>
                                    <div class="no-img">
                                        <i class="far fa-image"></i>
                                    </div>
                                <? endif; ?>
                            </div>

                            <div class="news-body">
                                <h3 class="news-title">
                                    <? if (!empty($row['category'])): ?>
                                        <span class="news-cat">[<?= $row['category'] ?>]</span>
                                    <? endif; ?>
                                    <?= $row['title'] ?>
                                    <?= $row['secret_icon'] ?>
                                    <?= $row['new_icon'] ?>
                                    <? if (($row['comment_count'] ?? 0) > 0): ?>
                                        <span class="cnt">(<?= $row['comment_count'] ?>)</span>
                                    <? endif; ?>
                                </h3>

                                <p class="news-summary">
                                    <?
                                    // content가 있다면 태그 제거 후 출력, 없으면 공백
                                    $summary = html_entity_decode($row['content'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $summary = strip_tags($summary);
                                    ?>

                                     <?= $summary ?>
                                </p>

                                <div class="news-meta">
                                    <span class="author"><?= $row['writer'] ?? '관리자' ?></span>
                                    <span class="divider">|</span>
                                    <span class="date"><?= $row['date'] ?></span>
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
