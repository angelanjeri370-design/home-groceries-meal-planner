<?php
require_once __DIR__ . '/../session_config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
  header("Location: ../login.php");
  exit();
}