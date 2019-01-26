<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// set default timezone
date_default_timezone_set('UTC');

try {

    // create (connect to) SQLite database in file
    $fileDatabase = new PDO('sqlite:messaging.sqlite3');
    $fileDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /**
     * Create Tables
     */

    $createSql = <<<SQL

CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY, 
    title TEXT,
    message TEXT,
    time INTEGER
)

SQL;
    $fileDatabase->exec($createSql);

    /**
     * [1] - Inserting
     */

    $messages = [
        ["title" => "Hello!", "message" => "Just testing...", "time" => 1327301464],
        ["title" => "Hello again!", "message" => "More testing...", "time" => 1327301465],
        ["title" => "Hi!", "message" => "SQLite3 is cool...", "time" => 1327301466],
    ];

    $insertSql = <<<SQL

INSERT INTO messages (title, message, time) 
VALUES (:title, :message, :time)

SQL;
    $stmt = $fileDatabase->prepare($insertSql);
    $stmt->bindParam(':title', $title, SQLITE3_TEXT);
    $stmt->bindParam(':message', $message, SQLITE3_TEXT);
    $stmt->bindParam(':time', $time, SQLITE3_INTEGER);

    // iterate thru all messages and execute prepared insert statement
    foreach ($messages as $item) {
        $title = $item['title'];
        $message = $item['message'];
        $time = $item['time'];

        $stmt->execute();
    }

    /**
     * [2] - Retrieving
     */

    $selectAfterInsertStmt = $fileDatabase->query("SELECT * FROM messages");
    $results = $selectAfterInsertStmt->fetchAll(PDO::FETCH_OBJ);

    print "<h2>[2] Retrieving</h1>";
    var_dump($results);

    /**
     * [3] - Updating
     */

    $newTitle = $fileDatabase->quote("This is an updated string");
    $updateSql = <<<SQL

UPDATE messages 
SET title = {$newTitle} 
WHERE time = ?

SQL;
    $stmt = $fileDatabase->prepare($updateSql);
    $didUpdate = $stmt->execute([1327301466]);

    print "<h2>[3] Updating (did update?)</h1>";
    var_export($didUpdate);

    /**
     * [4] - Retrieving (after update)
     */

    $selectSql = <<<SQL

SELECT * FROM messages

SQL;
    $selectAfterUpdateStmt = $fileDatabase->query("SELECT * FROM messages");
    $results = $selectAfterUpdateStmt->fetchAll(PDO::FETCH_OBJ);

    print "<h2>[3] Retrieving (after update?)</h1>";
    var_dump($results);

    /**
     * [5] - Drop tables
     */

    $fileDatabase->exec("DROP TABLE messages");
    $fileDatabase = null; // close file db connection

} catch(PDOException $e) {
    echo $e->getMessage();
}