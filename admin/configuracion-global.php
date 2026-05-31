<?php
// Modulo retirado: la configuracion de portada vive en config-index.php
// y el SEO se administra desde seo.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Location: config-index.php');
exit;
