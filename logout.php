<?php
session_start();
session_unset();    // tum session degisikliklerini siler
session_destroy();  // oturumu sonlandirir
header("Location: login.php"); // kullaniciyi login sayfasina yonlendirir
exit();
?>