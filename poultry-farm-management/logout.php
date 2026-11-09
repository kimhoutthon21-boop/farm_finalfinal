<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) { do_logout(); session_start(); flash('info', 'You have been logged out.'); }
redirect('login.php');
