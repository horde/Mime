<?php
if (!class_exists(\Horde_Test_Bootstrap::class)) {
    require_once 'Horde/Mime/bootstrap.php';
}
Horde_Test_Bootstrap::bootstrap(dirname(__FILE__));
