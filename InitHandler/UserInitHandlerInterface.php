<?php

namespace Symfony\BootCampBundle\InitHandler;

interface UserInitHandlerInterface {
    public function createAndPersistUser($username, $password);
}