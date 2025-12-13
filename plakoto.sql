DROP TABLE IF EXISTS `board`;
CREATE TABLE `board` (
  `x` int(1) NOT NULL,
  `y` int(1) NOT NULL,
  `piece_color` enum('W','B') DEFAULT NULL,
  PRIMARY KEY (`y`,`x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `board` WRITE; 
INSERT INTO `board` VALUES (1,1,'B'),(1,2,'B'),(1,11,'W'),(1,12,'W');

-- Insert NULL piece_color for all other positions in a 12x12 board
INSERT INTO `board` (`x`,`y`,`piece_color`)
SELECT X.i, Y.j, NULL
FROM (
  SELECT 1 AS i UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) AS X
CROSS JOIN (
  SELECT 1 AS j UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) AS Y
WHERE NOT (X.i = 1 AND Y.j IN (1,2,11,12));

UNLOCK TABLES;

DROP TABLE IF EXISTS `board_empty`;

CREATE TABLE `board_empty` (
  `x` int(1) NOT NULL,
  `y` int(1) NOT NULL,
  `piece_color` enum('W','B') DEFAULT NULL,
  PRIMARY KEY (`y`,`x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

LOCK TABLES `board_empty` WRITE; 
INSERT INTO `board_empty` VALUES (1,1,'B'),(1,2,'B'),(1,11,'W'),(1,12,'W');

-- Insert NULL piece_color for all other positions in a 12x12 board
INSERT INTO `board_empty` (`x`,`y`,`piece_color`)
SELECT X.i, Y.j, NULL
FROM (
  SELECT 1 AS i UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) AS X
CROSS JOIN (
  SELECT 1 AS j UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) AS Y
WHERE NOT (X.i = 1 AND Y.j IN (1,2,11,12));

UNLOCK TABLES;

DROP TABLE IF EXISTS `game_status`;
CREATE TABLE `game_status` (
  `status` enum('not active','initialized','started','ended','aborded') NOT NULL DEFAULT 'not active',
  `p_turn` enum('W','B') DEFAULT NULL,
  start_dice_w SMALLINT DEFAULT NULL,
  start_dice_b SMALLINT DEFAULT NULL,
  dice1 SMALLINT DEFAULT NULL,
  dice2 SMALLINT DEFAULT NULL,
  `result` enum('B','W') DEFAULT NULL,
  score TINYINT DEFAULT 1, -- μονό ή διπλό παιχνίδι
  `last_change` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `game_status` WRITE;
INSERT INTO `game_status` VALUES ('started','W','1','1','1','1',NULL,'1','2022-11-28 18:39:59');
UNLOCK TABLES;

DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `username` varchar(20) DEFAULT NULL,
  `piece_color` enum('B','W') NOT NULL,
  `token` varchar(100) DEFAULT NULL,
  `last_action` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`piece_color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `players` WRITE;
INSERT INTO `players` VALUES ('qqqqqq','B','8599a2efe05697622caeddae84507ee3','2022-11-28 18:16:51'),('aaaa','W','05da4297eecc648e840b6d3bfa772adc','2022-11-28 17:16:54');
UNLOCK TABLES;

DROP PROCEDURE IF EXISTS `clean_board`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `clean_board`()
BEGIN
	REPLACE INTO board SELECT * FROM board_empty;
	
	UPDATE `players` SET username=NULL, token=NULL, last_action=NULL;
  
	UPDATE `game_status` SET `status`='not active', `p_turn`=NULL, `result`=NULL, dice1=NULL, dice2=NULL, start_dice_w=NULL, start_dice_b=NULL;
END ;;
DELIMITER ;

DROP PROCEDURE IF EXISTS `move_piece`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `move_piece`(x1 int,y1 int,x2 int,y2 int)
BEGIN
	declare  p_color char;

	select piece_color into p_color FROM `board` WHERE X=x1 AND Y=y1;

	update board
	set piece_color=p_color
	where x=x2 and y=y2;

	UPDATE board
	SET piece=null,piece_color=null
	WHERE X=x1 AND Y=y1;

	update game_status set p_turn=if(p_color='W','B','W');
	
END ;;
DELIMITER ;
