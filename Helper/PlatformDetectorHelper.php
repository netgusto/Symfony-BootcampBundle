<?php

namespace Symfony\BootCampBundle\Helper;

use Habitat\Habitat;

class PlatformDetectorHelper {

    public static function detectPlatform() {

        $env = Habitat::getAll();

        if(array_key_exists('DYNO', $env) || array_key_exists('HEROKU_BUILD_TIME', $env)) {
            return new Platform\HerokuPlatform();
        }

        if(array_key_exists('SCALINGO_MYSQL_URL', $env) || array_key_exists('SCALINGO_POSTGRESQL_URL', $env)) {
            return new Platform\ScalingoPlatform();
        }

        if(array_key_exists('DATABASE_URL', $env)) {
            return new Platform\GenericPAASPlatform();
        }

        return new Platform\ClassicPlatform();
    }
}