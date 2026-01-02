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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
     En V3 : indiquer l'argument 'true' pour contrôler le token d'accès Jeedom
     En V4 : autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
    */
    ajax::init(array());
    
    if (init('action') == 'doMigration') {
        try {
            $resultMigration = Monitoring::doMigrationToV3();
            config::save('isMigrated', '1', 'Monitoring');
            ajax::success($resultMigration);
        } catch (Exception $e) {
            config::save('isMigrated', '0', 'Monitoring');
            ajax::error(displayException($e), $e->getCode());
        }
    }

    if (init('action') == 'getHealthData') {
        $eqLogics = eqLogic::byType('Monitoring');
        $healthData = array();
        
        foreach ($eqLogics as $eqLogic) {
            $type = $eqLogic->getConfiguration('localoudistant', '');
            $eqData = array(
                'id' => $eqLogic->getId(),
                'name' => $eqLogic->getName(),
                'isEnable' => (int)$eqLogic->getIsEnable(),
                'isVisible' => (int)$eqLogic->getIsVisible(),
                'type' => $type !== '' ? $type : 'unconfigured',
                'sshHostId' => $eqLogic->getConfiguration('SSHHostId', ''),
                'sshHostName' => '',
                'commands' => array()
            );
            
            // Get SSH host name if distant
            if ($eqData['type'] === 'distant' && $eqData['sshHostId'] !== '') {
                $sshHost = eqLogic::byId($eqData['sshHostId']);
                if (is_object($sshHost)) {
                    $eqData['sshHostName'] = $sshHost->getName();
                }
            }
            
            // Get specific commands values by logicalId
            $cmdLogicalIds = array(
                'sshStatus' => 'cnx_ssh',
                'cronStatus' => 'cron_status',
                'uptime' => 'uptime',
                'loadAvg1' => 'load_avg_1mn',
                'ip' => 'ip'
            );
            
            foreach ($cmdLogicalIds as $key => $logicalId) {
                $cmd = $eqLogic->getCmd('info', $logicalId);
                if (is_object($cmd)) {
                    $eqData['commands'][$key] = array(
                        'id' => $cmd->getId(),
                        'value' => $cmd->execCmd(),
                        'unit' => $cmd->getUnite()
                    );
                } else {
                    $eqData['commands'][$key] = null;
                }
            }
            
            $healthData[] = $eqData;
        }
        
        ajax::success($healthData);
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}

