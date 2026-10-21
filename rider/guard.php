<?php
require_once __DIR__ . '/../session_config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rider') {
  header("Location: ../login.php");
  exit();
}