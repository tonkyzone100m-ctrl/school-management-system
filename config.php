<?php

/*
 * Database configuration for the School Management System.
 *
 * LOCAL XAMPP VALUES (current file):
 *   Host: localhost   User: root   Password: (empty)   Database: student_management
 *
 * For InfinityFree, replace these values using config.example.php as a guide,
 * then upload this file to htdocs/ on the server.
 *
 * NOTE: This file is blocked from direct HTTP access via .htaccess and also
 * refuses to output anything when requested directly.
 */

if (realpath($_SERVER["SCRIPT_FILENAME"] ?? "") === realpath(__FILE__)) {
    http_response_code(403);
    exit("Forbidden");
}

define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "");
define("DB_NAME", "student_management");
define("DB_PORT", 3306);
