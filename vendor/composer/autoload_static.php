<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitfb08f8903bf3ee534a2737ebf49212be
{
    public static $files = array (
        'e633f18ec34bed06fd2632e28e767036' => __DIR__ . '/../..' . '/src/Payment.php',
        'fad120d25686fe7ddbb46aebb0301f81' => __DIR__ . '/../..' . '/src/drivers/Payment_pay.php',
        'd95d5821ffdb62c967f85e98d68bff08' => __DIR__ . '/../..' . '/src/drivers/Payment_paystar.php',
        'e99d0a6e7f0edd8d34002b4ff00b9d09' => __DIR__ . '/../..' . '/src/drivers/Payment_zarinpal.php',
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInitfb08f8903bf3ee534a2737ebf49212be::$classMap;

        }, null, ClassLoader::class);
    }
}
