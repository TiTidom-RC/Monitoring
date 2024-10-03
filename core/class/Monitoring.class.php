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
		log::add($_logName, 'info', '[DEP-INSTALL] Début des dépendances');
		config::save('lastDependancyInstallTime', date('Y-m-d H:i:s'), plugin::byId('Monitoring')->getId());

		try {
			$_plugin = plugin::byId('sshmanager');
			log::add($_logName, 'info', __('[DEP-INSTALL] Le plugin SSHManager est déjà installé', __FILE__));
			if (!$_plugin->isActive()) {
				log::add($_logName, 'error', __('[DEP-INSTALL] Le plugin SSHManager n\'est pas activé', __FILE__));
				$_plugin->setIsEnable(1, true, true);
				log::add($_logName, 'info', __('[DEP-INSTALL] Activation du plugin SSHManager', __FILE__));
			} else {
				log::add($_logName, 'info', __('[DEP-INSTALL] Plugin SSHManager :: actif', __FILE__));
			}
			log::add($_logName, 'info', '[DEP-INSTALL] Fin des dépendances');
		} catch (Exception $e) {
			log::add($_logName, 'warning', '[DEP-INSTALL] ' . $e->getMessage());
			log::add($_logName, 'info', __('[DEP-INSTALL] Lancement de l\'installation du plugin SSHManager', __FILE__));

			// Installation du plugin SSHManager (même version que celle du plugin Monitoring)
			$_pluginSource = update::byLogicalId('Monitoring');
			$_pluginToInstall = update::byLogicalId('sshmanager');
			if (!is_object($_pluginToInstall)) {
				$_pluginToInstall = new update();
			}
			$_pluginToInstall->setLogicalId('sshmanager');
			$_pluginToInstall->setType('plugin');
			$_pluginToInstall->setSource($_pluginSource->getSource());
			if ($_pluginSource->getSource() == 'github') {
				$_pluginToInstall->setConfiguration('user', $_pluginSource->getConfiguration('user'));
				$_pluginToInstall->setConfiguration('repository', 'SSH-Manager');
				if (strpos($_pluginSource->getConfiguration('version', 'stable'), 'dev') !== false) {
					$_pluginToInstall->setConfiguration('version', 'dev');
					log::add($_logName, 'info', '[DEP-INSTALL] Installation de la version :: dev (GitHub)');
				} else {
					$_pluginToInstall->setConfiguration('version', $_pluginSource->getConfiguration('version', 'stable'));
					log::add($_logName, 'info', '[DEP-INSTALL] Installation de la version :: ' . $_pluginSource->getConfiguration('version', 'stable') . ' (GitHub)');
				}
				$_pluginToInstall->setConfiguration('token', $_pluginSource->getConfiguration('token'));
			} else {
				$_pluginToInstall->setConfiguration('version', $_pluginSource->getConfiguration('version', 'stable'));
				log::add($_logName, 'info', '[DEP-INSTALL] Installation de la version :: ' . $_pluginSource->getConfiguration('version', 'stable') . ' (Market)');
			}
			$_pluginToInstall->save();
			$_pluginToInstall->doUpdate();
			
			// Vérification de l'installation du plugin SSHManager
			$isNotInstalled = true;
			$num = 30;
			$_plugin = null;
			while ($isNotInstalled && $num > 0) {
				try {
					$_plugin = plugin::byId('sshmanager');
					$isNotInstalled = false;
				} catch (Exception $e) {
					log::add($_logName, 'debug', '[DEP-INSTALL] While Message (' . strval($num) . ') :: ' . $e->getMessage());
					$num--;
					sleep(1);
				}
			}
			if ($num == 0) {
				log::add($_logName, 'error', '[DEP-INSTALL] Le plugin SSHManager n\'a pas pu être installé !');
			} else {
				log::add($_logName, 'info', '[DEP-INSTALL] Le plugin SSHManager est maintenant installé');
				if (is_object($_plugin)) {
					try {
						$_plugin->setIsEnable(1, true, true);
						log::add($_logName, 'info', '[DEP-INSTALL] Le plugin SSHManager est maintenant activé');
						jeedom::cleanFileSystemRight();
						log::add($_logName, 'info', '[DEP-INSTALL] Fin des dépendances');
					} catch (Exception $e) {
						log::add($_logName, 'warning', '[DEP-INSTALL] Exception :: ' . $e->getMessage());
						log::add($_logName, 'error', '[DEP-INSTALL] Le plugin SSHManager n\'a pas pu être installé !');
					}
				}
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
			log::add($_logName, 'info', __('[DEP-INFO] Installation des dépendances en cours', __FILE__));
			$return['state'] = 'in_progress';
		} else {
			try {
				$_plugin = plugin::byId('sshmanager');
				if (!$_plugin->isActive()) {
					log::add($_logName, 'error', __('[DEP-INFO] Le plugin SSHManager n\'est pas activé', __FILE__));
					$return['state'] = 'nok';
				} else {
					log::add($_logName, 'info', __('[DEP-INFO] Vérification des dépendances :: OK', __FILE__));
					$return['state'] = 'ok';
				}
			} catch (Exception $e) {
				log::add($_logName, 'debug', '[DEP-INFO] ' . $e->getMessage());
				log::add($_logName, 'error', __('[DEP-INFO] Le plugin SSHManager n\'est pas installé', __FILE__));
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
			$MonitoringCmd->setValue($cron_status_cmd);
			$MonitoringCmd->setIsVisible(0);
			$MonitoringCmd->setTemplate('dashboard', 'core::toggle');
			$MonitoringCmd->setTemplate('mobile', 'core::toggle');
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

		$MonitoringCmd = $this->getCmd(null, 'distri_name');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Distribution', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('distri_name');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fab fa-linux"></i>');
			$MonitoringCmd->setIsVisible(1);
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'uptime');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Uptime', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('uptime');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-hourglass-half"></i>');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'uptime_sec');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Uptime (Sec)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('uptime_sec');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'load_avg');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('load_avg');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-chart-line"></i>');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'load_avg_1mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 1 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('load_avg_1mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'load_avg_5mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 5 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('load_avg_5mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'load_avg_15mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 15 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('load_avg_15mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'memory');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-database"></i>');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_total');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Totale', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_total');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_free');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Libre', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_free');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_free_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Libre (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_free_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_used');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Utilisée', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_used');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_used_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Utilisée (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_used_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'swap');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-layer-group"></i>');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_total');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Total', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_total');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_free');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Libre', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_free');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_free_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Libre (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_free_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_used');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Libre', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_used');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_used_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Utilisé (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_used_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'network');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Réseau (TX-RX)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-network-wired"></i>');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'network_tx');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Réseau (TX)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_tx');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'network_rx');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Réseau (RX)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_rx');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'network_name');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Carte Réseau', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_name');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'network_ip');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Adresse IP', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_ip');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-hdd"></i>');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_total');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Total', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_total');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_free');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Libre', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_free');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_free_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Libre (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_free_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_used');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Utilisé', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_used');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_used_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Utilisé (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_used_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->save();
		}

		if ($this->getConfiguration('synology') == '1') {
			// Synology volume 2
			if ($this->getConfiguration('synologyv2') == '1') {

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->setDisplay('icon', '<i class="far fa-hdd"></i>');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_used_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}
			}
			
			// Synology volume USB
			if ($this->getConfiguration('synologyusb') == '1') {

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->setDisplay('icon', '<i class="fab fa-usb"></i>');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}
			}

			// Synology volume eSATA
			if ($this->getConfiguration('synologyesata') == '1') {

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->setDisplay('icon', '<i class="fab fa-usb"></i>');
					$MonitoringCmd->save();
				}
				
				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->save();
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_used_percent');
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
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-microchip"></i>');
			$MonitoringCmd->save();
		}

		$MonitoringCmd = $this->getCmd(null, 'cpu_nb');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Nb CPU', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cpu_nb');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
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

		$MonitoringCmd = $this->getCmd(null, 'cpu_freq');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Fréquence CPU', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cpu_freq');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
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

	public function getStats($cmd, $cmdName, &$replace, int $precision = 2) {
		if ($cmd->getIsHistorized() == 1) {
			$startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculPeriod') . ' hour'));
			$historyStatistique = $cmd->getStatistique($startHist, date('Y-m-d H:i:s'));
			if ($historyStatistique['avg'] == 0 && $historyStatistique['min'] == 0 && $historyStatistique['max'] == 0) {
				$replace['#' . $cmdName . '_averageHistory#'] = round(floatval($replace['#' . $cmdName . '#']), $precision);
				$replace['#' . $cmdName . '_minHistory#'] = round(floatval($replace['#' . $cmdName . '#']), $precision);
				$replace['#' . $cmdName . '_maxHistory#'] = round(floatval($replace['#' . $cmdName . '#']), $precision);
			} else {
				$replace['#' . $cmdName . '_averageHistory#'] = round($historyStatistique['avg'], $precision);
				$replace['#' . $cmdName . '_minHistory#'] = round($historyStatistique['min'], $precision);
				$replace['#' . $cmdName . '_maxHistory#'] = round($historyStatistique['max'], $precision);
			}
			// Tendance
			if ($this->getConfiguration('stats_tendance', '0') == '1') {
				$tendance = $cmd->getTendance($startHist, date('Y-m-d H:i:s'));
				log::add('Monitoring', 'debug', '[' . $this->getName() . '][getStats] Tendance :: ' . $cmd->getName() . ' :: ' . strval($tendance));
				if ($tendance > config::byKey('historyCalculTendanceThresholddMax')) {
					$replace['#' . $cmdName . '_tendance#'] = ' <i style="color: var(--al-info-color) !important;" class="fas fa-arrow-up"></i>';
				} elseif ($tendance < config::byKey('historyCalculTendanceThresholddMin')) {
					$replace['#' . $cmdName . '_tendance#'] = ' <i style="color: var(--al-info-color) !important;" class="fas fa-arrow-down"></i>';
				} else {
					$replace['#' . $cmdName . '_tendance#'] = ' <i style="color: var(--al-info-color) !important;" class="fas fa-minus"></i>';
				}
			} else {
				$replace['#' . $cmdName . '_tendance#'] = '';
			}
		} else {
			$replace['#' . $cmdName . '_averageHistory#'] = '-';
			$replace['#' . $cmdName . '_minHistory#'] = '-';
			$replace['#' . $cmdName . '_maxHistory#'] = '-';
			$replace['#' . $cmdName . '_tendance#'] = '';
		}
	}

	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$_version = jeedom::versionAlias($_version);

		$cnx_ssh = $this->getCmd(null,'cnx_ssh');
		$replace['#cnx_ssh#'] = is_object($cnx_ssh) ? $cnx_ssh->execCmd() : '';
		$replace['#cnx_ssh_id#'] = is_object($cnx_ssh) ? $cnx_ssh->getId() : '';

		$cron_status = $this->getCmd(null,'cron_status');
		$replace['#cron_status#'] = is_object($cron_status) ? $cron_status->execCmd() : '';
		$replace['#cron_status_id#'] = is_object($cron_status) ? $cron_status->getId() : '';
		$replace['#cron_status_display#'] = (is_object($cron_status) && $cron_status->getIsVisible()) ? "inline-block" : "none";
		$replace['#cron_status_custom#'] = $this->getConfiguration('pull_use_custom', '0');

		$distri_name = $this->getCmd(null,'distri_name');
		$replace['#distri_name_icon#'] = is_object($distri_name) ? (!empty($distri_name->getDisplay('icon')) ? $distri_name->getDisplay('icon') : '<i class="fab fa-linux"></i>') : '';
		$replace['#distri_name#'] = is_object($distri_name) ? $distri_name->execCmd() : '';
		$replace['#distri_name_id#'] = is_object($distri_name) ? $distri_name->getId() : '';
		$replace['#distri_name_display#'] = (is_object($distri_name) && $distri_name->getIsVisible()) ? "block" : "none";
		$replace['#distri_name_collect#'] = (is_object($distri_name) && $distri_name->getIsVisible()) ? $distri_name->getCollectDate() : "-";
        $replace['#distri_name_value#'] = (is_object($distri_name) && $distri_name->getIsVisible()) ? $distri_name->getValueDate() : "-";

		$load_avg_1mn = $this->getCmd(null,'load_avg_1mn');
		$replace['#load_avg_1mn_icon#'] = is_object($load_avg_1mn) ? (!empty($load_avg_1mn->getDisplay('icon')) ? $load_avg_1mn->getDisplay('icon') : '<i class="fas fa-chart-line"></i>') : '';
		$replace['#load_avg_1mn#'] = is_object($load_avg_1mn) ? $load_avg_1mn->execCmd() : '';
		$replace['#load_avg_1mn_id#'] = is_object($load_avg_1mn) ? $load_avg_1mn->getId() : '';
		$replace['#loadavg_display#'] = (is_object($load_avg_1mn) && $load_avg_1mn->getIsVisible()) ? "block" : "none";
		$replace['#loadavg_collect#'] = (is_object($load_avg_1mn) && $load_avg_1mn->getIsVisible()) ? $load_avg_1mn->getCollectDate() : "-";
        $replace['#loadavg_value#'] = (is_object($load_avg_1mn) && $load_avg_1mn->getIsVisible()) ? $load_avg_1mn->getValueDate() : "-";

		$replace['#load_avg_1mn_colorlow#'] = $this->getConfiguration('load_avg_1mn_colorlow');
		$replace['#load_avg_1mn_colorhigh#'] = $this->getConfiguration('load_avg_1mn_colorhigh');

		$this->getStats($load_avg_1mn, 'load_avg_1mn', $replace, 2);

		$load_avg_5mn = $this->getCmd(null,'load_avg_5mn');
		$replace['#load_avg_5mn#'] = is_object($load_avg_5mn) ? $load_avg_5mn->execCmd() : '';
		$replace['#load_avg_5mn_id#'] = is_object($load_avg_5mn) ? $load_avg_5mn->getId() : '';

		$replace['#load_avg_5mn_colorlow#'] = $this->getConfiguration('load_avg_5mn_colorlow');
		$replace['#load_avg_5mn_colorhigh#'] = $this->getConfiguration('load_avg_5mn_colorhigh');

		$this->getStats($load_avg_5mn, 'load_avg_5mn', $replace, 2);

		$load_avg_15mn = $this->getCmd(null,'load_avg_15mn');
		$replace['#load_avg_15mn#'] = is_object($load_avg_15mn) ? $load_avg_15mn->execCmd() : '';
		$replace['#load_avg_15mn_id#'] = is_object($load_avg_15mn) ? $load_avg_15mn->getId() : '';

		$replace['#load_avg_15mn_colorlow#'] = $this->getConfiguration('load_avg_15mn_colorlow');
		$replace['#load_avg_15mn_colorhigh#'] = $this->getConfiguration('load_avg_15mn_colorhigh');
		
		$this->getStats($load_avg_15mn, 'load_avg_15mn', $replace, 2);

		$uptime = $this->getCmd(null,'uptime');
		$replace['#uptime_icon#'] = is_object($uptime) ? (!empty($uptime->getDisplay('icon')) ? $uptime->getDisplay('icon') : '<i class="fas fa-hourglass-half"></i>') : '';
		$replace['#uptime#'] = is_object($uptime) ? $uptime->execCmd() : '';
		$replace['#uptime_id#'] = is_object($uptime) ? $uptime->getId() : '';
		$replace['#uptime_display#'] = (is_object($uptime) && $uptime->getIsVisible()) ? "block" : "none";
		$replace['#uptime_collect#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getCollectDate() : "-";
        $replace['#uptime_value#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getValueDate() : "-";
		
		$hdd_total = $this->getCmd(null,'hdd_total');
		$replace['#hdd_total_icon#'] = is_object($hdd_total) ? (!empty($hdd_total->getDisplay('icon')) ? $hdd_total->getDisplay('icon') : '<i class="fas fa-hdd"></i>') : '';
		$replace['#hdd_total#'] = is_object($hdd_total) ? $hdd_total->execCmd() : '';
		$replace['#hdd_total_id#'] = is_object($hdd_total) ? $hdd_total->getId() : '';
		$replace['#hdd_used_display#'] = (is_object($hdd_total) && $hdd_total->getIsVisible()) ? "block" : "none";
		$replace['#hdd_total_collect#'] = (is_object($hdd_total) && $hdd_total->getIsVisible()) ? $hdd_total->getCollectDate() : "-";
        $replace['#hdd_total_value#'] = (is_object($hdd_total) && $hdd_total->getIsVisible()) ? $hdd_total->getValueDate() : "-";

		$hdd_used = $this->getCmd(null,'hdd_used');
		$replace['#hdd_used#'] = is_object($hdd_used) ? $hdd_used->execCmd() : '';
		$replace['#hdd_used_id#'] = is_object($hdd_used) ? $hdd_used->getId() : '';

		$hdd_used_percent = $this->getCmd(null,'hdd_used_percent');
		$replace['#hdd_used_percent#'] = is_object($hdd_used_percent) ? $hdd_used_percent->execCmd() : '';
		$replace['#hdd_used_percent_id#'] = is_object($hdd_used_percent) ? $hdd_used_percent->getId() : '';

		$replace['#hdd_used_percent_colorlow#'] = $this->getConfiguration('hdd_used_percent_colorlow');
		$replace['#hdd_used_percent_colorhigh#'] = $this->getConfiguration('hdd_used_percent_colorhigh');

		$this->getStats($hdd_used_percent, 'hdd_used_percent', $replace, 0);
		
		$memory = $this->getCmd(null,'memory');
		$replace['#memory_icon#'] = is_object($memory) ? (!empty($memory->getDisplay('icon')) ? $memory->getDisplay('icon') : '<i class="fas fa-database"></i>') : '';
		$replace['#memory#'] = is_object($memory) ? $memory->execCmd() : '';
		$replace['#memory_id#'] = is_object($memory) ? $memory->getId() : '';
		$replace['#memory_display#'] = (is_object($memory) && $memory->getIsVisible()) ? "block" : "none";
		$replace['#memory_collect#'] = (is_object($memory) && $memory->getIsVisible()) ? $memory->getCollectDate() : "-";
        $replace['#memory_value#'] = (is_object($memory) && $memory->getIsVisible()) ? $memory->getValueDate() : "-";

		$memory_free_percent = $this->getCmd(null,'memory_free_percent');
		$replace['#memory_free_percent#'] = is_object($memory_free_percent) ? $memory_free_percent->execCmd() : '';
		$replace['#memory_free_percent_id#'] = is_object($memory_free_percent) ? $memory_free_percent->getId() : '';

		$replace['#memory_free_percent_colorhigh#'] = $this->getConfiguration('memory_free_percent_colorhigh');
		$replace['#memory_free_percent_colorlow#'] = $this->getConfiguration('memory_free_percent_colorlow');

		$this->getStats($memory_free_percent, 'memory_free_percent', $replace, 0);

		$swap = $this->getCmd(null,'swap');
		$replace['#swap_icon#'] = is_object($swap) ? (!empty($swap->getDisplay('icon')) ? $swap->getDisplay('icon') : '<i class="fas fa-layer-group"></i>') : '';
		$replace['#swap#'] = is_object($swap) ? $swap->execCmd() : '';
		$replace['#swap_id#'] = is_object($swap) ? $swap->getId() : '';
		$replace['#swap_display#'] = (is_object($swap) && $swap->getIsVisible()) ? "block" : "none";
		$replace['#swap_collect#'] = (is_object($swap) && $swap->getIsVisible()) ? $swap->getCollectDate() : "-";
        $replace['#swap_value#'] = (is_object($swap) && $swap->getIsVisible()) ? $swap->getValueDate() : "-";

		$swap_free_percent = $this->getCmd(null,'swap_free_percent');
		$replace['#swap_free_percent#'] = is_object($swap_free_percent) ? $swap_free_percent->execCmd() : '';
		$replace['#swap_free_percent_id#'] = is_object($swap_free_percent) ? $swap_free_percent->getId() : '';

		$replace['#swap_free_percent_colorhigh#'] = $this->getConfiguration('swap_free_percent_colorhigh');
		$replace['#swap_free_percent_colorlow#'] = $this->getConfiguration('swap_free_percent_colorlow');

		$this->getStats($swap_free_percent, 'swap_free_percent', $replace, 0);

		$network = $this->getCmd(null,'network');
		$replace['#network_icon#'] = is_object($network) ? (!empty($network->getDisplay('icon')) ? $network->getDisplay('icon') : '<i class="fas fa-network-wired"></i>') : '';
		$replace['#network#'] = is_object($network) ? $network->execCmd() : '';
		$replace['#network_id#'] = is_object($network) ? $network->getId() : '';
		$replace['#network_display#'] = (is_object($network) && $network->getIsVisible()) ? "block" : "none";
		$replace['#network_collect#'] = (is_object($network) && $network->getIsVisible()) ? $network->getCollectDate() : "-";
        $replace['#network_value#'] = (is_object($network) && $network->getIsVisible()) ? $network->getValueDate() : "-";

		$network_name = $this->getCmd(null,'network_name');
		$replace['#network_name#'] = is_object($network_name) ? $network_name->execCmd() : '';
		$replace['#network_name_id#'] = is_object($network_name) ? $network_name->getId() : '';

		$network_ip = $this->getCmd(null,'network_ip');
		$replace['#network_ip#'] = is_object($network_ip) ? $network_ip->execCmd() : '';
		$replace['#network_ip_id#'] = is_object($network_ip) ? $network_ip->getId() : '';

		$cpu = $this->getCmd(null,'cpu');
		$replace['#cpu_icon#'] = is_object($cpu) ? (!empty($cpu->getDisplay('icon')) ? $cpu->getDisplay('icon') : '<i class="fas fa-microchip"></i>') : '';
		$replace['#cpu#'] = is_object($cpu) ? $cpu->execCmd() : '';
		$replace['#cpu_id#'] = is_object($cpu) ? $cpu->getId() : '';
		$replace['#cpu_display#'] = (is_object($cpu) && $cpu->getIsVisible()) ? "block" : "none";
		$replace['#cpu_collect#'] = (is_object($cpu) && $cpu->getIsVisible()) ? $cpu->getCollectDate() : "-";
        $replace['#cpu_value#'] = (is_object($cpu) && $cpu->getIsVisible()) ? $cpu->getValueDate() : "-";

		$cpu_temp = $this->getCmd(null,'cpu_temp');
		$replace['#cpu_temp#'] = is_object($cpu_temp) ? $cpu_temp->execCmd() : '';
		$replace['#cpu_temp_id#'] = is_object($cpu_temp) ? $cpu_temp->getId() : '';
		$replace['#cpu_temp_display#'] = (is_object($cpu_temp) && $cpu_temp->getIsVisible()) ? 'OK' : '';

		$replace['#cpu_temp_colorlow#'] = $this->getConfiguration('cpu_temp_colorlow');
		$replace['#cpu_temp_colorhigh#'] = $this->getConfiguration('cpu_temp_colorhigh');

		$this->getStats($cpu_temp, 'cpu_temp', $replace, 0);

		// Syno Volume 2
		$SynoV2Visible = (is_object($this->getCmd(null,'syno_hddv2_total')) && $this->getCmd(null,'syno_hddv2_total')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoV2Visible == 'OK' && $this->getConfiguration('synologyv2') == '1') {
			$syno_hddv2_used = $this->getCmd(null,'syno_hddv2_used');
			$replace['#syno_hddv2_used#'] = is_object($syno_hddv2_used) ? $syno_hddv2_used->execCmd() : '';
			$replace['#syno_hddv2_used_id#'] = is_object($syno_hddv2_used) ? $syno_hddv2_used->getId() : '';

			$syno_hddv2_used_percent = $this->getCmd(null,'syno_hddv2_used_percent');
			$replace['#syno_hddv2_used_percent#'] = is_object($syno_hddv2_used_percent) ? $syno_hddv2_used_percent->execCmd() : '';
			$replace['#syno_hddv2_used_percent_id#'] = is_object($syno_hddv2_used_percent) ? $syno_hddv2_used_percent->getId() : '';
			$replace['#syno_hddv2_used_percent_colorlow#'] = $this->getConfiguration('syno_hddv2_used_percent_colorlow');
			$replace['#syno_hddv2_used_percent_colorhigh#'] = $this->getConfiguration('syno_hddv2_used_percent_colorhigh');

			$this->getStats($syno_hddv2_used_percent, 'syno_hddv2_used_percent', $replace, 0);

			$syno_hddv2_total = $this->getCmd(null,'syno_hddv2_total');
			$replace['#syno_hddv2_total_icon#'] = is_object($syno_hddv2_total) ? (!empty($syno_hddv2_total->getDisplay('icon')) ? $syno_hddv2_total->getDisplay('icon') : '<i class="far fa-hdd"></i>') : '';
			$replace['#synovolume2_display#'] = (is_object($syno_hddv2_total) && $syno_hddv2_total->getIsVisible()) ? 'OK' : '';
			$replace['#syno_hddv2_used_display#'] = (is_object($syno_hddv2_total) && $syno_hddv2_total->getIsVisible()) ? "block" : "none";
			$replace['#syno_hddv2_total#'] = is_object($syno_hddv2_total) ? $syno_hddv2_total->execCmd() : '';
			$replace['#syno_hddv2_total_id#'] = is_object($syno_hddv2_total) ? $syno_hddv2_total->getId() : '';
			$replace['#syno_hddv2_total_collect#'] = (is_object($syno_hddv2_total) && $syno_hddv2_total->getIsVisible()) ? $syno_hddv2_total->getCollectDate() : "-";
        	$replace['#syno_hddv2_total_value#'] = (is_object($syno_hddv2_total) && $syno_hddv2_total->getIsVisible()) ? $syno_hddv2_total->getValueDate() : "-";
		}

		// Syno Volume USB
		$SynoUSBVisible = (is_object($this->getCmd(null,'syno_hddusb_total')) && $this->getCmd(null,'syno_hddusb_total')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoUSBVisible == 'OK' && $this->getConfiguration('synologyusb') == '1') {
			$syno_hddusb_used = $this->getCmd(null,'syno_hddusb_used');
			$replace['#syno_hddusb_used#'] = is_object($syno_hddusb_used) ? $syno_hddusb_used->execCmd() : '';
			$replace['#syno_hddusb_used_id#'] = is_object($syno_hddusb_used) ? $syno_hddusb_used->getId() : '';

			$syno_hddusb_used_percent = $this->getCmd(null,'syno_hddusb_used_percent');
			$replace['#syno_hddusb_used_percent#'] = is_object($syno_hddusb_used_percent) ? $syno_hddusb_used_percent->execCmd() : '';
			$replace['#syno_hddusb_used_percent_id#'] = is_object($syno_hddusb_used_percent) ? $syno_hddusb_used_percent->getId() : '';

			$replace['#syno_hddusb_used_percent_colorlow#'] = $this->getConfiguration('syno_hddusb_used_percent_colorlow');
			$replace['#syno_hddusb_used_percent_colorhigh#'] = $this->getConfiguration('syno_hddusb_used_percent_colorhigh');

			$this->getStats($syno_hddusb_used_percent, 'syno_hddusb_used_percent', $replace, 0);

			$syno_hddusb_total = $this->getCmd(null,'syno_hddusb_total');
			$replace['#syno_hddusb_total_icon#'] = is_object($syno_hddusb_total) ? (!empty($syno_hddusb_total->getDisplay('icon')) ? $syno_hddusb_total->getDisplay('icon') : '<i class="fab fa-usb"></i>') : '';
			$replace['#synovolumeusb_display#'] = (is_object($syno_hddusb_total) && $syno_hddusb_total->getIsVisible()) ? 'OK' : '';
			$replace['#syno_hddusb_used_display#'] = (is_object($syno_hddusb_total) && $syno_hddusb_total->getIsVisible()) ? "block" : "none";
			$replace['#syno_hddusb_total#'] = is_object($syno_hddusb_total) ? $syno_hddusb_total->execCmd() : '';
			$replace['#syno_hddusb_total_id#'] = is_object($syno_hddusb_total) ? $syno_hddusb_total->getId() : '';
			$replace['#syno_hddusb_total_collect#'] = (is_object($syno_hddusb_total) && $syno_hddusb_total->getIsVisible()) ? $syno_hddusb_total->getCollectDate() : "-";
        	$replace['#syno_hddusb_total_value#'] = (is_object($syno_hddusb_total) && $syno_hddusb_total->getIsVisible()) ? $syno_hddusb_total->getValueDate() : "-";
		}

		// Syno Volume eSATA
		$SynoeSATAVisible = (is_object($this->getCmd(null,'syno_hddesata_total')) && $this->getCmd(null,'syno_hddesata_total')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoeSATAVisible == 'OK' && $this->getConfiguration('synologyesata') == '1') {
			$syno_hddesata_used = $this->getCmd(null,'syno_hddesata_used');
			$replace['#syno_hddesata_used#'] = is_object($syno_hddesata_used) ? $syno_hddesata_used->execCmd() : '';
			$replace['#syno_hddesata_used_id#'] = is_object($syno_hddesata_used) ? $syno_hddesata_used->getId() : '';

			$syno_hddesata_used_percent = $this->getCmd(null,'syno_hddesata_used_percent');
			$replace['#syno_hddesata_used_percent#'] = is_object($syno_hddesata_used_percent) ? $syno_hddesata_used_percent->execCmd() : '';
			$replace['#syno_hddesata_used_percent_id#'] = is_object($syno_hddesata_used_percent) ? $syno_hddesata_used_percent->getId() : '';

			$replace['#syno_hddesata_used_percent_colorlow#'] = $this->getConfiguration('syno_hddesata_used_percent_colorlow');
			$replace['#syno_hddesata_used_percent_colorhigh#'] = $this->getConfiguration('syno_hddesata_used_percent_colorhigh');

			$this->getStats($syno_hddesata_used_percent, 'syno_hddesata_used_percent', $replace, 0);

			$syno_hddesata_total = $this->getCmd(null,'syno_hddesata_total');
			$replace['#syno_hddesata_total_icon#'] = is_object($syno_hddesata_total) ? (!empty($syno_hddesata_total->getDisplay('icon')) ? $syno_hddesata_total->getDisplay('icon') : '<i class="fab fa-usb"></i>') : '';
			$replace['#synovolumeesata_display#'] = (is_object($syno_hddesata_total) && $syno_hddesata_total->getIsVisible()) ? 'OK' : '';
			$replace['#syno_hddesata_used_display#'] = (is_object($syno_hddesata_total) && $syno_hddesata_total->getIsVisible()) ? "block" : "none";
			$replace['#syno_hddesata_total#'] = is_object($syno_hddesata_total) ? $syno_hddesata_total->execCmd() : '';
			$replace['#syno_hddesata_total_id#'] = is_object($syno_hddesata_total) ? $syno_hddesata_total->getId() : '';
			$replace['#syno_hddesata_total_collect#'] = (is_object($syno_hddesata_total) && $syno_hddesata_total->getIsVisible()) ? $syno_hddesata_total->getCollectDate() : "-";
        	$replace['#syno_hddesata_total_value#'] = (is_object($syno_hddesata_total) && $syno_hddesata_total->getIsVisible()) ? $syno_hddesata_total->getValueDate() : "-";
		}

		$perso1 = $this->getCmd(null,'perso1');
		$replace['#perso1#'] = is_object($perso1) ? $perso1->execCmd() : '';
		$replace['#perso1_id#'] = is_object($perso1) ? $perso1->getId() : '';
		$replace['#perso1_display#'] = (is_object($perso1) && $perso1->getIsVisible()) ? "block" : "none";
		$replace['#perso1_collect#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getCollectDate() : "-";
        $replace['#perso1_value#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getValueDate() : "-";
		
		$replace['#perso1_name#'] = is_object($perso1) ? $perso1->getName() : '';
		$replace['#perso1_icon#'] = is_object($perso1) ? (!empty($perso1->getDisplay('icon')) ? $perso1->getDisplay('icon') : '<i class="fas fa-question-circle"></i>') : '';

		$perso1_unite = $this->getConfiguration('perso1_unite');
		$replace['#perso1_unite#'] = is_object($perso1) ? $perso1_unite : '';

		$replace ['#perso1_colorlow#'] = $this->getConfiguration('perso1_colorlow');
		$replace ['#perso1_colorhigh#'] = $this->getConfiguration('perso1_colorhigh');

		$this->getStats($perso1, 'perso1', $replace, 2);

		$perso2 = $this->getCmd(null,'perso2');
		$replace['#perso2#'] = is_object($perso2) ? $perso2->execCmd() : '';
		$replace['#perso2_id#'] = is_object($perso2) ? $perso2->getId() : '';
		$replace['#perso2_display#'] = (is_object($perso2) && $perso2->getIsVisible()) ? "block" : "none";
		$replace['#perso2_collect#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getCollectDate() : "-";
        $replace['#perso2_value#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getValueDate() : "-";
		
		$replace['#perso2_name#'] = (is_object($perso2)) ? $perso2->getName() : '';
		$replace['#perso2_icon#'] = (is_object($perso2)) ? (!empty($perso2->getDisplay('icon')) ? $perso2->getDisplay('icon') : '<i class="fas fa-question-circle"></i>') : '';
		
		$perso2_unite = $this->getConfiguration('perso2_unite');
		$replace['#perso2_unite#'] = is_object($perso2) ? $perso2_unite : '';

		$replace ['#perso2_colorlow#'] = $this->getConfiguration('perso2_colorlow');
		$replace ['#perso2_colorhigh#'] = $this->getConfiguration('perso2_colorhigh');

		$this->getStats($perso2, 'perso2', $replace, 2);

		foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
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
	
		if (class_exists('sshmanager')) {
			try {
				$cnx_ssh = sshmanager::checkSSHConnection($hostId) ? 'OK' : 'KO';
				log::add('Monitoring', ($cnx_ssh == 'KO' ? 'error': 'debug'), '['. $this->getName() .'][SSH-CNX] Connection :: ' . $cnx_ssh);
			} catch (Exception $e) {
				log::add('Monitoring', 'error', '['. $this->getName() .'][SSH-CNX] Connection Exception :: '. $e->getMessage());
				$cnx_ssh = 'KO';
			}
			return [$cnx_ssh, $hostId];
		} else {
			log::add('Monitoring', 'error', '['. $this->getName() .'][SSH-CNX] Connection :: Class SSHManager not found');
			return ['KO', $hostId];
		}
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
			if (!empty($cmdResult_ssh)) {
				log::add('Monitoring', 'debug', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' :: ' . str_replace("\r\n", "\\r\\n", $cmd_ssh));
				log::add('Monitoring', 'debug', '['. $this->getName() .'][SSH-EXEC] ' . $cmdName_ssh . ' Result :: ' . $cmdResult_ssh);
			}
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
			$memory = '';
			$memorylibre_percent = '';
			$network = '';
			$network_name = '';
			$network_ip = '';
	
			$cartereseau = $this->getNetworkCard($this->getConfiguration('cartereseau'));
	
			$confLocalOrRemote = $this->getConfiguration('localoudistant');
	
			// Configuration distante
			if ($confLocalOrRemote == 'distant' && $this->getIsEnable()) {
				[$cnx_ssh, $hostId] = $this->connectSSH();
				
				if ($cnx_ssh == 'OK') {
					if ($this->getConfiguration('synology') == '1') {
						if ($this->getConfiguration('syno_alt_name') == '1') {
							$distri_name_cmd = "cat /proc/sys/kernel/syno_hw_version 2>/dev/null";
						}
						else {
							$distri_name_cmd = "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null";
						}
						$VersionID_cmd = "awk -F'=' '/productversion/ {print $2}' /etc.defaults/VERSION 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
					}
					else {
						$distri_name_cmd = "awk -F'=' '/^PRETTY_NAME/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
	
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
					$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');
					
					if (trim($bitdistri_cmd) !== '') {
						$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');
					} else {
						$bitdistri = '';
					}
					
					$VersionID = $this->execSSH($hostId, $VersionID_cmd, 'VersionID');
					$loadav = $this->execSSH($hostId, $loadavg_cmd, 'LoadAverage');
					$ReseauRXTX = $this->execSSH($hostId, $ReseauRXTX_cmd, 'ReseauRXTX');
					$ReseauIP = $this->execSSH($hostId, $ReseauIP_cmd, 'ReseauIP');
					$memory = $this->execSSH($hostId, $memory_cmd, 'Memory');
					$swap = $this->execSSH($hostId, $swap_cmd, 'Swap');
	
					$perso1_cmd = $this->getConfiguration('perso1');
					$perso2_cmd = $this->getConfiguration('perso2');
	
					if (trim($perso1_cmd) !== '') {
						$perso1 = $this->execSSH($hostId, $perso1_cmd, 'Perso1');
					} else {
						$perso1 = '';
					}
					if (trim($perso2_cmd) !== '') {
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
	
						if (preg_match("#RasPlex|OpenELEC|LibreELEC#", $distri_name)) {
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
						} elseif (preg_match("#osmc#", $distri_name)) {
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
							
							$distri_name_cmd = "uname -a 2>/dev/null | awk '{print $2,$3}'";
							$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');
	
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
							$distri_name_cmd = "uname -a 2>/dev/null | awk '{ print $1,$3}'";
							$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');
	
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
	
							$distri_name_cmd = "cat /etc/*-release 2>/dev/null | awk '/^DistName/ { print $2 }'";
							$VersionID_cmd = "cat /etc/*-release 2>/dev/null | awk '/^VersionName/ { print $2 }'";
							$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
							$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/home$' | head -1 | awk '{ print $2,$3,$5 }'";
							
							$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');
							$VersionID = $this->execSSH($hostId, $VersionID_cmd, 'VersionID');
							$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
							
							if (isset($distri_name) && isset($VersionID)) {
								$distri_name = "Medion/Linux " . $VersionID . " (" . $distri_name . ")";
								log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][MEDION] Distribution :: ' . $distri_name);
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
						$distri_name_cmd = "cat /proc/sys/kernel/syno_hw_version 2>/dev/null";
					} else {
						$distri_name_cmd = "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null";
					}
					$VersionID_cmd = "awk -F'=' '/productversion/ {print $2}' /etc.defaults/VERSION 2>/dev/null | -v ORS=\"\" awk '{ gsub(/\"/, \"\"); print }'";
					$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep 'vg1000\|volume1' | head -1 | awk '{ print $2,$3,$5 }'";
				} else {
					$ARMv_cmd = "LC_ALL=C lscpu 2>/dev/null | awk -F':' '/Architecture/ { print $2 }' | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
					$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
	
					$ARMv = $this->execSRV($ARMv_cmd, 'ARMv');
					$bitdistri = $this->execSRV($bitdistri_cmd, 'BitDistri');
	
					$distri_name_cmd ="awk -F'=' '/^PRETTY_NAME/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
					$VersionID_cmd = "awk -F'=' '/VERSION_ID/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
					$hdd_cmd = "LC_ALL=C df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
				}
	
				$uptime_cmd = "awk '{ print $1 }' /proc/uptime 2>/dev/null | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
				$loadavg_cmd = "cat /proc/loadavg 2>/dev/null";
				$memory_cmd = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$7 }'";
				$swap_cmd = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
	
				$ReseauRXTX_cmd = "cat /proc/net/dev 2>/dev/null | grep ".$cartereseau." | awk '{print $1,$2,$10}' | awk -v ORS=\"\" '{ gsub(/:/, \"\"); print }'"; // on récupère le nom de la carte en plus pour l'afficher dans les infos
				$ReseauIP_cmd = "ip -o -f inet a 2>/dev/null | grep ".$cartereseau." | awk '{ print $4 }' | awk -v ORS=\"\" '{ gsub(/\/[0-9]+/, \"\"); print }'";
				
				$distri_name = $this->execSRV($distri_name_cmd, 'DistriName');
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
	
				if (trim($perso1_cmd) !== '') {
					// log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] Perso1 Cmd :: ' . str_replace("\r\n", "\\r\\n", $perso1_cmd));
					$perso1 = $this->execSRV($perso1_cmd, 'Perso1');
				}
				if (trim($perso2_cmd) !== '') {
					// log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] Perso2 Cmd :: ' . str_replace("\r\n", "\\r\\n", $perso2_cmd));
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
	
							if (isset($distri_name) && isset($versionsyno_TXT)) {
								$distri_name = trim($distri_name);
								$distri_name = $versionsyno_TXT.' ('.$distri_name.')';
							}
						}
					} else {
						if (isset($distri_name) && isset($bitdistri) && isset($ARMv)) {
							$distri_name = $distri_name . ' ' . $bitdistri . 'bits (' . $ARMv . ')';
						}
					}
					
					// Syno Volume 2
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1') {
						if (isset($hddv2)) {
							$hdddatav2 = explode(' ', $hddv2);
							if (isset($hdddatav2[0]) && isset($hdddatav2[1]) && isset($hdddatav2[2])) {
								$syno_hddv2_total = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatav2[0]);
								$syno_hddv2_used = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatav2[1]);
								$syno_hddv2_used_percent = preg_replace("/[^0-9.]/", "", $hdddatav2[2]);
								$syno_hddv2_used_percent = trim($syno_hddv2_used_percent);
							} else {
								$syno_hddv2_total = '';
								$syno_hddv2_used = '';
								$syno_hddv2_used_percent = '';
							}
						}
					}
	
					// Syno Volume USB 
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1') {
						if (isset($hddusb)) {
							$hdddatausb = explode(' ', $hddusb);
							if (isset($hdddatausb[0]) && isset($hdddatausb[1]) && isset($hdddatausb[2])) {
								$syno_hddusb_total = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatausb[0]);
								$syno_hddusb_used = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatausb[1]);
								$syno_hddusb_used_percent = preg_replace("/[^0-9.]/", "", $hdddatausb[2]);
								$syno_hddusb_used_percent = trim($syno_hddusb_used_percent);
							} else {
								$syno_hddusb_total = '';
								$syno_hddusb_used = '';
								$syno_hddusb_used_percent = '';
							}
						}
					}
	
					// Syno Volume eSATA 
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1') {
						if (isset($hddesata)) {
							$hdddataesata = explode(' ', $hddesata);
							if (isset($hdddataesata[0]) && isset($hdddataesata[1]) && isset($hdddataesata[2])) {
								$syno_hddesata_total = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddataesata[0]);
								$syno_hddesata_used = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddataesata[1]);
								$syno_hddesata_used_percent = preg_replace("/[^0-9.]/", "", $hdddataesata[2]);
								$syno_hddesata_used_percent = trim($syno_hddesata_used_percent);
							} else {
								$syno_hddesata_total = '';
								$syno_hddesata_used = '';
								$syno_hddesata_used_percent = '';
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
							$load_avg_1mn = $loadavg[0];
							$load_avg_5mn = $loadavg[1];
							$load_avg_15mn = $loadavg[2];
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
									$memorylibre_percent = round(intval($memorylibre) / intval($memory[0]) * 100);
								} else {
									$memorylibre_percent = 0;
								}
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Free % :: ' . $memorylibre_percent);
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
								$memory = 'Total : '.$memtotal.' - Libre : '.$memorylibre;
							}
						} elseif (preg_match("#FreeBSD#", $uname)) {
							$memory = explode(' ', $memory);
							if (isset($memory[0]) && isset($memory[1])) {
								if (intval($memory[0]) != 0) {
									$memorylibre_percent = round(intval($memory[1]) / intval($memory[0]) * 100);
								} else {
									$memorylibre_percent = 0;
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
							$memory = 'Total : '.$memtotal.' - Libre : '.$memorylibre;
						}
					} else {
						$memory = '';
					}
	
					if (isset($swap)) {
						$swap = explode(' ', $swap);
	
						if (isset($swap[0]) && isset($swap[2])) {
							if (intval($swap[0]) != 0) {
								$swaplibre_percent = round(intval($swap[2]) / intval($swap[0]) * 100);
							} else {
								$swaplibre_percent = 0;
							}
							log::add('Monitoring', 'debug', '['. $equipement .'][SWAP] Swap Free % :: ' . $swaplibre_percent);
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
							$network = 'TX : '.$ReseauTX.' - RX : '.$ReseauRX;
							$network_name = $ReseauRXTX[0];
							
							if (isset($ReseauIP)) {
								$network_ip = $ReseauIP;
							} else {
								$network_ip = '';
							}
							
							log::add('Monitoring', 'debug', '['. $equipement .'][RESEAU] Nom de la carte réseau / IP (RX / TX) :: ' .$network_name.' / IP= ' . $network_ip . ' (RX= '.$ReseauRX.' / TX= '.$ReseauTX.')');
						} else {
							log::add('Monitoring', 'error', '['. $equipement .'][RESEAU] Carte Réseau NON détectée :: KO');
						}
					}
	
					$hdd_total = '';
					$hdd_used = '';
					$hdd_used_percent = '';
					if (isset($hdd)) {
						$hdddata = explode(' ', $hdd);
						if (isset($hdddata[0]) && isset($hdddata[1]) && isset($hdddata[2])) {
							$hdd_total = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddata[0]);
							$hdd_used = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddata[1]);
							$hdd_used_percent = preg_replace("/[^0-9.]/", "", $hdddata[2]);
							$hdd_used_percent = trim($hdd_used_percent);
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
							if (preg_match("#RasPlex|OpenELEC|osmc|LibreELEC#", $distri_name) || preg_match("#piCorePlayer#", $uname) || preg_match("#medion#", $uname)) {
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
					if (!isset($cputemp0)) {$cputemp0 = '';}
					if (!isset($perso1)) {$perso1 = '';}
					if (!isset($perso2)) {$perso2 = '';}
					if (!isset($cnx_ssh)) {$cnx_ssh = '';}
					if (!isset($uname)) {$uname = 'Inconnu';}
					if (!isset($memory)) {$memory = '';}
					if (!isset($memorylibre_percent)) {$memorylibre_percent = '0';}
					if (!isset($Memswap)) {$Memswap = '';}
					if (!isset($swaplibre_percent)) {$swaplibre_percent = '0';}
					# TODO ajouter les commandes type syno ou temp
	
					$dataresult = array(
						'distri_name' => $distri_name,
						'uptime' => $uptime,
						'load_avg_1mn' => $load_avg_1mn,
						'load_avg_5mn' => $load_avg_5mn,
						'load_avg_15mn' => $load_avg_15mn,
						'memory' => $memory,
						'network' => $network,
						'network_name' => $network_name,
						'network_ip' => $network_ip,
						'hdd_total' => $hdd_total,
						'hdd_used' => $hdd_used,
						'hdd_used_percent' => $hdd_used_percent,
						'cpu' => $cpu,
						'cpu_temp' => $cputemp0,
						'cnx_ssh' => $cnx_ssh,
						'swap' => $Memswap,
						'memory_free_percent' => $memorylibre_percent,
						'swap_free_percent' => $swaplibre_percent,
						'perso1' => $perso1,
						'perso2' => $perso2,
					);
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1') {
						$dataresultv2 = array(
							'syno_hddv2_total' => $syno_hddv2_total,
							'syno_hddv2_used' => $syno_hddv2_used,
							'syno_hddv2_used_percent' => $syno_hddv2_used_percent,
						);
					}
	
					// Syno Volume USB
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1') {
						$dataresultusb = array(
							'syno_hddusb_total' => $syno_hddusb_total,
							'syno_hddusb_used' => $syno_hddusb_used,
							'syno_hddusb_used_percent' => $syno_hddusb_used_percent,
						);
					}
	
					// Syno Volume eSATA
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1') {
						$dataresultesata = array(
							'syno_hddesata_total' => $syno_hddesata_total,
							'syno_hddesata_used' => $syno_hddesata_used,
							'syno_hddesata_used_percent' => $syno_hddesata_used_percent,
						);
					}
	
					// Event sur les commandes après récupération des données
					$cnx_ssh = $this->getCmd(null,'cnx_ssh');
					if (is_object($cnx_ssh)) {
						$cnx_ssh->event($dataresult['cnx_ssh']);
					}
	
					$distri_name = $this->getCmd(null,'distri_name');
					if (is_object($distri_name)) {
						$distri_name->event($dataresult['distri_name']);
					}
	
					$uptime = $this->getCmd(null,'uptime');
					if (is_object($uptime)) {
						$uptime->event($dataresult['uptime']);
					}
	
					$load_avg_1mn = $this->getCmd(null,'load_avg_1mn');
					if (is_object($load_avg_1mn)) {
						$load_avg_1mn->event($dataresult['load_avg_1mn']);
					}
	
					$load_avg_5mn = $this->getCmd(null,'load_avg_5mn');
					if (is_object($load_avg_5mn)) {
						$load_avg_5mn->event($dataresult['load_avg_5mn']);
					}
	
					$load_avg_15mn = $this->getCmd(null,'load_avg_15mn');
					if (is_object($load_avg_15mn)) {
						$load_avg_15mn->event($dataresult['load_avg_15mn']);
					}
	
					$memory = $this->getCmd(null,'memory');
					if (is_object($memory)) {
						$memory->event($dataresult['memory']);
					}
	
					$memory_free_percent = $this->getCmd(null,'memory_free_percent');
					if (is_object($memory_free_percent)) {
						$memory_free_percent->event($dataresult['memory_free_percent']);
					}
	
					$swap = $this->getCmd(null,'swap');
					if (is_object($swap)) {
						$swap->event($dataresult['swap']);
					}
	
					$swap_free_percent = $this->getCmd(null,'swap_free_percent');
					if (is_object($swap_free_percent)) {
						$swap_free_percent->event($dataresult['swap_free_percent']);
					}
	
					$network = $this->getCmd(null,'network');
					if (is_object($network)) {
						$network->event($dataresult['network']);
					}
	
					$network_name = $this->getCmd(null,'network_name');
					if (is_object($network_name)) {
						$network_name->event($dataresult['network_name']);
					}
	
					$network_ip = $this->getCmd(null,'network_ip');
					if (is_object($network_ip)) {
						$network_ip->event($dataresult['network_ip']);
					}
	
					$hdd_total = $this->getCmd(null,'hdd_total');
					if (is_object($hdd_total)) {
						$hdd_total->event($dataresult['hdd_total']);
					}
	
					$hdd_used = $this->getCmd(null,'hdd_used');
					if (is_object($hdd_used)) {
						$hdd_used->event($dataresult['hdd_used']);
					}
	
					$hdd_used_percent = $this->getCmd(null,'hdd_used_percent');
					if (is_object($hdd_used_percent)) {
						$hdd_used_percent->event($dataresult['hdd_used_percent']);
					}
	
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1') {
						$syno_hddv2_total = $this->getCmd(null,'syno_hddv2_total');
						if (is_object($syno_hddv2_total)) {
							$syno_hddv2_total->event($dataresultv2['syno_hddv2_total']);
						}
						$syno_hddv2_used = $this->getCmd(null,'syno_hddv2_used');
						if (is_object($syno_hddv2_used)) {
							$syno_hddv2_used->event($dataresultv2['syno_hddv2_used']);
						}
						$syno_hddv2_used_percent = $this->getCmd(null,'syno_hddv2_used_percent');
						if (is_object($syno_hddv2_used_percent)) {
							$syno_hddv2_used_percent->event($dataresultv2['syno_hddv2_used_percent']);
						}
					}
	
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1') {
						$syno_hddusb_total = $this->getCmd(null,'syno_hddusb_total');
						if (is_object($syno_hddusb_total)) {
							$syno_hddusb_total->event($dataresultusb['syno_hddusb_total']);
						}
						$syno_hddusb_used = $this->getCmd(null,'syno_hddusb_used');
						if (is_object($syno_hddusb_used)) {
							$syno_hddusb_used->event($dataresultusb['syno_hddusb_used']);
						}
						$syno_hddusb_used_percent = $this->getCmd(null,'syno_hddusb_used_percent');
						if (is_object($syno_hddusb_used_percent)) {
							$syno_hddusb_used_percent->event($dataresultusb['syno_hddusb_used_percent']);
						}
					}
	
					if ($this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1') {
						$syno_hddesata_total = $this->getCmd(null,'syno_hddesata_total');
						if (is_object($syno_hddesata_total)) {
							$syno_hddesata_total->event($dataresultesata['syno_hddesata_total']);
						}
						$syno_hddesata_used = $this->getCmd(null,'syno_hddesata_used');
						if (is_object($syno_hddesata_used)) {
							$syno_hddesata_used->event($dataresultesata['syno_hddesata_used']);
						}
						$syno_hddesata_used_percent = $this->getCmd(null,'syno_hddesata_used_percent');
						if (is_object($syno_hddesata_used_percent)) {
							$syno_hddesata_used_percent->event($dataresultesata['syno_hddesata_used_percent']);
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
						'distri_name' => 'Connexion SSH KO',
						'cnx_ssh' => $cnx_ssh
					);
					$distri_name = $this->getCmd(null,'distri_name');
					if (is_object($distri_name)) {
						$distri_name->event($dataresult['distri_name']);
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
			} else {
				log::add('Monitoring', 'error', '['. $equipement .'][SSH] Reboot/Shutdown :: Connection SSH KO');
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
