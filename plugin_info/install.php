<?php

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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function Monitoring_install() {
    // Get Plugin Version from plugin_info/info.json
    $pluginVersion = Monitoring::getPluginVersion();
    config::save('pluginVersion', $pluginVersion, 'Monitoring');

    message::removeAll('Monitoring', 'update');
    message::add('Monitoring', 'Mise à jour du plugin Monitoring :: v' . $pluginVersion, null, 'update');

    $cron = cron::byClassAndFunction('Monitoring', 'pull');
    if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass('Monitoring');
        $cron->setFunction('pull');
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule('*/15 * * * *');
        $cron->setTimeout(15);
        $cron->save();
    }

    $cronLocal = cron::byClassAndFunction('Monitoring', 'pullLocal');
    if (!is_object($cronLocal)) {
        $cronLocal = new cron();
        $cronLocal->setClass('Monitoring');
        $cronLocal->setFunction('pullLocal');
        $cronLocal->setEnable(1);
        $cronLocal->setDeamon(0);
        $cronLocal->setSchedule('* * * * *');
        $cronLocal->setTimeout(1);
        $cronLocal->save();
    }

    if (config::byKey('configPull', 'Monitoring') == '') {
        config::save('configPull', '1', 'Monitoring');
    }
    if (config::byKey('configPullLocal', 'Monitoring') == '') {
        config::save('configPullLocal', '0', 'Monitoring');
    }
}

function Monitoring_update() {
    $pluginVersion = Monitoring::getPluginVersion();
    config::save('pluginVersion', $pluginVersion, 'Monitoring');

    // Check Version of the plugin
    log::add('Monitoring', 'debug', '[UPDATE_CHECK] Vérification des versions :: ' . jeedom::version() . ' vs ' . '4.5' . ' :: ' . version_compare(jeedom::version(), '4.5', '>='));
    if (version_compare(jeedom::version(), '4.5', '<')) {
        $updateConf = update::byLogicalId('Monitoring');
        if (is_object($updateConf)) {
            $updateConf->setConfiguration('doNotUpdate', '1');
            $updateConf->save();
        }

        message::removeAll('Monitoring', 'update');
        message::add('Monitoring', 'Mise à jour du plugin Monitoring :: v' . $pluginVersion, null, 'update');
        message::add('Monitoring', 'La version de Jeedom n\'est pas compatible avec le plugin Monitoring. Veuillez mettre à jour Jeedom pour bénéficier des dernières fonctionnalités.', null, 'update');
        event::add('jeedom::alert', array(
            'level' => 'danger',
            'message' => __('[WARNING] La prochaine version du plugin Monitoring ne supportera plus les versions de Jeedom < "4.5". Veuillez mettre à jour Jeedom pour bénéficier des dernières fonctionnalités.\n En attendant, les mises à jour de Monitoring sont désactivées.', __FILE__),
        ));
    }
    else {
        message::removeAll('Monitoring', 'update');
        message::add('Monitoring', 'Mise à jour du plugin Monitoring :: Version :: ' . $pluginVersion, null, 'update');
    }

    $cron = cron::byClassAndFunction('Monitoring', 'pull');
    if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass('Monitoring');
        $cron->setFunction('pull');
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule('*/15 * * * *');
        $cron->setTimeout(15);
        $cron->save();
    }

    $cronLocal = cron::byClassAndFunction('Monitoring', 'pullLocal');
    if (!is_object($cronLocal)) {
        $cronLocal = new cron();
        $cronLocal->setClass('Monitoring');
        $cronLocal->setFunction('pullLocal');
        $cronLocal->setEnable(1);
        $cronLocal->setDeamon(0);
        $cronLocal->setSchedule('* * * * *');
        $cronLocal->setTimeout(1);
        $cronLocal->save();
    }

    if (config::byKey('configPull', 'Monitoring') == '') {
        config::save('configPull', '1', 'Monitoring');
    }
    if (config::byKey('configPullLocal', 'Monitoring') == '') {
        config::save('configPullLocal', '0', 'Monitoring');
    }

    foreach (eqLogic::byType('Monitoring', false) as $Monitoring) {
        if ($Monitoring->getConfiguration('pull_use_custom') == '') {
            $Monitoring->setConfiguration('pull_use_custom', '0');
            $Monitoring->save();
        }
    }

    /* Ménage dans les répertoires du plugin suite au changement de nom du répertoire "ressources" -> "resources" */
    try {
        $dirToDelete = array(
            __DIR__ . '/../ressources',
            __DIR__ . '/../desktop/modal',
            __DIR__ . '/../mobile',
            __DIR__ . '/../core/img',
            __DIR__ . '/../resources'
        );
        
        $filesToDelete = array(
            __DIR__ . '/../plugin_info/packages.json',
            __DIR__ . '/../desktop/js/panel.js',
            __DIR__ . '/../desktop/php/panel.php',
        );

        foreach ($dirToDelete as $dir) {
            log::add('Monitoring', 'debug', '[CLEAN_CHECK] Vérification de la présence du répertoire ' . $dir);
            if (file_exists($dir)) {
                shell_exec('sudo rm -rf ' . $dir);
                log::add('Monitoring', 'debug', '[CLEAN_CHECK_OK] Le répertoire ' . $dir . ' a bien été effacé.');
            } else {
                log::add('Monitoring', 'debug', '[CLEAN_CHECK_NA] Répertoire ' . $dir . ' non trouvé. Aucune action requise.');
            }
        }
        foreach ($filesToDelete as $file) {
            log::add('Monitoring', 'debug', '[CLEAN_CHECK] Vérification de la présence du fichier : ' . $file);
            if (file_exists($file)) {
                shell_exec('sudo rm -f ' . $file);
                log::add('Monitoring', 'debug', '[CLEAN_CHECK_OK] Le fichier  ' . $file . ' a bien été effacé.');
            } else {
                log::add('Monitoring', 'debug', '[CLEAN_CHECK_NA] Fichier ' . $file . ' non trouvé. Aucune action requise.');
            }
        }
    } catch (Exception $e) {
        log::add('Monitoring', 'debug', '[CLEAN_CHECK_KO] WARNING :: Exception levée :: ' . $e->getMessage());
    }
}

function Monitoring_remove() {
    foreach (eqLogic::byType('Monitoring', false) as $Monitoring) {
        $cron = cron::byClassAndFunction('Monitoring', 'pullCustom', array('Monitoring_Id' => intval($Monitoring->getId())));
        if (is_object($cron)) {
            $cron->remove();
        }
    }
    $cron = cron::byClassAndFunction('Monitoring', 'pull');
    $cronLocal = cron::byClassAndFunction('Monitoring', 'pullLocal');
    if (is_object($cron)) {
        $cron->remove();
    }
    if (is_object($cronLocal)) {
        $cronLocal->remove();
    }
}
