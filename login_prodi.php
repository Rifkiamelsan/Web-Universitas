<?php
// Pengalihan ke Halaman Login Terpadu (Unified Single Sign-On)
header("Location: login.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
