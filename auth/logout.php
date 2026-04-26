<?php
require_once dirname(__DIR__) . '/includes/helpers.php';
safe_session_start();
$_SESSION = [];
session_destroy();
redirect(BASE_URL . '/auth/login.php');
