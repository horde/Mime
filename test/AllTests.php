<?php
use Horde\Test\AllTests;
if (!class_exists(AllTests::class)) {
    require_once 'Horde/Test/AllTests.php';
}
AllTests::init(__FILE__)->run();
