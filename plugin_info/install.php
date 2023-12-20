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

    if (config::byKey('conf::pull', 'Monitoring') == '') {
      config::save('conf::pull', '1', 'Monitoring');
    }
    if (config::byKey('conf::pullLocal', 'Monitoring') == '') {
      config::save('conf::pullLocal', '1', 'Monitoring');
    }
}

function Monitoring_update() {
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

    if (config::byKey('conf::pull', 'Monitoring') == '') {
      config::save('conf::pull', '1', 'Monitoring');
    }
    if (config::byKey('conf::pullLocal', 'Monitoring') == '') {
      config::save('conf::pullLocal', '1', 'Monitoring');
    }

    /* Ménage dans les répertoires du plugin suite au changement de nom du répertoire "ressources" -> "resources" */
    try {
      $dirToDelete = array (__DIR__ . '/../ressources',__DIR__ . '/../desktop/modal');
      $filesToDelete = array(__DIR__ . '/../plugin_info/packages.json',__DIR__ . '/../resources/install.sh');
      
      foreach ($dirToDelete as $dir) {
        log::add('Monitoring', 'debug', '[CLEAN_CHECK] Vérification de la présence du répertoire ' . $dir);
        if (file_exists($dir)) {
          shell_exec('sudo rm -rf ' . $dir);
          log::add('Monitoring', 'debug', '[CLEAN_CHECK_OK] Le répertoire ' . $dir . ' a bien été effacé.');
        }
        else {
          log::add('Monitoring', 'debug', '[CLEAN_CHECK_NA] Répertoire ' . $dir . ' non trouvé. Aucune action requise.');
        }
      }
      foreach ($filesToDelete as $file) {
        log::add('Monitoring', 'debug', '[CLEAN_CHECK] Vérification de la présence du fichier : '. $file);
        if (file_exists($file)) {
          shell_exec('sudo rm -f ' . $file);
          log::add('Monitoring', 'debug', '[CLEAN_CHECK_OK] Le fichier  ' . $file . ' a bien été effacé.');
        }
        else {
          log::add('Monitoring', 'debug', '[CLEAN_CHECK_NA] Fichier ' . $file . ' non trouvé. Aucune action requise.');
        }
      }
    } catch (Exception $e) {
      log::add('Monitoring', 'debug', '[CLEAN_CHECK_KO] WARNING :: Exception levée :: '. $e->getMessage());
    }
}

function Monitoring_remove() {
    $cron = cron::byClassAndFunction('Monitoring', 'pull');
    $cronLocal = cron::byClassAndFunction('Monitoring', 'pullLocal');
    if (is_object($cron)) {
        $cron->remove();
    }
    if (is_object($cronLocal)) {
      $cronLocal->remove();
  }
}
?>
