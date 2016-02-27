CREATE TABLE IF NOT EXISTS `account` (
  `account_id` INTEGER NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(512) NOT NULL UNIQUE,
  `password` text NOT NULL,
  `private_ip` varchar(512) DEFAULT NULL,
  `public_ip` varchar(512) DEFAULT NULL,
  `private_port` INTEGER DEFAULT NULL,
  `public_port` INTEGER DEFAULT NULL,
  `account_state` enum('TO_DELETE', 'TO_CONFIRM', 'CONFIRMED') NOT NULL DEFAULT 'TO_CONFIRM',
  `captcha` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`account_id`)
);

CREATE TABLE IF NOT EXISTS `message` (
  `message_id` INTEGER NOT NULL AUTO_INCREMENT,
  `date_added` DATETIME(6) NOT NULL DEFAULT NOW(6),
  `sender` varchar(1024) NOT NULL,
  `account_id` INTEGER NOT NULL,
  `microtime` VARCHAR(512) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`message_id`),
  FOREIGN KEY (`account_id`) REFERENCES `account` (`account_id`) ON DELETE CASCADE
);
