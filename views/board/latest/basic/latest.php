<div class="latest-skin-basic">
    <div class="latest-header">
        <strong>최근 게시물</strong>
        <a href="/board/<?= $board_id ?>/list" class="more-btn">+ 더보기</a>
    </div>

    <ul class="latest-ul">
        <?php if (!empty($latest_list)): ?>
            <?php foreach ($latest_list as $lt): ?>
            <li>
                <a href="<?= $lt['url'] ?>">
                    <span class="lt-title"><?= $lt['title'] ?></span>

                    <?php if(isset($lt['comment_count']) && $lt['comment_count'] > 0): ?>
                        <span class="lt-comment">[<?= $lt['comment_count'] ?>]</span>
                    <?php endif; ?>
                </a>
                <span class="lt-date"><?= $lt['date'] ?></span>
            </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="no-data">게시물이 없습니다.</li>
        <?php endif; ?>
    </ul>
</div>

<style>
.latest-skin-basic { border: 1px solid #ddd; padding: 10px; border-radius: 4px; }
.latest-header { display: flex; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
.latest-ul { list-style: none; padding: 0; margin: 0; }
.latest-ul li { display: flex; justify-content: space-between; padding: 3px 0; }
.latest-ul li a { text-decoration: none; color: #333; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lt-date { font-size: 0.85em; color: #888; margin-left: 10px; }
.lt-comment { font-size: 0.8em; color: #f00; }
</style>
