<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; witfhout even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * *************************** Requires ********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class Monitoring extends eqLogic {
	public function decrypt() {
		$this->setConfiguration('user', utils::decrypt($this->getConfiguration('user')));
		$this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
		$this->setConfiguration('ssh-key', utils::decrypt($this->getConfiguration('ssh-key')));
		$this->setConfiguration('ssh-passphrase', utils::decrypt($this->getConfiguration('ssh-passphrase')));
	}
	
	public function encrypt() {
		$this->setConfiguration('user', utils::encrypt($this->getConfiguration('user')));
		$this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
		$this->setConfiguration('ssh-key', utils::encrypt($this->getConfiguration('ssh-key')));
		$this->setConfiguration('ssh-passphrase', utils::encrypt($this->getConfiguration('ssh-passphrase')));
	}

	public static function dependancy_install() {
		$_logName = __CLASS__ . '_update';
		log::add($_logName, 'info', '[INSTALL] Installation des dépendances');

		try {
			$_plugin = plugin::byId('sshmanager');
			log::add($_logName, 'info', __('[INSTALL] Le plugin SSHManager est installé', __FILE__));
			if (!$_plugin->isActive()) {
				log::add($_logName, 'error', __('[INSTALL] Le plugin SSHManager n\'est pas activé', __FILE__));
				$_plugin->setIsEnable(1, true, true);
				log::add($_logName, 'info', __('[INSTALL] Activation du plugin SSHManager', __FILE__));
			} else {
				log::add($_logName, 'info', __('[INSTALL] Plugin SSHManager :: actif', __FILE__));
			}
		} catch (Exception $e) {
			log::add($_logName, 'warning', __('[INSTALL] Exception: ' . $e->getMessage(), __FILE__));
			
			log::add($_logName, 'error', __('[INSTALL] Lancement de l\'installation du plugin SSHManager', __FILE__));
			
			// Installation du plugin SSHManager
			$_update = update::byLogicalId('sshmanager');
			if (!is_object($_update)) {
				$_update = new update();
			}
			$_update->setLogicalId('sshmanager');
			$_update->setType('plugin');
			$_update->setSource('github');
			$_update->setConfiguration('user', 'TiTidom-RC');
			$_update->setConfiguration('repository', 'SSH-Manager');
			$_update->setConfiguration('version', 'dev');
			$_update->setConfiguration('token', '');
			$_update->save();
			$_update->doUpdate();
			sleep(2);
			
			try {
				$_plugin = plugin::byId('sshmanager');
				$_plugin->setIsEnable(1, true, true);
				log::add($_logName, 'info', __('[INSTALL] Activation du plugin SSHManager', __FILE__));
				jeedom::cleanFileSystemRight();
			} catch (Exception $e) {
				log::add($_logName, 'warning', '[INSTALL] Exception :: ' . $e->getMessage());
				log::add($_logName, 'error', '[INSTALL] Le plugin SSHManager n\'a pas pu être installé !');
			}
		}
        return array('log' => log::getPathToLog(__CLASS__ . '_update'));
    }

	public static function dependancy_info() {
        $_logName = __CLASS__ . '_update';

		$return = array();
		$return['log'] = log::getPathToLog($_logName);
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependency';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependency')) {
			log::add($_logName, 'info', __('[DEP] Installation des dépendances en cours', __FILE__));
			$return['state'] = 'in_progress';
		} else {
			try {
				$_plugin = plugin::byId('sshmanager');
				if (!$_plugin->isActive()) {
					log::add($_logName, 'error', __('[DEP] Le plugin SSHManager n\'est pas activé', __FILE__));
					$return['state'] = 'nok';

				} else {
					log::add($_logName, 'info', __('[DEP] Vérification des dépendances :: OK', __FILE__));
					$return['state'] = 'ok';
				}
			} catch (Exception $e) {
				log::add($_logName, 'warning', '[DEP] Exception :: ' . $e->getMessage());
				log::add($_logName, 'error', '[DEP] Le plugin SSHManager n\'est pas installé');
				$return['state'] = 'nok';
			}
		}
		
		return $return;
	}

	public static function pull() {
		log::add('Monitoring', 'debug', '[PULL] Config Pull :: '. config::byKey('configPull', 'Monitoring'));
		if (config::byKey('configPull', 'Monitoring') == '1') {
			foreach (eqLogic::byType('Monitoring', true) as $Monitoring) {
				if ($Monitoring->getConfiguration('pull_use_custom', '0') == '0' && ($Monitoring->getConfiguration('localoudistant') != 'local' || config::byKey('configPullLocal', 'Monitoring') == '0')) {
					$cronState = $Monitoring->getCmd(null, 'cron_status');
					if (is_object($cronState) && $cronState->execCmd() === 0) {
						log::add('Monitoring', 'debug', '[' . $Monitoring->getName() .'][PULL] Pull (15min) :: En Pause');
					} else {
						log::add('Monitoring', 'info', '[' . $Monitoring->getName() .'][PULL] Lancement (15min)');
						$Monitoring->getInformations();
						$mc = cache::byKey('MonitoringWidgetmobile' . $Monitoring->getId());
						$mc->remove();
						$mc = cache::byKey('MonitoringWidgetdashboard' . $Monitoring->getId());
						$mc->remove();
						$Monitoring->toHtml('mobile');
						$Monitoring->toHtml('dashboard');
						$Monitoring->refreshWidget();
					}
				}
			}
		}
	}

	public static function pullLocal() {
		log::add('Monitoring', 'debug', '[PULLLOCAL] Config PullLocal :: '. config::byKey('configPullLocal', 'Monitoring'));
		if (config::byKey('configPullLocal', 'Monitoring') == '1') {
			foreach (eqLogic::byType('Monitoring', true) as $Monitoring) {
				if ($Monitoring->getConfiguration('pull_use_custom', '0') == '0' && $Monitoring->getConfiguration('localoudistant') == 'local') {
					$cronState = $Monitoring->getCmd(null, 'cron_status');
					if (is_object($cronState) && $cronState->execCmd() === 0) {
						log::add('Monitoring', 'debug', '[' . $Monitoring->getName() .'][PULLLOCAL] PullLocal (1min) :: En Pause');
					} else {
						log::add('Monitoring', 'info', '[' . $Monitoring->getName() .'][PULLLOCAL] Lancement (1min)');
						$Monitoring->getInformations();
						$mc = cache::byKey('MonitoringWidgetmobile' . $Monitoring->getId());
						$mc->remove();
						$mc = cache::byKey('MonitoringWidgetdashboard' . $Monitoring->getId());
						$mc->remove();
						$Monitoring->toHtml('mobile');
						$Monitoring->toHtml('dashboard');
						$Monitoring->refreshWidget();
					}
				}
			}
		}
	}

	public static function pullCustom($_options) {
		$Monitoring = Monitoring::byId($_options['Monitoring_Id']);
		if (is_object($Monitoring)) {
			$cronState = $Monitoring->getCmd(null, 'cron_status');
			if (is_object($cronState) && $cronState->execCmd() === 0) {
				log::add('Monitoring', 'debug', '[' . $Monitoring->getName() .'][PULLCUSTOM] Pull (Custom) :: En Pause');
			} else {
				log::add('Monitoring', 'debug', '[' . $Monitoring->getName() .'][PULLCUSTOM] Lancement (Custom)');
				$Monitoring->getInformations();
				$mc = cache::byKey('MonitoringWidgetmobile' . $Monitoring->getId());
				$mc->remove();
				$mc = cache::byKey('MonitoringWidgetdashboard' . $Monitoring->getId());
				$mc->remove();
				$Monitoring->toHtml('mobile');
				$Monitoring->toHtml('dashboard');
				$Monitoring->refreshWidget();
			}
		}
	}

  	public static function postConfig_configPullLocal($value) {
	    log::add('Monitoring', 'debug', '[CONFIG-SAVE] Configuration PullLocal :: '. $value);
  	}
  	
	public static function postConfig_configPull($value) {
	    log::add('Monitoring', 'debug', '[CONFIG-SAVE] Configuration Pull :: '. $value);
  	}

	// Fonction exécutée automatiquement avant la suppression de l'équipement
	public function preRemove() {
		$cron = cron::byClassAndFunction('Monitoring', 'pullCustom', array('Monitoring_Id' => intval($this->getId())));
		if (is_object($cron)) {
			$cron->remove();
		}
	}

	public function postUpdate() {

	}

	public function postSave() {
		$MonitoringCmd = $this->getCmd(null, 'namedistri');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Distribution', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('namedistri');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setIsVisible(1);
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'uptime');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Démarré Depuis', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('uptime');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'loadavg1mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 1 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('loadavg1mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'loadavg5mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 5 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('loadavg5mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'loadavg15mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 15 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('loadavg15mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'Mem');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('Mem');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'Mempourc');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Libre (Pourcentage)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('Mempourc');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'Mem_swap');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('Mem_swap');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'Swappourc');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Libre (Pourcentage)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('Swappourc');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'ethernet0');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Réseau (TX-RX)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('ethernet0');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'ethernet0_name');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Carte Réseau', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('ethernet0_name');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'ethernet0_ip');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Adresse IP', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('ethernet0_ip');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hddtotal');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Total', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hddtotal');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hddused');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Utilisé', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hddused');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hddpourcused');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Utilisé (Pourcentage)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hddpourcused');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		if ($this->getConfiguration('synology') == '1') {
			// Synology volume 2
			if ($this->getConfiguration('synologyv2') == '1') {
				$MonitoringCmd = $this->getCmd(null, 'hddtotalv2');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddtotalv2');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'hddusedv2');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddusedv2');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'hddpourcusedv2');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Utilisé (Pourcentage)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddpourcusedv2');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

			}
			
			// Synology volume USB
			if ($this->getConfiguration('synologyusb') == '1') {
				$MonitoringCmd = $this->getCmd(null, 'hddtotalusb');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddtotalusb');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'hddusedusb');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddusedusb');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'hddpourcusedusb');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Utilisé (Pourcentage)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddpourcusedusb');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}
			}

			// Synology volume eSATA
			if ($this->getConfiguration('synologyesata') == '1') {
				$MonitoringCmd = $this->getCmd(null, 'hddtotalesata');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddtotalesata');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'hddusedesata');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddusedesata');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'hddpourcusedesata');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Utilisé (Pourcentage)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('hddpourcusedesata');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}
			}
		}

		$MonitoringCmd = $this->getCmd(null, 'cpu');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('CPU(s)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cpu');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'cpu_temp');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Température CPU', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cpu_temp');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'cnx_ssh');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('SSH Status', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cnx_ssh');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'cron_status');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Cron Status', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cron_status');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('binary');
			$MonitoringCmd->setIsVisible(1);
			$MonitoringCmd->setIsHistorized(0);
			$MonitoringCmd->save();
		}
		$cron_status_cmd = $MonitoringCmd->getId();

		$MonitoringCmd = $this->getCmd(null, 'cron_on');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Cron On', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cron_on');
			$MonitoringCmd->setType('action');
			$MonitoringCmd->setSubType('other');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-play-circle"></i>');
			$MonitoringCmd->setValue($cron_status_cmd);
			$MonitoringCmd->setIsVisible(0);
			$MonitoringCmd->setTemplate('dashboard', 'core::toggle');
            $MonitoringCmd->setTemplate('mobile', 'core::toggle');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'cron_off');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Cron Off', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cron_off');
			$MonitoringCmd->setType('action');
			$MonitoringCmd->setSubType('other');
			$MonitoringCmd->setDisplay('icon', '<i class="icon fas fa-pause-circle"></i>');
			$MonitoringCmd->setValue($cron_status_cmd);
			$MonitoringCmd->setIsVisible(0);
			$MonitoringCmd->setTemplate('dashboard', 'core::toggle');
			$MonitoringCmd->setTemplate('mobile', 'core::toggle');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'perso1');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('perso1', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('perso1');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setIsVisible(0);
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'perso2');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('perso2', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('perso2');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setIsVisible(0);
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'reboot');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Reboot', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('reboot');
			$MonitoringCmd->setType('action');
			$MonitoringCmd->setSubType('other');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'poweroff');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('PowerOff', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('poweroff');
			$MonitoringCmd->setType('action');
			$MonitoringCmd->setSubType('other');
			$MonitoringCmd->save();
		}

		if ($this->getConfiguration('pull_use_custom', '0') == '1') {
			$cron = cron::byClassAndFunction('Monitoring', 'pullCustom', array('Monitoring_Id' => intval($this->getId())));
			if (!is_object($cron)) {
				log::add('Monitoring', 'debug', '['. $this->getName() .'][POSTSAVE] Add CustomPull');
				$cron = new cron();
				$cron->setClass('Monitoring');
				$cron->setFunction('pullCustom');
				$cron->setOption(array('Monitoring_Id' => intval($this->getId())));
				$cron->setDeamon(0);
			}
			if ($this->getIsEnable()) {
				$cron->setEnable(1);
			} else {
				$cron->setEnable(0);
			}

			$_cronPattern = $this->getConfiguration('pull_cron', '*/15 * * * *');
			$cron->setSchedule($_cronPattern);

			if ($_cronPattern === '* * * * *') {
				$cron->setTimeout(1);
				log::add('Monitoring', 'debug', '['. $this->getName() .'][POSTSAVE] CustomPull :: Timeout 1min');
			} else {
				$_ExpMatch = array();
				$_ExpResult = preg_match('/^([0-9,]+|\*)\/([0-9]+)/', $_cronPattern, $_ExpMatch);
				if ($_ExpResult === 1) {
					$cron->setTimeout(intval($_ExpMatch[2]));
					log::add('Monitoring', 'debug', '['. $this->getName() .'][POSTSAVE] CustomPull :: Timeout '. $_ExpMatch[2] .'min');
				} else {
					$cron->setTimeout(15);
					log::add('Monitoring', 'debug', '['. $this->getName() .'][POSTSAVE] CustomPull :: Timeout 15min');
				}
			}
			$cron->save();
		} else {
			$cron = cron::byClassAndFunction('Monitoring', 'pullCustom', array('Monitoring_Id' => intval($this->getId())));
        	if (is_object($cron)) {
				log::add('Monitoring', 'debug', '['. $this->getName() .'][POSTSAVE] Remove CustomPull');
            	$cron->remove();
        	}
		}

		$this->getInformations();
	}

	public static $_widgetPossibility = array('custom' => true, 'custom::layout' => false);

	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$_version = jeedom::versionAlias($_version);

		$cnx_ssh = $this->getCmd(null,'cnx_ssh');
		$replace['#cnx_ssh#'] = (is_object($cnx_ssh)) ? $cnx_ssh->execCmd() : '';
		$replace['#cnx_ssh_id#'] = is_object($cnx_ssh) ? $cnx_ssh->getId() : '';

		$cron_status = $this->getCmd(null,'cron_status');
		$replace['#cron_status#'] = (is_object($cron_status)) ? $cron_status->execCmd() : '';
		$replace['#cron_status_id#'] = is_object($cron_status) ? $cron_status->getId() : '';
		$replace['#cron_status_display#'] = (is_object($cron_status) && $cron_status->getIsVisible()) ? "inline-block" : "none";
		$replace['#cron_status_custom#'] = $this->getConfiguration('pull_use_custom', '0');

		$namedistri = $this->getCmd(null,'namedistri');
		$replace['#namedistri#'] = (is_object($namedistri)) ? $namedistri->execCmd() : '';
		$replace['#namedistriid#'] = is_object($namedistri) ? $namedistri->getId() : '';
		$replace['#namedistri_display#'] = (is_object($namedistri) && $namedistri->getIsVisible()) ? "block" : "none";
		$replace['#namedistri_collect#'] = (is_object($namedistri) && $namedistri->getIsVisible()) ? $namedistri->getCollectDate() : "-";
        $replace['#namedistri_value#'] = (is_object($namedistri) && $namedistri->getIsVisible()) ? $namedistri->getValueDate() : "-";

		$loadavg1mn = $this->getCmd(null,'loadavg1mn');
		$replace['#loadavg1mn#'] = (is_object($loadavg1mn)) ? $loadavg1mn->execCmd() : '';
		$replace['#loadavg1mnid#'] = is_object($loadavg1mn) ? $loadavg1mn->getId() : '';
		$replace['#loadavg_display#'] = (is_object($loadavg1mn) && $loadavg1mn->getIsVisible()) ? "block" : "none";
		$replace['#loadavg_collect#'] = (is_object($loadavg1mn) && $loadavg1mn->getIsVisible()) ? $loadavg1mn->getCollectDate() : "-";
        $replace['#loadavg_value#'] = (is_object($loadavg1mn) && $loadavg1mn->getIsVisible()) ? $loadavg1mn->getValueDate() : "-";	

		$replace['#loadavg1mn_colorlow#'] = $this->getConfiguration('loadavg1mn_colorlow');
		$replace['#loadavg1mn_colorhigh#'] = $this->getConfiguration('loadavg1mn_colorhigh');

		$loadavg5mn = $this->getCmd(null,'loadavg5mn');
		$replace['#loadavg5mn#'] = (is_object($loadavg5mn)) ? $loadavg5mn->execCmd() : '';
		$replace['#loadavg5mnid#'] = is_object($loadavg5mn) ? $loadavg5mn->getId() : '';

		$replace['#loadavg5mn_colorlow#'] = $this->getConfiguration('loadavg5mn_colorlow');
		$replace['#loadavg5mn_colorhigh#'] = $this->getConfiguration('loadavg5mn_colorhigh');

		$loadavg15mn = $this->getCmd(null,'loadavg15mn');
		$replace['#loadavg15mn#'] = (is_object($loadavg15mn)) ? $loadavg15mn->execCmd() : '';
		$replace['#loadavg15mnid#'] = is_object($loadavg15mn) ? $loadavg15mn->getId() : '';

		$replace['#loadavg15mn_colorlow#'] = $this->getConfiguration('loadavg15mn_colorlow');
		$replace['#loadavg15mn_colorhigh#'] = $this->getConfiguration('loadavg15mn_colorhigh');
		
		$uptime = $this->getCmd(null,'uptime');
		$replace['#uptime#'] = (is_object($uptime)) ? $uptime->execCmd() : '';
		$replace['#uptimeid#'] = is_object($uptime) ? $uptime->getId() : '';
		$replace['#uptime_display#'] = (is_object($uptime) && $uptime->getIsVisible()) ? "block" : "none";
		$replace['#uptime_collect#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getCollectDate() : "-";
        $replace['#uptime_value#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getValueDate() : "-";
		
		$hddtotal = $this->getCmd(null,'hddtotal');
		$replace['#hddtotal#'] = (is_object($hddtotal)) ? $hddtotal->execCmd() : '';
		$replace['#hddtotalid#'] = is_object($hddtotal) ? $hddtotal->getId() : '';
		$replace['#hddused_display#'] = (is_object($hddtotal) && $hddtotal->getIsVisible()) ? "block" : "none";
		$replace['#hddtotal_collect#'] = (is_object($hddtotal) && $hddtotal->getIsVisible()) ? $hddtotal->getCollectDate() : "-";
        $replace['#hddtotal_value#'] = (is_object($hddtotal) && $hddtotal->getIsVisible()) ? $hddtotal->getValueDate() : "-";

		$hddused = $this->getCmd(null,'hddused');
		$replace['#hddused#'] = (is_object($hddused)) ? $hddused->execCmd() : '';
		$replace['#hddusedid#'] = is_object($hddused) ? $hddused->getId() : '';

		$hddused_pourc = $this->getCmd(null,'hddpourcused');
		$replace['#hddpourcused#'] = (is_object($hddused_pourc)) ? $hddused_pourc->execCmd() : '';
		$replace['#hddpourcusedid#'] = is_object($hddused_pourc) ? $hddused_pourc->getId() : '';

		$replace['#hddpourcused_colorlow#'] = $this->getConfiguration('hddpourcused_colorlow');
		$replace['#hddpourcused_colorhigh#'] = $this->getConfiguration('hddpourcused_colorhigh');
				
		$Mem = $this->getCmd(null,'Mem');
		$replace['#Mem#'] = (is_object($Mem)) ? $Mem->execCmd() : '';
		$replace['#Memid#'] = is_object($Mem) ? $Mem->getId() : '';
		$replace['#Mem_display#'] = (is_object($Mem) && $Mem->getIsVisible()) ? "block" : "none";
		$replace['#Mem_collect#'] = (is_object($Mem) && $Mem->getIsVisible()) ? $Mem->getCollectDate() : "-";
        $replace['#Mem_value#'] = (is_object($Mem) && $Mem->getIsVisible()) ? $Mem->getValueDate() : "-";

		$Mempourc = $this->getCmd(null,'Mempourc');
		$replace['#Mempourc#'] = (is_object($Mempourc)) ? $Mempourc->execCmd() : '';
		$replace['#Mempourcid#'] = is_object($Mempourc) ? $Mempourc->getId() : '';

		$replace['#Mempourc_colorhigh#'] = $this->getConfiguration('Mempourc_colorhigh');
		$replace['#Mempourc_colorlow#'] = $this->getConfiguration('Mempourc_colorlow');

		$Mem_swap = $this->getCmd(null,'Mem_swap');
		$replace['#Mem_swap#'] = (is_object($Mem_swap)) ? $Mem_swap->execCmd() : '';
		$replace['#Mem_swapid#'] = is_object($Mem_swap) ? $Mem_swap->getId() : '';
		$replace['#Mem_swap_display#'] = (is_object($Mem_swap) && $Mem_swap->getIsVisible()) ? "block" : "none";
		$replace['#Mem_swap_collect#'] = (is_object($Mem_swap) && $Mem_swap->getIsVisible()) ? $Mem_swap->getCollectDate() : "-";
        $replace['#Mem_swap_value#'] = (is_object($Mem_swap) && $Mem_swap->getIsVisible()) ? $Mem_swap->getValueDate() : "-";

		$Swappourc = $this->getCmd(null,'Swappourc');
		$replace['#Swappourc#'] = (is_object($Swappourc)) ? $Swappourc->execCmd() : '';
		$replace['#Swappourcid#'] = is_object($Swappourc) ? $Swappourc->getId() : '';

		$replace['#Swappourc_colorhigh#'] = $this->getConfiguration('Swappourc_colorhigh');
		$replace['#Swappourc_colorlow#'] = $this->getConfiguration('Swappourc_colorlow');

		$ethernet0 = $this->getCmd(null,'ethernet0');
		$replace['#ethernet0#'] = (is_object($ethernet0)) ? $ethernet0->execCmd() : '';
		$replace['#ethernet0id#'] = is_object($ethernet0) ? $ethernet0->getId() : '';
		$replace['#ethernet0_display#'] = (is_object($ethernet0) && $ethernet0->getIsVisible()) ? "block" : "none";
		$replace['#ethernet0_collect#'] = (is_object($ethernet0) && $ethernet0->getIsVisible()) ? $ethernet0->getCollectDate() : "-";
        $replace['#ethernet0_value#'] = (is_object($ethernet0) && $ethernet0->getIsVisible()) ? $ethernet0->getValueDate() : "-";

		$ethernet0_name = $this->getCmd(null,'ethernet0_name');
		$replace['#ethernet0_name#'] = (is_object($ethernet0_name)) ? $ethernet0_name->execCmd() : '';
		$replace['#ethernet0_nameid#'] = is_object($ethernet0_name) ? $ethernet0_name->getId() : '';

		$ethernet0_ip = $this->getCmd(null,'ethernet0_ip');
		$replace['#ethernet0_ip#'] = (is_object($ethernet0_ip)) ? $ethernet0_ip->execCmd() : '';
		$replace['#ethernet0_ipid#'] = is_object($ethernet0_ip) ? $ethernet0_ip->getId() : '';

		$cpu = $this->getCmd(null,'cpu');
		$replace['#cpu#'] = (is_object($cpu)) ? $cpu->execCmd() : '';
		$replace['#cpuid#'] = is_object($cpu) ? $cpu->getId() : '';
		$replace['#cpu_display#'] = (is_object($cpu) && $cpu->getIsVisible()) ? "block" : "none";
		$replace['#cpu_collect#'] = (is_object($cpu) && $cpu->getIsVisible()) ? $cpu->getCollectDate() : "-";
        $replace['#cpu_value#'] = (is_object($cpu) && $cpu->getIsVisible()) ? $cpu->getValueDate() : "-";

		$cpu_temp = $this->getCmd(null,'cpu_temp');
		$replace['#cpu_temp#'] = (is_object($cpu_temp)) ? $cpu_temp->execCmd() : '';
		$replace['#cpu_tempid#'] = is_object($cpu_temp) ? $cpu_temp->getId() : '';
		$replace['#cpu_temp_display#'] = (is_object($cpu_temp) && $cpu_temp->getIsVisible()) ? 'OK' : '';

		$replace['#cpu_temp_colorlow#'] = $this->getConfiguration('cpu_temp_colorlow');
		$replace['#cpu_temp_colorhigh#'] = $this->getConfiguration('cpu_temp_colorhigh');

		// Syno Volume 2
		$SynoV2Visible = (is_object($this->getCmd(null,'hddtotalv2')) && $this->getCmd(null,'hddtotalv2')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoV2Visible == 'OK' && $this->getConfiguration('synologyv2') == '1') {
			$hddusedv2 = $this->getCmd(null,'hddusedv2');
			$replace['#hddusedv2#'] = (is_object($hddusedv2)) ? $hddusedv2->execCmd() : '';
			$replace['#hddusedv2id#'] = is_object($hddusedv2) ? $hddusedv2->getId() : '';

			$hddusedv2_pourc = $this->getCmd(null,'hddpourcusedv2');
			$replace['#hddpourcusedv2#'] = (is_object($hddusedv2_pourc)) ? $hddusedv2_pourc->execCmd() : '';
			$replace['#hddpourcusedv2id#'] = is_object($hddusedv2_pourc) ? $hddusedv2_pourc->getId() : '';
			$replace['#hddpourcusedv2_colorlow#'] = $this->getConfiguration('hddpourcusedv2_colorlow');
			$replace['#hddpourcusedv2_colorhigh#'] = $this->getConfiguration('hddpourcusedv2_colorhigh');

			$hddtotalv2 = $this->getCmd(null,'hddtotalv2');
			$replace['#synovolume2_display#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? 'OK' : '';
			$replace['#hddusedv2_display#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? "block" : "none";
			$replace['#hddtotalv2#'] = (is_object($hddtotalv2)) ? $hddtotalv2->execCmd() : '';
			$replace['#hddtotalv2id#'] = is_object($hddtotalv2) ? $hddtotalv2->getId() : '';
			$replace['#hddtotalv2_collect#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? $hddtotalv2->getCollectDate() : "-";
        	$replace['#hddtotalv2_value#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? $hddtotalv2->getValueDate() : "-";
		}

		// Syno Volume USB
		$SynoUSBVisible = (is_object($this->getCmd(null,'hddtotalusb')) && $this->getCmd(null,'hddtotalusb')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoUSBVisible == 'OK' && $this->getConfiguration('synologyusb') == '1') {
			$hddusedusb = $this->getCmd(null,'hddusedusb');
			$replace['#hddusedusb#'] = (is_object($hddusedusb)) ? $hddusedusb->execCmd() : '';
			$replace['#hddusedusbid#'] = is_object($hddusedusb) ? $hddusedusb->getId() : '';

			$hddusedusb_pourc = $this->getCmd(null,'hddpourcusedusb');
			$replace['#hddpourcusedusb#'] = (is_object($hddusedusb_pourc)) ? $hddusedusb_pourc->execCmd() : '';
			$replace['#hddpourcusedusbid#'] = is_object($hddusedusb_pourc) ? $hddusedusb_pourc->getId() : '';

			$replace['#hddpourcusedusb_colorlow#'] = $this->getConfiguration('hddpourcusedusb_colorlow');
			$replace['#hddpourcusedusb_colorhigh#'] = $this->getConfiguration('hddpourcusedusb_colorhigh');

			$hddtotalusb = $this->getCmd(null,'hddtotalusb');
			$replace['#synovolumeusb_display#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? 'OK' : '';
			$replace['#hddusedusb_display#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? "block" : "none";
			$replace['#hddtotalusb#'] = (is_object($hddtotalusb)) ? $hddtotalusb->execCmd() : '';
			$replace['#hddtotalusbid#'] = is_object($hddtotalusb) ? $hddtotalusb->getId() : '';
			$replace['#hddtotalusb_collect#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? $hddtotalusb->getCollectDate() : "-";
        	$replace['#hddtotalusb_value#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? $hddtotalusb->getValueDate() : "-";
		}

		// Syno Volume eSATA
		$SynoeSATAVisible = (is_object($this->getCmd(null,'hddtotalesata')) && $this->getCmd(null,'hddtotalesata')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoeSATAVisible == 'OK' && $this->getConfiguration('synologyesata') == '1') {
			$hddusedesata = $this->getCmd(null,'hddusedesata');
			$replace['#hddusedesata#'] = (is_object($hddusedesata)) ? $hddusedesata->execCmd() : '';
			$replace['#hddusedesataid#'] = is_object($hddusedesata) ? $hddusedesata->getId() : '';

			$hddusedesata_pourc = $this->getCmd(null,'hddpourcusedesata');
			$replace['#hddpourcusedesata#'] = (is_object($hddusedesata_pourc)) ? $hddusedesata_pourc->execCmd() : '';
			$replace['#hddpourcusedesataid#'] = is_object($hddusedesata_pourc) ? $hddusedesata_pourc->getId() : '';

			$replace['#hddpourcusedesata_colorlow#'] = $this->getConfiguration('hddpourcusedesata_colorlow');
			$replace['#hddpourcusedesata_colorhigh#'] = $this->getConfiguration('hddpourcusedesata_colorhigh');

			$hddtotalesata = $this->getCmd(null,'hddtotalesata');
			$replace['#synovolumeesata_display#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? 'OK' : '';
			$replace['#hddusedesata_display#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? "block" : "none";
			$replace['#hddtotalesata#'] = (is_object($hddtotalesata)) ? $hddtotalesata->execCmd() : '';
			$replace['#hddtotalesataid#'] = is_object($hddtotalesata) ? $hddtotalesata->getId() : '';
			$replace['#hddtotalesata_collect#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? $hddtotalesata->getCollectDate() : "-";
        	$replace['#hddtotalesata_value#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? $hddtotalesata->getValueDate() : "-";
		}

		$perso1 = $this->getCmd(null,'perso1');
		$replace['#perso1#'] = (is_object($perso1)) ? $perso1->execCmd() : '';
		$replace['#perso1id#'] = is_object($perso1) ? $perso1->getId() : '';
		$replace['#perso1_display#'] = (is_object($perso1) && $perso1->getIsVisible()) ? "block" : "none";
		$replace['#perso1_collect#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getCollectDate() : "-";
        $replace['#perso1_value#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getValueDate() : "-";
		
		$perso1_name = (is_object($perso1)) ? $this->getCmd(null,'perso1')->getName() : '';
		$perso1_icon = (is_object($perso1)) ? $this->getCmd(null,'perso1')->getdisplay('icon') : '';
		$replace['#perso1_name#'] = (is_object($perso1)) ? $perso1_name : '';
		$replace['#perso1_icon#'] = (is_object($perso1)) ? $perso1_icon : '';

		$perso1_unite = $this->getConfiguration('perso1_unite');
		$replace['#perso1_unite#'] = (is_object($perso1)) ? $perso1_unite : '';

		$replace ['#perso1_colorlow#'] = $this->getConfiguration('perso1_colorlow');
		$replace ['#perso1_colorhigh#'] = $this->getConfiguration('perso1_colorhigh');

		$perso2 = $this->getCmd(null,'perso2');
		$replace['#perso2#'] = (is_object($perso2)) ? $perso2->execCmd() : '';
		$replace['#perso2id#'] = is_object($perso2) ? $perso2->getId() : '';
		$replace['#perso2_display#'] = (is_object($perso2) && $perso2->getIsVisible()) ? "block" : "none";
		$replace['#perso2_collect#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getCollectDate() : "-";
        $replace['#perso2_value#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getValueDate() : "-";
		
		$perso2_name = (is_object($perso2)) ? $this->getCmd(null,'perso2')->getName() : '';
		$perso2_icon = (is_object($perso2)) ? $this->getCmd(null,'perso2')->getdisplay('icon') : '';
		$replace['#perso2_name#'] = (is_object($perso2)) ? $perso2_name : '';
		$replace['#perso2_icon#'] = (is_object($perso2)) ? $perso2_icon : '';
		
		$perso2_unite = $this->getConfiguration('perso2_unite');
		$replace['#perso2_unite#'] = (is_object($perso2)) ? $perso2_unite : '';

		$replace ['#perso2_colorlow#'] = $this->getConfiguration('perso2_colorlow');
		$replace ['#perso2_colorhigh#'] = $this->getConfiguration('perso2_colorhigh');

		foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
			// $replace['#cmd_' . $cmd->getLogicalId() . '_display#'] = (is_object($cmd) && $cmd->getIsVisible()) ? "#cmd_" . $cmd->getLogicalId() . "_display#" : "none";
			$replace['#cmd_' . $cmd->getLogicalId() . '_display#'] = (is_object($cmd) && $cmd->getIsVisible()) ? "inline-block" : "none";
		}

		$html = template_replace($replace, getTemplate('core', $_version, 'Monitoring','Monitoring'));
		cache::set('MonitoringWidget' . $_version . $this->getId(), $html, 0);
		
		return $html;
	}


	public static function getPluginVersion() {
        $pluginVersion = '0.0.0';
		try {
			if (!file_exists(dirname(__FILE__) . '/../../plugin_info/info.json')) {
				log::add('Monitoring', 'warning', '[VERSION] fichier info.json manquant');
			}
			$data = json_decode(file_get_contents(dirname(__FILE__) . '/../../plugin_info/info.json'), true);
			if (!is_array($data)) {
				log::add('Monitoring', 'warning', '[VERSION] Impossible de décoder le fichier info.json');
			}
			try {
				$pluginVersion = $data['pluginVersion'];
			} catch (\Exception $e) {
				log::add('Monitoring', 'warning', '[VERSION] Impossible de récupérer la version du plugin');
			}
		}
		catch (\Exception $e) {
			log::add('Monitoring', 'warning', '[VERSION] Get ERROR :: ' . $e->getMessage());
		}
		log::add('Monitoring', 'info', '[VERSION] PluginVersion :: ' . $pluginVersion);
        return $pluginVersion;
    }

	public function getNetworkCard($_networkCard = '') {
		$networkCard = '';
		if ($_networkCard == 'netautre') {
			$networkCard = $this->getConfiguration('cartereseauautre');
		} elseif ($_networkCard == 'netauto') {
			$networkCard = "$(ip -o -f inet a 2>/dev/null | grep -Ev 'docker|127.0.0.1' | head -1 | awk '{ print $2 }' | awk -F'@' -v ORS=\"\" '{ print $1 }')";
		} else {
			$networkCard = $_networkCard;
		}
		return $networkCard;
	}

	public function connectSSH() {
		$hostId = $this->getConfiguration('SSHHostId');
		$cnx_ssh = '';
	
		try {
			$cnx_ssh = sshmanager::checkConnection($hostId) ? 'OK' : 'KO';
			log::add('Monitoring', ($cnx_ssh == 'KO' ? 'error': 'debug'), '['. $this->getName() .'][SSH-CNX] Connection SSH :: ' . $cnx_ssh);
		} catch (Exception $e) {
			log::add('Monitoring', 'error', '['. $this->getName() .'][SSH-CNX] Connection Exception :: '. $e->getMessage());
			$cnx_ssh = 'KO';
		}
		return [$cnx_ssh, $hostId];
	}

	public function execSRV($cmd_srv = '', $cmdName_srv = '', $timeout_srv = true) {
		$conf_timeoutSrv = $this->getConfiguration('timeoutsrv', 30);
		$cmdResult_srv = '';
	
		try {
			$_cmd = trim($cmd_srv);
			if ($timeout_srv && $conf_timeoutSrv > 0 && !preg_match('/^[^|]*(;|^\b(timeout)\b)/', $_cmd)) {
				if (preg_match('/LC_ALL=C/', $_cmd)) {
					$_cmd = preg_replace('/LC_ALL=C/', 'LC_ALL=C timeout ' . $conf_timeoutSrv, $_cmd, 1);
				}
				else {
					$_cmd = 'timeout ' . $conf_timeoutSrv . ' ' . $_cmd;
				}
			}
	
			$cmdResult_srv = exec($_cmd, $output_srv, $returnCode_srv);
			if ($returnCode_srv !== 0) {
				log::add('Monitoring', 'debug', '['. $this->getName() .'][LOCAL-EXEC] ' . $cmdName_srv . ' :: ' . str_replace("\r\n", "\\r\\n", $_cmd));
				log::add('Monitoring', 'error', '['. $this->getName() .'][LOCAL-EXEC] ' . $cmdName_srv . ' ReturnCode :: ' . $returnCode_srv);
				$cmdResult_srv = '';
			}
			if (!empty($cmdResult_srv)) {
				$cmdResult_srv = trim($cmdResult_srv);
				log::add('Monitoring', 'debug', '['. $this->getName() .'][LOCAL-EXEC] ' . $cmdName_srv . ' :: ' . str_replace("\r\n", "\\r\\n", $_cmd));
				log::add('Monitoring', 'debug', '['. $this->getName() .'][LOCAL-EXEC] ' . $cmdName_srv . ' Result :: ' . $cmdResult_srv);
			}
			
		} catch (Exception $e) {
			$cmdResult_srv = '';
			log::add('Monitoring', 'debug', '['. $this->getName() .'][LOCAL-EXEC] ' . $cmdName_srv . ' :: ' . str_replace("\r\n", "\\r\\n", $_cmd));
			log::add('Monitoring', 'error', '['. $this->getName() .'][LOCAL-EXEC] ' . $cmdName_srv . ' Exception :: ' . $e->getMessage());
	
		}
		return $cmdResult_srv;
	}

	public function execSSH($hostId, $cmd_ssh = '', $cmdName_ssh = '') {
		$cmdResult_ssh = '';
		try {
			$cmdResult_ssh = sshmanager::executeCmds($hostId, $cmd_ssh, $cmdName_ssh);
		} catch (SSHException $ex) {
			$cmdResult_ssh = '';
			log::add('Monitoring', 'debug', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' :: ' . str_replace("\r\n", "\\r\\n", $cmd_ssh));
			log::add('Monitoring', 'error', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' SSHException :: ' . $ex->getMessage());
			log::add('Monitoring', 'debug', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' SSHException LastError :: ' . $ex->getLastError());
			log::add('Monitoring', 'debug', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' SSHException Logs ::' . "\r\n" . $ex->getLog());
		} catch (Exception $e) {
			$cmdResult_ssh = '';
			log::add('Monitoring', 'debug', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' :: ' . str_replace("\r\n", "\\r\\n", $cmd_ssh));
			log::add('Monitoring', 'error', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' Cmd Exception :: ' . $e->getMessage());
		}
		return $cmdResult_ssh;
	}

	public function getInformations() {
		$equipement = $this->getName();
		try {
			$bitdistri_cmd = '';
			$uname = "Inconnu";
			$Mem = '';
			$memorylibre_pourc = '';
			$ethernet0 = '';
			$ethernet0_name = '';
			$ethernet0_ip = '';
	
			$cartereseau = $this->getNetworkCard($this->getConfiguration('cartereseau'));
	
			$confLocalOrRemote = $this->getConfiguration('localoudistant');
	
			// Configuration distante
			if ($confLocalOrRemote == 'distant' && $this->getIsEnable()) {
				[$cnx_ssh, $hostId] = $this->connectSSH();
				
				if ($cnx_ssh == 'OK') {
					if ($this->getConfiguration('synology') == '1') {
						if ($this->getConfiguration('syno_alt_name') == '1') {
							$namedistri_cmd = "cat /proc/sys/kernel/syno_hw_version 2>/dev/null";
						}
						else {
							$namedistri_cmd = "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null";
						}
						$VersionID_cmd = "awk -F'=' '/productversion/ {print $2}' /etc.defaults/VERSION 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
					}
					else {
						$namedistri_cmd = "awk -F'=' '/^PRETTY_NAME/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
	
						$VersionID_cmd = "awk -F'=' '/VERSION_ID/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
						$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
					}
	
					$memory_cmd = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$7 }'";
					$swap_cmd = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
					$loadavg_cmd = "cat /proc/loadavg 2>/dev/null";
					
					$ReseauRXTX_cmd = "cat /proc/net/dev 2>/dev/null | grep ".$cartereseau." | awk '{print $1,$2,$10}' | awk -v ORS=\"\" '{ gsub(/:/, \"\"); print }'";
					
					$ReseauIP_cmd = "LC_ALL=C ip -o -f inet a 2>/dev/null | grep ".$cartereseau." | awk '{ print $4 }' | awk -v ORS=\"\" '{ gsub(/\/[0-9]+/, \"\"); print }'";
					
					// ARMv Command
					$ARMv_cmd = "LC_ALL=C lscpu 2>/dev/null | awk -F':' '/Architecture/ { print $2 }' | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
					
					$ARMv = $this->execSSH($hostId, $ARMv_cmd, 'ARMv');
	
					// Uptime Command
					$uptime_cmd = "awk '{ print $1 }' /proc/uptime 2>/dev/null | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
					$uptime = $this->execSSH($hostId, $uptime_cmd, 'Uptime');
					$namedistri = $this->execSSH($hostId, $namedistri_cmd, 'NameDistri');
					$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');
					$VersionID = $this->execSSH($hostId, $VersionID_cmd, 'VersionID');
					$loadav = $this->execSSH($hostId, $loadavg_cmd, 'LoadAverage');
					$ReseauRXTX = $this->execSSH($hostId, $ReseauRXTX_cmd, 'ReseauRXTX');
					$ReseauIP = $this->execSSH($hostId, $ReseauIP_cmd, 'ReseauIP');
					$memory = $this->execSSH($hostId, $memory_cmd, 'Memory');
					$swap = $this->execSSH($hostId, $swap_cmd, 'Swap');
	
					$perso1_cmd = $this->getConfiguration('perso1');
					$perso2_cmd = $this->getConfiguration('perso2');
	
					if ($perso1_cmd != '') {
						$perso1 = $this->execSSH($hostId, $perso1_cmd, 'Perso1');
					} else {
						$perso1 = '';
					}
					if ($perso2_cmd != '') {
						$perso2 = $this->execSSH($hostId, $perso2_cmd, 'Perso2');
					} else {
						$perso2 = '';
					}
					
					if ($this->getConfiguration('synology') == '1') {
	
						$nbcpuARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_core 2>/dev/null";
						$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
						$cpufreq0ARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_clock 2>/dev/null";
						$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0');
	
						$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep 'vg1000\|volume1' | head -1 | awk '{ print $2,$3,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
						$versionsyno_cmd = "cat /etc.defaults/VERSION 2>/dev/null | awk '{ gsub(/\"/, \"\"); print }' | awk NF=NF RS='\r\n' OFS='&'"; // Récupération de tout le fichier de version pour le parser et récupérer le nom des champs
						$versionsyno = $this->execSSH($hostId, $versionsyno_cmd, 'VersionSyno');
	
						if ($this->getconfiguration('syno_use_temp_path')) {
							$cputemp0_cmd = $this->getconfiguration('syno_temp_path');
							log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][SYNO] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						} else {
							$cputemp0_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1)";
							log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][SYNO] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						}
						if ($cputemp0_cmd != '') {
							$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0');
						} else {
							$cputemp0 = '';
						}
					
						if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1') {
							$hddv2cmd = "LC_ALL=C df -h 2>/dev/null | grep 'vg1001\|volume2' | head -1 | awk '{ print $2,$3,$5 }'"; // DSM 5.x / 6.x / 7.x
							$hddv2 = $this->execSSH($hostId, $hddv2cmd, 'HDDv2');
						}
	
						if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1') {
							$hddusbcmd = "LC_ALL=C df -h 2>/dev/null | grep 'usb1p1\|volumeUSB1' | head -1 | awk '{ print $2,$3,$5 }'"; // DSM 5.x / 6.x / 7.x
							$hddusb = $this->execSSH($hostId, $hddusbcmd, 'HDDusb');
						}
	
						if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1') {
							$hddesatacmd = "LC_ALL=C df -h 2>/dev/null | grep 'sdf1\|volumeSATA' | head -1 | awk '{ print $2,$3,$5 }'"; // DSM 5.x / 6.x / 7.x
							$hddesata = $this->execSSH($hostId, $hddesatacmd, 'HDDesata');
						}
	
					} elseif ($ARMv == 'armv6l') {
						$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep 'CPU(s):' | awk '{ print $2 }'";
						$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
						$uname = '.';
	
						$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
						$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0-1');
	
						if ($cpufreq0 == '') {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0-2');
						}
	
						// TODO A vérifier sur les conditions des IF
						$cputemp_cmd = $this->getCmd(null,'cpu_temp');
						if (is_object($cputemp_cmd)) {
							if ($this->getconfiguration('linux_use_temp_cmd')) {
								$cputemp0armv6l_cmd = $this->getconfiguration('linux_temp_cmd');
								log::add('Monitoring', 'info', '['. $equipement .'][SSH-CMD][ARM6L] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0armv6l_cmd));	
							} else {
								$cputemp0armv6l_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
								log::add('Monitoring', 'info', '['. $equipement .'][SSH-CMD][ARM6L] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0armv6l_cmd));
							}
							if ($cputemp0armv6l_cmd != '') {
								$cputemp0 = $this->execSSH($hostId, $cputemp0armv6l_cmd, 'cputemp0');
							} else {
								$cputemp0 = '';
							}
						}
	
					} elseif ($ARMv == 'armv7l' || $ARMv == 'aarch64' || $ARMv == 'mips64') {
						
						$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'";
						$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
						$uname = '.';
	
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
						$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0-1');
	
						if ($cpufreq0 == '') {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0-2');
						}
	
						$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
						$cputemp_cmd = $this->getCmd(null,'cpu_temp');
						if (is_object($cputemp_cmd)) {
							if ($this->getconfiguration('linux_use_temp_cmd')) {
								$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
								$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-1');
							} else {
								$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";    // OK RPi2
								$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-2');
								
								if ($cputemp0 == '') {
									$cputemp0_cmd = "cat /sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1_input 2>/dev/null"; // OK Banana Pi (Cubie surement un jour...)
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-3');
								}
								log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][AARCH64] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
							}							
						}
					} elseif ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386') {
						// $NF = '';
						$cputemp0 ='';
						$uname = '.';
						
						$nbcpuVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print \$NF }'";
						// $nbcpuVM_cmd = "lscpu 2>/dev/null | grep 'Processeur(s)' | awk '{ print \$NF }'"; // OK pour Debian
						$nbcpu = $this->execSSH($hostId, $nbcpuVM_cmd, 'NbCPU');
	
						// if ($nbcpu == '') {
						// 	$nbcpuVMbis_cmd = "lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print \$NF }'"; // OK pour LXC Linux/Ubuntu
						// 	$nbcpu = $this->execSSH($hostId, $nbcpuVMbis_cmd, 'NbCPU-2');
						// }
						$nbcpu = preg_replace("/[^0-9]/", "", $nbcpu);
						log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][X86] NbCPU :: ' . $nbcpu);
	
						$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
						// $cpufreqVM_cmd = "lscpu 2>/dev/null | grep 'Vitesse du processeur en MHz' | awk '{print \$NF}'"; // OK pour Debian/Ubuntu, mais pas Ubuntu 22.04
						// $cpufreq = $this->execSSH($hostId, $cpufreqVM_cmd, 'cpufreq-1');
						
						$cpufreqVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep -Ei '^CPU( max)? MHz' | awk '{ print \$NF }'";    // OK pour LXC Linux, Proxmox, Debian 10/11
						$cpufreq = $this->execSSH($hostId, $cpufreqVM_cmd, 'cpufreq-1');
	
						// if ($cpufreq == '') {
						// 	$cpufreqVMbis_cmd = "LC_ALL=C lscpu 2>/dev/null | grep -i '^CPU max MHz' | awk '{ print \$NF }'";    // OK pour LXC Linux
						// 	$cpufreq = $this->execSSH($hostId, $cpufreqVMbis_cmd, 'cpufreq-2');
						// }
						
						if ($cpufreq == '') {
							$cpufreqVMbis_cmd = "cat /proc/cpuinfo 2>/dev/null | grep -i '^cpu MHz' | head -1 | cut -d':' -f2 | awk '{ print \$NF }'";    // OK pour Debian 10,11,12, Ubuntu 22.04, pve-debian12
							$cpufreq = $this->execSSH($hostId, $cpufreqVMbis_cmd, 'cpufreq-2');
						}
						$cpufreq = preg_replace("/[^0-9.,]/", "", $cpufreq);
						log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][X86] CPUFreq :: ' . $cpufreq);
	
						$cputemp_cmd = $this->getCmd(null,'cpu_temp');
						if (is_object($cputemp_cmd)) {
							if ($this->getconfiguration('linux_use_temp_cmd')) {
								$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
								log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][X86] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
								if ($cputemp0_cmd != '') {
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-1');
								} else {
									$cputemp0 = '';
								}
							} else {
								$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone0/temp 2>/dev/null";	// Default
								log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][X86] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-2');
								
								if ($cputemp0 == '') {
									$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone1/temp 2>/dev/null"; // Default Zone 1
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-3');
								}
								if ($cputemp0 == '') {
									$cputemp0_cmd = "cat /sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input 2>/dev/null";	// OK AOpen DE2700
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-4');
								}
								if ($cputemp0 == '') {
									// $cputemp0AMD_cmd = "cat /sys/devices/pci0000:00/0000:00:18.3/hwmon/hwmon0/temp1_input 2>/dev/null";	// OK AMD Ryzen
									$cputemp0AMD_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"; // OK Search temp?_input
									$cputemp0 = $this->execSSH($hostId, $cputemp0AMD_cmd, 'cputemp0-5');
								}
								if ($cputemp0 == '') {
									$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"Package\")) {printf(\"%f\",$4);} }'"; // OK by sensors
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-6');
								}
								if ($cputemp0 == '') {
									$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")) {printf(\"%f\",$3);} }'"; // OK by sensors
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0-7');
								}
							}
						}
					} elseif ($ARMv == '' & $this->getConfiguration('synology') != '1') {
						$unamecmd = "uname -a 2>/dev/null | awk '{print $2,$1}'";
						$uname = $this->execSSH($hostId, $unamecmd, 'uname');
	
						if (preg_match("#RasPlex|OpenELEC|LibreELEC#", $namedistri)) {
							$bitdistri = '32';
							$ARMv = 'arm';
	
							$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
							$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							// TODO faut il ajouter le preg_match pour les autres distri ARM ?
							log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][ARM] NbCPU :: ' . $nbcpu);
	
							$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/dev/mmcblk0p2' | head -1 | awk '{ print $2,$3,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0');
	
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][ARM] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								} else {
									$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][ARM] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								}
								if ($cputemp0_cmd != '') {
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0');
								} else {
									$cputemp0 = '';
								}
							}
						} elseif (preg_match("#osmc#", $namedistri)) {
							$bitdistri = '32';
							$ARMv = 'arm';
	
							$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
							$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0');
	
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][ARM] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								} else {
									$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][ARM] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								}
								if ($cputemp0_cmd != '') {
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0');
								} else {
									$cputemp0 = '';
								}
							}
						} elseif (preg_match("#piCorePlayer#", $uname)) {
							$bitdistri = '32';
							$ARMv = 'arm';
							
							$namedistri_cmd = "uname -a 2>/dev/null | awk '{print $2,$3}'";
							$namedistri = $this->execSSH($hostId, $namedistri_cmd, 'NameDistri');
	
							$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
							$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep /dev/mmcblk0p | head -1 | awk '{print $2,$3,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0');
	
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][ARM] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								} else {
									$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][ARM] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								}
								if ($cputemp0_cmd != '') {
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0');
								} else {
									$cputemp0 = '';
								}
							}
						} elseif (preg_match("#FreeBSD#", $uname)) {
							$namedistri_cmd = "uname -a 2>/dev/null | awk '{ print $1,$3}'";
							$namedistri = $this->execSSH($hostId, $namedistri_cmd, 'NameDistri');
	
							$ARMv_cmd = "sysctl hw.machine | awk '{ print $2}'";
							$ARMv = $this->execSSH($hostId, $ARMv_cmd, 'ARMv');
	
							$loadavg_cmd = "LC_ALL=C uptime | awk '{print $8,$9,$10}'";
							$loadav = $this->execSSH($hostId, $loadavg_cmd, 'LoadAverage');
	
							$memory_cmd = "dmesg | grep Mem | tr '\n' ' ' | awk '{print $4,$10}'";
							$memory = $this->execSSH($hostId, $memory_cmd, 'Memory');
	
							$bitdistri_cmd = "sysctl kern.smp.maxcpus | awk '{ print $2}'";
							$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');
	
							$nbcpuARM_cmd = "sysctl hw.ncpu | awk '{ print $2}'";
							$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
							$cpufreq0ARM_cmd = "sysctl -a | egrep -E 'cpu.0.freq' | awk '{ print $2}'";
							$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0');
	
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][FreeBSD] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								} else {
									$cputemp0_cmd = "sysctl -a | egrep -E 'cpu.0.temp' | awk '{ print $2}'";
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][FreeBSD] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								}
								if ($cputemp0_cmd != '') {
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0');
								} else {
									$cputemp0 = '';
								}
							}
						}
						elseif (preg_match("#medion#", $uname)) {
							$nbcpu = '';
							$cpufreq0 = '';
							$cputemp0 = '';
	
							$ARMv = "arm";
	
							$namedistri_cmd = "cat /etc/*-release 2>/dev/null | awk '/^DistName/ { print $2 }'";
							$VersionID_cmd = "cat /etc/*-release 2>/dev/null | awk '/^VersionName/ { print $2 }'";
							$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
							$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/home$' | head -1 | awk '{ print $2,$3,$5 }'";
							
							$namedistri = $this->execSSH($hostId, $namedistri_cmd, 'NameDistri');
							$VersionID = $this->execSSH($hostId, $VersionID_cmd, 'VersionID');
							$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
							
							if (isset($namedistri) && isset($VersionID)) {
								$namedistri = "Medion/Linux " . $VersionID . " (" . $namedistri . ")";
								log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][MEDION] Distribution :: ' . $namedistri);
							}
	
							$nbcpuARM_cmd = "cat /proc/cpuinfo 2>/dev/null | awk -F':' '/^Processor/ { print $2}'";
							$nbcpu = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
							$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0-1');
							
							if ($cpufreq0 == '') {
								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
								$cpufreq0 = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'cpufreq0-2');
							}
	
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][MEDION] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								} else {
									$cputemp0_cmd = "sysctl -a | egrep -E 'cpu.0.temp' | awk '{ print $2 }'";
									log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][MEDION] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								}
								if ($cputemp0_cmd != '') {
									$cputemp0 = $this->execSSH($hostId, $cputemp0_cmd, 'cputemp0');
								} else {
									$cputemp0 = '';
								}
							}
						}
					}
				}
			}
			elseif ($this->getConfiguration('localoudistant') == 'local' && $this->getIsEnable()) {
				$cnx_ssh = 'No';
				
				if ($this->getConfiguration('synology') == '1') {
					if ($this->getConfiguration('syno_alt_name') == '1') {
						$namedistri_cmd = "cat /proc/sys/kernel/syno_hw_version 2>/dev/null";
					} else {
						$namedistri_cmd = "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null";
					}
					$VersionID_cmd = "awk -F'=' '/productversion/ {print $2}' /etc.defaults/VERSION 2>/dev/null | -v ORS=\"\" awk '{ gsub(/\"/, \"\"); print }'";
					$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep 'vg1000\|volume1' | head -1 | awk '{ print $2,$3,$5 }'";
				} else {
					$ARMv_cmd = "LC_ALL=C lscpu 2>/dev/null | awk -F':' '/Architecture/ { print $2 }' | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
					$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
	
					$ARMv = $this->execSRV($ARMv_cmd, 'ARMv');
					$bitdistri = $this->execSRV($bitdistri_cmd, 'BitDistri');
	
					$namedistri_cmd ="awk -F'=' '/^PRETTY_NAME/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
					$VersionID_cmd = "awk -F'=' '/VERSION_ID/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
					$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
				}
	
				$uptime_cmd = "awk '{ print $1 }' /proc/uptime 2>/dev/null | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
				$loadavg_cmd = "cat /proc/loadavg 2>/dev/null";
				$memory_cmd = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$7 }'";
				$swap_cmd = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
	
				$ReseauRXTX_cmd = "cat /proc/net/dev 2>/dev/null | grep ".$cartereseau." | awk '{print $1,$2,$10}' | awk -v ORS=\"\" '{ gsub(/:/, \"\"); print }'"; // on récupère le nom de la carte en plus pour l'afficher dans les infos
				$ReseauIP_cmd = "ip -o -f inet a 2>/dev/null | grep ".$cartereseau." | awk '{ print $4 }' | awk -v ORS=\"\" '{ gsub(/\/[0-9]+/, \"\"); print }'";
				
				$namedistri = $this->execSRV($namedistri_cmd, 'NameDistri');
				$VersionID = $this->execSRV($VersionID_cmd, 'VersionID');
				$hdd = $this->execSRV($hdd_cmd, 'HDD');
	
				$uptime = $this->execSRV($uptime_cmd, 'Uptime');
				$loadav = $this->execSRV($loadavg_cmd, 'LoadAverage');
				$memory = $this->execSRV($memory_cmd, 'Memory');
				$swap = $this->execSRV($swap_cmd, 'Swap');
				$ReseauRXTX = $this->execSRV($ReseauRXTX_cmd, 'ReseauRXTX');
				$ReseauIP = $this->execSRV($ReseauIP_cmd, 'ReseauIP');
				
				$perso1_cmd = $this->getConfiguration('perso1');
				$perso2_cmd = $this->getConfiguration('perso2');
	
				if ($perso1_cmd != '') {
					log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] Perso1 Cmd :: ' . str_replace("\r\n", "\\r\\n", $perso1_cmd));
					$perso1 = $this->execSRV($perso1_cmd, 'Perso1');
				}
				if ($perso2_cmd != '') {
					log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] Perso2 Cmd :: ' . str_replace("\r\n", "\\r\\n", $perso2_cmd));
					$perso2 = $this->execSRV($perso2_cmd, 'Perso2');
				}
	
				if ($this->getConfiguration('synology') == '1') {
					$uname = '.';
					$nbcpuARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_core 2>/dev/null";
					$cpufreq0ARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_clock 2>/dev/null";
					$versionsyno_cmd = "cat /etc.defaults/VERSION 2>/dev/null | awk '{ gsub(/\"/, \"\"); print }' | awk NF=NF RS='\r\n' OFS='&'"; // Récupération de tout le fichier de version pour le parser et récupérer le nom des champs
	
					$nbcpu = $this->execSRV($nbcpuARM_cmd, 'NbCPU');
					$cpufreq0 = $this->execSRV($cpufreq0ARM_cmd, 'cpufreq0');
					$versionsyno = $this->execSRV($versionsyno_cmd, 'VersionSyno');
	
					if ($this->getconfiguration('syno_use_temp_path')) {
						$cputemp0_cmd = $this->getconfiguration('syno_temp_path');
						log::add('Monitoring','debug', '['. $equipement .'][LOCAL][SYNO] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
					} else {
						$cputemp0_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null";
						log::add('Monitoring','debug', '['. $equipement .'][LOCAL][SYNO] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
					}
					if ($cputemp0_cmd != '') {
						$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0');
					} else {
						$cputemp0 = '';
					}
	
					if ($this->getConfiguration('synology') == '1' /* && $SynoV2Visible == 'OK' */ && $this->getConfiguration('synologyv2') == '1') {
						$hddv2cmd = "LC_ALL=C df -h 2>/dev/null | grep 'vg1001\|volume2' | head -1 | awk '{ print $2,$3,$5 }'";
						$hddv2 = $this->execSRV($hddv2cmd, 'HDDv2');
					}
	
					if ($this->getConfiguration('synology') == '1' /* && $SynoUSBVisible == 'OK' */ && $this->getConfiguration('synologyusb') == '1') {
						$hddusbcmd = "LC_ALL=C df -h 2>/dev/null | grep 'usb1p1\|volumeUSB1' | head -1 | awk '{ print $2,$3,$5 }'";
						$hddusb = $this->execSRV($hddusbcmd, 'HDDUSB');
					}
	
					if ($this->getConfiguration('synology') == '1' /* && $SynoeSATAVisible == 'OK' */ && $this->getConfiguration('synologyesata') == '1') {
						$hddesatacmd = "LC_ALL=C df -h 2>/dev/null | grep 'sdf1\|volumeSATA' | head -1 | awk '{ print $2,$3,$5 }'";
						$hddesata = $this->execSRV($hddesatacmd, 'HDDeSATA');
					}
				} elseif ($ARMv == 'armv6l') {
					$uname = '.';
					$cpufreq0 = '';
					$cputemp0 = '';
	
					$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep 'CPU(s):' | awk '{ print $2 }'";
					$nbcpu = $this->execSRV($nbcpuARM_cmd, 'NbCPU');
					
					if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq')) {
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq";
						$cpufreq0 = $this->execSRV($cpufreq0ARM_cmd, 'cpufreq0-1');
					}
					if ($cpufreq0 == '') {
						if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq')) {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq";
							$cpufreq0 = $this->execSRV($cpufreq0ARM_cmd, 'cpufreq0-2');
						}
					}
					
					$cputemp_cmd = $this->getCmd(null,'cpu_temp');
					if (is_object($cputemp_cmd)) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][ARM6L] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
						} elseif (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
							$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp";
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][ARM6L] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						}
						if ($cputemp0 != '') {
							$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0');
						} else {
							$cputemp0 = '';
						}
					}
				} elseif ($ARMv == 'armv7l' || $ARMv == 'aarch64') {
					$uname = '.';
					$cputemp0 = '';
					$cpufreq0 = '';
	
					$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'";
					$nbcpu = $this->execSRV($nbcpuARM_cmd, 'NbCPU');
					
					if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq')) {
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq";
						$cpufreq0 = $this->execSRV($cpufreq0ARM_cmd, 'cpufreq0-1');
					}
					if ($cpufreq0 == '') {
						if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq')) {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq";
							$cpufreq0 = $this->execSRV($cpufreq0ARM_cmd, 'cpufreq0-2');
						}
					}	
					
					$cputemp_cmd = $this->getCmd(null, 'cpu_temp');
					if (is_object($cputemp_cmd)) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][AARCH64] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
							if ($cputemp0_cmd != '') {
								$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-1');
							} else {
								$cputemp0 = '';
							}
						} else {
							if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
								$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp"; // OK RPi2/3, Odroid
								$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-2');
							}
							if ($cputemp0 == '') {
								if (file_exists('/sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1')) {
									$cputemp0_cmd = "cat /sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1"; // OK Banana Pi (Cubie surement un jour...)
									$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-3');
								}
							}
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][AARCH64] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						}
					}
				} elseif ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386') {
					$uname = '.';
					$cputemp0 = '';
					$cpufreq = '';
	
					// $nbcpuVM_cmd = "lscpu 2>/dev/null | grep 'Processeur(s)' | awk '{ print \$NF }'"; // OK pour Debian
					// $nbcpu = $this->execSRV($nbcpuVM_cmd, 'NbCPU-1');
					
					$nbcpuVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print \$NF }'"; // OK pour LXC Linux/Ubuntu
					$nbcpu = $this->execSRV($nbcpuVM_cmd, 'NbCPU');
					
					$nbcpu = preg_replace("/[^0-9]/", "", $nbcpu);
					log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL][X86] NbCPU :: ' . $nbcpu);
	
					// $cpufreqVM_cmd = "lscpu 2>/dev/null | grep 'Vitesse du processeur en MHz' | awk '{print \$NF}'"; // OK pour Debian/Ubuntu, mais pas Ubuntu 22.04
					// $cpufreq = $this->execSRV($cpufreqVM_cmd, 'cpufreq0-1');
					
					$cpufreqVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep -Ei '^CPU( max)? MHz' | awk '{ print \$NF }'";    // OK pour LXC Linux, Proxmox, Debian 10/11
					$cpufreq = $this->execSRV($cpufreqVM_cmd, 'cpufreq0-1');
	
					if ($cpufreq == '') {
						$cpufreqVMbis_cmd = "cat /proc/cpuinfo 2>/dev/null | grep -i '^cpu MHz' | head -1 | cut -d':' -f2 | awk '{ print \$NF }'";    // OK pour Debian 10,11,12, Ubuntu 22.04, pve-debian12
						$cpufreq = $this->execSRV($cpufreqVMbis_cmd, 'cpufreq0-2');
					}
					$cpufreq = preg_replace("/[^0-9.,]/", "", $cpufreq);
					log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL][X86] CPUFreq :: ' . $cpufreq);
					
					$cputemp_cmd = $this->getCmd(null,'cpu_temp');
					if (is_object($cputemp_cmd)) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][X86] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
							if ($cputemp0_cmd != '') {
								$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-1');
							} else {
								$cputemp0 = '';
							}
						} else {
							if (file_exists('/sys/devices/virtual/thermal/thermal_zone0/temp')) {
								$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone0/temp"; // OK Dell Whyse
								$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-2');
							}					
							if ($cputemp0 == '') {
								if (file_exists('/sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input')) {
									$cputemp0_cmd = "cat /sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input";	// OK AOpen DE2700
									$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-3');
								}
							}
							if ($cputemp0 == '') {
								$cputemp0_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"; // OK AMD Ryzen
								$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-4');
							}
							if ($cputemp0 == '') {
								$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"Package\")) {printf(\"%f\",$4);} }'"; // OK by sensors
								$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-5');
							}
							if ($cputemp0 == '') {
								$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")) {printf(\"%f\",$3);} }'"; // OK by sensors MB
								$cputemp0 = $this->execSRV($cputemp0_cmd, 'cputemp0-6');
							}
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][X86] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						}
					}
				}
			}
	
			if (isset($cnx_ssh)) {
				if ($this->getConfiguration('localoudistant') == 'local' || $cnx_ssh == 'OK') {
					if ($this->getConfiguration('synology') == '1') {
						if (isset($versionsyno)) {
							parse_str($versionsyno, $versionsyno_DSM);
							log::add('Monitoring', 'debug', '['. $equipement .'][DSM/SRM] Parse version :: OK');
	
							if (isset($versionsyno_DSM['productversion']) && isset($versionsyno_DSM['buildnumber']) && isset($versionsyno_DSM['smallfixnumber'])) {
								log::add('Monitoring', 'debug', '['. $equipement .'][DSM/SRM] Version :: DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'].' Update '.$versionsyno_DSM['smallfixnumber']);
								$versionsyno_TXT = 'DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'].' Update '.$versionsyno_DSM['smallfixnumber'];
							} elseif (isset($versionsyno_DSM['productversion']) && isset($versionsyno_DSM['buildnumber'])) {
								log::add('Monitoring', 'debug', '['. $equipement .'][DSM/SRM] Version (Version-Build) :: DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber']);
								$versionsyno_TXT = 'DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'];
							} else {
								log::add('Monitoring', 'error', '['. $equipement .'][DSM/SRM] Version :: KO');
								$versionsyno_TXT = '';
							}
	
							if (isset($namedistri) && isset($versionsyno_TXT)) {
								$namedistri = trim($namedistri);
								$namedistri = $versionsyno_TXT.' ('.$namedistri.')';
							}
						}
					} else {
						if (isset($namedistri) && isset($bitdistri) && isset($ARMv)) {
							$namedistri = $namedistri . ' ' . $bitdistri . 'bits (' . $ARMv . ')';
						}
					}
					
					// Syno Volume 2
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1') {
						if (isset($hddv2)) {
							$hdddatav2 = explode(' ', $hddv2);
							if (isset($hdddatav2[0]) && isset($hdddatav2[1]) && isset($hdddatav2[2])) {
								$hddtotalv2 = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatav2[0]);
								$hddusedv2 = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatav2[1]);
								$hddusedv2_pourc = preg_replace("/[^0-9.]/", "", $hdddatav2[2]);
								$hddusedv2_pourc = trim($hddusedv2_pourc);
							} else {
								$hddtotalv2 = '';
								$hddusedv2 = '';
								$hddusedv2_pourc = '';
							}
						}
					}
	
					// Syno Volume USB 
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1') {
						if (isset($hddusb)) {
							$hdddatausb = explode(' ', $hddusb);
							if (isset($hdddatausb[0]) && isset($hdddatausb[1]) && isset($hdddatausb[2])) {
								$hddtotalusb = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatausb[0]);
								$hddusedusb = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatausb[1]);
								$hddusedusb_pourc = preg_replace("/[^0-9.]/", "", $hdddatausb[2]);
								$hddusedusb_pourc = trim($hddusedusb_pourc);
							} else {
								$hddtotalusb = '';
								$hddusedusb = '';
								$hddusedusb_pourc = '';
							}
						}
					}
	
					// Syno Volume eSATA 
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1') {
						if (isset($hddesata)) {
							$hdddataesata = explode(' ', $hddesata);
							if (isset($hdddataesata[0]) && isset($hdddataesata[1]) && isset($hdddataesata[2])) {
								$hddtotalesata = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddataesata[0]);
								$hddusedesata = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddataesata[1]);
								$hddusedesata_pourc = preg_replace("/[^0-9.]/", "", $hdddataesata[2]);
								$hddusedesata_pourc = trim($hddusedesata_pourc);
							} else {
								$hddtotalesata = '';
								$hddusedesata = '';
								$hddusedesata_pourc = '';
							}
						}
					}
	
					if (isset($uptime)) {
						$uptime_jours = sprintf('%d', floor(floatval($uptime) / 86400));
						$uptime_hours = sprintf('%d', floor((floatval($uptime) % 86400) / 3600));
						$uptime_minutes = sprintf('%02d', floor((floatval($uptime) % 3600) / 60));
						$uptime_seconds = sprintf('%0.2f', fmod(floatval($uptime), 60));
	
						$uptime_res = '';
						if (intval($uptime_jours) > 0) {
							$uptime_res .= $uptime_jours . ' jour(s), '; 
						}
						$uptime = $uptime_res . $uptime_hours . 'h ' . $uptime_minutes . 'min ' . $uptime_seconds . 's';
					}
	
					if (isset($loadav)) {
						$loadavg = explode(" ", $loadav);
						if (isset($loadavg[0]) && isset($loadavg[1]) && isset($loadavg[2])) {
							$loadavg1mn = $loadavg[0];
							$loadavg5mn = $loadavg[1];
							$loadavg15mn = $loadavg[2];
						}
					}
	
					if (isset($memory)) {
						if (!preg_match("#FreeBSD#", $uname)) {
							$memory = explode(' ', $memory);
							if ($this->getConfiguration('synology') == '1') {
								if (isset($memory[3])) {
									$memorylibre = intval($memory[3]);
									log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Version Syno :: ' . $VersionID . ' / Mémoire Libre :: '.$memorylibre);
								}
							} else {
								if (isset($memory[3])) {
									$memorylibre = intval($memory[3]);
									log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Version Linux :: ' . $VersionID . ' / Mémoire Libre :: '.$memorylibre);
								}
							}
							
							if (isset($memory[0]) && isset($memorylibre)) {
								if (intval($memory[0]) != 0) {
									$memorylibre_pourc = round(intval($memorylibre) / intval($memory[0]) * 100);
									log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memorylibre% :: ' . $memorylibre_pourc);
								} else {
									$memorylibre_pourc = 0;
								}
							}
	
							if (isset($memorylibre)) {
								if ((intval($memorylibre) / 1024) >= 1024) {
									$memorylibre = round(intval($memorylibre) / 1048576, 2) . " Go";
								} else {
									$memorylibre = round(intval($memorylibre) / 1024) . " Mo";
								}
							}
							if (isset($memory[0])) {
								if ((intval($memory[0]) / 1024) >= 1024) {
									$memtotal = round(intval($memory[0]) / 1048576, 2) . " Go";
								} else {
									$memtotal = round(intval($memory[0]) / 1024, 2) . " Mo";
								}
							}
							if (isset($memtotal) && isset($memorylibre)) {
								$Mem = 'Total : '.$memtotal.' - Libre : '.$memorylibre;
							}
						} elseif (preg_match("#FreeBSD#", $uname)) {
							$memory = explode(' ', $memory);
							if (isset($memory[0]) && isset($memory[1])) {
								if (intval($memory[0]) != 0) {
									$memorylibre_pourc = round(intval($memory[1]) / intval($memory[0]) * 100);
								} else {
									$memorylibre_pourc = 0;
								}
							}
							if ((intval($memory[1]) / 1024) >= 1024) {
								$memorylibre = round(intval($memory[1]) / 1048576, 2) . " Go";
							} else{
								$memorylibre = round(intval($memory[1]) / 1024) . " Mo";
							}
							if (($memory[0] / 1024) >= 1024) {
								$memtotal = round(intval($memory[0]) / 1048576, 2) . " Go";
							} else{
								$memtotal = round(intval($memory[0]) / 1024) . " Mo";
							}
							$Mem = 'Total : '.$memtotal.' - Libre : '.$memorylibre;
						}
					} else {
						$Mem = '';
					}
	
					if (isset($swap)) {
						$swap = explode(' ', $swap);
	
						if (isset($swap[0]) && isset($swap[2])) {
							if (intval($swap[0]) != 0) {
								$swaplibre_pourc = round(intval($swap[2]) / intval($swap[0]) * 100);
							} else {
								$swaplibre_pourc = 0;
							}
						}
	
						if (isset($swap[0])) {
							if ((intval($swap[0]) / 1024) >= 1024) {
								$swap[0] = round(intval($swap[0]) / 1048576, 1) . " Go";
							} else {
								$swap[0] = round(intval($swap[0]) / 1024, 1) . " Mo";
							}
						}
						if (isset($swap[1])) {
							if ((intval($swap[1]) / 1024) >= 1024) {
								$swap[1] = round(intval($swap[1]) / 1048576, 1) . " Go";
							} else {
								$swap[1] = round(intval($swap[1]) / 1024, 1) . " Mo";
							}
						}
						if (isset($swap[2])) {
							if ((intval($swap[2]) / 1024) >= 1024) {
								$swap[2] = round(intval($swap[2]) / 1048576, 1) . " Go";
							} else {
								$swap[2] = round(intval($swap[2]) / 1024, 1) . " Mo";
							}
						}
	
						if (isset($swap[0]) && isset($swap[1]) && isset($swap[2])) {
							$swap[0] = str_replace("B"," o", $swap[0]);
							$swap[1] = str_replace("B"," o", $swap[1]);
							$swap[2] = str_replace("B"," o", $swap[2]);
							$Memswap = 'Total : '.$swap[0].' - Utilisé : '.$swap[1].' - Libre : '.$swap[2];
						}
					} else {
						$Memswap = '';
					}
	
					if (isset($ReseauRXTX)) {
						$ReseauRXTX = explode(' ', $ReseauRXTX);
						if (isset($ReseauRXTX[0]) && isset($ReseauRXTX[1]) && isset($ReseauRXTX[2])) {
							if ((intval($ReseauRXTX[2]) / 1024) >= 1073741824) {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1099511627776, 2) . " To";
							} elseif ((intval($ReseauRXTX[2]) / 1024) >= 1048576) {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1073741824, 2) . " Go";
							} elseif ((intval($ReseauRXTX[2]) / 1024) >= 1024) {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1048576, 2) . " Mo";
							} else {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1024) . " Ko";
							}
							
							if ((intval($ReseauRXTX[1]) / 1024) >= 1073741824) {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1099511627776, 2) . " To";
							} elseif ((intval($ReseauRXTX[1]) / 1024) >= 1048576) {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1073741824, 2) . " Go";
							} elseif ((intval($ReseauRXTX[1]) / 1024) >= 1024) {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1048576, 2) . " Mo";
							} else {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1024) . " Ko";
							}
							$ethernet0 = 'TX : '.$ReseauTX.' - RX : '.$ReseauRX;
							$ethernet0_name = $ReseauRXTX[0];
							
							if (isset($ReseauIP)) {
								$ethernet0_ip = $ReseauIP;
							} else {
								$ethernet0_ip = '';
							}
							
							log::add('Monitoring', 'debug', '['. $equipement .'][RESEAU] Nom de la carte réseau / IP (RX / TX) :: ' .$ethernet0_name.' / IP= ' . $ethernet0_ip . ' (RX= '.$ReseauRX.' / TX= '.$ReseauTX.')');
						} else {
							log::add('Monitoring', 'error', '['. $equipement .'][RESEAU] Carte Réseau NON détectée :: KO');
						}
					}
	
					$hddtotal = '';
					$hddused = '';
					$hddused_pourc = '';
					if (isset($hdd)) {
						$hdddata = explode(' ', $hdd);
						if (isset($hdddata[0]) && isset($hdddata[1]) && isset($hdddata[2])) {
							$hddtotal = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddata[0]);
							$hddused = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddata[1]);
							$hddused_pourc = preg_replace("/[^0-9.]/", "", $hdddata[2]);
							$hddused_pourc = trim($hddused_pourc);
						}
					}
	
					if (isset($ARMv)) {
						if ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386') {
							if ((floatval($cpufreq) / 1000) > 1) {
								$cpufreq = round(floatval($cpufreq) / 1000, 1, PHP_ROUND_HALF_UP) . " GHz";
							} else {
								$cpufreq = $cpufreq . " MHz";
							}
							
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if (floatval($cputemp0) > 200) {
									$cputemp0 = floatval($cputemp0) / 1000;
									$cputemp0 = round(floatval($cputemp0), 1);
								}
							}
							$cpu = $nbcpu.' - '.$cpufreq;
						} elseif ($ARMv == 'armv6l' || $ARMv == 'armv7l' || $ARMv == 'aarch64' || $ARMv == 'mips64') {
							if ((floatval($cpufreq0) / 1000) > 1000) {
								$cpufreq0 = round(floatval($cpufreq0) / 1000000, 1, PHP_ROUND_HALF_UP) . " GHz";
							} else {
								$cpufreq0 = round(floatval($cpufreq0) / 1000) . " MHz";
							}
							
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if (floatval($cputemp0) > 200) {
									$cputemp0 = floatval($cputemp0) / 1000;
									$cputemp0 = round(floatval($cputemp0), 1);
								}
							}
							if (floatval($cpufreq0) == 0) {
								$cpu = $nbcpu.' Socket(s) ';
								$cpufreq0 = '';
							} else {
								$cpu = $nbcpu.' - '.$cpufreq0;
							}
						} elseif ($ARMv == 'arm') {
							if (preg_match("#RasPlex|OpenELEC|osmc|LibreELEC#", $namedistri) || preg_match("#piCorePlayer#", $uname) || preg_match("#medion#", $uname)) {
								if ((floatval($cpufreq0) / 1000) > 1000) {
									$cpufreq0 = round(floatval($cpufreq0) / 1000000, 1, PHP_ROUND_HALF_UP) . " GHz";
								} else {
									$cpufreq0 = round(floatval($cpufreq0) / 1000) . " MHz";
								}
								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd)) {
									if (floatval($cputemp0) > 200) {
										$cputemp0 = floatval($cputemp0) / 1000;
										$cputemp0 = round(floatval($cputemp0), 1);
									}
								}
								$cpu = $nbcpu.' - '.$cpufreq0;
							}
						}
					}
	
					if ($this->getConfiguration('synology') == '1') {
						if ((floatval($cpufreq0) / 1000) > 1) {
							$cpufreq0 = round(floatval($cpufreq0) / 1000, 1, PHP_ROUND_HALF_UP) . " GHz";
						} else {
							$cpufreq0 = $cpufreq0 . " MHz";
						}
						if (floatval($cputemp0) > 200) {
							$cputemp0 = floatval($cputemp0) / 1000;
							$cputemp0 = round(floatval($cputemp0), 1);
						}
						$cpu = $nbcpu.' - '.$cpufreq0;
					}
					if (empty($cputemp0)) {$cputemp0 = '';}
					if (empty($perso1)) {$perso1 = '';}
					if (empty($perso2)) {$perso2 = '';}
					if (empty($cnx_ssh)) {$cnx_ssh = '';}
					if (empty($uname)) {$uname = 'Inconnu';}
					if (empty($Mem)) {$Mem = '';}
					if (empty($memorylibre_pourc)) {$memorylibre_pourc = '';}
					if (empty($Memswap)) {$Memswap = '';}
					if (empty($swaplibre_pourc)) {$swaplibre_pourc = '';}
					# TODO ajouter les commandes type syno ou temp
	
					$dataresult = array(
						'namedistri' => $namedistri,
						'uptime' => $uptime,
						'loadavg1mn' => $loadavg1mn,
						'loadavg5mn' => $loadavg5mn,
						'loadavg15mn' => $loadavg15mn,
						'Mem' => $Mem,
						'ethernet0' => $ethernet0,
						'ethernet0_name' => $ethernet0_name,
						'ethernet0_ip' => $ethernet0_ip,
						'hddtotal' => $hddtotal,
						'hddused' => $hddused,
						'hddpourcused' => $hddused_pourc,
						'cpu' => $cpu,
						'cpu_temp' => $cputemp0,
						'cnx_ssh' => $cnx_ssh,
						'Mem_swap' => $Memswap,
						'Mempourc' => $memorylibre_pourc,
						'Swappourc' => $swaplibre_pourc,
						'perso1' => $perso1,
						'perso2' => $perso2,
					);
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1') {
						$dataresultv2 = array(
							'hddtotalv2' => $hddtotalv2,
							'hddusedv2' => $hddusedv2,
							'hddpourcusedv2' => $hddusedv2_pourc,
						);
					}
	
					// Syno Volume USB
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1') {
						$dataresultusb = array(
							'hddtotalusb' => $hddtotalusb,
							'hddusedusb' => $hddusedusb,
							'hddpourcusedusb' => $hddusedusb_pourc,
						);
					}
	
					// Syno Volume eSATA
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1') {
						$dataresultesata = array(
							'hddtotalesata' => $hddtotalesata,
							'hddusedesata' => $hddusedesata,
							'hddpourcusedesata' => $hddusedesata_pourc,
						);
					}
	
					// Event sur les commandes après récupération des données
					$cnx_ssh = $this->getCmd(null,'cnx_ssh');
					if (is_object($cnx_ssh)) {
						$cnx_ssh->event($dataresult['cnx_ssh']);
					}
	
					$namedistri = $this->getCmd(null,'namedistri');
					if (is_object($namedistri)) {
						$namedistri->event($dataresult['namedistri']);
					}
	
					$uptime = $this->getCmd(null,'uptime');
					if (is_object($uptime)) {
						$uptime->event($dataresult['uptime']);
					}
	
					$loadavg1mn = $this->getCmd(null,'loadavg1mn');
					if (is_object($loadavg1mn)) {
						$loadavg1mn->event($dataresult['loadavg1mn']);
					}
	
					$loadavg5mn = $this->getCmd(null,'loadavg5mn');
					if (is_object($loadavg5mn)) {
						$loadavg5mn->event($dataresult['loadavg5mn']);
					}
	
					$loadavg15mn = $this->getCmd(null,'loadavg15mn');
					if (is_object($loadavg15mn)) {
						$loadavg15mn->event($dataresult['loadavg15mn']);
					}
	
					$Mem = $this->getCmd(null,'Mem');
					if (is_object($Mem)) {
						$Mem->event($dataresult['Mem']);
					}
	
					$Mempourc = $this->getCmd(null,'Mempourc');
					if (is_object($Mempourc)) {
						$Mempourc->event($dataresult['Mempourc']);
					}
	
					$Mem_swap = $this->getCmd(null,'Mem_swap');
					if (is_object($Mem_swap)) {
						$Mem_swap->event($dataresult['Mem_swap']);
					}
	
					$Swappourc = $this->getCmd(null,'Swappourc');
					if (is_object($Swappourc)) {
						$Swappourc->event($dataresult['Swappourc']);
					}
	
					$ethernet0 = $this->getCmd(null,'ethernet0');
					if (is_object($ethernet0)) {
						$ethernet0->event($dataresult['ethernet0']);
					}
	
					$ethernet0_name = $this->getCmd(null,'ethernet0_name');
					if (is_object($ethernet0_name)) {
						$ethernet0_name->event($dataresult['ethernet0_name']);
					}
	
					$ethernet0_ip = $this->getCmd(null,'ethernet0_ip');
					if (is_object($ethernet0_ip)) {
						$ethernet0_ip->event($dataresult['ethernet0_ip']);
					}
	
					$hddtotal = $this->getCmd(null,'hddtotal');
					if (is_object($hddtotal)) {
						$hddtotal->event($dataresult['hddtotal']);
					}
	
					$hddused = $this->getCmd(null,'hddused');
					if (is_object($hddused)) {
						$hddused->event($dataresult['hddused']);
					}
	
					$hddused_pourc = $this->getCmd(null,'hddpourcused');
					if (is_object($hddused_pourc)) {
						$hddused_pourc->event($dataresult['hddpourcused']);
					}
	
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1') {
						$hddtotalv2 = $this->getCmd(null,'hddtotalv2');
						if (is_object($hddtotalv2)) {
							$hddtotalv2->event($dataresultv2['hddtotalv2']);
						}
						$hddusedv2 = $this->getCmd(null,'hddusedv2');
						if (is_object($hddusedv2)) {
							$hddusedv2->event($dataresultv2['hddusedv2']);
						}
						$hddusedv2_pourc = $this->getCmd(null,'hddpourcusedv2');
						if (is_object($hddusedv2_pourc)) {
							$hddusedv2_pourc->event($dataresultv2['hddpourcusedv2']);
						}
					}
	
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1') {
						$hddtotalusb = $this->getCmd(null,'hddtotalusb');
						if (is_object($hddtotalusb)) {
							$hddtotalusb->event($dataresultusb['hddtotalusb']);
						}
						$hddusedusb = $this->getCmd(null,'hddusedusb');
						if (is_object($hddusedusb)) {
							$hddusedusb->event($dataresultusb['hddusedusb']);
						}
						$hddusedusb_pourc = $this->getCmd(null,'hddpourcusedusb');
						if (is_object($hddusedusb_pourc)) {
							$hddusedusb_pourc->event($dataresultusb['hddpourcusedusb']);
						}
					}
	
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1') {
						$hddtotalesata = $this->getCmd(null,'hddtotalesata');
						if (is_object($hddtotalesata)) {
							$hddtotalesata->event($dataresultesata['hddtotalesata']);
						}
						$hddusedesata = $this->getCmd(null,'hddusedesata');
						if (is_object($hddusedesata)) {
							$hddusedesata->event($dataresultesata['hddusedesata']);
						}
						$hddusedesata_pourc = $this->getCmd(null,'hddpourcusedesata');
						if (is_object($hddusedesata_pourc)) {
							$hddusedesata_pourc->event($dataresultesata['hddpourcusedesata']);
						}
					}
	
					$cpu = $this->getCmd(null,'cpu');
					if (is_object($cpu)) {
						$cpu->event($dataresult['cpu']);
					}
	
					$cpu_temp = $this->getCmd(null,'cpu_temp');
					if (is_object($cpu_temp)) {
						$cpu_temp->event($dataresult['cpu_temp']);
					}
	
					$perso1 = $this->getCmd(null,'perso1');
					if (is_object($perso1)) {
						$perso1->event($dataresult['perso1']);
					}
	
					$perso2 = $this->getCmd(null,'perso2');
					if (is_object($perso2)) {
						$perso2->event($dataresult['perso2']);
					}
				} elseif ($cnx_ssh == 'KO') {
					$dataresult = array(
						'namedistri' => 'Connexion SSH KO',
						'cnx_ssh' => $cnx_ssh
					);
					$namedistri = $this->getCmd(null,'namedistri');
					if (is_object($namedistri)) {
						$namedistri->event($dataresult['namedistri']);
					}
					$cnx_ssh = $this->getCmd(null,'cnx_ssh');
					if (is_object($cnx_ssh)) {
						$cnx_ssh->event($dataresult['cnx_ssh']);
					}
				}
			}
		} catch (Exception $e) {
			log::add('Monitoring', 'error', '[' . $equipement . '][getInformations] Exception (Line ' . $e->getLine() . ') :: '. $e->getMessage());
			log::add('Monitoring', 'debug', '[' . $equipement . '][getInformations] Exception Trace :: '. json_encode($e->getTrace()));
		}
	}

	function getCaseAction($paramaction) {
		$confLocalOrRemote = $this->getConfiguration('localoudistant');
		$equipement = $this->getName();
		
		if ($confLocalOrRemote == 'distant' && $this->getIsEnable()) {
			[$cnx_ssh, $hostId] = $this->connectSSH();
				
			if ($cnx_ssh == 'OK') {
				switch ($paramaction) {
					case "reboot":
						if ($this->getConfiguration('synology') == '1') {
							$rebootcmd = "timeout 3 sudo -S /sbin/shutdown -r now 2>/dev/null";
							log::add('Monitoring', 'info', '['. $equipement .'][SSH][SYNO-REBOOT] Lancement commande distante REBOOT');
						} else {
							$rebootcmd = "timeout 3 sudo -S reboot 2>/dev/null";
							log::add('Monitoring', 'info', '['. $equipement .'][SSH][LINUX-REBOOT] Lancement commande distante REBOOT');
						}
						$reboot = $this->execSSH($hostId, $rebootcmd, 'Reboot');
						break;
					case "poweroff":
						if ($this->getConfiguration('synology') == '1') {
							$poweroffcmd = 'timeout 3 sudo -S /sbin/shutdown -h now 2>/dev/null';
							log::add('Monitoring', 'info', '['. $equipement .'][SSH][SYNO-POWEROFF] Lancement commande distante POWEROFF');
						} else {
							$poweroffcmd = "timeout 3 sudo -S poweroff 2>/dev/null";
							log::add('Monitoring', 'info', '['. $equipement .'][SSH][LINUX-POWEROFF] Lancement commande distante POWEROFF');
						}
						$poweroff = $this->execSSH($hostId, $poweroffcmd, 'PowerOff');
						break;
				}
			}
		} elseif ($this->getConfiguration('localoudistant') == 'local' && $this->getIsEnable()) {
			switch ($paramaction) {
				case "reboot":
					if ($this->getConfiguration('synology') == '1') {
						$rebootcmd = "timeout 3 sudo -S /sbin/shutdown -r now 2>/dev/null";
						log::add('Monitoring', 'info', '['. $equipement .'][LOCAL][SYNO-REBOOT] Lancement commande locale REBOOT');
					} else {
						// $rebootcmd = "timeout 3 sudo -S shutdown -r now 2>/dev/null";
						$rebootcmd = "timeout 3 sudo -S reboot 2>/dev/null";
						log::add('Monitoring', 'info', '['. $equipement .'][LOCAL][LINUX-REBOOT] Lancement commande locale REBOOT');
					}
					$reboot = $this->execSRV($rebootcmd, 'Reboot');
					break;
				case "poweroff":
					if ($this->getConfiguration('synology') == '1') {
						// $poweroffcmd = 'sudo /sbin/shutdown -P now >/dev/null & /sbin/shutdown -P now >/dev/null';
						$poweroffcmd = 'timeout 3 sudo -S /sbin/shutdown -h now 2>/dev/null';
						log::add('Monitoring', 'info', '['. $equipement .'][LOCAL][SYNO-POWEROFF] Lancement commande locale POWEROFF');
					} else {
						// $poweroffcmd = 'timeout 3 sudo -S shutdown -h now 2>/dev/null';
						$poweroffcmd = "timeout 3 sudo -S poweroff 2>/dev/null";
						log::add('Monitoring', 'info', '['. $equipement .'][LOCAL][LINUX-POWEROFF] Lancement commande locale POWEROFF');
					}
					$poweroff = $this->execSRV($poweroffcmd, 'PowerOff');
					break;
			}
		}
	}
}

