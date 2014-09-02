<?php

use Habitat\Habitat;

$_bootenv = function($container) {

    $authorized_keys = $container->getParameter('bootcamp.environment.authorizedkeys');

    $env_default_app = $container->hasParameter('environment.application.defaults') ? $container->getParameter('environment.application.defaults') : array();
    $env_default_user = $container->hasParameter('environment.user') ? $container->getParameter('environment.user') : array();
    $merged_env = array_merge(
        is_array($env_default_app) ? $env_default_app : array(),        # application defaults
        is_array($env_default_user) ? $env_default_user : array(),      # user defaults
        Habitat::getAll()       # the real environment
    );

    $container->setParameter(
        'environment',
        array_filter($merged_env, function($var) use (&$merged_env, &$authorized_keys) {
            $res = in_array(key($merged_env), $authorized_keys);
            next($merged_env);
            return $res;
        })
    );
};

$_bootenv($container);
unset($_bootenv);