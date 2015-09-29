<?php

namespace Netgusto\BootCampBundle\Helper\Platform;

abstract class AbstractPaasPlatform implements PlatformInterface {
    public function isLocalFileStoragePersistent() {
        return FALSE;
    }
}