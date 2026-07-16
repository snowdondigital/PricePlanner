<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
redirect(user() ? 'dashboard.php' : 'login.php');
