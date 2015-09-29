<?php

namespace Netgusto\BootCampBundle\Helper;

/*
The MIT License (MIT)

Copyright (c) 2014 Bravesheep

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

=====

Taken from https://github.com/bravesheep/database-url-bundle/blob/master/src/Bravesheep/DatabaseUrlBundle/DatabaseUrlResolver.php

*/

class DatabaseUrlResolverHelper {

    private static $scheme_drivers = array(
        'postgres' => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql' => 'pdo_pgsql',
        'pdo_pgsql' => 'pdo_pgsql',
        'mysql' => 'pdo_mysql',
        'pdo_mysql' => 'pdo_mysql',
        'sqlite' => 'pdo_sqlite',
        'pdo_sqlite' => 'pdo_sqlite',
        'mssql' => 'pdo_sqlsrv',
        'pdo_mssql' => 'pdo_sqlsrv',
    );

    public static function resolve($url)
    {
        // some special cases for sqlite urls
        if (stripos($url, 'sqlite://') === 0) {
            $parts = parse_url('sqlite://host/' . substr($url, 9));
        } else if (stripos($url, 'pdo_sqlite://') === 0) {
            $parts = parse_url('pdo_sqlite://host/' . substr($url, 13));
        } else {
            $parts = parse_url($url);
        }

        #echo '<pre>' . print_r($parts, TRUE) . '</pre>';
        #die($url);

        if (false === $parts) {
            throw new \LogicException("Invalid url '{$url}'.");
        }

        $parameters = array();
        if (!isset($parts['scheme'])) {
            throw new \LogicException("Unkown scheme in '{$url}'.");
        }

        if (!isset(self::$scheme_drivers[$parts['scheme']])) {
            throw new \LogicException("Unknown database scheme '{$parts['scheme']}'");
        }

        $parameters['driver'] = self::$scheme_drivers[$parts['scheme']];

        if ($parameters['driver'] === 'pdo_sqlite') {
            if ($url === 'pdo_sqlite://:memory:' || $url === 'sqlite://:memory:') {
                $parameters['path'] = ':memory:';
            } else {
                $parameters['path'] = isset($parts['path']) ? ltrim($parts['path'], '/') : ':memory:';
            }

            if ($parameters['path'] === ':memory:') {
                $parameters['path'] = null;
                $parameters['memory'] = true;
            } else {
                $parameters['memory'] = false;
            }

            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
                if (isset($query['absolute']) && $parameters['path'] !== ':memory:') {
                    $parameters['path'] = '/' . $parameters['path'];
                }
            }

            $parameters['host'] = null;
            $parameters['port'] = null;
            $parameters['user'] = null;
            $parameters['password'] = null;
            $parameters['name'] = null;
        } else {
            $parameters['host'] = isset($parts['host']) ? $parts['host'] : null;
            $parameters['port'] = isset($parts['port']) ? $parts['port'] : null;
            $parameters['user'] = isset($parts['user']) ? $parts['user'] : null;
            $parameters['password'] = isset($parts['pass']) ? $parts['pass'] : null;
            $parameters['name'] = isset($parts['path']) ? substr($parts['path'], 1) : null;
            $parameters['path'] = null;
            $parameters['memory'] = false;
        }
        return $parameters;
    }
}
