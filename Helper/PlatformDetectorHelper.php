<?php

namespace Symfony\BootCampBundle\Helper;

use Habitat\Habitat;

class PlatformDetectorHelper {

    public static function detectPlatform() {

        $env = Habitat::getAll();

        if(array_key_exists('DYNO', $env) || array_key_exists('HEROKU_BUILD_TIME', $env)) {
            return new Platform\HerokuPlatform();
        }

        # TODO: detect AppsDeck

        return new Platform\ClassicPlatform();
    }
}