
CREATE TABLE IF NOT EXISTS world (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    randomNumber INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fortune (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL
) ENGINE=InnoDB;

DELIMITER $$
CREATE PROCEDURE seed_world()
BEGIN
    DECLARE i INT DEFAULT 1;
    IF (SELECT COUNT(*) FROM world) = 0 THEN
        START TRANSACTION;
        WHILE i <= 10000 DO
            INSERT INTO world (id, randomNumber) VALUES (i, FLOOR(1 + RAND() * 10000));
            SET i = i + 1;
        END WHILE;
        COMMIT;
    END IF;
END$$
DELIMITER ;

CALL seed_world();
DROP PROCEDURE seed_world;

INSERT IGNORE INTO fortune (id, message) VALUES
    (1, 'fortune: No such file or directory'),
    (2, 'A computer scientist is someone who fixes things that aren''t broken.'),
    (3, 'After enough decimal places, nobody gives a damn.'),
    (4, 'A bad random number generator: 1, 1, 1, 1, 1, 4.33e+67, 1, 1, 1'),
    (5, 'A computer program does what you tell it to do, not what you want it to do.'),
    (6, 'Emacs is a nice operating system, but I prefer UNIX.'),
    (7, 'Any program that runs right is obsolete.'),
    (8, 'A list is only as strong as its weakest link. — Donald Knuth'),
    (9, 'Feature: A bug with seniority.'),
    (10, 'Computers make very fast, very accurate mistakes.'),
    (11, '<script>alert("This should not be displayed in a browser alert box.");</script>'),
    (12, 'Q: What is the equivalent of Viagra for programmers? A: A can of Jolt and a pizza & a deadline.');
