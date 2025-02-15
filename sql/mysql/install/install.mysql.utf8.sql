CREATE TABLE IF NOT EXISTS `#__zsphinx_recent_search` (
  `search_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `search_query` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`search_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
