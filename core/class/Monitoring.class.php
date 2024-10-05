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
		$orderCmd = 1;

		$MonitoringCmd = $this->getCmd(null, 'cnx_ssh');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('SSH Status', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cnx_ssh');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}
		
		$MonitoringCmd = $this->getCmd(null, 'reboot');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Reboot', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('reboot');
			$MonitoringCmd->setType('action');
			$MonitoringCmd->setSubType('other');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'poweroff');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('PowerOff', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('poweroff');
			$MonitoringCmd->setType('action');
			$MonitoringCmd->setSubType('other');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'uptime_sec');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Uptime (Sec)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('uptime_sec');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('sec');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'load_avg_1mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 1 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('load_avg_1mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'load_avg_5mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 5 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('load_avg_5mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}
		$MonitoringCmd = $this->getCmd(null, 'load_avg_15mn');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Charge Système 15 min', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('load_avg_15mn');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_total');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Totale', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_total');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_used');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Utilisée', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_used');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_free');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Libre', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_free');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		

		$MonitoringCmd = $this->getCmd(null, 'memory_buffcache');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire BuffCache', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_buffcache');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_available');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Disponible', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_available');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_used_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Utilisée (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_used_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('%');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'memory_free_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Libre (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_free_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('%');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}
		
		$MonitoringCmd = $this->getCmd(null, 'memory_available_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Mémoire Disponible (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('memory_available_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('%');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_total');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Total', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_total');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_used');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Utilisé', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_used');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_free');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Libre', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_free');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_used_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Utilisé (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_used_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('%');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'swap_free_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Swap Libre (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('swap_free_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('%');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'network_tx');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Réseau (TX)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_tx');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('octets');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'network_rx');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Réseau (RX)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_rx');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('octets');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'network_name');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Carte Réseau', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_name');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'network_ip');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Adresse IP', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_ip');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_total');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Total', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_total');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_used');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Utilisé', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_used');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_free');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Libre', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_free');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('Ko');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_used_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Utilisé (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_used_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('%');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'hdd_free_percent');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Espace Disque Libre (Pourcent)', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('hdd_free_percent');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('%');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_used_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv2_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 2 Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv2_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
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
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddusb_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume USB Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddusb_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
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
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Ko');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_used_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddesata_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume eSATA Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddesata_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'cpu_temp');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Température CPU', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cpu_temp');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('°C');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'cpu_nb');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Nb CPU', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cpu_nb');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'cpu_freq');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Fréquence CPU', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cpu_freq');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('numeric');
			$MonitoringCmd->setUnite('MHz');
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
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
		try {
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
		} catch (Exception $e) {
			log::add('Monitoring', 'error', '[' . $this->getName() . '][getStats] ' . $e->getMessage());
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

		// Distribution Linux
		$distri_name = $this->getCmd(null,'distri_name');
		$replace['#distri_name_icon#'] = is_object($distri_name) ? (!empty($distri_name->getDisplay('icon')) ? $distri_name->getDisplay('icon') : '<i class="fab fa-linux"></i>') : '';
		$replace['#distri_name#'] = is_object($distri_name) ? $distri_name->execCmd() : '';
		$replace['#distri_name_id#'] = is_object($distri_name) ? $distri_name->getId() : '';
		$replace['#distri_name_display#'] = (is_object($distri_name) && $distri_name->getIsVisible()) ? "block" : "none";
		$replace['#distri_name_collect#'] = (is_object($distri_name) && $distri_name->getIsVisible()) ? $distri_name->getCollectDate() : "-";
        $replace['#distri_name_value#'] = (is_object($distri_name) && $distri_name->getIsVisible()) ? $distri_name->getValueDate() : "-";

		// Load Average
		$load_avg = $this->getCmd(null,'load_avg');
		$replace['#load_avg_icon#'] = is_object($load_avg) ? (!empty($load_avg->getDisplay('icon')) ? $load_avg->getDisplay('icon') : '<i class="fas fa-chart-line"></i>') : '';
		$replace['#load_avg_display#'] = (is_object($load_avg) && $load_avg->getIsVisible()) ? "block" : "none";
		$replace['#load_avg_collect#'] = (is_object($load_avg) && $load_avg->getIsVisible()) ? $load_avg->getCollectDate() : "-";
        $replace['#load_avg_value#'] = (is_object($load_avg) && $load_avg->getIsVisible()) ? $load_avg->getValueDate() : "-";
		$replace['#load_avg_id#'] = is_object($load_avg) ? $load_avg->getId() : '';

		$load_avg_1mn = $this->getCmd(null,'load_avg_1mn');
		$replace['#load_avg_1mn#'] = is_object($load_avg_1mn) ? $load_avg_1mn->execCmd() : '';
		$replace['#load_avg_1mn_id#'] = is_object($load_avg_1mn) ? $load_avg_1mn->getId() : '';

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

		// Uptime
		$uptime = $this->getCmd(null,'uptime');
		$replace['#uptime_icon#'] = is_object($uptime) ? (!empty($uptime->getDisplay('icon')) ? $uptime->getDisplay('icon') : '<i class="fas fa-hourglass-half"></i>') : '';
		$replace['#uptime#'] = is_object($uptime) ? $uptime->execCmd() : '';
		$replace['#uptime_id#'] = is_object($uptime) ? $uptime->getId() : '';
		$replace['#uptime_display#'] = (is_object($uptime) && $uptime->getIsVisible()) ? "block" : "none";
		$replace['#uptime_collect#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getCollectDate() : "-";
        $replace['#uptime_value#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getValueDate() : "-";
		
		// HDD
		$hdd = $this->getCmd(null,'hdd');
		$replace['#hdd_icon#'] = is_object($hdd) ? (!empty($hdd->getDisplay('icon')) ? $hdd->getDisplay('icon') : '<i class="fas fa-hdd"></i>') : '';
		$replace['#hdd_display#'] = (is_object($hdd) && $hdd->getIsVisible()) ? "block" : "none";
		$replace['#hdd_collect#'] = (is_object($hdd) && $hdd->getIsVisible()) ? $hdd->getCollectDate() : "-";
        $replace['#hdd_value#'] = (is_object($hdd) && $hdd->getIsVisible()) ? $hdd->getValueDate() : "-";
		$replace['#hdd_id#'] = is_object($hdd) ? $hdd->getId() : '';
		$replace['#hdd#'] = is_object($hdd) ? $hdd->execCmd() : '';

		$hdd_used_percent = $this->getCmd(null,'hdd_used_percent');
		$replace['#hdd_used_percent#'] = is_object($hdd_used_percent) ? $hdd_used_percent->execCmd() : '';
		$replace['#hdd_used_percent_id#'] = is_object($hdd_used_percent) ? $hdd_used_percent->getId() : '';
		
		$replace['#hdd_used_percent_colorlow#'] = $this->getConfiguration('hdd_used_percent_colorlow');
		$replace['#hdd_used_percent_colorhigh#'] = $this->getConfiguration('hdd_used_percent_colorhigh');

		$this->getStats($hdd_used_percent, 'hdd_used_percent', $replace, 0);
		
		// Mémoire
		$memory = $this->getCmd(null,'memory');
		$replace['#memory_icon#'] = is_object($memory) ? (!empty($memory->getDisplay('icon')) ? $memory->getDisplay('icon') : '<i class="fas fa-database"></i>') : '';
		$replace['#memory#'] = is_object($memory) ? $memory->execCmd() : '';
		$replace['#memory_id#'] = is_object($memory) ? $memory->getId() : '';
		$replace['#memory_display#'] = (is_object($memory) && $memory->getIsVisible()) ? "block" : "none";
		$replace['#memory_collect#'] = (is_object($memory) && $memory->getIsVisible()) ? $memory->getCollectDate() : "-";
        $replace['#memory_value#'] = (is_object($memory) && $memory->getIsVisible()) ? $memory->getValueDate() : "-";

		$memory_available_percent = $this->getCmd(null,'memory_available_percent');
		$replace['#memory_available_percent#'] = is_object($memory_available_percent) ? $memory_available_percent->execCmd() : '';
		$replace['#memory_available_percent_id#'] = is_object($memory_available_percent) ? $memory_available_percent->getId() : '';
		
		$replace['#memory_available_percent_colorhigh#'] = $this->getConfiguration('memory_available_percent_colorhigh');
		$replace['#memory_available_percent_colorlow#'] = $this->getConfiguration('memory_available_percent_colorlow');

		$this->getStats($memory_available_percent, 'memory_available_percent', $replace, 0);

		// Swap
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

		// Réseau
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

		// CPU
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
		$SynoV2Visible = (is_object($this->getCmd(null,'syno_hddv2')) && $this->getCmd(null,'syno_hddv2')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoV2Visible == 'OK' && $this->getConfiguration('synologyv2') == '1') {
			$syno_hddv2 = $this->getCmd(null,'syno_hddv2');
			$replace['#syno_hddv2_icon#'] = is_object($syno_hddv2) ? (!empty($syno_hddv2->getDisplay('icon')) ? $syno_hddv2->getDisplay('icon') : '<i class="far fa-hdd"></i>') : '';
			$replace['#syno_hddv2_collect#'] = (is_object($syno_hddv2) && $syno_hddv2->getIsVisible()) ? $syno_hddv2->getCollectDate() : "-";
        	$replace['#syno_hddv2_value#'] = (is_object($syno_hddv2) && $syno_hddv2->getIsVisible()) ? $syno_hddv2->getValueDate() : "-";
			$replace['#syno_hddv2_display#'] = (is_object($syno_hddv2) && $syno_hddv2->getIsVisible()) ? "block" : "none";
			
			$replace['#synovolume2_display#'] = (is_object($syno_hddv2) && $syno_hddv2->getIsVisible()) ? 'OK' : '';

			$replace['#syno_hddv2#'] = is_object($syno_hddv2) ? $syno_hddv2->execCmd() : '';
			$replace['#syno_hddv2_id#'] = is_object($syno_hddv2) ? $syno_hddv2->getId() : '';

			$syno_hddv2_used_percent = $this->getCmd(null,'syno_hddv2_used_percent');
			$replace['#syno_hddv2_used_percent#'] = is_object($syno_hddv2_used_percent) ? $syno_hddv2_used_percent->execCmd() : '';
			$replace['#syno_hddv2_used_percent_id#'] = is_object($syno_hddv2_used_percent) ? $syno_hddv2_used_percent->getId() : '';
			$replace['#syno_hddv2_used_percent_colorlow#'] = $this->getConfiguration('syno_hddv2_used_percent_colorlow');
			$replace['#syno_hddv2_used_percent_colorhigh#'] = $this->getConfiguration('syno_hddv2_used_percent_colorhigh');

			$this->getStats($syno_hddv2_used_percent, 'syno_hddv2_used_percent', $replace, 0);
		}

		// Syno Volume USB
		$SynoUSBVisible = (is_object($this->getCmd(null,'syno_hddusb')) && $this->getCmd(null,'syno_hddusb')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoUSBVisible == 'OK' && $this->getConfiguration('synologyusb') == '1') {
			$syno_hddusb = $this->getCmd(null,'syno_hddusb');
			$replace['#syno_hddusb_icon#'] = is_object($syno_hddusb) ? (!empty($syno_hddusb->getDisplay('icon')) ? $syno_hddusb->getDisplay('icon') : '<i class="fab fa-usb"></i>') : '';
			$replace['#syno_hddusb_display#'] = (is_object($syno_hddusb) && $syno_hddusb->getIsVisible()) ? "block" : "none";
			$replace['#synovolumeusb_display#'] = (is_object($syno_hddusb) && $syno_hddusb->getIsVisible()) ? 'OK' : '';
			$replace['#syno_hddusb_collect#'] = (is_object($syno_hddusb) && $syno_hddusb->getIsVisible()) ? $syno_hddusb->getCollectDate() : "-";
        	$replace['#syno_hddusb_value#'] = (is_object($syno_hddusb) && $syno_hddusb->getIsVisible()) ? $syno_hddusb->getValueDate() : "-";

			$replace['#syno_hddusb#'] = is_object($syno_hddusb) ? $syno_hddusb->execCmd() : '';
			$replace['#syno_hddusb_id#'] = is_object($syno_hddusb) ? $syno_hddusb->getId() : '';

			$syno_hddusb_used_percent = $this->getCmd(null,'syno_hddusb_used_percent');
			$replace['#syno_hddusb_used_percent#'] = is_object($syno_hddusb_used_percent) ? $syno_hddusb_used_percent->execCmd() : '';
			$replace['#syno_hddusb_used_percent_id#'] = is_object($syno_hddusb_used_percent) ? $syno_hddusb_used_percent->getId() : '';
			
			$replace['#syno_hddusb_used_percent_colorlow#'] = $this->getConfiguration('syno_hddusb_used_percent_colorlow');
			$replace['#syno_hddusb_used_percent_colorhigh#'] = $this->getConfiguration('syno_hddusb_used_percent_colorhigh');

			$this->getStats($syno_hddusb_used_percent, 'syno_hddusb_used_percent', $replace, 0);
		}

		// Syno Volume eSATA
		$SynoeSATAVisible = (is_object($this->getCmd(null,'syno_hddesata')) && $this->getCmd(null,'syno_hddesata')->getIsVisible()) ? 'OK' : '';

		if ($this->getConfiguration('synology') == '1' && $SynoeSATAVisible == 'OK' && $this->getConfiguration('synologyesata') == '1') {
			$syno_hddesata = $this->getCmd(null,'syno_hddesata');
			$replace['#syno_hddesata_icon#'] = is_object($syno_hddesata) ? (!empty($syno_hddesata->getDisplay('icon')) ? $syno_hddesata->getDisplay('icon') : '<i class="fab fa-usb"></i>') : '';
			$replace['#syno_hddesata_display#'] = (is_object($syno_hddesata) && $syno_hddesata->getIsVisible()) ? "block" : "none";
			$replace['#synovolumeesata_display#'] = (is_object($syno_hddesata) && $syno_hddesata->getIsVisible()) ? 'OK' : '';
			$replace['#syno_hddesata_collect#'] = (is_object($syno_hddesata) && $syno_hddesata->getIsVisible()) ? $syno_hddesata->getCollectDate() : "-";
        	$replace['#syno_hddesata_value#'] = (is_object($syno_hddesata) && $syno_hddesata->getIsVisible()) ? $syno_hddesata->getValueDate() : "-";

			$replace['#syno_hddesata#'] = is_object($syno_hddesata) ? $syno_hddesata->execCmd() : '';
			$replace['#syno_hddesata_id#'] = is_object($syno_hddesata) ? $syno_hddesata->getId() : '';

			$syno_hddesata_used_percent = $this->getCmd(null,'syno_hddesata_used_percent');
			$replace['#syno_hddesata_used_percent#'] = is_object($syno_hddesata_used_percent) ? $syno_hddesata_used_percent->execCmd() : '';
			$replace['#syno_hddesata_used_percent_id#'] = is_object($syno_hddesata_used_percent) ? $syno_hddesata_used_percent->getId() : '';
			
			$replace['#syno_hddesata_used_percent_colorlow#'] = $this->getConfiguration('syno_hddesata_used_percent_colorlow');
			$replace['#syno_hddesata_used_percent_colorhigh#'] = $this->getConfiguration('syno_hddesata_used_percent_colorhigh');

			$this->getStats($syno_hddesata_used_percent, 'syno_hddesata_used_percent', $replace, 0);
		}

		// Perso1
		$perso1 = $this->getCmd(null,'perso1');
		$replace['#perso1#'] = is_object($perso1) ? $perso1->execCmd() : '';
		$replace['#perso1_icon#'] = is_object($perso1) ? (!empty($perso1->getDisplay('icon')) ? $perso1->getDisplay('icon') : '<i class="fas fa-question-circle"></i>') : '';
		$replace['#perso1_display#'] = (is_object($perso1) && $perso1->getIsVisible()) ? "block" : "none";
		$replace['#perso1_collect#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getCollectDate() : "-";
        $replace['#perso1_value#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getValueDate() : "-";

		$replace['#perso1_id#'] = is_object($perso1) ? $perso1->getId() : '';
		$replace['#perso1_name#'] = is_object($perso1) ? $perso1->getName() : '';

		$replace['#perso1_unite#'] = is_object($perso1) ? $this->getConfiguration('perso1_unite') : '';
		$replace['#perso1_colorlow#'] = $this->getConfiguration('perso1_colorlow');
		$replace['#perso1_colorhigh#'] = $this->getConfiguration('perso1_colorhigh');

		$this->getStats($perso1, 'perso1', $replace, 2);

		// Perso2
		$perso2 = $this->getCmd(null,'perso2');
		$replace['#perso2#'] = is_object($perso2) ? $perso2->execCmd() : '';
		$replace['#perso2_icon#'] = (is_object($perso2)) ? (!empty($perso2->getDisplay('icon')) ? $perso2->getDisplay('icon') : '<i class="fas fa-question-circle"></i>') : '';
		$replace['#perso2_display#'] = (is_object($perso2) && $perso2->getIsVisible()) ? "block" : "none";
		$replace['#perso2_collect#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getCollectDate() : "-";
        $replace['#perso2_value#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getValueDate() : "-";
		
		$replace['#perso2_id#'] = is_object($perso2) ? $perso2->getId() : '';
		$replace['#perso2_name#'] = (is_object($perso2)) ? $perso2->getName() : '';
		
		$replace['#perso2_unite#'] = is_object($perso2) ? $this->getConfiguration('perso2_unite') : '';
		$replace['#perso2_colorlow#'] = $this->getConfiguration('perso2_colorlow');
		$replace['#perso2_colorhigh#'] = $this->getConfiguration('perso2_colorhigh');

		$this->getStats($perso2, 'perso2', $replace, 2);

		foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
			$replace['#cmd_' . $cmd->getLogicalId() . '_display#'] = (is_object($cmd) && $cmd->getIsVisible()) ? "inline-block" : "none";
		}

		$html = template_replace($replace, getTemplate('core', $_version, 'Monitoring', 'Monitoring'));
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

	public function formatSize($size, string $start = 'o') {
		$units = array('o', 'Ko', 'Mo', 'Go', 'To');
		$unitIndex = ($unitIndex = array_search($start, $units)) === false ? 0 : $unitIndex;
		$size = intval($size);

		while ($size >= 1024 && $unitIndex < count($units) - 1) {
			$size /= 1024;
			$unitIndex++;
		}
		return round($size, 2) . ' ' . $units[$unitIndex];
	}

	public function formatUptime($uptime) {
		$uptimeNum = floatval($uptime);
		$days = sprintf('%0.0f', floor($uptimeNum / 86400));
		$hours = sprintf('%0.0f', floor(fmod($uptimeNum, 86400) / 3600));
		$minutes = sprintf('%0.0f', floor(fmod($uptimeNum, 3600) / 60));
		$seconds = sprintf('%0.2f', fmod($uptimeNum, 60));
		
		$uptimeFormated = '';
		if ($days != '0') {
			$uptimeFormated .= $days . ' jour' . ($days == '1' ? '' : 's') . (($hours != '0' || $minutes != '0' || $seconds != '0.00') ? ', ' : '');
		}
		if ($hours != '0') {
			$uptimeFormated .= $hours . 'h ';
		}
		if ($minutes != '0') {
			$uptimeFormated .= $minutes . 'min ';
		}
		if ($seconds != '0.00') {
			$uptimeFormated .= $seconds . 's';
		}
		return $uptimeFormated;
	}

	public function getInformations() {
		$equipement = $this->getName();
		try {
			$uname = "Inconnu";

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
						$bitdistri_cmd = "";
					}
					else {
						$distri_name_cmd = "awk -F'=' '/^PRETTY_NAME/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
	
						$VersionID_cmd = "awk -F'=' '/VERSION_ID/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
						$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
					}
	
					$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');
					$VersionID = $this->execSSH($hostId, $VersionID_cmd, 'VersionID');

					if (trim($bitdistri_cmd) !== '') {
						$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');
					} else {
						$bitdistri = '';
					}

					// ARMv Command
					$ARMv_cmd = "LC_ALL=C lscpu 2>/dev/null | awk -F':' '/Architecture/ { print $2 }' | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
					$ARMv = $this->execSSH($hostId, $ARMv_cmd, 'ARMv');

					// Uptime Command
					$uptime_cmd = "awk '{ print $1 }' /proc/uptime 2>/dev/null | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
					$uptime = $this->execSSH($hostId, $uptime_cmd, 'Uptime');
					
					// LoadAverage Command
					$loadavg_cmd = "cat /proc/loadavg 2>/dev/null";
					$loadav = $this->execSSH($hostId, $loadavg_cmd, 'LoadAverage');

					// Memory Command
					$memory_cmd = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$6,$7 }'";
					$memory = $this->execSSH($hostId, $memory_cmd, 'Memory');

					// Swap Command
					$swap_cmd = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
					$swap = $this->execSSH($hostId, $swap_cmd, 'Swap');

					// Network Command
					$ReseauRXTX_cmd = "cat /proc/net/dev 2>/dev/null | grep ".$cartereseau." | awk '{print $1,$2,$10}' | awk -v ORS=\"\" '{ gsub(/:/, \"\"); print }'";
					$ReseauRXTX = $this->execSSH($hostId, $ReseauRXTX_cmd, 'ReseauRXTX');

					$ReseauIP_cmd = "LC_ALL=C ip -o -f inet a 2>/dev/null | grep ".$cartereseau." | awk '{ print $4 }' | awk -v ORS=\"\" '{ gsub(/\/[0-9]+/, \"\"); print }'";
					$ReseauIP = $this->execSSH($hostId, $ReseauIP_cmd, 'ReseauIP');

					// Perso1 et Perso2 Commands
					$perso1_cmd = $this->getConfiguration('perso1');
					if (trim($perso1_cmd) !== '') {
						$perso1 = $this->execSSH($hostId, $perso1_cmd, 'Perso1');
					} else {
						$perso1 = '';
					}

					$perso2_cmd = $this->getConfiguration('perso2');
					if (trim($perso2_cmd) !== '') {
						$perso2 = $this->execSSH($hostId, $perso2_cmd, 'Perso2');
					} else {
						$perso2 = '';
					}
					
					if ($this->getConfiguration('synology') == '1') {
	
						// Synology NbCPU Command
						$nbcpuARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_core 2>/dev/null";
						$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
						
						// Synology CPUFreq Command
						$cpufreq0ARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_clock 2>/dev/null";
						$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq');
	
						// Synology HDD Command
						$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep 'vg1000\|volume1' | head -1 | awk '{ print $2,$3,$4,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
						// Synology Version Command
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
							$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp');
						} else {
							$cpu_temp = '';
						}
						
						// Synology HDDv2 Command
						if ($this->getConfiguration('synologyv2') == '1') {
							$hddv2cmd = "LC_ALL=C df -l 2>/dev/null | grep 'vg1001\|volume2' | head -1 | awk '{ print $2,$3,$4,$5 }'"; // DSM 5.x / 6.x / 7.x
							$hddv2 = $this->execSSH($hostId, $hddv2cmd, 'HDDv2');
						}
	
						// Synology HDDusb Command
						if ($this->getConfiguration('synologyusb') == '1') {
							$hddusbcmd = "LC_ALL=C df -l 2>/dev/null | grep 'usb1p1\|volumeUSB1' | head -1 | awk '{ print $2,$3,$4,$5 }'"; // DSM 5.x / 6.x / 7.x
							$hddusb = $this->execSSH($hostId, $hddusbcmd, 'HDDusb');
						}
						
						// Synology HDDesata Command
						if ($this->getConfiguration('synologyesata') == '1') {
							$hddesatacmd = "LC_ALL=C df -l 2>/dev/null | grep 'sdf1\|volumeSATA' | head -1 | awk '{ print $2,$3,$4,$5 }'"; // DSM 5.x / 6.x / 7.x
							$hddesata = $this->execSSH($hostId, $hddesatacmd, 'HDDesata');
						}
	
					} elseif ($ARMv == 'armv6l') {

						// ARMv6L NbCPU Command
						$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep 'CPU(s):' | awk '{ print $2 }'";
						$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
						
						// ARMv6L uname Command
						$uname = '.';
						
						// ARMv6L HDD Command
						$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$4,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
						// ARMv6L CPUFreq Command
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
						$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq-1');
	
						if ($cpu_freq == '') {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq-2');
						}
	
						// ARMv6L cputemp Command
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
								$cpu_temp = $this->execSSH($hostId, $cputemp0armv6l_cmd, 'CPUTemp');
							} else {
								$cpu_temp = '';
							}
						}
	
					} elseif ($ARMv == 'armv7l' || $ARMv == 'aarch64' || $ARMv == 'mips64') {
						
						// aarch64 NbCPU Command
						$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'";
						$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
						// aarch64 uname Command
						$uname = '.';
	
						// aarch64 CPUFreq Command
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
						$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq-1');
	
						if ($cpu_freq == '') {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq-2');
						}
	
						// aarch64 HDD Command
						$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$4,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
						// aarch64 cputemp Command
						$cputemp_cmd = $this->getCmd(null,'cpu_temp');
						if (is_object($cputemp_cmd)) {
							if ($this->getconfiguration('linux_use_temp_cmd')) {
								$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
								$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-1');
							} else {
								$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";    // OK RPi2
								$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-2');
								
								if ($cpu_temp == '') {
									$cputemp0_cmd = "cat /sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1_input 2>/dev/null"; // OK Banana Pi (Cubie surement un jour...)
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-3');
								}
								log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][AARCH64] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
							}							
						}

					} elseif ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386') {
						$cpu_temp ='';
						$uname = '.';
						
						// NbCPU Command
						$nbcpuVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print \$NF }'";
						$cpu_nb = $this->execSSH($hostId, $nbcpuVM_cmd, 'NbCPU');
						$cpu_nb = preg_replace("/[^0-9]/", "", $cpu_nb);
						log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][X86] NbCPU :: ' . $cpu_nb);
	
						// HDD Command
						$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$4,$5 }'";
						$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
						
						// CPUFreq Command
						$cpufreqVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep -Ei '^CPU( max)? MHz' | awk '{ print \$NF }'";    // OK pour LXC Linux, Proxmox, Debian 10/11
						$cpu_freq = $this->execSSH($hostId, $cpufreqVM_cmd, 'CPUFreq-1');
						
						if ($cpu_freq == '') {
							$cpufreqVMbis_cmd = "cat /proc/cpuinfo 2>/dev/null | grep -i '^cpu MHz' | head -1 | cut -d':' -f2 | awk '{ print \$NF }'";    // OK pour Debian 10,11,12, Ubuntu 22.04, pve-debian12
							$cpu_freq = $this->execSSH($hostId, $cpufreqVMbis_cmd, 'CPUFreq-2');
						}
						$cpu_freq = preg_replace("/[^0-9.,]/", "", $cpu_freq);
						log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][X86] CPUFreq :: ' . $cpu_freq);
	
						// cputemp Command
						$cputemp_cmd = $this->getCmd(null,'cpu_temp');
						if (is_object($cputemp_cmd)) {
							if ($this->getconfiguration('linux_use_temp_cmd')) {
								$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
								log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][X86] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
								if ($cputemp0_cmd != '') {
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-1');
								} else {
									$cpu_temp = '';
								}
							} else {
								$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone0/temp 2>/dev/null";	// Default
								log::add('Monitoring','debug', '['. $equipement .'][SSH-CMD][X86] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
								$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-2');
								
								if ($cpu_temp == '') {
									$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone1/temp 2>/dev/null"; // Default Zone 1
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-3');
								}
								if ($cpu_temp == '') {
									$cputemp0_cmd = "cat /sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input 2>/dev/null";	// OK AOpen DE2700
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-4');
								}
								if ($cpu_temp == '') {
									$cputemp0AMD_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"; // OK Search temp?_input
									$cpu_temp = $this->execSSH($hostId, $cputemp0AMD_cmd, 'CPUTemp-5');
								}
								if ($cpu_temp == '') {
									$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"Package\")) {printf(\"%f\",$4);} }'"; // OK by sensors
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-6');
								}
								if ($cpu_temp == '') {
									$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")) {printf(\"%f\",$3);} }'"; // OK by sensors
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp-7');
								}
							}
						}

					} elseif ($ARMv == '' && $this->getConfiguration('synology') != '1') {

						// Uname Command
						$unamecmd = "uname -a 2>/dev/null | awk '{print $2,$1}'";
						$uname = $this->execSSH($hostId, $unamecmd, 'uname');
	
						if (preg_match("#RasPlex|OpenELEC|LibreELEC#", $distri_name)) {
							$bitdistri = '32';
							$ARMv = 'arm';
	
							$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
							$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][ARM] NbCPU :: ' . $cpu_nb);
							
							// HDD Command
							$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/dev/mmcblk0p2' | head -1 | awk '{ print $2,$3,$4,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
							// CPUFreq Command
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq');
	
							// cputemp Command
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
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp');
								} else {
									$cpu_temp = '';
								}
							}

						} elseif (preg_match("#osmc#", $distri_name)) {
							 
							$bitdistri = '32';
							$ARMv = 'arm';
	
							// NbCPU Command
							$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
							$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							// HDD Command
							$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$4,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
							
							// CPUFreq Command
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq');
	
							// cputemp Command
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
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp');
								} else {
									$cpu_temp = '';
								}
							}

						} elseif (preg_match("#piCorePlayer#", $uname)) {

							$bitdistri = '32';
							$ARMv = 'arm';
							
							// DistriName Command
							$distri_name_cmd = "uname -a 2>/dev/null | awk '{print $2,$3}'";
							$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');
	
							// NbCPU Command
							$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
							$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							// HDD Command
							$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep /dev/mmcblk0p | head -1 | awk '{print $2,$3,$4,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
	
							// CPUFreq Command
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
							$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq');
	
							// cputemp Command
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
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp');
								} else {
									$cpu_temp = '';
								}
							}

						} elseif (preg_match("#FreeBSD#", $uname)) {
							
							// DistriName Command
							$distri_name_cmd = "uname -a 2>/dev/null | awk '{ print $1,$3}'";
							$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');
	
							// ARMv Command
							$ARMv_cmd = "sysctl hw.machine | awk '{ print $2}'";
							$ARMv = $this->execSSH($hostId, $ARMv_cmd, 'ARMv');
	
							// LoadAverage Command
							$loadavg_cmd = "LC_ALL=C uptime | awk '{print $8,$9,$10}'";
							$loadav = $this->execSSH($hostId, $loadavg_cmd, 'LoadAverage');
	
							// Memory Command
							$memory_cmd = "dmesg | grep Mem | tr '\n' ' ' | awk '{print $4,$10}'";
							$memory = $this->execSSH($hostId, $memory_cmd, 'Memory');
	
							// BitDistri Command
							$bitdistri_cmd = "sysctl kern.smp.maxcpus | awk '{ print $2}'";
							$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');
	
							// NbCPU Command
							$nbcpuARM_cmd = "sysctl hw.ncpu | awk '{ print $2}'";
							$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
							
							// HDD Command
							$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$4,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
							
							// CPUFreq Command
							$cpufreq0ARM_cmd = "sysctl -a | egrep -E 'cpu.0.freq' | awk '{ print $2}'";
							$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq');
	
							// cputemp Command
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
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp');
								} else {
									$cpu_temp = '';
								}
							}
						}

						elseif (preg_match("#medion#", $uname)) {

							$cpu_nb = '';
							$cpu_freq = '';
							$cpu_temp = '';
	
							$ARMv = "arm";
	
							// DistriName Command
							$distri_name_cmd = "cat /etc/*-release 2>/dev/null | awk '/^DistName/ { print $2 }'";
							$distri_name = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');

							$VersionID_cmd = "cat /etc/*-release 2>/dev/null | awk '/^VersionName/ { print $2 }'";
							$VersionID = $this->execSSH($hostId, $VersionID_cmd, 'VersionID');

							$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
							$bitdistri = $this->execSSH($hostId, $bitdistri_cmd, 'BitDistri');

							$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/home$' | head -1 | awk '{ print $2,$3,$4,$5 }'";
							$hdd = $this->execSSH($hostId, $hdd_cmd, 'HDD');
							
							if (isset($distri_name) && isset($VersionID)) {
								$distri_name = "Medion/Linux " . $VersionID . " (" . $distri_name . ")";
								log::add('Monitoring', 'debug', '['. $equipement .'][SSH-CMD][MEDION] Distribution :: ' . $distri_name);
							}
	
							// NbCPU Command
							$nbcpuARM_cmd = "cat /proc/cpuinfo 2>/dev/null | awk -F':' '/^Processor/ { print $2}'";
							$cpu_nb = $this->execSSH($hostId, $nbcpuARM_cmd, 'NbCPU');
	
							// CPUFreq Command
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
							$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq-1');
							
							if ($cpu_freq == '') {
								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
								$cpu_freq = $this->execSSH($hostId, $cpufreq0ARM_cmd, 'CPUFreq-2');
							}
	
							// cputemp Command
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
									$cpu_temp = $this->execSSH($hostId, $cputemp0_cmd, 'CPUTemp');
								} else {
									$cpu_temp = '';
								}
							}
						}
					}
				}
			}
			elseif ($this->getConfiguration('localoudistant') == 'local' && $this->getIsEnable()) {
				$cnx_ssh = 'No';
				
				if ($this->getConfiguration('synology') == '1') {
					
					// Syno no ARMv and no BitDistri
					$ARMv = '';
					$bitdistri = '';

					if ($this->getConfiguration('syno_alt_name') == '1') {
						$distri_name_cmd = "cat /proc/sys/kernel/syno_hw_version 2>/dev/null";
					} else {
						$distri_name_cmd = "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null";
					}
					$VersionID_cmd = "awk -F'=' '/productversion/ {print $2}' /etc.defaults/VERSION 2>/dev/null | -v ORS=\"\" awk '{ gsub(/\"/, \"\"); print }'";
					$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep 'vg1000\|volume1' | head -1 | awk '{ print $2,$3,$4,$5 }'";
					
				} else {
					// ARMv Command
					$ARMv_cmd = "LC_ALL=C lscpu 2>/dev/null | awk -F':' '/Architecture/ { print $2 }' | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
					$ARMv = $this->execSRV($ARMv_cmd, 'ARMv');
					
					// BitDistri Command
					$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
					$bitdistri = $this->execSRV($bitdistri_cmd, 'BitDistri');
	
					// DitriName Command
					$distri_name_cmd ="awk -F'=' '/^PRETTY_NAME/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";

					// VersionID Command
					$VersionID_cmd = "awk -F'=' '/VERSION_ID/ {print $2}' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";

					// HDD Command
					$hdd_cmd = "LC_ALL=C df -l 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$4,$5 }'";
				}
	
				$distri_name = $this->execSRV($distri_name_cmd, 'DistriName');
				$VersionID = $this->execSRV($VersionID_cmd, 'VersionID');
				$hdd = $this->execSRV($hdd_cmd, 'HDD');

				// UpTime Command
				$uptime_cmd = "awk '{ print $1 }' /proc/uptime 2>/dev/null | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
				$uptime = $this->execSRV($uptime_cmd, 'Uptime');

				// LoadAverage Command
				$loadavg_cmd = "cat /proc/loadavg 2>/dev/null";
				$loadav = $this->execSRV($loadavg_cmd, 'LoadAverage');

				// Memory Command
				$memory_cmd = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$6,$7 }'";
				$memory = $this->execSRV($memory_cmd, 'Memory');

				// Swap Command
				$swap_cmd = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
				$swap = $this->execSRV($swap_cmd, 'Swap');

				// Network Command
				$ReseauRXTX_cmd = "cat /proc/net/dev 2>/dev/null | grep ".$cartereseau." | awk '{print $1,$2,$10}' | awk -v ORS=\"\" '{ gsub(/:/, \"\"); print }'"; // on récupère le nom de la carte en plus pour l'afficher dans les infos
				$ReseauRXTX = $this->execSRV($ReseauRXTX_cmd, 'ReseauRXTX');

				$ReseauIP_cmd = "ip -o -f inet a 2>/dev/null | grep ".$cartereseau." | awk '{ print $4 }' | awk -v ORS=\"\" '{ gsub(/\/[0-9]+/, \"\"); print }'";
				$ReseauIP = $this->execSRV($ReseauIP_cmd, 'ReseauIP');
				
				// Perso1 Command
				$perso1_cmd = $this->getConfiguration('perso1');
				if (trim($perso1_cmd) !== '') {
					$perso1 = $this->execSRV($perso1_cmd, 'Perso1');
				}

				// Perso2 Command
				$perso2_cmd = $this->getConfiguration('perso2');
				if (trim($perso2_cmd) !== '') {
					$perso2 = $this->execSRV($perso2_cmd, 'Perso2');
				}
	
				if ($this->getConfiguration('synology') == '1') {
					$uname = '.';
					
					// Synology NbCPU Command
					$nbcpuARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_core 2>/dev/null";
					$cpu_nb = $this->execSRV($nbcpuARM_cmd, 'NbCPU');

					// Synology CPUFreq Command
					$cpufreq0ARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_clock 2>/dev/null";
					$cpu_freq = $this->execSRV($cpufreq0ARM_cmd, 'CPUFreq');

					// Synology Version Command
					$versionsyno_cmd = "cat /etc.defaults/VERSION 2>/dev/null | awk '{ gsub(/\"/, \"\"); print }' | awk NF=NF RS='\r\n' OFS='&'"; // Récupération de tout le fichier de version pour le parser et récupérer le nom des champs				
					$versionsyno = $this->execSRV($versionsyno_cmd, 'VersionSyno');
	
					if ($this->getconfiguration('syno_use_temp_path')) {
						$cputemp0_cmd = $this->getconfiguration('syno_temp_path');
						log::add('Monitoring','debug', '['. $equipement .'][LOCAL][SYNO] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
					} else {
						$cputemp0_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null";
						log::add('Monitoring','debug', '['. $equipement .'][LOCAL][SYNO] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
					}
					if ($cputemp0_cmd != '') {
						$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp');
					} else {
						$cpu_temp = '';
					}
	
					if ($this->getConfiguration('synologyv2') == '1') {
						$hddv2cmd = "LC_ALL=C df -l 2>/dev/null | grep 'vg1001\|volume2' | head -1 | awk '{ print $2,$3,$4,$5 }'";
						$hddv2 = $this->execSRV($hddv2cmd, 'HDDv2');
					}
	
					if ($this->getConfiguration('synologyusb') == '1') {
						$hddusbcmd = "LC_ALL=C df -l 2>/dev/null | grep 'usb1p1\|volumeUSB1' | head -1 | awk '{ print $2,$3,$4,$5 }'";
						$hddusb = $this->execSRV($hddusbcmd, 'HDDUSB');
					}
	
					if ($this->getConfiguration('synologyesata') == '1') {
						$hddesatacmd = "LC_ALL=C df -l 2>/dev/null | grep 'sdf1\|volumeSATA' | head -1 | awk '{ print $2,$3,$4,$5 }'";
						$hddesata = $this->execSRV($hddesatacmd, 'HDDeSATA');
					}
				} elseif ($ARMv == 'armv6l') {
					$uname = '.';
					$cpu_freq = '';
					$cpu_temp = '';
	
					// NbCPU Command
					$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep 'CPU(s):' | awk '{ print $2 }'";
					$cpu_nb = $this->execSRV($nbcpuARM_cmd, 'NbCPU');
					
					// CPUFreq Command
					if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq')) {
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq";
						$cpu_freq = $this->execSRV($cpufreq0ARM_cmd, 'CPUFreq-1');
					}
					if ($cpu_freq == '') {
						if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq')) {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq";
							$cpu_freq = $this->execSRV($cpufreq0ARM_cmd, 'CPUFreq-2');
						}
					}
					
					// cputemp Command
					$cputemp_cmd = $this->getCmd(null,'cpu_temp');
					if (is_object($cputemp_cmd)) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][ARM6L] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
						} elseif (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
							$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp";
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][ARM6L] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						}
						if ($cputemp0_cmd != '') {
							$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp');
						} else {
							$cpu_temp = '';
						}
					}

				} elseif ($ARMv == 'armv7l' || $ARMv == 'aarch64') {
					$uname = '.';
					$cpu_temp = '';
					$cpu_freq = '';
	
					// NbCPU Command
					$nbcpuARM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'";
					$cpu_nb = $this->execSRV($nbcpuARM_cmd, 'NbCPU');
					
					// CPUFreq Command
					if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq')) {
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq";
						$cpu_freq = $this->execSRV($cpufreq0ARM_cmd, 'CPUFreq-1');
					}
					if ($cpu_freq == '') {
						if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq')) {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq";
							$cpu_freq = $this->execSRV($cpufreq0ARM_cmd, 'CPUFreq-2');
						}
					}	
					
					// cputemp Command
					$cputemp_cmd = $this->getCmd(null, 'cpu_temp');
					if (is_object($cputemp_cmd)) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][AARCH64] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
							if ($cputemp0_cmd != '') {
								$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-1');
							} else {
								$cpu_temp = '';
							}
						} else {
							if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
								$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp"; // OK RPi2/3, Odroid
								$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-2');
							}
							if ($cpu_temp == '') {
								if (file_exists('/sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1')) {
									$cputemp0_cmd = "cat /sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1"; // OK Banana Pi (Cubie surement un jour...)
									$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-3');
								}
							}
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][AARCH64] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						}
					}

				} elseif ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386') {
					$uname = '.';
					$cpu_temp = '';
					$cpu_freq = '';
					
					// NbCPU Command
					$nbcpuVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print \$NF }'"; // OK pour LXC Linux/Ubuntu
					$cpu_nb = $this->execSRV($nbcpuVM_cmd, 'NbCPU');
					$cpu_nb = preg_replace("/[^0-9]/", "", $cpu_nb);
					log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL][X86] NbCPU :: ' . $cpu_nb);

					// CPUFreq Command
					$cpufreqVM_cmd = "LC_ALL=C lscpu 2>/dev/null | grep -Ei '^CPU( max)? MHz' | awk '{ print \$NF }'";    // OK pour LXC Linux, Proxmox, Debian 10/11
					$cpu_freq = $this->execSRV($cpufreqVM_cmd, 'CPUFreq-1');
	
					if ($cpu_freq == '') {
						$cpufreqVMbis_cmd = "cat /proc/cpuinfo 2>/dev/null | grep -i '^cpu MHz' | head -1 | cut -d':' -f2 | awk '{ print \$NF }'";    // OK pour Debian 10,11,12, Ubuntu 22.04, pve-debian12
						$cpu_freq = $this->execSRV($cpufreqVMbis_cmd, 'CPUFreq-2');
					}
					$cpu_freq = preg_replace("/[^0-9.,]/", "", $cpu_freq);
					log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL][X86] CPUFreq :: ' . $cpu_freq);
					
					// cputemp Command
					$cputemp_cmd = $this->getCmd(null,'cpu_temp');
					if (is_object($cputemp_cmd)) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd = $this->getconfiguration('linux_temp_cmd');
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][X86] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));	
							if ($cputemp0_cmd != '') {
								$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-1');
							} else {
								$cpu_temp = '';
							}
						} else {
							if (file_exists('/sys/devices/virtual/thermal/thermal_zone0/temp')) {
								$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone0/temp"; // OK Dell Whyse
								$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-2');
							}					
							if ($cpu_temp == '') {
								if (file_exists('/sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input')) {
									$cputemp0_cmd = "cat /sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input";	// OK AOpen DE2700
									$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-3');
								}
							}
							if ($cpu_temp == '') {
								$cputemp0_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"; // OK AMD Ryzen
								$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-4');
							}
							if ($cpu_temp == '') {
								$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"Package\")) {printf(\"%f\",$4);} }'"; // OK by sensors
								$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-5');
							}
							if ($cpu_temp == '') {
								$cputemp0_cmd = "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")) {printf(\"%f\",$3);} }'"; // OK by sensors MB
								$cpu_temp = $this->execSRV($cputemp0_cmd, 'CPUTemp-6');
							}
							log::add('Monitoring','debug', '['. $equipement .'][LOCAL][X86] Commande Température :: ' . str_replace("\r\n", "\\r\\n", $cputemp0_cmd));
						}
					}
				}
			}
	
			// Traitement des données récupérées
			if (isset($cnx_ssh)) {

				// Connexion Local ou Connexion SSH OK
				if ($this->getConfiguration('localoudistant') == 'local' || $cnx_ssh == 'OK') {

					// Synology (New)
					if ($this->getConfiguration('synology') == '1') {
						// Syno DistriName
						if (isset($versionsyno)) {
							parse_str($versionsyno, $versionsyno_DSM);
							log::add('Monitoring', 'debug', '['. $equipement .'][DSM/SRM] Parse version :: OK');
	
							if (isset($versionsyno_DSM['productversion'], $versionsyno_DSM['buildnumber'], $versionsyno_DSM['smallfixnumber'])) {
								log::add('Monitoring', 'debug', '['. $equipement .'][DSM/SRM] Version :: DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'].' Update '.$versionsyno_DSM['smallfixnumber']);
								$versionsyno_TXT = 'DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'].' Update '.$versionsyno_DSM['smallfixnumber'];
							} elseif (isset($versionsyno_DSM['productversion'], $versionsyno_DSM['buildnumber'])) {
								log::add('Monitoring', 'debug', '['. $equipement .'][DSM/SRM] Version (Version-Build) :: DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber']);
								$versionsyno_TXT = 'DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'];
							} else {
								log::add('Monitoring', 'error', '['. $equipement .'][DSM/SRM] Version :: KO');
								$versionsyno_TXT = '';
							}
	
							if (isset($distri_name, $versionsyno_TXT)) {
								$distri_name = $versionsyno_TXT . ' (' . trim($distri_name) . ')';
							} else {
								$distri_name = '';
							}
						} else {
							$distri_name = '';
						}

						// Syno CPUFreq
						if ((floatval($cpu_freq) / 1000) > 1) {
							$cpu_freq_txt = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP) . " GHz";
						} else {
							$cpu_freq_txt = $cpu_freq . " MHz";
						}

						// Syno CPU Temp
						if (floatval($cpu_temp) > 200) {
							$cpu_temp = round(floatval($cpu_temp) / 1000, 1);
						}

						// Syno CPU
						$cpu = $cpu_nb . ' - ' . $cpu_freq_txt;

						// Syno Volume 2
						if ($this->getConfiguration('synologyv2') == '1') {
							if (isset($hddv2)) {
								$hddv2_data = explode(' ', $hddv2);
								if (count($hddv2_data) == 4) {
									$syno_hddv2_total = intval($hddv2_data[0]);
									$syno_hddv2_used = intval($hddv2_data[1]);
									$syno_hddv2_free = intval($hddv2_data[2]);
									if ($syno_hddv2_total != 0) {
										$syno_hddv2_used_percent = round(($syno_hddv2_used / $syno_hddv2_total) * 100, 1);
										$syno_hddv2_free_percent = round(($syno_hddv2_free / $syno_hddv2_total) * 100, 1);
									} else {
										$syno_hddv2_used_percent = 0.0;
										$syno_hddv2_free_percent = 0.0;
									}
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDv2] Syno HDDv2 Total :: ' . $syno_hddv2_total);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDv2] Syno HDDv2 Used :: ' . $syno_hddv2_used);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDv2] Syno HDDv2 Free :: ' . $syno_hddv2_free);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDv2] Syno HDDv2 Used % :: ' . $syno_hddv2_used_percent);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDv2] Syno HDDv2 Free % :: ' . $syno_hddv2_free_percent);

									$syno_hddv2 = 'T : ' . $this->formatSize($syno_hddv2_total, 'Ko') . ' - U : ' . $this->formatSize($syno_hddv2_used, 'Ko') . ' - F : ' . $this->formatSize($syno_hddv2_free, 'Ko');

								} else {
									$syno_hddv2_total = 0;
									$syno_hddv2_used = 0;
									$syno_hddv2_free = 0;
									$syno_hddv2_used_percent = 0.0;
									$syno_hddv2_free_percent = 0.0;
									$syno_hddv2 = '';
								}
							} else {
								$syno_hddv2_total = 0;
								$syno_hddv2_used = 0;
								$syno_hddv2_free = 0;
								$syno_hddv2_used_percent = 0.0;
								$syno_hddv2_free_percent = 0.0;
								$syno_hddv2 = '';
							}
						}

						// Syno Volume USB
						if ($this->getConfiguration('synologyusb') == '1') {
							if (isset($hddusb)) {
								$hddusb_data = explode(' ', $hddusb);
								if (count($hddusb_data) == 4) {
									$syno_hddusb_total = intval($hddusb_data[0]);
									$syno_hddusb_used = intval($hddusb_data[1]);
									$syno_hddusb_free = intval($hddusb_data[2]);
									if ($syno_hddusb_total != 0) {
										$syno_hddusb_used_percent = round(($syno_hddusb_used / $syno_hddusb_total) * 100, 1);
										$syno_hddusb_free_percent = round(($syno_hddusb_free / $syno_hddusb_total) * 100, 1);
									} else {
										$syno_hddusb_used_percent = 0.0;
										$syno_hddusb_free_percent = 0.0;
									}
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDUSB] Syno HDDUSB Total :: ' . $syno_hddusb_total);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDUSB] Syno HDDUSB Used :: ' . $syno_hddusb_used);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDUSB] Syno HDDUSB Free :: ' . $syno_hddusb_free);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDUSB] Syno HDDUSB Used % :: ' . $syno_hddusb_used_percent);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDUSB] Syno HDDUSB Free % :: ' . $syno_hddusb_free_percent);

									$syno_hddusb = 'T : ' . $this->formatSize($syno_hddusb_total, 'Ko') . ' - U : ' . $this->formatSize($syno_hddusb_used, 'Ko') . ' - F : ' . $this->formatSize($syno_hddusb_free, 'Ko');
								} else {
									$syno_hddusb_total = 0;
									$syno_hddusb_used = 0;
									$syno_hddusb_free = 0;
									$syno_hddusb_used_percent = 0.0;
									$syno_hddusb_free_percent = 0.0;
									$syno_hddusb = '';
								}
							} else {
								$syno_hddusb_total = 0;
								$syno_hddusb_used = 0;
								$syno_hddusb_free = 0;
								$syno_hddusb_used_percent = 0.0;
								$syno_hddusb_free_percent = 0.0;
								$syno_hddusb = '';
							}
						}

						// Syno Volume eSATA
						if ($this->getConfiguration('synologyesata') == '1') {
							if (isset($hddesata)) {
								$hdddesata_data = explode(' ', $hddesata);
								if (count($hdddesata_data) == 4) {
									$syno_hddesata_total = intval($hdddesata_data[0]);
									$syno_hddesata_used = intval($hdddesata_data[1]);
									$syno_hddesata_free = intval($hdddesata_data[2]);
									if ($syno_hddesata_total != 0) {
										$syno_hddesata_used_percent = round(($syno_hddesata_used / $syno_hddesata_total) * 100, 1);
										$syno_hddesata_free_percent = round(($syno_hddesata_free / $syno_hddesata_total) * 100, 1);
									} else {
										$syno_hddesata_used_percent = 0.0;
										$syno_hddesata_free_percent = 0.0;
									}
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDeSATA] Syno HDDeSATA Total :: ' . $syno_hddesata_total);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDeSATA] Syno HDDeSATA Used :: ' . $syno_hddesata_used);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDeSATA] Syno HDDeSATA Free :: ' . $syno_hddesata_free);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDeSATA] Syno HDDeSATA Used % :: ' . $syno_hddesata_used_percent);
									log::add('Monitoring', 'debug', '['. $equipement .'][HDDeSATA] Syno HDDeSATA Free % :: ' . $syno_hddesata_free_percent);

									$syno_hddesata = 'T : ' . $this->formatSize($syno_hddesata_total, 'Ko') . ' - U : ' . $this->formatSize($syno_hddesata_used, 'Ko') . ' - F : ' . $this->formatSize($syno_hddesata_free, 'Ko');
								} else {
									$syno_hddesata_total = 0;
									$syno_hddesata_used = 0;
									$syno_hddesata_free = 0;
									$syno_hddesata_used_percent = 0.0;
									$syno_hddesata_free_percent = 0.0;
									$syno_hddesata = '';
								}
							} else {
								$syno_hddesata_total = 0;
								$syno_hddesata_used = 0;
								$syno_hddesata_free = 0;
								$syno_hddesata_used_percent = 0.0;
								$syno_hddesata_free_percent = 0.0;
								$syno_hddesata = '';
							}
						}
					} else {
						// Distri Name (New)
						if (isset($distri_name, $bitdistri, $ARMv)) {
							$distri_name = trim($distri_name) . ' ' . $bitdistri . 'bits (' . $ARMv . ')';
						}
					}
	
					// Uptime (New)
					if (isset($uptime)) {
						$uptime_sec = floatval($uptime);
						$uptime = $this->formatUptime($uptime);
					} else {
						$uptime_sec = 0;
						$uptime = '';
					}
	
					// LoadAverage (New)
					if (isset($loadav)) {
						$loadavg = explode(' ', $loadav);
						if (count($loadavg) == 5) {
							$load_avg_1mn = floatval($loadavg[0]);
							$load_avg_5mn = floatval($loadavg[1]);
							$load_avg_15mn = floatval($loadavg[2]);
							$load_avg = '1 min : ' . $load_avg_1mn . ' - 5 min : ' . $load_avg_5mn . ' - 15 min : ' . $load_avg_15mn;
						} else {
							$load_avg_1mn = 0.0;
							$load_avg_5mn = 0.0;
							$load_avg_15mn = 0.0;
							$load_avg = '';
						}
					} else {
						$load_avg_1mn = 0.0;
						$load_avg_5mn = 0.0;
						$load_avg_15mn = 0.0;
						$load_avg = '';
					}
	
					// Memory (New)
					if (isset($memory)) {
						// Cas général
						if (!preg_match("#FreeBSD#", $uname)) {
							$memory = explode(' ', $memory);
							if (count($memory) == 5) {
								$memory_total = intval($memory[0]);
								$memory_used = intval($memory[1]);
								$memory_free = intval($memory[2]);
								$memory_buffcache = intval($memory[3]);
								$memory_available = intval($memory[4]);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Total :: ' . $memory_total);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Used :: ' . $memory_used);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Free :: ' . $memory_free);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Cache :: ' . $memory_buffcache);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Available :: ' . $memory_available);	

								if ($memory_total != 0) {
									$memory_free_percent = round($memory_free / $memory_total * 100, 1);
									$memory_used_percent = round(($memory_used + $memory_buffcache) / $memory_total * 100, 1);
									$memory_available_percent = round($memory_available / $memory_total * 100, 1);
								} else {
									$memory_free_percent = 0.0;
									$memory_used_percent = 0.0;
									$memory_available_percent = 0.0;
								}
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Free % :: ' . $memory_free_percent);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Used % :: ' . $memory_used_percent);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Available % :: ' . $memory_available_percent);

								$memory = 'T : '. $this->formatSize($memory_total, 'Ko') . ' - U : ' . $this->formatSize($memory_used, 'Ko') . ' - A : ' . $this->formatSize($memory_available, 'Ko');
							} else {
								$memory_total = 0;
								$memory_used = 0;
								$memory_free = 0;
								$memory_buffcache = 0;
								$memory_available = 0;
								$memory_free_percent = 0.0;
								$memory_used_percent = 0.0;
								$memory_available_percent = 0.0;
								$memory = '';
							}
						// Cas spécifique FreeBSD
						} elseif (preg_match("#FreeBSD#", $uname)) {
							$memory = explode(' ', $memory);
							if (count($memory) == 2) {	
								$memory_free = intval($memory[1]);
								$memory_total = intval($memory[0]);
								$memory_used = $memory_total - $memory_free;
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Total :: ' . $memory_total);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Used :: ' . $memory_used);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Free :: ' . $memory_free);

								if ($memory_total != 0) {
									$memory_free_percent = round($memory_free / $memory_total * 100, 1);
									$memory_used_percent = round($memory_used / $memory_total * 100, 1);
								} else {
									$memory_free_percent = 0.0;
									$memory_used_percent = 0.0;
								}
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Free % :: ' . $memory_free_percent);
								log::add('Monitoring', 'debug', '['. $equipement .'][MEMORY] Memory Used % :: ' . $memory_used_percent);

								$memory = 'T : '. $this->formatSize($memory_total, 'Ko') . ' - U : ' . $this->formatSize($memory_used, 'Ko') . ' - F : ' . $this->formatSize($memory_free, 'Ko');

							} else {
								$memory_free = 0;
								$memory_total = 0;
								$memory_used = 0;
								$memory_free_percent = 0.0;
								$memory_used_percent = 0.0;
								$memory = '';
							}
						}
					} else {
						$memory_free = 0;
						$memory_total = 0;
						$memory_used = 0;
						$memory_free_percent = 0.0;
						$memory_used_percent = 0.0;
						$memory = '';
					}
	
					// Swap (New)
					if (isset($swap)) {
						$swap = explode(' ', $swap);
						if (count($swap) == 3) {
							if (intval($swap[0]) != 0) {
								$swap_free_percent = round(intval($swap[2]) / intval($swap[0]) * 100, 1);
								$swap_used_percent = round(intval($swap[1]) / intval($swap[0]) * 100, 1);
							} else {
								$swap_free_percent = 0.0;
								$swap_used_percent = 0.0;
							}
							log::add('Monitoring', 'debug', '['. $equipement .'][SWAP] Swap Free % :: ' . $swap_free_percent);
							log::add('Monitoring', 'debug', '['. $equipement .'][SWAP] Swap Used % :: ' . $swap_used_percent);

							$swap_total = intval($swap[0]);
							$swap_used = intval($swap[1]);
							$swap_free = intval($swap[2]);
							$swap_display = 'T : ' . $this->formatSize($swap[0], 'Ko') . ' - U : ' . $this->formatSize($swap[1], 'Ko') . ' - F : ' . $this->formatSize($swap[2], 'Ko');

						} else {
							$swap_free_percent = 0.0;
							$swap_used_percent = 0.0;
							$swap_total = 0;
							$swap_used = 0;
							$swap_free = 0;
							$swap_display = '';
						}
					} else {
						$swap_free_percent = 0.0;
						$swap_used_percent = 0.0;
						$swap_total = 0;
						$swap_used = 0;
						$swap_free = 0;
						$swap_display = '';
					}
	
					// Réseau (New)
					if (isset($ReseauRXTX)) {
						$ReseauRXTX = explode(' ', $ReseauRXTX);
						if (count($ReseauRXTX) == 3) {
							$network_tx = intval($ReseauRXTX[2]);
							$network_rx = intval($ReseauRXTX[1]);
							$network = 'TX : '. $this->formatSize($network_tx) .' - RX : '. $this->formatSize($network_rx);
							$network_name = $ReseauRXTX[0];
							
							if (isset($ReseauIP)) {
								$network_ip = $ReseauIP;
							} else {
								$network_ip = '';
							}
							
							log::add('Monitoring', 'debug', '['. $equipement .'][RESEAU] Nom de la carte réseau / IP (RX / TX) :: ' .$network_name.' / IP= ' . $network_ip . ' (RX= '. $this->formatSize($network_rx) .' / TX= '. $this->formatSize($network_tx) .')');
						} else {
							$network_tx = 0;
							$network_rx = 0;
							$network = '';
							$network_name = '';
							$network_ip = '';
							log::add('Monitoring', 'error', '['. $equipement .'][RESEAU] Carte Réseau NON détectée :: KO');
						}
					} else {
						$network_tx = 0;
						$network_rx = 0;
						$network = '';
						$network_name = '';
						$network_ip = '';
						log::add('Monitoring', 'error', '['. $equipement .'][RESEAU] Carte Réseau NON détectée :: KO');
					}
	
					// HDD (New)
					if (isset($hdd)) {
						$hdddata = explode(' ', $hdd);
						if (count($hdddata) == 4) {
							$hdd_total = intval($hdddata[0]);
							$hdd_used = intval($hdddata[1]);
							$hdd_free = intval($hdddata[2]);
							if ($hdd_total != 0) {
								$hdd_used_percent = round($hdd_used / $hdd_total * 100, 1);
								$hdd_free_percent = round($hdd_free / $hdd_total * 100, 1);
							} else {
								$hdd_used_percent = 0.0;
								$hdd_free_percent = 0.0;
							}
							log::add('Monitoring', 'debug', '['. $equipement .'][HDD] HDD Total :: ' . $hdd_total);
							log::add('Monitoring', 'debug', '['. $equipement .'][HDD] HDD Used :: ' . $hdd_used);
							log::add('Monitoring', 'debug', '['. $equipement .'][HDD] HDD Free :: ' . $hdd_free);
							log::add('Monitoring', 'debug', '['. $equipement .'][HDD] HDD Used % :: ' . $hdd_used_percent);
							log::add('Monitoring', 'debug', '['. $equipement .'][HDD] HDD Free % :: ' . $hdd_free_percent);
							
							$hdd = 'T : '. $this->formatSize($hdd_total, 'Ko') . ' - U : ' . $this->formatSize($hdd_used, 'Ko') . ' - F : ' . $this->formatSize($hdd_free, 'Ko');

						} else {
							$hdd_total = 0;
							$hdd_used = 0;
							$hdd_free = 0;
							$hdd_used_percent = 0.0;
							$hdd_free_percent = 0.0;
							$hdd = '';
						}
					} else {
						$hdd_total = 0;
						$hdd_used = 0;
						$hdd_free = 0;
						$hdd_used_percent = 0.0;
						$hdd_free_percent = 0.0;
						$hdd = '';
					}

					// ARMv (New)
					if (isset($ARMv)) {
						if ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386') {
							
							// CPUFreq
							if ((floatval($cpu_freq) / 1000) > 1) {
								$cpu_freq_txt = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP) . " GHz";
							} else {
								$cpu_freq_txt = $cpu_freq . " MHz";
							}

							// CPU Temp
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if (floatval($cpu_temp) > 200) {
									$cpu_temp = round(floatval($cpu_temp) / 1000, 1);
								}
							}

							// CPU
							$cpu = $cpu_nb . ' - ' . $cpu_freq_txt;

						} elseif ($ARMv == 'armv6l' || $ARMv == 'armv7l' || $ARMv == 'aarch64' || $ARMv == 'mips64') {
							
							// CPUFreq
							if ((floatval($cpu_freq) / 1000) > 1000) {
								$cpu_freq_txt = round(floatval($cpu_freq) / 1000000, 1, PHP_ROUND_HALF_UP) . " GHz";
								$cpu_freq = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP);
							} else {
								$cpu_freq_txt = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP) . " MHz";
								$cpu_freq = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP);
							}
							
							// CPU Temp
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd)) {
								if (floatval($cpu_temp) > 200) {
									$cpu_temp = round(floatval($cpu_temp) / 1000, 1);
								}
							}

							// CPU
							if (floatval($cpu_freq) == 0) {
								$cpu = $cpu_nb . ' Socket(s) ';
								$cpu_freq = 0.0;
							} else {
								$cpu = $cpu_nb . ' - ' . $cpu_freq_txt;
							}

						} elseif ($ARMv == 'arm') {
							if (preg_match("#RasPlex|OpenELEC|osmc|LibreELEC#", $distri_name) || preg_match("#piCorePlayer#", $uname) || preg_match("#medion#", $uname)) {
								
								// CPUFreq
								if ((floatval($cpu_freq) / 1000) > 1000) {
									$cpu_freq_txt = round(floatval($cpu_freq) / 1000000, 1, PHP_ROUND_HALF_UP) . " GHz";
									$cpu_freq = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP);
									
								} else {
									$cpu_freq_txt = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP) . " MHz";
									$cpu_freq = round(floatval($cpu_freq) / 1000, 1, PHP_ROUND_HALF_UP);
								}

								// CPU Temp
								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd)) {
									if (floatval($cpu_temp) > 200) {
										$cpu_temp = round(floatval($cpu_temp) / 1000, 1);
									}
								}

								$cpu = $cpu_nb . ' - ' . $cpu_freq_txt;
							}
						}
					}
					
					// Array des résultats
					if (!isset($perso1)) {$perso1 = '';}
					if (!isset($perso2)) {$perso2 = '';}
	
					$dataresult = array(
						'cnx_ssh' => $cnx_ssh,
						'distri_name' => $distri_name,
						'uptime' => $uptime,
						'uptime_sec' => $uptime_sec,
						'load_avg' => $load_avg,
						'load_avg_1mn' => $load_avg_1mn,
						'load_avg_5mn' => $load_avg_5mn,
						'load_avg_15mn' => $load_avg_15mn,
						'memory_total' => $memory_total,
						'memory_used' => $memory_used,
						'memory_free' => $memory_free,
						'memory_buffcache' => $memory_buffcache,
						'memory_available' => $memory_available,
						'memory' => $memory,
						'memory_free_percent' => $memory_free_percent,
						'memory_used_percent' => $memory_used_percent,
						'memory_available_percent' => $memory_available_percent,
						'swap' => $swap_display,
						'swap_free_percent' => $swap_free_percent,
						'swap_used_percent' => $swap_used_percent,
						'swap_total' => $swap_total,
						'swap_used' => $swap_used,
						'swap_free' => $swap_free,
						'network' => $network,
						'network_tx' => $network_tx,
						'network_rx' => $network_rx,
						'network_name' => $network_name,
						'network_ip' => $network_ip,
						'hdd' => $hdd,
						'hdd_total' => $hdd_total,
						'hdd_used' => $hdd_used,
						'hdd_free' => $hdd_free,
						'hdd_used_percent' => $hdd_used_percent,
						'hdd_free_percent' => $hdd_free_percent,
						'cpu' => $cpu,
						'cpu_temp' => $cpu_temp,
						'cpu_nb' => $cpu_nb,
						'cpu_freq' => $cpu_freq,
						'perso1' => $perso1,
						'perso2' => $perso2,
					);

					if ($this->getConfiguration('synology') == '1') {
						if ($this->getConfiguration('synologyv2') == '1') {
							$dataresult = array_merge($dataresult, [
								'syno_hddv2' => $syno_hddv2,
								'syno_hddv2_total' => $syno_hddv2_total,
								'syno_hddv2_used' => $syno_hddv2_used,
								'syno_hddv2_free' => $syno_hddv2_free,
								'syno_hddv2_used_percent' => $syno_hddv2_used_percent,
								'syno_hddv2_free_percent' => $syno_hddv2_free_percent,
								
							]);
						}
						if ($this->getConfiguration('synologyusb') == '1') {
							$dataresult = array_merge($dataresult, [
								'syno_hddusb' => $syno_hddusb,
								'syno_hddusb_total' => $syno_hddusb_total,
								'syno_hddusb_used' => $syno_hddusb_used,
								'syno_hddusb_used_percent' => $syno_hddusb_used_percent,
								'syno_hddusb_free' => $syno_hddusb_free,
								'syno_hddusb_free_percent' => $syno_hddusb_free_percent,
								
							]);
						}
						if ($this->getConfiguration('synologyesata') == '1') {
							$dataresult = array_merge($dataresult, [
								'syno_hddesata' => $syno_hddesata,
								'syno_hddesata_total' => $syno_hddesata_total,
								'syno_hddesata_used' => $syno_hddesata_used,
								'syno_hddesata_used_percent' => $syno_hddesata_used_percent,
								'syno_hddesata_free' => $syno_hddesata_free,
								'syno_hddesata_free_percent' => $syno_hddesata_free_percent,
							]);
						}
					}

					// Event sur les commandes après récupération des données
					foreach ($dataresult as $key => $value) {
						$cmd = $this->getCmd(null, $key);
						if (is_object($cmd)) {
							$cmd->event($value);
						}
					}
				} elseif ($cnx_ssh == 'KO') {
					$dataresult = array(
						'distri_name' => 'Connexion SSH KO',
						'cnx_ssh' => $cnx_ssh
					);
					foreach ($dataresult as $key => $value) {
						$cmd = $this->getCmd(null, $key);
						if (is_object($cmd)) {
							$cmd->event($value);
						}
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
