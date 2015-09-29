<?php

namespace Netgusto\BootCampBundle\InitHandler;

interface UserInitHandlerInterface {
    public function createAndPersistUser($username, $password);
}