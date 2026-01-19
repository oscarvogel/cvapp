<?php
require_once __DIR__ . '/../init.php';
logout_user();
header('Location: login.php');
exit;