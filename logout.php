<?php
require_once 'config.php';

if (isLoggedIn()) {
    logActivity('Logout', 'User logged out');
}

session_destroy();
redirect('/login.php');