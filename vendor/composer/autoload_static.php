<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc81645b28d9119bb10e57f15cd8259cc
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc81645b28d9119bb10e57f15cd8259cc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc81645b28d9119bb10e57f15cd8259cc::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitc81645b28d9119bb10e57f15cd8259cc::$classMap;

        }, null, ClassLoader::class);
    }
}
