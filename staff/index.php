<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();
header('Location: dashboard.php');
exit;
