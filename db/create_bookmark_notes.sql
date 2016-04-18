# Database init.
CREATE DATABASE IF NOT EXISTS `ybk`;
USE `ybk`;

# Bookmark note table
DROP TABLE IF EXISTS `ybk_notes`;

CREATE TABLE `ybk_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bookmark_id` varchar(255) NOT NULL,
  `user` varchar(255) NOT NULL,
  `context` text,
  `review` text,
  `mark` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `bookmark_id` (`bookmark_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
