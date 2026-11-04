<?php
require __DIR__ . '/../src/bootstrap.php';
\Pharmaco\Auth\Auth::logout();
header('Location: Login.php');
