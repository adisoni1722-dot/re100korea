/* -----------------------------
   1. 게시판 설정 boards
------------------------------ */
CREATE TABLE `boards` (
  `board_id` varchar(50) NOT NULL COMMENT '게시판 고유 키',
  `title` varchar(100) NOT NULL COMMENT '게시판 이름',
  `skin` varchar(50) DEFAULT 'basic',
  `use_secret` tinyint(1) DEFAULT 0,
  `use_file` tinyint(1) DEFAULT 0,
  `read_level` tinyint(1) DEFAULT 0,
  `write_level` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`board_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* -----------------------------
   2. 게시글 posts (성능 인덱스 적용)
------------------------------ */
CREATE TABLE `posts` (
  `post_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `board_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `writer` varchar(50) DEFAULT NULL,
  `member_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL COMMENT '비회원 비밀번호 해시',
  `is_secret` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`post_id`),

  KEY `idx_board_created_at` (`board_id`, `created_at` DESC),
  KEY `idx_board_postid` (`board_id`, `post_id` DESC),
  KEY `idx_member_id` (`member_id`),

  CONSTRAINT `fk_posts_board`
    FOREIGN KEY (`board_id`) REFERENCES `boards` (`board_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* -----------------------------
   3. 댓글 comments (최적화)
------------------------------ */
CREATE TABLE `comments` (
  `comments_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(11) unsigned NOT NULL,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `depth` tinyint(1) NOT NULL DEFAULT 0 CHECK (`depth` IN (0,1)),
  `writer` varchar(50) NOT NULL,
  `member_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`comments_id`),

  KEY `idx_post_created_at` (`post_id`, `created_at`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_parent_id` (`parent_id`),

  CONSTRAINT `fk_comments_post`
    FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `comments` (`comments_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* -----------------------------
   4. 파일 post_files (최적화)
------------------------------ */
CREATE TABLE `post_files` (
  `file_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(11) unsigned NOT NULL,
  `origin_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) unsigned DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`file_id`),

  KEY `idx_posts_fileid` (`post_id`, `file_id`),

  CONSTRAINT `fk_files_post`
    FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* -----------------------------
   5. 기본 데이터
------------------------------ */
INSERT INTO `boards` (`board_id`, `title`, `skin`, `use_secret`, `use_file`)
VALUES
('notice', '공지사항', 'basic', 0, 0),
('qna', '질문답변', 'basic', 1, 0),
('photo', '포토갤러리', 'gallery', 0, 1);
