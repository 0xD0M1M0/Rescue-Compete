-- phpMyAdmin SQL Dump (bereinigt, ohne INSERTs)
-- Server: MariaDB 10.11.x

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
# /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
# /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
# /*!40101 SET NAMES utf8mb4 */;

-- ---------------------------------------------------------------------
-- SCHEMA: webappdb
-- ---------------------------------------------------------------------

-- -----------------------
-- Tabellen
-- -----------------------

CREATE TABLE IF NOT EXISTS `Answer` (
                                        `ID` int(11) NOT NULL,
    `Question_ID` int(11) NOT NULL,
    `Text` text NOT NULL,
    `IsCorrect` tinyint(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `CollectionFormToken` (
                                                     `ID` int(11) NOT NULL,
    `collection_ID` int(11) NOT NULL,
    `formNumber` int(11) NOT NULL,
    `token` varchar(64) NOT NULL COMMENT 'Cryptographically random token',
    `createdAt` timestamp NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Stand-in-Tabellen für Views werden nicht mehr benötigt
-- Views werden am Ende des Schemas direkt erstellt

CREATE TABLE IF NOT EXISTS `CollectionQuestion` (
                                                    `collection_ID` int(11) NOT NULL,
    `question_ID` int(11) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `FormCollection` (
                                                `ID` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `timeLimit` int(11) DEFAULT 180 COMMENT 'Zeitlimit in Sekunden pro Formular',
    `totalQuestions` int(11) NOT NULL,
    `formsCount` int(11) NOT NULL,
    `createdAt` timestamp NULL DEFAULT current_timestamp(),
    `station_ID` int(11) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `Mannschaft` (
                                            `ID` int(11) NOT NULL,
    `Teamname` varchar(100) NOT NULL,
    `Kreisverband` varchar(32) NOT NULL,
    `Landesverband` varchar(32) NOT NULL,
    `Gesamtpunkte` int(10) UNSIGNED DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `MannschaftProtokoll` (
                                                     `mannschaft_ID` int(11) NOT NULL,
    `protokoll_Nr` int(11) NOT NULL,
    `erreichte_Punkte` int(11) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `MannschaftStaffel` (
                                                   `mannschaft_ID` int(11) NOT NULL,
    `staffel_ID` int(11) NOT NULL,
    `schwimmzeit` time(4) NOT NULL,
    `strafzeit` time(4) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `MannschaftWertung` (
                                                   `mannschaft_ID` int(11) NOT NULL,
    `wertung_ID` int(11) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `Protokoll` (
                                           `Nr` int(11) NOT NULL,
    `Name` varchar(64) NOT NULL,
    `max_Punkte` int(11) NOT NULL,
    `station_ID` int(11) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `Question` (
                                          `ID` int(11) NOT NULL,
    `QuestionPool_ID` int(11) NOT NULL,
    `Text` text NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `QuestionPool` (
                                              `ID` int(11) NOT NULL,
    `Name` varchar(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ResultConfiguration` (
                                                     `ID` int(11) NOT NULL,
    `Key` varchar(64) NOT NULL,
    `Value` text NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `Staffel` (
                                         `ID` int(11) NOT NULL,
    `name` varchar(32) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `Station` (
                                         `ID` int(11) NOT NULL,
    `name` varchar(32) DEFAULT NULL,
    `Nr` int(11) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `StationWeight` (
                                               `station_ID` int(11) NOT NULL,
    `weight` int(11) NOT NULL DEFAULT 100
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `TeamFormAnswer` (
                                                `ID` int(11) NOT NULL,
    `teamFormInstance_ID` int(11) NOT NULL,
    `question_ID` int(11) NOT NULL,
    `answer_ID` int(11) NOT NULL,
    `savedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `TeamFormInstance` (
                                                  `ID` int(11) NOT NULL,
    `team_ID` int(11) NOT NULL,
    `collection_ID` int(11) NOT NULL,
    `formNumber` int(11) NOT NULL COMMENT 'Formnummer innerhalb der Collection (1,2,3,4)',
    `completed` tinyint(1) DEFAULT 0,
    `points` int(11) DEFAULT 0,
    `startTime` datetime DEFAULT NULL,
    `timeLimit` int(11) DEFAULT NULL COMMENT 'Snapshot bei Timer-Start; NULL = nutze FormCollection.timeLimit',
    `completionDate` datetime DEFAULT NULL,
    `token` varchar(32) DEFAULT NULL,
    `assignedQuestions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array der zugewiesenen Fragen-IDs' CHECK (json_valid(`assignedQuestions`)),
    `createdAt` timestamp NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `User` (
                                      `ID` int(11) NOT NULL,
    `username` varchar(32) NOT NULL,
    `passwordHash` varchar(255) DEFAULT NULL,
    `acc_typ` varchar(16) NOT NULL,
    `mannschaft_ID` int(11) DEFAULT NULL,
    `station_ID` int(11) DEFAULT NULL,
    `oidc_sub` varchar(128) DEFAULT NULL,
    `sso_email` varchar(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `Wertungsklasse` (
                                                `ID` int(11) NOT NULL,
    `name` varchar(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `WertungStaffel` (
                                                `wertung_ID` int(11) NOT NULL,
    `staffel_ID` int(11) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `WertungStation` (
                                                `wertung_ID` int(11) NOT NULL,
    `station_ID` int(11) NOT NULL,
    `weight` int(11) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------
-- Indizes
-- -----------------------

ALTER TABLE `Answer`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_answer_question` (`Question_ID`);

ALTER TABLE `CollectionFormToken`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uk_collectionformtoken_token` (`token`),
  ADD UNIQUE KEY `uk_collectionformtoken_collection_form` (`collection_ID`,`formNumber`),
  ADD KEY `idx_collectionformtoken_collection` (`collection_ID`);

ALTER TABLE `CollectionQuestion`
    ADD PRIMARY KEY (`collection_ID`,`question_ID`),
  ADD KEY `idx_collectionquestion_question` (`question_ID`);

ALTER TABLE `FormCollection`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_formcollection_station` (`station_ID`);

ALTER TABLE `Mannschaft`
    ADD PRIMARY KEY (`ID`);

ALTER TABLE `MannschaftProtokoll`
    ADD PRIMARY KEY (`mannschaft_ID`,`protokoll_Nr`),
  ADD KEY `protokoll_Nr` (`protokoll_Nr`);

ALTER TABLE `MannschaftStaffel`
    ADD PRIMARY KEY (`mannschaft_ID`,`staffel_ID`),
  ADD KEY `staffel_ID` (`staffel_ID`);

ALTER TABLE `MannschaftWertung`
    ADD PRIMARY KEY (`mannschaft_ID`,`wertung_ID`),
  ADD KEY `wertung_ID` (`wertung_ID`);

ALTER TABLE `Protokoll`
    ADD PRIMARY KEY (`Nr`),
  ADD UNIQUE KEY `Nr` (`Nr`),
  ADD KEY `station_Nr` (`station_ID`);

ALTER TABLE `Question`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uk_QuestionText` (`Text`(191)),
  ADD KEY `fk_question_pool` (`QuestionPool_ID`);

ALTER TABLE `QuestionPool`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uk_PoolName` (`Name`);

ALTER TABLE `ResultConfiguration`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uk_ConfigKey` (`Key`);

ALTER TABLE `Staffel`
    ADD PRIMARY KEY (`ID`);

ALTER TABLE `Station`
    ADD PRIMARY KEY (`ID`);

ALTER TABLE `StationWeight`
    ADD PRIMARY KEY (`station_ID`);

ALTER TABLE `TeamFormAnswer`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uk_teamformanswer_instance_question` (`teamFormInstance_ID`,`question_ID`),
  ADD KEY `idx_teamformanswer_instance` (`teamFormInstance_ID`),
  ADD KEY `idx_teamformanswer_question` (`question_ID`),
  ADD KEY `idx_teamformanswer_answer` (`answer_ID`);

ALTER TABLE `TeamFormInstance`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uk_teamforminstance_team_collection_form` (`team_ID`,`collection_ID`,`formNumber`),
  ADD UNIQUE KEY `uk_teamforminstance_token` (`token`),
  ADD KEY `idx_teamforminstance_team` (`team_ID`),
  ADD KEY `idx_teamforminstance_collection` (`collection_ID`),
  ADD KEY `idx_teamforminstance_starttime` (`startTime`),
  ADD KEY `idx_teamforminstance_completed` (`completed`);

ALTER TABLE `User`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uk_user_oidc_sub` (`oidc_sub`),
  ADD UNIQUE KEY `uk_user_sso_email` (`sso_email`),
  ADD KEY `mannschaft_ID` (`mannschaft_ID`),
  ADD KEY `station_Nr` (`station_ID`);

ALTER TABLE `Wertungsklasse`
    ADD PRIMARY KEY (`ID`);

ALTER TABLE `WertungStaffel`
    ADD PRIMARY KEY (`wertung_ID`,`staffel_ID`),
  ADD KEY `staffel_ID` (`staffel_ID`);

ALTER TABLE `WertungStation`
    ADD PRIMARY KEY (`wertung_ID`,`station_ID`),
  ADD KEY `station_ID` (`station_ID`);

-- -----------------------
-- AUTO_INCREMENT
-- -----------------------

ALTER TABLE `Answer` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `CollectionFormToken` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `FormCollection` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Mannschaft` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Protokoll` MODIFY `Nr` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Question` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `QuestionPool` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ResultConfiguration` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Staffel` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Station` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `TeamFormAnswer` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `TeamFormInstance` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `User` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Wertungsklasse` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

-- -----------------------
-- FOREIGN KEYS
-- -----------------------

ALTER TABLE `Answer`
    ADD CONSTRAINT `fk_answer_question` FOREIGN KEY (`Question_ID`) REFERENCES `Question` (`ID`);

ALTER TABLE `CollectionFormToken`
    ADD CONSTRAINT `fk_collectionformtoken_collection` FOREIGN KEY (`collection_ID`) REFERENCES `FormCollection` (`ID`) ON DELETE CASCADE;

ALTER TABLE `CollectionQuestion`
    ADD CONSTRAINT `fk_collectionquestion_collection` FOREIGN KEY (`collection_ID`) REFERENCES `FormCollection` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_collectionquestion_question` FOREIGN KEY (`question_ID`) REFERENCES `Question` (`ID`) ON DELETE CASCADE;

ALTER TABLE `FormCollection`
    ADD CONSTRAINT `fk_formcollection_station` FOREIGN KEY (`station_ID`) REFERENCES `Station` (`ID`) ON DELETE SET NULL;

ALTER TABLE `MannschaftProtokoll`
    ADD CONSTRAINT `MannschaftProtokoll_ibfk_1` FOREIGN KEY (`mannschaft_ID`) REFERENCES `Mannschaft` (`ID`),
  ADD CONSTRAINT `MannschaftProtokoll_ibfk_2` FOREIGN KEY (`protokoll_Nr`) REFERENCES `Protokoll` (`Nr`);

ALTER TABLE `MannschaftStaffel`
    ADD CONSTRAINT `MannschaftStaffel_ibfk_1` FOREIGN KEY (`mannschaft_ID`) REFERENCES `Mannschaft` (`ID`),
  ADD CONSTRAINT `MannschaftStaffel_ibfk_2` FOREIGN KEY (`staffel_ID`) REFERENCES `Staffel` (`ID`);

ALTER TABLE `MannschaftWertung`
    ADD CONSTRAINT `MannschaftWertung_ibfk_1` FOREIGN KEY (`mannschaft_ID`) REFERENCES `Mannschaft` (`ID`),
  ADD CONSTRAINT `MannschaftWertung_ibfk_2` FOREIGN KEY (`wertung_ID`) REFERENCES `Wertungsklasse` (`ID`);

ALTER TABLE `Protokoll`
    ADD CONSTRAINT `Protokoll_ibfk_1` FOREIGN KEY (`station_ID`) REFERENCES `Station` (`ID`);

ALTER TABLE `Question`
    ADD CONSTRAINT `fk_question_pool` FOREIGN KEY (`QuestionPool_ID`) REFERENCES `QuestionPool` (`ID`);

ALTER TABLE `StationWeight`
    ADD CONSTRAINT `fk_stationweight_station` FOREIGN KEY (`station_ID`) REFERENCES `Station` (`ID`);

ALTER TABLE `TeamFormAnswer`
    ADD CONSTRAINT `fk_teamformanswer_answer` FOREIGN KEY (`answer_ID`) REFERENCES `Answer` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teamformanswer_instance` FOREIGN KEY (`teamFormInstance_ID`) REFERENCES `TeamFormInstance` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teamformanswer_question` FOREIGN KEY (`question_ID`) REFERENCES `Question` (`ID`) ON DELETE CASCADE;

ALTER TABLE `TeamFormInstance`
    ADD CONSTRAINT `fk_teamforminstance_collection` FOREIGN KEY (`collection_ID`) REFERENCES `FormCollection` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teamforminstance_team` FOREIGN KEY (`team_ID`) REFERENCES `Mannschaft` (`ID`) ON DELETE CASCADE;

ALTER TABLE `User`
    ADD CONSTRAINT `User_ibfk_1` FOREIGN KEY (`mannschaft_ID`) REFERENCES `Mannschaft` (`ID`),
  ADD CONSTRAINT `User_ibfk_2` FOREIGN KEY (`station_ID`) REFERENCES `Station` (`ID`);

-- -----------------------
-- ROUTINEN / FUNKTIONEN
-- -----------------------
DELIMITER $$

CREATE OR REPLACE PROCEDURE `autoSubmitExpiredForm` (IN `instanceId` INT)
BEGIN
  DECLARE teamId INT;
  DECLARE collectionId INT;
  DECLARE formNumber INT;
  DECLARE correctAnswers INT DEFAULT 0;
  DECLARE totalQuestions INT;

SELECT team_ID, collection_ID, formNumber
INTO teamId, collectionId, formNumber
FROM TeamFormInstance
WHERE ID = instanceId AND completed = 0;

IF teamId IS NOT NULL THEN
SELECT COUNT(*) INTO correctAnswers
FROM TeamFormAnswer tfa
         JOIN Answer a ON tfa.answer_ID = a.ID
WHERE tfa.teamFormInstance_ID = instanceId
  AND a.IsCorrect = 1;

SELECT JSON_LENGTH(assignedQuestions) INTO totalQuestions
FROM TeamFormInstance
WHERE ID = instanceId;

UPDATE TeamFormInstance
SET completed = 1,
    points = correctAnswers,
    completionDate = NOW()
WHERE ID = instanceId;

SELECT CONCAT('Form auto-submitted for Team ', teamId,
              '. Score: ', correctAnswers, '/', COALESCE(totalQuestions, 0)) AS result;
ELSE
SELECT 'Instance not found or already completed' AS result;
END IF;
END$$

CREATE OR REPLACE PROCEDURE `createTeamFormInstance` (IN `teamId` INT, IN `collectionId` INT, IN `formNumber` INT)
BEGIN
  DECLARE totalQuestions INT;
  DECLARE formsCount INT;
  DECLARE questionsForThisForm INT;
  DECLARE instanceId INT;
  DECLARE questionIds JSON;

SELECT ID INTO instanceId
FROM TeamFormInstance
WHERE team_ID = teamId AND collection_ID = collectionId AND formNumber = formNumber;

IF instanceId IS NOT NULL THEN
SELECT instanceId AS existing_instance_id, 'Instance already exists' AS message;
ELSE
SELECT fc.totalQuestions, fc.formsCount
INTO totalQuestions, formsCount
FROM FormCollection fc
WHERE fc.ID = collectionId;

SET questionsForThisForm = distributeQuestionsToForms(totalQuestions, formsCount, formNumber);

    SET @sql = CONCAT(
      'SELECT JSON_ARRAYAGG(cq.question_ID) INTO @questionIds ',
      'FROM ( ',
      ' SELECT cq.question_ID ',
      ' FROM CollectionQuestion cq ',
      ' WHERE cq.collection_ID = ', collectionId, ' ',
      ' ORDER BY RAND() ',
      ' LIMIT ', questionsForThisForm, ' ',
      ') cq'
    );

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET questionIds = @questionIds;

INSERT INTO TeamFormInstance (team_ID, collection_ID, formNumber, assignedQuestions)
VALUES (teamId, collectionId, formNumber, questionIds);

SET instanceId = LAST_INSERT_ID();

SELECT instanceId AS created_instance_id,
       questionIds AS assigned_questions,
       'Instance created successfully' AS message;
END IF;
END$$

CREATE OR REPLACE PROCEDURE `deleteFormCollection` (IN `collectionId` INT)
BEGIN
  DECLARE collectionName VARCHAR(255);
  DECLARE affectedTeams INT DEFAULT 0;
  DECLARE affectedInstances INT DEFAULT 0;

SELECT name INTO collectionName
FROM FormCollection
WHERE ID = collectionId;

SELECT COUNT(DISTINCT team_ID) INTO affectedTeams
FROM TeamFormInstance
WHERE collection_ID = collectionId;

SELECT COUNT(*) INTO affectedInstances
FROM TeamFormInstance
WHERE collection_ID = collectionId;

DELETE FROM FormCollection WHERE ID = collectionId;

SELECT CONCAT('Collection "', COALESCE(collectionName, 'Unknown'), '" gelöscht. ',
              'Betroffene Teams: ', affectedTeams, ', ',
              'Gelöschte Instanzen: ', affectedInstances) AS result;
END$$

CREATE OR REPLACE PROCEDURE `saveFormAnswer` (IN `instanceId` INT, IN `questionId` INT, IN `answerId` INT)
BEGIN
INSERT INTO TeamFormAnswer (teamFormInstance_ID, question_ID, answer_ID)
VALUES (instanceId, questionId, answerId)
    ON DUPLICATE KEY UPDATE
                         answer_ID = VALUES(answer_ID),
                         savedAt = CURRENT_TIMESTAMP;
END$$

CREATE OR REPLACE FUNCTION `distributeQuestionsToForms` (`totalQuestions` INT, `formsCount` INT, `formNumber` INT)
RETURNS INT(11) DETERMINISTIC
BEGIN
  DECLARE questionsPerForm INT;
  DECLARE remainder INT;
  DECLARE result INT;

  SET questionsPerForm = totalQuestions DIV formsCount;
  SET remainder = totalQuestions MOD formsCount;

  IF formNumber <= remainder THEN
    SET result = questionsPerForm + 1;
ELSE
    SET result = questionsPerForm;
END IF;

RETURN result;
END$$

CREATE OR REPLACE FUNCTION `getQuestionStartIndex` (`totalQuestions` INT, `formsCount` INT, `formNumber` INT)
RETURNS INT(11) DETERMINISTIC
BEGIN
  DECLARE questionsPerForm INT;
  DECLARE remainder INT;
  DECLARE startIndex INT DEFAULT 0;
  DECLARE i INT DEFAULT 1;

  SET questionsPerForm = totalQuestions DIV formsCount;
  SET remainder = totalQuestions MOD formsCount;

  WHILE i < formNumber DO
    IF i <= remainder THEN
      SET startIndex = startIndex + questionsPerForm + 1;
ELSE
      SET startIndex = startIndex + questionsPerForm;
END IF;
    SET i = i + 1;
END WHILE;

RETURN startIndex;
END$$

CREATE OR REPLACE FUNCTION `isValidToken` (`tokenValue` VARCHAR(32))
RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA
BEGIN
  DECLARE tokenCount INT DEFAULT 0;

SELECT COUNT(*) INTO tokenCount
FROM TeamFormInstance
WHERE token = tokenValue;

RETURN tokenCount > 0;
END$$

CREATE OR REPLACE FUNCTION `resolveFormToken` (`tokenCode` VARCHAR(64))
RETURNS LONGTEXT CHARSET utf8mb4 COLLATE utf8mb4_bin
DETERMINISTIC READS SQL DATA
BEGIN
  DECLARE result JSON DEFAULT NULL;

SELECT JSON_OBJECT(
               'type','collection',
               'collection_id', cft.collection_ID,
               'form_number',  cft.formNumber,
               'collection_name', fc.name
       )
INTO result
FROM CollectionFormToken cft
         JOIN FormCollection fc ON cft.collection_ID = fc.ID
WHERE cft.token = tokenCode
    LIMIT 1;

RETURN result;
END$$

DELIMITER ;

-- -----------------------
-- TRIGGER
-- -----------------------

DROP TRIGGER IF EXISTS `tr_teamforminstance_questions`;
DELIMITER $$
CREATE TRIGGER `tr_teamforminstance_questions`
    BEFORE INSERT ON `TeamFormInstance` FOR EACH ROW
BEGIN
    DECLARE totalQuestions INT;
  DECLARE formsCount INT;
  DECLARE questionsForThisForm INT;

  IF NEW.assignedQuestions IS NULL THEN
    SELECT fc.totalQuestions, fc.formsCount
    INTO totalQuestions, formsCount
    FROM FormCollection fc
    WHERE fc.ID = NEW.collection_ID;

    SET questionsForThisForm = distributeQuestionsToForms(totalQuestions, formsCount, NEW.formNumber);
    SET NEW.assignedQuestions = JSON_ARRAY();
END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `tr_teamforminstance_token`;
DELIMITER $$
CREATE TRIGGER `tr_teamforminstance_token`
    BEFORE INSERT ON `TeamFormInstance` FOR EACH ROW
BEGIN
    IF NEW.token IS NULL THEN
    SET NEW.token = CONCAT(
      SUBSTRING(MD5(CONCAT(NEW.team_ID, NEW.collection_ID, NEW.formNumber, NOW())), 1, 16),
      SUBSTRING(MD5(RAND()), 1, 16)
    );
END IF;
END$$
DELIMITER ;

-- -----------------------
-- VIEWS
-- -----------------------

DROP VIEW IF EXISTS `CollectionOverview`;
CREATE OR REPLACE VIEW `CollectionOverview` AS
SELECT
    fc.ID AS ID,
    fc.name AS name,
    fc.description AS description,
    fc.timeLimit AS timeLimit,
    fc.totalQuestions AS totalQuestions,
    fc.formsCount AS formsCount,
    fc.createdAt AS createdAt,
    s.name AS stationName,
    COUNT(DISTINCT tfi.team_ID) AS assignedTeams,
    COUNT(CASE WHEN tfi.completed = 1 THEN tfi.ID END) AS completedForms,
    COUNT(tfi.ID) AS totalInstances,
    AVG(CASE WHEN tfi.completed = 1 THEN tfi.points END) AS averagePoints,
    ROUND(COUNT(CASE WHEN tfi.completed = 1 THEN tfi.ID END) / NULLIF(COUNT(tfi.ID),0) * 100, 1) AS completionRate
FROM FormCollection fc
         LEFT JOIN Station s ON fc.station_ID = s.ID
         LEFT JOIN TeamFormInstance tfi ON fc.ID = tfi.collection_ID
GROUP BY fc.ID, fc.name, fc.description, fc.timeLimit, fc.totalQuestions, fc.formsCount, fc.createdAt, s.name;

DROP VIEW IF EXISTS `CollectionPerformance`;
CREATE OR REPLACE VIEW `CollectionPerformance` AS
SELECT
    fc.ID AS collectionId,
    fc.name AS collectionName,
    fc.formsCount,
    fc.totalQuestions,
    fc.timeLimit,
    COUNT(DISTINCT tfi.team_ID) AS teamsAssigned,
    COUNT(tfi.ID) AS totalInstances,
    COUNT(CASE WHEN tfi.completed = 1 THEN 1 END) AS completedInstances,
    AVG(CASE WHEN tfi.completed = 1 THEN tfi.points END) AS averageScore,
    MAX(CASE WHEN tfi.completed = 1 THEN tfi.points END) AS maxScore,
    MIN(CASE WHEN tfi.completed = 1 THEN tfi.points END) AS minScore,
    ROUND(COUNT(CASE WHEN tfi.completed = 1 THEN 1 END) / NULLIF(COUNT(tfi.ID),0) * 100, 1) AS completionRate
FROM FormCollection fc
         LEFT JOIN TeamFormInstance tfi ON fc.ID = tfi.collection_ID
GROUP BY fc.ID, fc.name, fc.formsCount, fc.totalQuestions, fc.timeLimit;

DROP VIEW IF EXISTS `TeamCollectionProgress`;
CREATE OR REPLACE VIEW `TeamCollectionProgress` AS
SELECT
    m.ID AS teamId,
    m.Teamname AS Teamname,
    m.Kreisverband AS Kreisverband,
    fc.ID AS collectionId,
    fc.name AS collectionName,
    COUNT(tfi.ID) AS totalForms,
    COUNT(CASE WHEN tfi.completed = 1 THEN tfi.ID END) AS completedForms,
    SUM(CASE WHEN tfi.completed = 1 THEN tfi.points ELSE 0 END) AS totalPoints,
    ROUND(COUNT(CASE WHEN tfi.completed = 1 THEN tfi.ID END) / NULLIF(COUNT(tfi.ID),0) * 100, 1) AS completionPercentage
FROM Mannschaft m
         JOIN FormCollection fc
         LEFT JOIN TeamFormInstance tfi
                   ON m.ID = tfi.team_ID AND fc.ID = tfi.collection_ID
GROUP BY m.ID, m.Teamname, m.Kreisverband, fc.ID, fc.name;

DROP VIEW IF EXISTS `TeamFormStatistics`;
CREATE OR REPLACE VIEW `TeamFormStatistics` AS
SELECT
    tfi.ID AS instanceId,
    m.Teamname AS Teamname,
    m.Kreisverband AS Kreisverband,
    fc.name AS collectionName,
    tfi.formNumber AS formNumber,
    tfi.completed AS completed,
    tfi.points AS points,
    JSON_LENGTH(tfi.assignedQuestions) AS assignedQuestionsCount,
    COALESCE((SELECT COUNT(0) FROM TeamFormAnswer WHERE TeamFormAnswer.teamFormInstance_ID = tfi.ID),0) AS answeredQuestions,
    CASE
        WHEN tfi.completed = 1 THEN 'Abgeschlossen'
        WHEN tfi.startTime IS NOT NULL THEN 'In Bearbeitung'
        ELSE 'Nicht gestartet'
        END AS status,
    tfi.startTime AS startTime,
    tfi.completionDate AS completionDate,
    tfi.token AS token
FROM TeamFormInstance tfi
         JOIN Mannschaft m ON tfi.team_ID = m.ID
         JOIN FormCollection fc ON tfi.collection_ID = fc.ID
ORDER BY m.Teamname ASC, fc.name ASC, tfi.formNumber ASC;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
# /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
# /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