class MonitoringCmd extends cmd {
	/* * *************************Attributs****************************** */
	public static $_widgetPossibility = array('custom' => false);

	/* * *********************Methode d'instance************************* */
	public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		$paramaction = $this->getLogicalId();

		if ($this->getType() == "action") {
			// $eqLogic->getCmd();
			switch ($paramaction) {
				case "reboot":
				case "poweroff":
					$eqLogic->getCaseAction($paramaction);
					break;
				case "cron_on":
					log::add('Monitoring', 'debug', '['. $eqLogic->getName() .'][CRON] Execution Commande :: ' . $paramaction);
					$cron_status_cmd = $eqLogic->getCmd(null, 'cron_status');
					if (is_object($cron_status_cmd)) {
						$cron_status_cmd->event(1);
						$eqLogic->refreshWidget();
					}
					break;
				case "cron_off":
					log::add('Monitoring', 'debug', '['. $eqLogic->getName() .'][CRON] Execution Commande :: ' . $paramaction);
					$cron_status_cmd = $eqLogic->getCmd(null, 'cron_status');
					if (is_object($cron_status_cmd)) {
						$cron_status_cmd->event(0);
						$eqLogic->refreshWidget();
					}
					break;
				default:
					throw new Exception(__('Commande non implémentée actuellement', __FILE__));
			}
		} else {
			throw new Exception(__('Commande non implémentée actuellement', __FILE__));
		}
		return true;
	}
}
