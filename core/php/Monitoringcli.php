<?php

/** @entrypoint */
/** @console */

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once __DIR__ . '/../../../../core/php/console.php';
require_once __DIR__ . '/../../../../core/php/core.inc.php';
if (!isset($argv[1])) {
    $argv[1] = '';
}
if (!isset($argv[2])) {
    $argv[2] = '';
}

switch ($argv[1]) {
    case 'depinstall':
        log::add('Monitoring', 'warning', 'Dependancy install end for ' . $argv[1]);
        break;
    default:
        help();
        break;
}

function help() {
    echo "Usage:  Monitoringcli.php [OPTIONS] COMMAND\n\n";
    echo "Monitoringcli allow you to do some action on the plugin from command line\n\n";
    echo "Options : \n";

    echo "\n\n";
    echo "Commands : \n";
    echo "\t depinstall : install dependencies\n";
}
