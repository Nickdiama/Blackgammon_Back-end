-- Δημιουργία του ταμπλό
DROP TABLE IF EXISTS `board`;
CREATE TABLE `board` (
  position INT UNSIGNED NOT NULL,
  w_count  INT UNSIGNED NOT NULL DEFAULT 0,
  b_count  INT UNSIGNED NOT NULL DEFAULT 0,
  top_color ENUM('W','B') DEFAULT NULL,
  PRIMARY KEY (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Εισαγωγή θέσεων από 0 έως 24 (0,25: εκτός ταμπλό, 1-24: θέσεις στο ταμπλό)
LOCK TABLES `board` WRITE;

INSERT INTO board (position)
SELECT n FROM (
  SELECT 0 n UNION ALL
  SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
  UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
  UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
  UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL
  SELECT 25
) AS positions;

-- Αρχική θέση άσπρων πουλιών (κάτω αριστερά)
UPDATE board
SET w_count = 2
WHERE position = 1;

-- Αρχική θέση μαύρων πουλιών (πάνω αριστερά)
UPDATE board
SET b_count = 2
WHERE position = 24;

-- Υπόλοιπα πούλια (εκτός ταμπλό)
UPDATE board
SET w_count = 13
WHERE position = 0;  

UPDATE board
SET b_count = 13
WHERE position = 25;

-- Ρύθμιση top_color W και B στις αρχικές θέσεις
UPDATE board
SET top_color = 'W'
WHERE position = 1;

UPDATE board
SET top_color = 'B'
WHERE position = 24;

-- Ρύθμιση top_color NULL στις υπόλοιπες θέσεις
UPDATE board
SET top_color = NULL
WHERE position NOT IN (1,24);

UNLOCK TABLES;

-- Δημιουργία του πίνακα game_status
DROP TABLE IF EXISTS `game_status`;
CREATE TABLE `game_status` (
  `status` enum('not active','initialized','started','ended','aborted') NOT NULL DEFAULT 'not active',
  `p_turn` enum('W','B') DEFAULT NULL,
  start_dice_w SMALLINT DEFAULT NULL,
  start_dice_b SMALLINT DEFAULT NULL,
  dice1 SMALLINT DEFAULT NULL,
  dice2 SMALLINT DEFAULT NULL,
  dice3 SMALLINT DEFAULT NULL,
  dice4 SMALLINT DEFAULT NULL,
  double_count TINYINT DEFAULT 0,
  `result` enum('B','W') DEFAULT NULL,
  score TINYINT DEFAULT 1, -- μονό ή διπλό παιχνίδι
  `last_change` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Αρχικοποίηση του πίνακα game_status
LOCK TABLES `game_status` WRITE;
INSERT INTO `game_status` VALUES ('not active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '1','2022-11-28 18:39:59');
UNLOCK TABLES;

-- Δημιουργία του πίνακα players
DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `username` varchar(20) DEFAULT NULL,
  `piece_color` enum('B','W') DEFAULT NULL,
  `token` varchar(100) DEFAULT NULL,
  `last_action` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`piece_color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Αρχικοποίηση του πίνακα players
LOCK TABLES `players` WRITE;
INSERT INTO `players` VALUES ('qqqqqq','B',NULL,'2022-11-28 18:16:51'),('aaaa','W',NULL,'2022-11-28 17:16:54');
UNLOCK TABLES;

-- Δημιουργία procedure για αρχικοποίηση του παιχνιδιού
DROP PROCEDURE IF EXISTS `clean_board`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `clean_board`()
BEGIN
	-- Επαναφορά του ταμπλό
	UPDATE board SET w_count = 0, b_count = 0, top_color = NULL;
	
	-- Αρχική θέση άσπρων πουλιών (κάτω αριστερά)
	UPDATE board SET w_count = 2, top_color = 'W' WHERE position = 1;
	
	-- Αρχική θέση μαύρων πουλιών (πάνω αριστερά)
	UPDATE board SET b_count = 2, top_color = 'B' WHERE position = 24;
	
	-- Υπόλοιπα πούλια (εκτός ταμπλό)
	UPDATE board SET w_count = 13, top_color = 'W' WHERE position = 0;
  UPDATE board SET b_count = 13, top_color = 'B' WHERE position = 25;

	UPDATE `players` SET username=NULL, token=NULL, last_action=NULL;
  
	UPDATE `game_status` SET `status`='not active', `p_turn`=NULL, `result`=NULL, `dice1`=NULL, `dice2`=NULL, `dice3`=NULL, `dice4`=NULL, `double_count`=0, `start_dice_w`=NULL, `start_dice_b`=NULL;
END ;;
DELIMITER ;

-- Δημιουργία procedure για κίνηση πούλιου
DROP PROCEDURE IF EXISTS `move_piece`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `move_piece`(player_token varchar(100), from_pos int, to_pos int)
BEGIN
	declare p_color char;

	-- Βρες το χρώμα του παίκτη
	select piece_color into p_color 
	FROM `players` 
	WHERE token = player_token;

	-- Μείωση του αριθμού των πουλιών από την αρχική θέση και αύξηση στην τελική θέση
	IF p_color = 'W' THEN
		UPDATE board SET w_count = w_count - 1 WHERE position = from_pos;
		UPDATE board SET w_count = w_count + 1 WHERE position = to_pos;
	ELSEIF p_color = 'B' THEN
		UPDATE board SET b_count = b_count - 1 WHERE position = from_pos;
		UPDATE board SET b_count = b_count + 1 WHERE position = to_pos;
	END IF;

END ;;
DELIMITER ;
