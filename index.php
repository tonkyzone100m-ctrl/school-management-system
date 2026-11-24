<?php

/*
 * Application entry point.
 * Sends authenticated users to their role dashboard and everyone else to login.
 */

require __DIR__ . "/functions.php";

if (!empty($_SESSION["id"]) && !empty($_SESSION["role"])) {
    redirect(dashboard_for($_SESSION["role"]));
}

redirect("login.php");
