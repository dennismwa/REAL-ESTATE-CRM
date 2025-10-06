<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
} else {
    redirect('/login.php');
}