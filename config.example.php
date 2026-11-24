<?php

/*
 * Database configuration template for InfinityFree shared hosting.
 *
 * Copy this file to config.php and fill in the values from the InfinityFree
 * Control Panel (MySQL Databases section). Never commit real credentials.
 *
 * InfinityFree MySQL format:
 *   DB_HOST : sqlXXX.infinityfree.com      (e.g. sql104.infinityfree.com)
 *   DB_USER : if0_XXXXXXXX                 (your InfinityFree account id)
 *   DB_PASS : your MySQL account password
 *   DB_NAME : if0_XXXXXXXX_school          (database name you created)
 *   DB_PORT : 3306
 *
 * Local XAMPP example:
 *   DB_HOST = localhost, DB_USER = root, DB_PASS = "", DB_NAME = student_management
 */

if (realpath($_SERVER["SCRIPT_FILENAME"] ?? "") === realpath(__FILE__)) {
    http_response_code(403);
    exit("Forbidden");
}

define("DB_HOST", "sqlXXX.infinityfree.com");
define("DB_USER", "if0_XXXXXXX");
define("DB_PASS", "your_mysql_password");
define("DB_NAME", "if0_XXXXXXX_school");
define("DB_PORT", 3306);
