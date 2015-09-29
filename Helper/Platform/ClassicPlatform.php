<?php

namespace Netgusto\BootCampBundle\Helper\Platform;

class ClassicPlatform implements PlatformInterface {

    public function getPlatformName() {
        return 'Classic platform';
    }

    public function isLocalFileStoragePersistent() {
        return TRUE;
    }
}