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

	public static function getConfigForCommunity() {

		$isSSHMExist = class_exists('sshmanager');

		$CommunityInfo = "```\n";
		$CommunityInfo .= 'Debian :: ' . system::getOsVersion() . "\n";
		$CommunityInfo .= 'Plugin Monitoring (Version / Branche) : ' . config::byKey('pluginVersion', 'Monitoring', 'N/A') . ' / ' . config::byKey('pluginBranch', 'Monitoring', 'N/A') . "\n";
		$CommunityInfo .= 'Plugin SSH Manager (Version / Branche) : ' . config::byKey('pluginVersion', 'sshmanager', 'N/A') . ' / ' . config::byKey('pluginBranch', 'sshmanager', 'N/A') . "\n";
		
		/* $CommunityInfo .= "\n";
		$CommunityInfo .= 'Liste des équipements Monitoring : ' . "\n";
		foreach (eqLogic::byType('Monitoring') as $Monitoring) {
			$CommunityInfo .= '  - ' . $Monitoring->getName() . ' (' . $Monitoring->getConfiguration('localoudistant') . ')' . "\n";
		} */

		if (!$isSSHMExist) {
			$CommunityInfo .= "\n";
			$CommunityInfo .= 'Plugin SSH Manager non activé !' . "\n";
		} else {
			/* $CommunityInfo .= "\n";
			$CommunityInfo .= 'Liste des équipements SSH Manager : ' . "\n";
			foreach (eqLogic::byType('sshmanager') as $sshManager) {
				$CommunityInfo .= '  - ' . $sshManager->getName() . ' (' . $sshManager->getConfiguration('auth-method') . ')' . "\n";
			} */
		}

		$CommunityInfo .= "```";
		return $CommunityInfo;
	}

	public static function doMigrationToV3() {
		if (class_exists('sshmanager')) {
			log::add('Monitoring', 'info', __('[MIGRATION] Début de la migration vers la v3.0', __FILE__));
		
			// Récupération de tous les équipements de type Monitoring
			$eqLogics = eqLogic::byType('Monitoring');
			$nbMigrated = 0;
			foreach ($eqLogics as $eqLogic) {
				log::add('Monitoring', 'debug', __('[MIGRATION] Equipement :: ', __FILE__) . $eqLogic->getName());
				$oldConfLocalOrRemote = $eqLogic->getConfiguration('maitreesclave');
				if ($oldConfLocalOrRemote == 'deporte' || $oldConfLocalOrRemote == 'deporte-key') {
					log::add('Monitoring', 'info', __('[MIGRATION] Equipement Distant :: ', __FILE__) . $eqLogic->getName() . ' :: Migration en cours');
					try {
						$sshManager = new sshmanager();
						$sshManager->setEqType_name('sshmanager');
						$sshManager->setName($eqLogic->getName() . ' - SSH');
						$sshManager->setIsEnable($eqLogic->getIsEnable());
						$sshManager->setIsVisible(false);
						$sshManager->setObject_id($eqLogic->getObject_id());
						$sshManager->setConfiguration('host', $eqLogic->getConfiguration('addressip'));
						$sshManager->setConfiguration('port', $eqLogic->getConfiguration('portssh'));
						$sshManager->setConfiguration('username', $eqLogic->getConfiguration('user'));
						$sshManager->setConfiguration('password', $eqLogic->getConfiguration('password'));
						$sshManager->setConfiguration('ssh-key', $eqLogic->getConfiguration('ssh-key'));
						$sshManager->setConfiguration('ssh-passphrase', $eqLogic->getConfiguration('ssh-passphrase'));
						$sshManager->setConfiguration('timeout', $eqLogic->getConfiguration('timeoutssh'));
						$sshManager->setConfiguration('auth-method', $eqLogic->getConfiguration('maitreesclave') == 'deporte' ? 'password' : 'ssh-key');
						$sshManager->save();
						$nbMigrated++;
						message::add('Monitoring', __('Equipement Distant :: ', __FILE__) . $eqLogic->getName() . ' :: Migration OK', 'migration');
						log::add('Monitoring', 'info', __('[MIGRATION] Equipement Distant :: ', __FILE__) . $eqLogic->getName() . ' :: Migration OK');
					} catch (Exception $e) {
						log::add('Monitoring', 'error', __('[MIGRATION] Erreur lors de la migration de l\'équipement :: ', __FILE__) . $eqLogic->getName() . ' :: ' . $e->getMessage());
					}
				}
			}
			log::add('Monitoring', 'info', __('[MIGRATION] Fin de la migration vers la v3.0', __FILE__) . ' :: ' . $nbMigrated . ' équipement(s) migré(s)');
			return ('Migration vers la v3.0 terminée :: ' . $nbMigrated . ' équipement(s) migré(s)');
		} else {
			throw new Exception(__('Le plugin SSH Manager n\'est pas actif !', __FILE__));
		}
	}

	public static function dependancy_install() {
		$_logName = __CLASS__ . '_update';
		config::save('lastDependancyInstallTime', date('Y-m-d H:i:s'), plugin::byId('Monitoring')->getId());

		log::add($_logName, 'info', __('[DEP-INSTALL] >>>> Début des dépendances <<<<', __FILE__));
		try {
			$result = shell_exec('php ' . __DIR__ . '/../php/Monitoringcli.php' . ' depinstall ' . $_logName . ' 2>&1 &');
			if (!empty($result)) {
				log::add($_logName, 'debug', __('[DEP-INSTALL] Résultat des dépendances :: ', __FILE__) . $result);
			}
		} catch (Exception $e) {
			log::add($_logName, 'error', __('[DEP-INSTALL] Erreur lors de l\'installation des dépendances :: ', __FILE__) . $e->getMessage());
		}
        return array('log' => log::getPathToLog($_logName));
    }

	public static function dependancy_info() {
        $_logName = __CLASS__ . '_update';

		$return = array();
		$return['log'] = log::getPathToLog($_logName);
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependency';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependency')) {
			$return['state'] = 'in_progress';
		} else {
			try {
				$_plugin = plugin::byId('sshmanager');
				if (!$_plugin->isActive()) {
					log::add($_logName, 'error', __('[DEP-INFO] Le plugin SSHManager n\'est pas activé !', __FILE__));
					$return['state'] = 'nok';
				} else {
					log::add($_logName, 'info', __('[DEP-INFO] Vérification des dépendances :: OK', __FILE__));
					$return['state'] = 'ok';
				}
			} catch (Exception $e) {
				log::add($_logName, 'debug', '[DEP-INFO] ' . $e->getMessage());
				log::add($_logName, 'error', __('[DEP-INFO] Le plugin SSHManager n\'est pas installé !', __FILE__));
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

		$MonitoringCmd = $this->getCmd(null, 'refresh');
        if (!is_object($MonitoringCmd)) {
            $MonitoringCmd = new sshmanagerCmd();
			$MonitoringCmd->setName(__('Rafraichir', __FILE__));
            $MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('refresh');
            $MonitoringCmd->setType('action');
            $MonitoringCmd->setSubType('other');
			$MonitoringCmd->setIsVisible(1);
			$MonitoringCmd->setOrder($orderCmd++);
            $MonitoringCmd->save();
        } else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'cnx_ssh');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('SSH Status', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cnx_ssh');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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

		// Initialisation de la valeur de la commande cron_status
		if (is_object($MonitoringCmd) && $MonitoringCmd->execCmd() === '') {
			// log::add('Monitoring', 'debug',  '[' . $this->getName() .'][PostSave] Cron Status Value :: Empty');
			$this->checkAndUpdateCmd($MonitoringCmd->getLogicalId(), '1');
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
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'os_version');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Version OS', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('os_version');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'network_infos');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Infos Réseau', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('network_infos');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('icon', '<i class="fas fa-ethernet"></i>');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setUnite('Mo');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}
			}

			// Synology volume 3
			if ($this->getConfiguration('synologyv3') == '1') {

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv3');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 3', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv3');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->setDisplay('icon', '<i class="far fa-hdd"></i>');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv3_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 3 Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv3_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv3_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 3 Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv3_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv3_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 3 Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv3_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv3_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 3 Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv3_used_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv3_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 3 Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv3_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}
			}
			
			// Synology volume 4
			if ($this->getConfiguration('synologyv4') == '1') {

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv4');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 4', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv4');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('string');
					$MonitoringCmd->setDisplay('icon', '<i class="far fa-hdd"></i>');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv4_total');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 4 Total', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv4_total');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv4_used');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 4 Utilisé', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv4_used');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv4_free');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 4 Libre', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv4_free');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv4_used_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 4 Utilisé (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv4_used_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
					$MonitoringCmd->setOrder($orderCmd++);
					$MonitoringCmd->save();
				} else {
					$orderCmd++;
				}

				$MonitoringCmd = $this->getCmd(null, 'syno_hddv4_free_percent');
				if (!is_object($MonitoringCmd)) {
					$MonitoringCmd = new MonitoringCmd();
					$MonitoringCmd->setName(__('Syno Volume 4 Libre (Pourcent)', __FILE__));
					$MonitoringCmd->setEqLogic_id($this->getId());
					$MonitoringCmd->setLogicalId('syno_hddv4_free_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setLogicalId('syno_hddusb_used_percent');
					$MonitoringCmd->setType('info');
					$MonitoringCmd->setSubType('numeric');
					$MonitoringCmd->setUnite('%');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setUnite('Mo');
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(0);
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
					$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
					$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
					$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(1);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
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
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'perso1');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Perso1', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('perso1');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
			$MonitoringCmd->setIsVisible(0);
			$MonitoringCmd->setOrder($orderCmd++);
			$MonitoringCmd->save();
		} else {
			$orderCmd++;
		}

		$MonitoringCmd = $this->getCmd(null, 'perso2');
		if (!is_object($MonitoringCmd)) {
			$MonitoringCmd = new MonitoringCmd();
			$MonitoringCmd->setName(__('Perso2', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('perso2');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
			$MonitoringCmd->setDisplay('forceReturnLineBefore', '1');
			$MonitoringCmd->setDisplay('forceReturnLineAfter', '1');
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

	public function toHtml($_version = 'dashboard') {

		// $backtrace = debug_backtrace();
		// log::add('Monitoring', 'debug', '['. $this->getName() .'][toHtml] Caller :: ' . json_encode($backtrace));

		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);

		$cmdToReplace = array(
			'cnx_ssh' => array('exec', 'id'),
			'cron_status' => array('exec', 'id', 'display_inline', 'pull_use_custom'),
			'distri_name' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			
			'load_avg' => array('icon', 'id', 'display', 'collect', 'value'),
			'load_avg_1mn' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats'),
			'load_avg_5mn' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats'),
			'load_avg_15mn' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats'),
			
			'uptime' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			
			'hdd' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'hdd_free_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats'),
			
			'memory' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'memory_available_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats'),
			
			'swap' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'swap_free_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats'),
			
			'network' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'network_infos' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'network_name' => array('exec', 'id'),
			'network_ip' => array('exec', 'id'),
			
			'cpu' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'cpu_temp' => array('exec', 'id', 'display', 'colorlow', 'colorhigh', 'stats'),

			'perso1' => array('icon', 'exec', 'id', 'display', 'collect', 'value', 'name', 'unite', 'colorlow', 'colorhigh', 'stats'),
			'perso2' => array('icon', 'exec', 'id', 'display', 'collect', 'value', 'name', 'unite', 'colorlow', 'colorhigh', 'stats')
		);

		// Synology
		$syno_hddv2_array = array(
			'syno_hddv2' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'syno_hddv2_free_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats')
		);

		$syno_hddv3_array = array(
			'syno_hddv3' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'syno_hddv3_free_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats')
		);

		$syno_hddv4_array = array(
			'syno_hddv4' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'syno_hddv4_free_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats')
		);

		$syno_hddusb_array = array(
			'syno_hddusb' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'syno_hddusb_free_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats')
		);

		$syno_hddesata_array = array(
			'syno_hddesata' => array('icon', 'exec', 'id', 'display', 'collect', 'value'),
			'syno_hddesata_free_percent' => array('exec', 'id', 'colorlow', 'colorhigh', 'stats')
		);
	
		if ($this->getConfiguration('synology') == '1') {
			if ($this->getConfiguration('synologyv2') == '1') {
				$cmdToReplace = array_merge($cmdToReplace, $syno_hddv2_array);
			}
			if ($this->getConfiguration('synologyv3') == '1') {
				$cmdToReplace = array_merge($cmdToReplace, $syno_hddv3_array);
			}
			if ($this->getConfiguration('synologyv4') == '1') {
				$cmdToReplace = array_merge($cmdToReplace, $syno_hddv4_array);
			}
			if ($this->getConfiguration('synologyusb') == '1') {
				$cmdToReplace = array_merge($cmdToReplace, $syno_hddusb_array);
			}
			if ($this->getConfiguration('synologyesata') == '1') {
				$cmdToReplace = array_merge($cmdToReplace, $syno_hddesata_array);
			}
		}

		foreach ($cmdToReplace as $cmdName => $cmdOptions) {
			$this->getCmdReplace($cmdName, $cmdOptions, $replace);
		}

		// Commandes Actions
		foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
			$replace['#cmd_' . $cmd->getLogicalId() . '_display#'] = (is_object($cmd) && $cmd->getIsVisible()) ? "inline-block" : "none";
		}

		return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'Monitoring', 'Monitoring')));
	}

	public static function getPluginBranch() {
        $pluginBranch = 'N/A';
		try {
			$_updateMonitoring = update::byLogicalId('Monitoring');
			$pluginBranch = $_updateMonitoring->getConfiguration('version', 'N/A') . ' (' . $_updateMonitoring->getSource() . ')';
		}
		catch (\Exception $e) {
			log::add('Monitoring', 'warning', '[BRANCH] Get ERROR :: ' . $e->getMessage());
		}
		log::add('Monitoring', 'info', '[BRANCH] PluginBranch :: ' . $pluginBranch);
        return $pluginBranch;
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

	public function getCmdPerso($perso) {
		$result = '';
		$perso_cmd = $this->getCmd(null, $perso);
		if (is_object($perso_cmd)) {
			$perso_command = $perso_cmd->getConfiguration($perso);
			$result = trim($perso_command);
		} else {
			$result = '';
		}
		return $result;
	}

	public function getCPUFreq($cpuFreqArray, $equipement, $localOrRemote = 'local', $hostId = '') {
		$result = ['cpu_freq' => '', 'cpu_freq_id' => ''];
		foreach ($cpuFreqArray as $id => [$type, $command]) {
			if ($type == 'file' && file_exists($command)) {
				$cpu_freq_cmd = "cat " . $command;
			} elseif ($type == 'cmd') {
				$cpu_freq_cmd = $command;
			} else {
				$cpu_freq_cmd = '';
			}
			$cpu_freq = trim($cpu_freq_cmd) !== '' ? ($localOrRemote == 'local' ? $this->execSRV($cpu_freq_cmd, 'CPUFreq-' . $id) : $this->execSSH($hostId, $cpu_freq_cmd, 'CPUFreq-' . $id)) : '';
			$cpu_freq = preg_replace("/[^0-9.,]/", "", $cpu_freq);
			if (!empty($cpu_freq)) {
				$result = ['cpu_freq' => $cpu_freq, 'cpu_freq_id' => $id];
				break;
			}
		}
		return $result;
	}

	public function getCPUTemp($tempArray, $equipement, $localoudistant = 'local', $hostId = '') {
		$result = ['cpu_temp' => '', 'cpu_temp_id' => ''];

		if ($this->getConfiguration('linux_use_temp_cmd')) {
			$cpu_temp_cmd = $this->getconfiguration('linux_temp_cmd');
			log::add('Monitoring','debug', '['. $equipement .'][' . $localoudistant == 'local' ? 'LOCAL' : 'SSH-EXEC' .'] Commande Température (Custom) :: ' . str_replace("\r\n", "\\r\\n", $cpu_temp_cmd));	
			$cpu_temp = trim($cpu_temp_cmd) !== '' ? ($localoudistant == 'local' ? $this->execSRV($cpu_temp_cmd, 'CPUTemp-Custom') : $this->execSSH($hostId, $cpu_temp_cmd, 'CPUTemp-Custom')) : '';
			if (!empty($cpu_temp)) {
				$result = ['cpu_temp' => $cpu_temp, 'cpu_temp_id' => 'Custom'];
			}
		} elseif (is_array($tempArray)) {
			foreach ($tempArray as $id => [$type, $command]) {	
				if ($type == 'file' && file_exists($command)) {
					$cpu_temp_cmd = "cat " . $command;
				} elseif ($type == 'cmd') {
					$cpu_temp_cmd = $command;
				} else {
					$cpu_temp_cmd = '';
				}
				$cpu_temp = trim($cpu_temp_cmd) !== '' ? ($localoudistant == 'local' ? $this->execSRV($cpu_temp_cmd, 'CPUTemp-' . $id) : $this->execSSH($hostId, $cpu_temp_cmd, 'CPUTemp-' . $id)) : '';
				if (!empty($cpu_temp)) {
					$result = ['cpu_temp' => $cpu_temp, 'cpu_temp_id' => $id];
					break;
				}
			}
		}
		return $result;
	}
	
	public function getSynoVersion(string $_version, string $_syno_model, string $_equipement) {
		$result = '';

		parse_str($_version, $syno_version_array);
		log::add('Monitoring', 'debug', '['. $_equipement .'][DSM/SRM] Parse version :: OK');
		
		if (isset($syno_version_array['productversion'], $syno_version_array['buildnumber'], $syno_version_array['smallfixnumber'])) {
			log::add('Monitoring', 'debug', '['. $_equipement .'][DSM/SRM] Version :: DSM '.$syno_version_array['productversion'].'-'.$syno_version_array['buildnumber'].' Update '.$syno_version_array['smallfixnumber']);
			$syno_version_TXT = 'DSM '.$syno_version_array['productversion'].'-'.$syno_version_array['buildnumber'].' Update '.$syno_version_array['smallfixnumber'];
		} elseif (isset($syno_version_array['productversion'], $syno_version_array['buildnumber'])) {
			log::add('Monitoring', 'debug', '['. $_equipement .'][DSM/SRM] Version (Version-Build) :: DSM '.$syno_version_array['productversion'].'-'.$syno_version_array['buildnumber']);
			$syno_version_TXT = 'DSM '.$syno_version_array['productversion'].'-'.$syno_version_array['buildnumber'];
		} else {
			log::add('Monitoring', 'error', '['. $_equipement .'][DSM/SRM] Version :: KO');
			$syno_version_TXT = 'DSM';
		}
		
		$result = $syno_version_TXT . ' (' . trim($_syno_model) . ')';
		return $result;
	}

	public function getStats($cmd, $cmdName, int $precision = 2) {
		try {
			if ($cmd->getIsHistorized() == 1) {
				$startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculPeriod') . ' hour'));
				$historyStatistique = $cmd->getStatistique($startHist, date('Y-m-d H:i:s'));
				if ($historyStatistique['avg'] == 0 && $historyStatistique['min'] == 0 && $historyStatistique['max'] == 0) {
					$cmd_value = $cmd->execCmd();
					$cmd->setConfiguration($cmdName . '_averageHistory', round(floatval($cmd_value), $precision));
					$cmd->setConfiguration($cmdName . '_minHistory', round(floatval($cmd_value), $precision));
					$cmd->setConfiguration($cmdName . '_maxHistory', round(floatval($cmd_value), $precision));
					$cmd->save();
				} else {
					$cmd->setConfiguration($cmdName . '_averageHistory', round($historyStatistique['avg'], $precision));
					$cmd->setConfiguration($cmdName . '_minHistory', round($historyStatistique['min'], $precision));
					$cmd->setConfiguration($cmdName . '_maxHistory', round($historyStatistique['max'], $precision));
					$cmd->save();
				}
				// Tendance
				if ($this->getConfiguration('stats_tendance', '0') == '1') {
					$tendance = $cmd->getTendance($startHist, date('Y-m-d H:i:s'));
					log::add('Monitoring', 'debug', '[' . $this->getName() . '][getStats] Tendance :: ' . $cmd->getName() . ' :: ' . strval($tendance));
					if ($tendance > config::byKey('historyCalculTendanceThresholddMax')) {
						$cmd->setConfiguration($cmdName . '_tendance', 'arrow-up');
					} elseif ($tendance < config::byKey('historyCalculTendanceThresholddMin')) {
						$cmd->setConfiguration($cmdName . '_tendance', 'arrow-down');
					} else {
						$cmd->setConfiguration($cmdName . '_tendance', 'minus');
					}
					$cmd->save();

				} else {
					$cmd->setConfiguration($cmdName . '_tendance', '');
					$cmd->save();
				}
			} else {
				$cmd->setConfiguration($cmdName . '_averageHistory', '-');
				$cmd->setConfiguration($cmdName . '_minHistory', '-');
				$cmd->setConfiguration($cmdName . '_maxHistory', '-');
				$cmd->setConfiguration($cmdName . '_tendance', '');
				$cmd->save();
			}
		} catch (Exception $e) {
			log::add('Monitoring', 'error', '[' . $this->getName() . '][getStats] ' . $e->getMessage());
		}
	}

	public function getDefaultIcon(string $cmdName) {
		$icon = '';
		switch ($cmdName) {
			case 'distri_name':
				$icon = '<i class="fab fa-linux"></i>';
				break;
			case 'load_avg':
				$icon = '<i class="fas fa-chart-line"></i>';
				break;
			case 'uptime':
				$icon = '<i class="fas fa-hourglass-half"></i>';
				break;
			case 'hdd':
				$icon = '<i class="fas fa-hdd"></i>';
				break;
			case 'memory':
				$icon = '<i class="fas fa-database"></i>';
				break;
			case 'swap':
				// $icon = '<i class="fas fa-exchange-alt"></i>';
				$icon = '<i class="fas fa-layer-group"></i>';
				break;
			case 'network':
				$icon = '<i class="fas fa-network-wired"></i>';
				break;
			case 'network_infos':
				$icon = '<i class="fas fas fa-ethernet"></i>';
				break;
			case 'cpu':
				$icon = '<i class="fas fa-microchip"></i>';
				break;
			case 'syno_hddv2':
			case 'syno_hddv3':
			case 'syno_hddv4':
				$icon = '<i class="far fa-hdd"></i>';
				break;
			case 'syno_hddusb':
			case 'syno_hddesata':
				$icon = '<i class="fab fa-usb"></i>';
				break;
			case 'perso1':
			case 'perso2':
				$icon = '<i class="fas fa-cogs"></i>';
				break;
			default:
				$icon = '';
				break;
		}
		return $icon;
	}

	public function getCmdReplace(string $cmdName, array $cmdOptions, &$replace) {
		$cmd = $this->getCmd(null, $cmdName);
		$isCmdObject = is_object($cmd);
		$cmdNamePrefix = '#' . $cmdName;

		$optionActions = [
			'exec' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '#'] = $isCmdObject ? $cmd->execCmd() : '';
			},
			'id' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '_id#'] = $isCmdObject ? $cmd->getId() : '';
			},
			'icon' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace, $cmdName) {
				$replace[$cmdNamePrefix . '_icon#'] = $isCmdObject ? (!empty($cmd->getDisplay('icon')) ? $cmd->getDisplay('icon') : $this->getDefaultIcon($cmdName)) : '';
			},
			'display' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '_display#'] = ($isCmdObject && $cmd->getIsVisible()) ? "block" : "none";
			},
			'display_inline' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '_display#'] = ($isCmdObject && $cmd->getIsVisible()) ? "inline-block" : "none";
			},
			'collect' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '_collect#'] = $isCmdObject ? $cmd->getCollectDate() : "-";
			},
			'value' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '_value#'] = $isCmdObject ? $cmd->getValueDate() : "-";
			},
			'pull_use_custom' => function() use ($cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '_custom#'] = $this->getConfiguration('pull_use_custom', '0');
			},
			'name' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace) {
				$replace[$cmdNamePrefix . '_name#'] = $isCmdObject ? $cmd->getName() : '';
			},
			'unite' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace, $cmdName) {
				$replace[$cmdNamePrefix . '_unite#'] = $isCmdObject ? $cmd->getUnite() : '';
			},
			'colorlow' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace, $cmdName) {
				$replace[$cmdNamePrefix . '_colorlow#'] = $isCmdObject ? $cmd->getConfiguration($cmdName . '_colorlow') : '';
			},
			'colorhigh' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, &$replace, $cmdName) {
				$replace[$cmdNamePrefix . '_colorhigh#'] = $isCmdObject ? $cmd->getConfiguration($cmdName . '_colorhigh') : '';
			},
			'stats' => function() use ($isCmdObject, $cmd, $cmdNamePrefix, $cmdName, &$replace) {
				$replace[$cmdNamePrefix . '_averageHistory#'] = $isCmdObject ? $cmd->getConfiguration($cmdName . '_averageHistory') : '-';
				$replace[$cmdNamePrefix . '_minHistory#'] = $isCmdObject ? $cmd->getConfiguration($cmdName . '_minHistory') : '-';
				$replace[$cmdNamePrefix . '_maxHistory#'] = $isCmdObject ? $cmd->getConfiguration($cmdName . '_maxHistory') : '-';
				$replace[$cmdNamePrefix . '_tendance#'] = ($isCmdObject && $cmd->getConfiguration($cmdName . '_tendance', '') !== '') ? ' <i style="color: var(--al-info-color) !important;" class="fas fa-' . $cmd->getConfiguration($cmdName . '_tendance') . '"></i>' : '';
			}
		];

		foreach ($cmdOptions as $option) {
			if (isset($optionActions[$option])) {
				$optionActions[$option]();
			} else {
				log::add('Monitoring', 'error', '[' . $this->getName() . '][CmdReplace] Option inconnue :: ' . $option);
			}
		}
	}

	public function getRemoteArchKeys($hostId, $osType = '') {
		[$archKey, $archSubKey, $archKeyType, $ARMv, $distri_name_value] = ['unknown', '', 'Unknown', '', ''];
		
		if ($osType == 'Synology') {
			// Synology
			$archKey = 'syno';
			$archSubKey = '';
			$archKeyType = 'Synology';
		} elseif ($osType == 'QNAP') {
			// QNAP
			$archKey = 'qnap';
			$archSubKey = '';
			$archKeyType = 'QNAP';
		} elseif ($osType == 'AsusWRT') {
			// AsusWRT
			$archKey = 'asuswrt';
			$archSubKey = '';
			$archKeyType = 'AsusWRT';
		} else {
			// ARMv
			$ARMv_cmd = "LC_ALL=C lscpu 2>/dev/null | awk -F':' '/Architecture/ { print $2 }' | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
			$ARMv = $this->execSSH($hostId, $ARMv_cmd, 'ARMv');

			$foundARMv = false;

			if (!empty($ARMv)) {
				$foundARMv = true;
				$archKey = $ARMv;
				$archKeyType = 'ARMv';
			}

			// Search with distri_name
			$distri_name_cmd = "awk -F'=' '/^PRETTY_NAME/ { print $2 }' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
			$distri_name_value = $this->execSSH($hostId, $distri_name_cmd, 'DistriName');

			// Search for specific distribution in distri_name_value
			$distriValues = ['OpenELEC', 'LibreELEC', 'piCorePlayer', 'FreeBSD'];
			$foundDistri = false;
			foreach ($distriValues as $distriValue) {
				if (stripos($distri_name_value, $distriValue) !== false) {
					$foundDistri = true;
					if ($foundARMv) {
						$archSubKey = $distriValue;
						$archKeyType .= ' + DistriName';
					} else {
						$archKey = $distriValue;
						$archSubKey = '';
						$archKeyType = 'DistriName';
					}
					break;
				}
			}
			if (!$foundDistri) {
				// Search with uname
				$uname_cmd = "uname -a 2>/dev/null";
				$uname = $this->execSSH($hostId, $uname_cmd, 'uname');
				$unameValues = ['medion'];
				foreach ($unameValues as $unameValue) {
					if (stripos($uname, $unameValue) !== false) {
						if ($foundARMv) {
							$archSubKey = $unameValue;
							$archKeyType .= ' + Uname';
						} else {
							$archKey = $unameValue;
							$archKeyType = 'Uname';
						}
						break;
					}
				}
			}
		}

		return [$archKey, $archSubKey, $archKeyType, $ARMv, $distri_name_value];
	}

	public function getCommands($key, $subKey = '', $cartereseau = '', $confLocalorRemote = 'local') {
		if (!empty($subKey)) {
			log::add('Monitoring', 'debug', '['. $this->getName() .'][getCommands] Key / SubKey (LocalorRemote) :: ' . $key . ' / ' . $subKey . ' (' . $confLocalorRemote . ')');
		} else {
			log::add('Monitoring', 'debug', '['. $this->getName() .'][getCommands] Key (LocalorRemote) :: ' . $key . ' (' . $confLocalorRemote . ')');
		}
		
		// Cmd Templates
		
		$hdd_command = "LC_ALL=C df -l 2>/dev/null | grep '%s' | head -1 | awk '{ print $2,$3,$4,$5 }'";
		// Lorsque l'option -l n'est pas disponible
		$hdd_command_alt = "LC_ALL=C \\df 2>/dev/null | grep '%s' | head -1 | awk '{ print $2,$3,$4,$5 }'";
		
		$distri_bits_command = "getconf LONG_BIT 2>/dev/null";
		// Lorsque la commande getconf n'est pas disponible
		$distri_bits_command_alt = "uname -m | grep -q '64' && echo \"64\" || echo \"32\"";

		$memory_command = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$6,$7 }'";
		$swap_command = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
		$network_command = "cat /proc/net/dev 2>/dev/null | grep \"" . $cartereseau . ":\" | awk '{ print $1,$2,$10 }' | awk -v ORS=\"\" '{ gsub(/:/, \"\"); print }'";
		$network_ip_command = "LC_ALL=C ip -o -f inet a 2>/dev/null | grep \"" . $cartereseau . " \" | awk '{ print $4 }' | awk -v ORS=\"\" '{ gsub(/\/[0-9]+/, \"\"); print }'";
		$load_avg_command = "cat /proc/loadavg 2>/dev/null";
		$uptime_command = "awk '{ print $1 }' /proc/uptime 2>/dev/null | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
		$release_command = "awk -F'=' '/%s/ { print $2 }' /etc/*-release 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";
		
		$cpu_freq_x86_array = [
			1 => ['cmd', "LC_ALL=C lscpu 2>/dev/null | grep -Ei '^CPU( max)? MHz' | awk '{ print \$NF }'"], // OK pour LXC Linux, Proxmox, Debian 10/11
			2 => ['cmd', "cat /proc/cpuinfo 2>/dev/null | grep -i '^cpu MHz' | head -1 | cut -d':' -f2 | awk '{ print \$NF }'"] // OK pour Debian 10,11,12, Ubuntu 22.04, pve-debian12
		];
		$cpu_freq_arm_array = [
			1 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/cpuinfo_max_freq 2>/dev/null"],
			2 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null"],
		];

		$cpu_temp_zone0_array = [
			1 => ['cmd', "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null"]
		];

		$cpu_nb_x86_command = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print \$NF }'";
		$cpu_nb_aarch64_command = "LC_ALL=C lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'";
		$cpu_nb_arm6l_command = "LC_ALL=C lscpu 2>/dev/null | grep 'CPU(s):' | awk '{ print $2 }'";
		$cpu_nb_arm_command = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";

		$pcp_version_command = "awk -F'=' '/^PCPVERS/ { print $2 }' /usr/local/etc/pcp/pcpversion.cfg | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'";

		// Local
		$cmdLocalCommon = [
			'distri_bits' => $distri_bits_command,
			'distri_name' => sprintf($release_command, '^PRETTY_NAME'),
			'os_version' => sprintf($release_command, '^VERSION_ID'),
			'uptime' => $uptime_command,
			'load_avg' => $load_avg_command,
			'memory' => $memory_command,
			'swap' => $swap_command,
			'hdd' => sprintf($hdd_command, '/$'),
			'network' => $network_command, // on récupère le nom de la carte en plus pour l'afficher dans les infos
			'network_ip' => $network_ip_command,
		];
	
		// Local Specific
		$cmdLocalSpecific = [
			'x86_64' => [
				'cpu_nb' => $cpu_nb_x86_command,
				'cpu_freq' => $cpu_freq_x86_array,
				'cpu_temp' => [
					1 => ['file', "/sys/devices/virtual/thermal/thermal_zone0/temp"], // OK Dell Whyse
					2 => ['file', "/sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input"], // OK AOpen DE2700
					3 => ['cmd', "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"], // OK AMD Ryzen
					4 => ['cmd', "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"Package\")) { printf(\"%f\",$4);} }'"], // OK by sensors
					5 => ['cmd', "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")) { printf(\"%f\",$3);} }'"] // OK by sensors MB
				],
			],
			'aarch64' => [
				'cpu_nb' => $cpu_nb_aarch64_command,
				'cpu_freq' => [
					1 => ['file', "/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq"], 
					2 => ['file', "/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq"]
				],
				'cpu_temp' => [
					1 => ['file', "/sys/class/thermal/thermal_zone0/temp"], // OK RPi2/3, Odroid
					2 => ['file', "/sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1"] // OK Banana Pi (Cubie surement un jour...)
				],
			],
			'armv6l' => [
				'cpu_nb' => $cpu_nb_arm6l_command,
				'cpu_freq' => [
					1 => ['file', "/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq"],
					2 => ['file', "/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq"]
				],
				'cpu_temp' => [
					1 => ['file', "/sys/class/thermal/thermal_zone0/temp"]
				]
			],
		];

		$cmdLocalSpecific['armv7l'] = &$cmdLocalSpecific['aarch64'];
		$cmdLocalSpecific['i686'] = &$cmdLocalSpecific['x86_64'];
		$cmdLocalSpecific['i386'] = &$cmdLocalSpecific['x86_64'];

		// Remote
		$cmdRemoteCommon = [
			'uptime' => $uptime_command,
			'load_avg' => $load_avg_command,
			'memory' => $memory_command,
			'swap' => $swap_command,
			'network' => $network_command, // on récupère le nom de la carte en plus pour l'afficher dans les infos
			'network_ip' => $network_ip_command,
		];

		// Remote Specific
		$cmdRemoteSpecific = [
			'armv6l' => [ // ARMv
				'distri_bits' => ['cmd', $distri_bits_command],
				'distri_name' => ['cmd', sprintf($release_command, '^PRETTY_NAME')],
				'os_version' => sprintf($release_command, '^VERSION_ID'),
				'cpu_nb' => $cpu_nb_arm6l_command,
				'cpu_freq' => [
					1 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null"],
					2 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null"]
				],
				'cpu_temp' => $cpu_temp_zone0_array,
				'hdd' => sprintf($hdd_command, '/$')
			],
			'aarch64' => [ // ARMv (+ armv7l)
				'distri_bits' => ['cmd', $distri_bits_command],
				'distri_name' => ['cmd', sprintf($release_command, '^PRETTY_NAME')],
				'os_version' => sprintf($release_command, '^VERSION_ID'),
				'cpu_nb' => $cpu_nb_aarch64_command,
				'cpu_freq' => [
					1 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null"],
					2 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null"]
				],
				'cpu_temp' => [				
					1 => ['cmd', "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null"], // OK RPi2
					2 => ['cmd', "cat /sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1_input 2>/dev/null"] // OK Banana Pi (Cubie surement un jour...)
				],
				'hdd' => sprintf($hdd_command, '/$')
			],
			'x86_64' => [ // ARMv
				'distri_bits' => ['cmd', $distri_bits_command],
				'distri_name' => ['cmd', sprintf($release_command, '^PRETTY_NAME')],
				'os_version' => sprintf($release_command, '^VERSION_ID'),
				'cpu_nb' => $cpu_nb_x86_command,
				'cpu_freq' => $cpu_freq_x86_array,
				'cpu_temp' => [
					1 => ['cmd', "cat /sys/devices/virtual/thermal/thermal_zone0/temp 2>/dev/null"], // Default
					2 => ['cmd', "cat /sys/devices/virtual/thermal/thermal_zone1/temp 2>/dev/null"], // Default Zone 1
					3 => ['cmd', "cat /sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input 2>/dev/null"], // OK AOpen DE2700
					4 => ['cmd', "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"], // OK Search temp?_input
					5 => ['cmd', "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"Package\")) { printf(\"%f\",$4);} }'"], // OK by sensors
					6 => ['cmd', "LC_ALL=C sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")) { printf(\"%f\",$3);} }'"] // OK by sensors
				],
				'hdd' => sprintf($hdd_command, '/$')
			],
			'LibreELEC' => [
				// LibreELEC :: SubKey (détecté via ARMv comme x86_64 / armv7l, mais il manque le disque et le 32bits)
				'distri_bits' => ['cmd', $distri_bits_command_alt],
				'hdd' => sprintf($hdd_command, '/storage')
			],
			'OpenELEC' => [
				// OpenElec :: SubKey (distri_name ?) 
				'distri_bits' => ['cmd', $distri_bits_command_alt],
				'hdd' => sprintf($hdd_command, '/storage')
			],
			'piCorePlayer' => [ // distri_name
				'ARMv' => ['cmd', "uname -m 2>/dev/null"],
				'distri_bits' => ['cmd', "uname -m | grep -q '64' && echo \"64\" || echo \"32\""],
				// 'distri_name' => ['cmd', "uname -a 2>/dev/null | awk '{ print $2,$3 }'"],
				'distri_name' => ['cmd', $pcp_version_command],
				// 'os_version' => sprintf($release_command, '^VERSION'),
				'os_version' => $pcp_version_command,
				'network_ip' => "ifconfig | awk '/^[a-z]/ { iface=$1 } /inet / && $2 != \"addr:127.0.0.1\" { print iface, $2 }' | head -1 | awk -v ORS=\"\" -F'[: ]' '{print $3}'",
				'cpu_nb' => "grep 'processor' /proc/cpuinfo 2>/dev/null | wc -l",
				'cpu_freq' => $cpu_freq_arm_array,
				'cpu_temp' => $cpu_temp_zone0_array,
				'hdd' => sprintf($hdd_command_alt, '/mnt/mmcblk')
			],
			'FreeBSD' => [ // distri_name
				// pour récupérer la carte réseau et l'adrese IP : ifconfig | awk '/^[a-z]/ { iface=$1 } /inet / && $2 != "127.0.0.1" { print iface, $2 }' | awk -v ORS="" -F': ' '{print $1, $2}'
				// récuperer le nom de la carte réseau : "ifconfig -u -l ether | awk -v ORS=\"\" '{ print $1 }'"
				// Stats réseaux avec nom de la carte réseau, adresse IP, et TX, RX : "netstat -b -i -n -f inet | grep '" . $cartereseau . "' | head -1 | awk -v ORS=\"\" '{ print $1,$8,$11 }'"
				// Adresse IP : "ifconfig -u le0 | awk -v ORS=\"\" '/inet / { print $2 }'"
				'ARMv' => ['cmd', "sysctl hw.machine | awk '{ print $2}'"],
				'distri_bits' => ['cmd', $distri_bits_command],
				'distri_name' => ['cmd', "uname -a 2>/dev/null | awk '{ print $1,$3 }'"],
				'os_version' => sprintf($release_command, '^VERSION_ID'),
				'uptime' => "sysctl -n kern.boottime | awk -v ORS=\"\" -F'[{}=,]' '{gsub(/ /, \"\", $3); gsub(/ /, \"\", $5); print $3 \".\" $5}'",
				'load_avg' => "sysctl -n vm.loadavg | awk '{ print $2, $3, $4 }'",
				'memory' => "dmesg | grep Mem | tr '\n' ' ' | awk '{ print $4,$10 }'", // A Corriger, ne fonctionne pas
				'network' => "netstat -b -i -n -f inet | grep '" . $cartereseau . "' | awk -v ORS=\"\" '{ print $1,$8,$11 }'", // on récupère le nom de la carte en plus pour l'afficher dans les infos
				'network_ip' => "ifconfig -u " . $cartereseau . " | awk -v ORS=\"\" '/inet / { print $2 }'",
				'cpu_nb' => "sysctl hw.ncpu | awk '{ print $2}'",
				'cpu_freq' => [
					1 => ['cmd', "sysctl -a | egrep -E 'cpu.0.freq' | awk '{ print $2 }'"],
				],
				'cpu_temp' => [
					1 => ['cmd', "sysctl -a | egrep -E 'cpu.0.temp' | awk '{ print $2 }'"],
				],
				'hdd' => sprintf($hdd_command, '/$')
			],
			'medion' => [ // uname
				'ARMv' => ['value', "arm"],
				'distri_bits' => ['cmd', $distri_bits_command],
				'distri_name' => ['cmd', "cat /etc/*-release 2>/dev/null | awk '/^DistName/ { print $2 }'"], // TODO A revoir avec la syntaxe des autres distri_name
				'os_version' => "cat /etc/*-release 2>/dev/null | awk '/^VersionName/ { print $2 }'", // TODO A revoir avec la syntaxe des autres os_version
				'cpu_nb' => "cat /proc/cpuinfo 2>/dev/null | awk -F':' '/^Processor/ { print $2}'",
				'cpu_freq' => [
					1 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null"],
					2 => ['cmd', "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null"]
				],
				'cpu_temp' => [
					1 => ['cmd', "sysctl -a | egrep -E 'cpu.0.temp' | awk '{ print $2 }'"],
				],
				'hdd' => sprintf($hdd_command, '/home$')
			],
			'syno'=> [ // Synology
				'ARMv' => ['value', "syno"],
				'distri_bits' => ['value', ""],
				'distri_name' => ['value', ""],
				'os_version' => "awk -F'=' '/productversion/ { print $2 }' /etc.defaults/VERSION 2>/dev/null | awk -v ORS=\"\" '{ gsub(/\"/, \"\"); print }'", 
				'syno_model' =>  "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null",
				'syno_model_alt' => "cat /proc/sys/kernel/syno_hw_version 2>/dev/null",
				'syno_version' => "cat /etc.defaults/VERSION 2>/dev/null | awk '{ gsub(/\"/, \"\"); print }' | awk NF=NF RS='\r\n' OFS='&'", // Récupération de tout le fichier de version pour le parser et récupérer le nom des champs
				'cpu_nb' => "cat /proc/sys/kernel/syno_CPU_info_core 2>/dev/null",
				'cpu_freq' => [
					1 => ['cmd', "cat /proc/sys/kernel/syno_CPU_info_clock 2>/dev/null"]
				],
				'cpu_temp' => [
					1 => ['cmd', "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1)"]
				],
				'hdd' => sprintf($hdd_command, 'vg1000\|volume1'),
				'syno_hddv2' => sprintf($hdd_command, 'vg1001\|volume2'), // DSM 5.x / 6.x / 7.x
				'syno_hddv3' => sprintf($hdd_command, 'vg1002\|volume3'), // DSM 5.x / 6.x / 7.x
				'syno_hddv4' => sprintf($hdd_command, 'vg1003\|volume4'), // DSM 5.x / 6.x / 7.x
				'syno_hddusb' => sprintf($hdd_command, 'usb1p1\|volumeUSB1'), // DSM 5.x / 6.x / 7.x
				'syno_hddesata' => sprintf($hdd_command, 'sdf1\|volumeSATA') // DSM 5.x / 6.x / 7.x
			],
			'asuswrt' => [
				'ARMv' => ['value', "asuswrt"],
			],
			'qnap' => [
				'ARMv' => ['value', "qnap"],
			],
		];

		$cmdRemoteSpecific['armv7l'] = &$cmdRemoteSpecific['aarch64']; // Included OS : OSMC, LibreELEC, OpenELEC?
		$cmdRemoteSpecific['mips64'] = &$cmdRemoteSpecific['aarch64'];
		$cmdRemoteSpecific['i686'] = &$cmdRemoteSpecific['x86_64'];
		$cmdRemoteSpecific['i386'] = &$cmdRemoteSpecific['x86_64'];

		if ($confLocalorRemote == 'local') {
			// Local
			$foundKey = null;
			foreach (array_keys($cmdLocalSpecific) as $arrayKey) {
				if (stripos($key, $arrayKey) !== false) {
					$foundKey = $arrayKey;
					break;
				}
			}
			if ($foundKey !== null) {
				return array_merge($cmdLocalCommon, $cmdLocalSpecific[$foundKey]);
			} else {
				throw new Exception(__('Aucune commande locale disponible pour cette architecture', __FILE__) . ' :: ' . $key);
			}
		} else {
			// Distant
			$foundKey = null;
			foreach (array_keys($cmdRemoteSpecific) as $arrayKey) {
				if (stripos($key, $arrayKey) !== false) {
					$foundKey = $arrayKey;
					break;
				}
			}
			if ($foundKey !== null) {
				$result = array_merge($cmdRemoteCommon, $cmdRemoteSpecific[$foundKey]);
				if (!empty($subKey)) {
					$foundSubKey = null;
					foreach (array_keys($cmdRemoteSpecific) as $arrayKey) {
						if (stripos($subKey, $arrayKey) !== false) {
							$foundSubKey = $arrayKey;
							break;
						}
					}
					if ($foundSubKey !== null) {
						$result = array_merge($result, $cmdRemoteSpecific[$foundSubKey]);
					} else {
						throw new Exception(__('Aucune commande distante disponible pour cette architecture', __FILE__) . ' (SubKey) :: ' . $subKey);
					}
				}
				return $result;
			} else {
				throw new Exception(__('Aucune commande distante disponible pour cette architecture', __FILE__) . ' (Key) :: ' . $key);
			}
		}	
	}

	public function getNetworkCard($_networkCard = '', $_localorremote = 'local', $_hostId = '', $_archKey = '') {
		$networkCard = '';
		if ($_networkCard == 'netautre') {
			$networkCard = trim($this->getConfiguration('cartereseauautre'));
		} elseif ($_networkCard == 'netauto') {
			$networkCard_cmd = '';
			if ($_archKey == 'FreeBSD') {
				$networkCard_cmd = "ifconfig -u -l ether 2>/dev/null | awk -v ORS=\"\" '{ print $1 }'";
			} elseif ($_archKey == 'piCorePlayer') {
				$networkCard_cmd = "ifconfig | awk '/^[a-z]/ { iface=$1 } /inet / && $2 != \"addr:127.0.0.1\" { print iface, $2 }' | head -1 | awk -v ORS=\"\" -F'[: ]' '{ print $1 }'";
			} else {
				$networkCard_cmd = "LC_ALL=C ip -o -f inet a 2>/dev/null | grep -Ev 'docker|127.0.0.1' | head -1 | awk '{ print $2 }' | awk -F'@' -v ORS=\"\" '{ print $1 }'";	
			}
			$networkCard = $_localorremote == 'local' ? $this->execSRV($networkCard_cmd, 'NetworkCard') : $this->execSSH($_hostId, $networkCard_cmd, 'NetworkCard');
		} else {
			$networkCard = $_networkCard;
		}

		log::add('Monitoring', 'debug', '['. $this->getName() .'][getNetworkCard] NetworkCard :: ' . $networkCard);
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

	public function formatCPU($_cpu_nb, $_cpu_freq, $_cpu_temp, $_OS, $_equipement) {
		$unitCPUFreq = [
			'syno' => 'MHz',
			'arm' => 'KHz',
			'x86_64' => 'MHz',
			'i686' => 'MHz',
			'i386' => 'MHz',
			'aarch64' => 'KHz',
			'armv6l' => 'KHz',
			'armv7l' => 'KHz',
			'mips64' => 'KHz',
		];

		// log::add('Monitoring', 'debug', '['. $_equipement .'][formatCPU] OS :: ' . $_OS);

		// CPUFreq
		// TODO Voir quelle est l'unité pour un medion ou un freebsd pour la fréquence des CPU
		[$cpu_freq, $cpu_freq_txt] = $this->formatFreq($_cpu_freq, $unitCPUFreq[$_OS] ?? 'KHz');	

		// CPU Temp
		$cpu_temp = $this->formatTemp($_cpu_temp);

		// CPU
		$cpu = (floatval($cpu_freq) == 0) ? $_cpu_nb . ' Socket(s)' : $_cpu_nb . ' - ' . $cpu_freq_txt;

		// CPU, CPUFreq, CPU Temp
		return [$cpu, $cpu_freq, $cpu_temp];

	}

	public function formatNetwork($_network_txrx, $_network_ip, $_equipement) {
		// Network TX, Network RX, Network Name, Network Ip, Text
		$network_ip = isset($_network_ip) ? $_network_ip : '';

		// Init result
		$result = [0.00, 0.00, '', $network_ip, '', ''];

		if (empty($_network_txrx)) {
			return $result;
		}

		$network_data = explode(' ', $_network_txrx);
		if (count($network_data) != 3) {
			return $result;
		}

		// TX, RX, Name, Network Text, NetworkInfos Text
		$network_tx = intval($network_data[2]);
		$network_rx = intval($network_data[1]);
		$network_name = $network_data[0];

		$network = __('TX', __FILE__) . ' : ' . $this->formatSize($network_tx) . ' - ' . __('RX', __FILE__) . ' : ' . $this->formatSize($network_rx);
		$network_infos = __('Carte Réseau', __FILE__) . ' : ' . $network_name . ' - ' . __('IP', __FILE__) . ' : ' . $network_ip;

		// Convert to Mo, source in octects. it's to avoid problem with big values in Jeedom History DB
		$network_tx = $network_tx != 0 ? round($network_tx / 1024 / 1024, 2) : 0.00;
		$network_rx = $network_rx != 0 ? round($network_rx / 1024 / 1024, 2) : 0.00;

		log::add('Monitoring', 'debug', '['. $_equipement .'][RESEAU] Carte Réseau / IP (TX - RX) :: ' . $network_name . ' / IP : ' . $network_ip . ' (' . $network .')');
		
		$result = [$network_tx, $network_rx, $network_name, $network_ip, $network, $network_infos];
		return $result;
	}

	public function formatSwap($_swap, $_equipement) {
		// Total, Used, Free, Used %, Free %, Text
		$result = [0.00, 0.00, 0.00, 0.0, 0.0, ''];

		if (empty($_swap)) {
			return $result;
		}

		$swap_data = explode(' ', $_swap);
		if (count($swap_data) != 3) {
			return $result;
		}

		// Total, Used, Free, Used %, Free %, Text
		$swap_total = intval($swap_data[0]);
		$swap_used = intval($swap_data[1]);
		$swap_free = intval($swap_data[2]);

		log::add('Monitoring', 'debug', '['. $_equipement .'] Swap Total :: ' . $swap_total);
		log::add('Monitoring', 'debug', '['. $_equipement .'] Swap Used :: ' . $swap_used);
		log::add('Monitoring', 'debug', '['. $_equipement .'] Swap Free :: ' . $swap_free);

		if ($swap_total != 0) {
			$swap_used_percent = round($swap_used / $swap_total * 100, 1);
			$swap_free_percent = round($swap_free / $swap_total * 100, 1);
		} else {
			$swap_used_percent = 0.0;
			$swap_free_percent = 0.0;
		}

		log::add('Monitoring', 'debug', '['. $_equipement .'] Swap Used % :: ' . $swap_used_percent);
		log::add('Monitoring', 'debug', '['. $_equipement .'] Swap Free % :: ' . $swap_free_percent);

		$swap = __('Total', __FILE__) . ' : ' . $this->formatSize($swap_total, 'Ko') . ' - ' . __('Utilisé', __FILE__) . ' : ' . $this->formatSize($swap_used, 'Ko') . ' - ' . __('Libre', __FILE__) . ' : ' . $this->formatSize($swap_free, 'Ko');
		
		// Convert to Mo, source in Ko. it's to avoid problem with big values in Jeedom History DB
		$swap_total = $swap_total != 0 ? round($swap_total / 1024, 2) : 0.00;
		$swap_used = $swap_used != 0 ? round($swap_used / 1024, 2) : 0.00;
		$swap_free = $swap_free != 0 ? round($swap_free / 1024, 2) : 0.00;

		$result = [$swap_total, $swap_used, $swap_free, $swap_used_percent, $swap_free_percent, $swap];
		return $result;
	}

	public function formatMemory($_memory, $_archKey, $_equipement) {
		$result = [0, 0, 0, 0, 0, 0.0, 0.0, 0.0, ''];

		if (empty($_memory)) {
			return $result;
		}

		if (stripos($_archKey, 'FreeBSD') === false) {
			$memory_data = explode(' ', $_memory);
			if (count($memory_data) != 5) {
				return $result;
			}

			// Total, Used, Free, Buff/Cache, Available, Used %, Free %, Buff/Cache %, Text
			$memory_total = intval($memory_data[0]);
			$memory_used = intval($memory_data[1]);
			$memory_free = intval($memory_data[2]);
			$memory_buffcache = intval($memory_data[3]);
			$memory_available = intval($memory_data[4]);

			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Total :: ' . $memory_total);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Used :: ' . $memory_used);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Free :: ' . $memory_free);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Buff/Cache :: ' . $memory_buffcache);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Available :: ' . $memory_available);

			if ($memory_total != 0) {
				$memory_used_percent = round(($memory_used + $memory_buffcache) / $memory_total * 100, 1);
				$memory_free_percent = round($memory_free / $memory_total * 100, 1);
				$memory_available_percent = round($memory_available / $memory_total * 100, 1);
			} else {
				$memory_used_percent = 0.0;
				$memory_free_percent = 0.0;
				$memory_available_percent = 0.0;
			}

			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Used % :: ' . $memory_used_percent);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Free % :: ' . $memory_free_percent);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Available % :: ' . $memory_available_percent);

			$memory = __('Total', __FILE__) . ' : ' . $this->formatSize($memory_total, 'Ko') . ' - ' . __('Utilisée', __FILE__) . ' : ' . $this->formatSize($memory_used, 'Ko') . ' - ' . __('Disponible', __FILE__) . ' : ' . $this->formatSize($memory_available, 'Ko');
			
			// Convert to Mo, source in Ko. it's to avoid problem with big values in Jeedom History DB
			$memory_total = $memory_total != 0 ? round($memory_total / 1024, 2) : 0.00;
			$memory_used = $memory_used != 0 ? round($memory_used / 1024, 2) : 0.00;
			$memory_free = $memory_free != 0 ? round($memory_free / 1024, 2) : 0.00;
			$memory_buffcache = $memory_buffcache != 0 ? round($memory_buffcache / 1024, 2) : 0.00;
			$memory_available = $memory_available != 0 ? round($memory_available / 1024, 2) : 0.00;
			
			$result = [$memory_total, $memory_used, $memory_free, $memory_buffcache, $memory_available, $memory_used_percent, $memory_free_percent, $memory_available_percent, $memory];

		} else {
			// FreeBSD
			$memory_data = explode(' ', $_memory);
			if (count($memory_data) != 2) {
				return $result;
			}

			// Total, Used*, Free, Buff/Cache = N/A, Available = N/A, Free %, Used %, Text
			$memory_total = intval($memory_data[0]);
			$memory_free = intval($memory_data[1]);
			$memory_used = $memory_total - $memory_free;

			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Total :: ' . $memory_total);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Free :: ' . $memory_free);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Used :: ' . $memory_used);

			if ($memory_total != 0) {
				$memory_free_percent = round($memory_free / $memory_total * 100, 1);
				$memory_used_percent = round($memory_used / $memory_total * 100, 1);
			} else {
				$memory_free_percent = 0.0;
				$memory_used_percent = 0.0;
			}

			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Free % :: ' . $memory_free_percent);
			log::add('Monitoring', 'debug', '['. $_equipement .'] Memory Used % :: ' . $memory_used_percent);

			$memory = __('Total', __FILE__) . ' : ' . $this->formatSize($memory_total, 'Ko') . ' - ' . __('Utilisée', __FILE__) . ' : ' . $this->formatSize($memory_used, 'Ko') . ' - ' . __('Libre', __FILE__) . ' : ' . $this->formatSize($memory_free, 'Ko');
			
			// Convert to Mo, source in Ko. it's to avoid problem with big values in Jeedom History DB
			$memory_total = $memory_total != 0 ? round($memory_total / 1024, 2) : 0.00;
			$memory_used = $memory_used != 0 ? round($memory_used / 1024, 2) : 0.00;
			$memory_free = $memory_free != 0 ? round($memory_free / 1024, 2) : 0.00;

			$result = [$memory_total, $memory_used, $memory_free, 0, 0, $memory_used_percent, $memory_free_percent, 0.0, $memory];
		}
		return $result;
	}

	public function formatLoadAvg($load) {
		$result = [0.0, 0.0, 0.0, ''];
		if (empty($load)) {
			return $result;
		}
		$load_data = explode(' ', $load);
		if (count($load_data) < 3) {
			return $result;
		}
		// Load 1, 5, 15
		$load_1 = floatval($load_data[0]);
		$load_5 = floatval($load_data[1]);
		$load_15 = floatval($load_data[2]);

		$load_txt =  '1 min : ' . $load_1 . ' - 5 min : ' . $load_5 . ' - 15 min : ' . $load_15;

		$result = [$load_1, $load_5, $load_15, $load_txt];
		return $result;
	}

	public function formatHDD($hdd_value, $hdd_name, $equipement) {
		$result = [0.00, 0.00, 0.00, 0.0, 0.0, '']; // Total, Used, Free, Used %, Free %, Text

		if (empty($hdd_value)) {
			return $result;
		}

		$hdd_data = explode(' ', $hdd_value);
		if (count($hdd_data) != 4) {
			return $result;
		}

		$hdd_total = intval($hdd_data[0]);
		$hdd_used = intval($hdd_data[1]);
		$hdd_free = intval($hdd_data[2]);
		if ($hdd_total != 0) {
			$hdd_used_percent = round($hdd_used / $hdd_total * 100, 1);
			$hdd_free_percent = round($hdd_free / $hdd_total * 100, 1);
		} else {
			$hdd_used_percent = 0.0;
			$hdd_free_percent = 0.0;
		}
		log::add('Monitoring', 'debug', '['. $equipement .'][' . $hdd_name .'] HDD Total :: ' . $hdd_total);
		log::add('Monitoring', 'debug', '['. $equipement .'][' . $hdd_name .'] HDD Used :: ' . $hdd_used);
		log::add('Monitoring', 'debug', '['. $equipement .'][' . $hdd_name .'] HDD Free :: ' . $hdd_free);
		log::add('Monitoring', 'debug', '['. $equipement .'][' . $hdd_name .'] HDD Used % :: ' . $hdd_used_percent);
		log::add('Monitoring', 'debug', '['. $equipement .'][' . $hdd_name .'] HDD Free % :: ' . $hdd_free_percent);

		$hdd = __('Total', __FILE__) . ' : ' . $this->formatSize($hdd_total, 'Ko') . ' - ' . __('Utilisé', __FILE__) . ' : ' . $this->formatSize($hdd_used, 'Ko') . ' - ' . __('Libre', __FILE__) . ' : ' . $this->formatSize($hdd_free, 'Ko');
		
		// Convert to Mo, source in Ko. it's to avoid problem with big values in Jeedom History DB
		$hdd_total = $hdd_total != 0 ? round($hdd_total / 1024, 2) : 0.00;
		$hdd_used = $hdd_used != 0 ? round($hdd_used / 1024, 2) : 0.00;
		$hdd_free = $hdd_free != 0 ? round($hdd_free / 1024, 2) : 0.00;

		// HDD Total, Used, Free, Used %, Free %, Text
		$result = [$hdd_total, $hdd_used, $hdd_free, $hdd_used_percent, $hdd_free_percent, $hdd];			
		return $result;	
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

	public function formatFreq($freq, string $start = 'MHz') {
		$units = array('KHz', 'MHz', 'GHz');
		$unitIndex = ($unitIndex = array_search($start, $units)) === false ? 0 : $unitIndex;
		$freq = floatval($freq);

		if ($start == 'KHz') {
			// Le résultat est toujours renvoyé en MHz
			$freq_result = round($freq / 1000, 1, PHP_ROUND_HALF_UP);
		} else {
			$freq_result = round($freq, 1, PHP_ROUND_HALF_UP);
		}

		while ($freq >= 1000 && $unitIndex < count($units) - 1) {
			$freq /= 1000;
			$unitIndex++;
		}

		return [$freq_result, round($freq, 1, PHP_ROUND_HALF_UP) . ' ' . $units[$unitIndex]];
	}

	public function formatTemp($temp) {
		$tempNum = floatval($temp);
		if ($tempNum > 200) {
			$tempNum = $tempNum / 1000;
		}
		return round($tempNum, 1, PHP_ROUND_HALF_UP);
	}

	public function formatUptime($uptime, $type = 'uptime') {
		if ($type == 'unix') {
			$uptimeNum = microtime(true) - floatval($uptime);
		} else {
			$uptimeNum = floatval($uptime);
		}
		
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
		return [$uptimeFormated, $uptimeNum];
	}

	public function getInformations() {
		$equipement = $this->getName();
		try {
			// Configuration Locale ou Distante
			$confLocalOrRemote = $this->getConfiguration('localoudistant');
			
			// Configuration distante
			if ($confLocalOrRemote == 'distant' && $this->getIsEnable()) {
				[$cnx_ssh, $hostId] = $this->connectSSH();
				
				if ($cnx_ssh == 'OK') {

					// Architecture Keys
					$archKey = '';
					$archSubKey = '';
					$archKeyType = '';

					// Configuration Spécifiques à un équipement
					$isSynology = ($this->getConfiguration('synology') == '1') ? true : false;
					$isAsusWRT = ($this->getConfiguration('asuswrt') == '1') ? true : false;
					$isQNAP = ($this->getConfiguration('qnap') == '1') ? true : false;

					$osType = $isSynology ? "Synology" : ($isAsusWRT ? "AsusWRT" : ($isQNAP ? "QNAP" : ''));

					// Get Architecture Keys + $ARMv + $distri_name_value
					[$archKey, $archSubKey, $archKeyType, $ARMv, $distri_name_value] = $this->getRemoteArchKeys($hostId, $osType);

					if (!empty($archSubKey)) {
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] ArchKey / ArchSubKey :: ' . $archKey . ' / ' . $archSubKey . ' (' . $archKeyType . ')');
					} else {
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] ArchKey :: ' . $archKey . ' (' . $archKeyType . ')');
					}

					$cartereseau = $this->getNetworkCard($this->getConfiguration('cartereseau'), 'remote', $hostId, $archKey);
					$commands = $this->getCommands($archKey, $archSubKey, $cartereseau, 'remote');

					$ARMv = $ARMv ?? ($commands['ARMv'][0] === 'cmd' ? $this->execSSH($hostId, $commands['ARMv'][1], 'ARMv') : $commands['ARMv'][1]);
					
					// Pour contourner le bug de la version du piCorePlayer qui n'est pas bonne dans le fichier /etc/os-release
					if ($archKey == "piCorePlayer") {
						$distri_name_value = $commands['distri_name'][0] === 'cmd' ? $this->execSSH($hostId, $commands['distri_name'][1], 'DistriName') : $commands['distri_name'][1];
					} else {
						$distri_name_value = $distri_name_value ?? ($commands['distri_name'][0] === 'cmd' ? $this->execSSH($hostId, $commands['distri_name'][1], 'DistriName') : $commands['distri_name'][1]);	
					}
					$distri_bits = $commands['distri_bits'][0] === 'cmd' ? $this->execSSH($hostId, $commands['distri_bits'][1], 'DistriBits') : $commands['distri_bits'][1];
					
					$os_version_value = $this->execSSH($hostId, $commands['os_version'], 'OsVersion');

					$uptime_value = $this->execSSH($hostId, $commands['uptime'], 'Uptime');
					$load_avg_value = $this->execSSH($hostId, $commands['load_avg'], 'LoadAverage');
					$memory_value = $this->execSSH($hostId, $commands['memory'], 'Memory');
					$swap_value = $this->execSSH($hostId, $commands['swap'], 'Swap');
					$hdd_value = $this->execSSH($hostId, $commands['hdd'], 'HDD');
					$network_value = $this->execSSH($hostId, $commands['network'], 'ReseauRXTX');
					$network_ip_value = $this->execSSH($hostId, $commands['network_ip'], 'ReseauIP');
					$cpu_nb = $this->execSSH($hostId, $commands['cpu_nb'], 'NbCPU');
					
					extract($this->getCPUFreq($commands['cpu_freq'], $equipement, 'remote', $hostId));
					extract($this->getCPUTemp($commands['cpu_temp'], $equipement, 'remote', $hostId));

					if ($isSynology) {
						$syno_model_cmd = $this->getConfiguration('syno_alt_name') == '1' ? $commands['syno_model_alt'] : $commands['syno_model'];
						$syno_model = $this->execSSH($hostId, $syno_model_cmd, 'SynoModel');

						$syno_version_file = $this->execSSH($hostId, $commands['syno_version'], 'SynoVersion');
						
						$syno_hddv2_value = $this->getConfiguration('synologyv2') == '1' ? $this->execSSH($hostId, $commands['syno_hddv2'], 'SynoHDDv2') : '';
						$syno_hddv3_value = $this->getConfiguration('synologyv3') == '1' ? $this->execSSH($hostId, $commands['syno_hddv3'], 'SynoHDDv3') : '';
						$syno_hddv4_value = $this->getConfiguration('synologyv4') == '1' ? $this->execSSH($hostId, $commands['syno_hddv4'], 'SynoHDDv4') : '';
						$syno_hddusb_value = $this->getConfiguration('synologyusb') == '1' ? $this->execSSH($hostId, $commands['syno_hddusb'], 'SynoHDDUSB') : '';
						$syno_hddesata_value = $this->getConfiguration('synologyesata') == '1' ? $this->execSSH($hostId, $commands['syno_hddesata'], 'SynoHDDeSATA') : '';
					}

					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] DistriName :: ' . $distri_name_value);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] DistriBits :: ' . $distri_bits);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] OsVersion :: ' . $os_version_value);
					
					if ($isSynology) {
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] SynoModel :: ' . $syno_model);
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] SynoVersion :: ' . $syno_version_file);
					}
					
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] Uptime :: ' . $uptime_value);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] LoadAverage :: ' . $load_avg_value);
					
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] Memory :: ' . $memory_value);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] Swap :: ' . $swap_value);
					
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] HDD :: ' . $hdd_value);

					if ($isSynology) {
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] SynoHDDv2 :: ' . $syno_hddv2_value);
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] SynoHDDv3 :: ' . $syno_hddv3_value);
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] SynoHDDv4 :: ' . $syno_hddv4_value);
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] SynoHDDUSB :: ' . $syno_hddusb_value);
						log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] SynoHDDeSATA :: ' . $syno_hddesata_value);
					}
					
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] ReseauRXTX :: ' . $network_value);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] ReseauIP :: ' . $network_ip_value);
					
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] NbCPU :: ' . $cpu_nb);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] CPUFreq :: ' . $cpu_freq);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] CPUFreq Id :: ' . $cpu_freq_id);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] CPUTemp :: ' . $cpu_temp);
					log::add('Monitoring', 'debug', '['. $equipement .'][REMOTE] CPUTemp Id :: ' . $cpu_temp_id);

					// Perso1 Command
					$perso1_cmd = $this->getCmdPerso('perso1');
					$perso1 = !empty($perso1_cmd) ? $this->execSSH($hostId, $perso1_cmd, 'Perso1') : '';

					// Perso2 Command
					$perso2_cmd = $this->getCmdPerso('perso2');
					$perso2 = !empty($perso2_cmd) ? $this->execSSH($hostId, $perso2_cmd, 'Perso2') : '';

				}
			}
			elseif ($this->getConfiguration('localoudistant') == 'local' && $this->getIsEnable()) {
				$cnx_ssh = 'No';
				
				// ARMv Command
				$ARMv_cmd = "LC_ALL=C lscpu 2>/dev/null | awk -F':' '/Architecture/ { print $2 }' | awk -v ORS=\"\" '{ gsub(/^[[:space:]]+|[[:space:]]+$/, \"\"); print }'";
				$ARMv = $this->execSRV($ARMv_cmd, 'ARMv');
				
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] ARMv :: ' . $ARMv);

				$cartereseau = $this->getNetworkCard($this->getConfiguration('cartereseau'), 'local');
				$commands = $this->getCommands($ARMv, '', $cartereseau, 'local');

				$distri_bits = $this->execSRV($commands['distri_bits'], 'DistriBits');
				$distri_name_value = $this->execSRV($commands['distri_name'], 'DistriName');
				$os_version_value = $this->execSRV($commands['os_version'], 'OsVersion');
				$uptime_value = $this->execSRV($commands['uptime'], 'Uptime');
				$load_avg_value = $this->execSRV($commands['load_avg'], 'LoadAverage');
				$memory_value = $this->execSRV($commands['memory'], 'Memory');
				$swap_value = $this->execSRV($commands['swap'], 'Swap');
				$hdd_value = $this->execSRV($commands['hdd'], 'HDD');
				$network_value = $this->execSRV($commands['network'], 'ReseauRXTX');
				$network_ip_value = $this->execSRV($commands['network_ip'], 'ReseauIP');
				$cpu_nb = $this->execSRV($commands['cpu_nb'], 'NbCPU');

				extract($this->getCPUFreq($commands['cpu_freq'], $equipement));
				extract($this->getCPUTemp($commands['cpu_temp'], $equipement));
				
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] DistriName :: ' . $distri_name_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] DistriBits :: ' . $distri_bits);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] OsVersion :: ' . $os_version_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] Uptime :: ' . $uptime_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] LoadAverage :: ' . $load_avg_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] Memory :: ' . $memory_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] Swap :: ' . $swap_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] HDD :: ' . $hdd_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] ReseauRXTX :: ' . $network_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] ReseauIP :: ' . $network_ip_value);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] NbCPU :: ' . $cpu_nb);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] CPUFreq :: ' . $cpu_freq);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] CPUFreq Id :: ' . $cpu_freq_id);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] CPUTemp :: ' . $cpu_temp);
				log::add('Monitoring', 'debug', '['. $equipement .'][LOCAL] CPUTemp Id :: ' . $cpu_temp_id);

				// Perso1 Command
				$perso1_cmd = $this->getCmdPerso('perso1');
				$perso1 = !empty($perso1_cmd) ? $this->execSRV($perso1_cmd, 'Perso1') : '';

				// Perso2 Command
				$perso2_cmd = $this->getCmdPerso('perso2');
				$perso2 = !empty($perso2_cmd) ? $this->execSRV($perso2_cmd, 'Perso2') : '';
	
			}
	
			// Traitement des données récupérées
			if (isset($cnx_ssh)) {

				// Connexion Local ou Connexion SSH OK
				if ($this->getConfiguration('localoudistant') == 'local' || $cnx_ssh == 'OK') {

					// Synology (New)
					if ($isSynology) {
						// Syno DistriName (New)
						$distri_name = isset($syno_version_file, $syno_model) ? $this->getSynoVersion($syno_version_file, $syno_model, $equipement) : '';

						// Syno Volume 2
						if ($this->getConfiguration('synologyv2') == '1') {
							[$syno_hddv2_total, $syno_hddv2_used, $syno_hddv2_free, $syno_hddv2_used_percent, $syno_hddv2_free_percent, $syno_hddv2] = isset($syno_hddv2_value) ? $this->formatHDD($syno_hddv2_value, 'Syno HDDv2', $equipement) : [0, 0, 0, 0.0, 0.0, ''];
						}

						// Syno Volume 3
						if ($this->getConfiguration('synologyv3') == '1') {
							[$syno_hddv3_total, $syno_hddv3_used, $syno_hddv3_free, $syno_hddv3_used_percent, $syno_hddv3_free_percent, $syno_hddv3] = isset($syno_hddv3_value) ? $this->formatHDD($syno_hddv3_value, 'Syno HDDv3', $equipement) : [0, 0, 0, 0.0, 0.0, ''];
						}

						// Syno Volume 4
						if ($this->getConfiguration('synologyv4') == '1') {
							[$syno_hddv4_total, $syno_hddv4_used, $syno_hddv4_free, $syno_hddv4_used_percent, $syno_hddv4_free_percent, $syno_hddv4] = isset($syno_hddv4_value) ? $this->formatHDD($syno_hddv4_value, 'Syno HDDv4', $equipement) : [0, 0, 0, 0.0, 0.0, ''];
						}
						
						// Syno Volume USB
						if ($this->getConfiguration('synologyusb') == '1') {
							[$syno_hddusb_total, $syno_hddusb_used, $syno_hddusb_free, $syno_hddusb_used_percent, $syno_hddusb_free_percent, $syno_hddusb] = isset($syno_hddusb_value) ? $this->formatHDD($syno_hddusb_value, 'Syno HDDUSB', $equipement) : [0, 0, 0, 0.0, 0.0, ''];
						}

						// Syno Volume eSATA
						if ($this->getConfiguration('synologyesata') == '1') {
							[$syno_hddesata_total, $syno_hddesata_used, $syno_hddesata_free, $syno_hddesata_used_percent, $syno_hddesata_free_percent, $syno_hddesata] = isset($syno_hddesata_value) ? $this->formatHDD($syno_hddesata_value, 'Syno HDDeSATA', $equipement) : [0, 0, 0, 0.0, 0.0, ''];
						}
					} elseif ($archKey == 'medion') {
						// Medion DistriName (New)
						$distri_name = isset($distri_name_value, $os_version_value) ? 'Medion/Linux ' . $os_version_value . ' (' . trim($distri_name_value) . ')' : ''; 
					} else {
						// General DistriName (New)
						$distri_name = isset($distri_name_value, $distri_bits, $ARMv) ? trim($distri_name_value) . ' ' . $distri_bits . 'bits (' . $ARMv . ')' : '';
					}
	
					// Uptime (New)
					if ($archKey == 'FreeBSD') {
						[$uptime, $uptime_sec] = isset($uptime_value) ? $this->formatUptime($uptime_value, 'unix') : ['', 0];
					} else {
						[$uptime, $uptime_sec] = isset($uptime_value) ? $this->formatUptime($uptime_value) : ['', 0];
					}
	
					// LoadAverage (New)
					[$load_avg_1mn, $load_avg_5mn, $load_avg_15mn, $load_avg] = isset($load_avg_value) ? $this->formatLoadAvg($load_avg_value) : [0.0, 0.0, 0.0, ''];
	
					// Memory (New)
					[$memory_total, $memory_used, $memory_free, $memory_buffcache, $memory_available, $memory_used_percent, $memory_free_percent, $memory_available_percent, $memory] = isset($memory_value) ? $this->formatMemory($memory_value, $archKey, $equipement) : [0, 0, 0, 0, 0, 0.0, 0.0, 0.0, ''];
	
					// Swap (New)
					[$swap_total, $swap_used, $swap_free, $swap_used_percent, $swap_free_percent, $swap_display] = isset($swap_value) ? $this->formatSwap($swap_value, $equipement) : [0, 0, 0, 0.0, 0.0, ''];
	
					// Réseau (New)
					[$network_tx, $network_rx, $network_name, $network_ip, $network, $network_infos] = isset($network_value) ? $this->formatNetwork($network_value, $network_ip_value, $equipement) : [0, 0, '', '', '', ''];
	
					// HDD (New)
					[$hdd_total, $hdd_used, $hdd_free, $hdd_used_percent, $hdd_free_percent, $hdd] = isset($hdd_value) ? $this->formatHDD($hdd_value, 'HDD', $equipement) : [0, 0, 0, 0.0, 0.0, ''];
					
					// CPU (New)
					[$cpu, $cpu_freq, $cpu_temp] = isset($cpu_nb, $cpu_freq, $cpu_temp) ? $this->formatCPU($cpu_nb, $cpu_freq, $cpu_temp, $ARMv, $equipement) : ['', 0, 0];

					// Array des résultats
					if (!isset($perso1)) {$perso1 = '';}
					if (!isset($perso2)) {$perso2 = '';}
	
					$dataresult = array(
						'cnx_ssh' => $cnx_ssh,
						'distri_name' => $distri_name,
						'os_version' => $os_version_value,
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
						'network_infos' => $network_infos,
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

					$dataresult_stats = array(
						'load_avg_1mn' => 2,
						'load_avg_5mn' => 2,
						'load_avg_15mn' => 2,
						'hdd_free_percent' => 1,
						'swap_free_percent' => 1,
						'memory_available_percent' => 1,
						'cpu_temp' => 1,
						'perso1' => 2,
						'perso2' => 2
					);

					if ($isSynology) {
						if ($this->getConfiguration('synologyv2') == '1') {
							$dataresult = array_merge($dataresult, [
								'syno_hddv2' => $syno_hddv2,
								'syno_hddv2_total' => $syno_hddv2_total,
								'syno_hddv2_used' => $syno_hddv2_used,
								'syno_hddv2_free' => $syno_hddv2_free,
								'syno_hddv2_used_percent' => $syno_hddv2_used_percent,
								'syno_hddv2_free_percent' => $syno_hddv2_free_percent,
							]);

							$dataresult_stats = array_merge($dataresult_stats, [
								'syno_hddv2_free_percent' => 1,
							]);
						}
						if ($this->getConfiguration('synologyv3') == '1') {
							$dataresult = array_merge($dataresult, [
								'syno_hddv3' => $syno_hddv3,
								'syno_hddv3_total' => $syno_hddv3_total,
								'syno_hddv3_used' => $syno_hddv3_used,
								'syno_hddv3_free' => $syno_hddv3_free,
								'syno_hddv3_used_percent' => $syno_hddv3_used_percent,
								'syno_hddv3_free_percent' => $syno_hddv3_free_percent,
							]);

							$dataresult_stats = array_merge($dataresult_stats, [
								'syno_hddv3_free_percent' => 1,
							]);
						}
						if ($this->getConfiguration('synologyv4') == '1') {
							$dataresult = array_merge($dataresult, [
								'syno_hddv4' => $syno_hddv4,
								'syno_hddv4_total' => $syno_hddv4_total,
								'syno_hddv4_used' => $syno_hddv4_used,
								'syno_hddv4_free' => $syno_hddv4_free,
								'syno_hddv4_used_percent' => $syno_hddv4_used_percent,
								'syno_hddv4_free_percent' => $syno_hddv4_free_percent,
							]);

							$dataresult_stats = array_merge($dataresult_stats, [
								'syno_hddv4_free_percent' => 1,
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

							$dataresult_stats = array_merge($dataresult_stats, [
								'syno_hddusb_free_percent' => 1,
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

							$dataresult_stats = array_merge($dataresult_stats, [
								'syno_hddesata_free_percent' => 1,
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

					// getStats pour les commandes
					foreach ($dataresult_stats as $cmd_name => $precision) {
						$cmd = $this->getCmd(null, $cmd_name);
						if (is_object($cmd)) {
							$this->getStats($cmd, $cmd_name, $precision);
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
		$isSynology = $this->getConfiguration('synology') == '1' ? true : false;

		if ($confLocalOrRemote == 'distant' && $this->getIsEnable()) {
			[$cnx_ssh, $hostId] = $this->connectSSH();
				
			if ($cnx_ssh == 'OK') {
				switch ($paramaction) {
					case "reboot":
						if ($isSynology) {
							$rebootcmd = "timeout 3 sudo -S /sbin/shutdown -r now 2>/dev/null";
							log::add('Monitoring', 'info', '['. $equipement .'][SSH][SYNO-REBOOT] Lancement commande distante REBOOT');
						} else {
							$rebootcmd = "timeout 3 sudo -S reboot 2>/dev/null";
							log::add('Monitoring', 'info', '['. $equipement .'][SSH][LINUX-REBOOT] Lancement commande distante REBOOT');
						}
						$reboot = $this->execSSH($hostId, $rebootcmd, 'Reboot');
						break;
					case "poweroff":
						if ($isSynology) {
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
					if ($isSynology) {
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
					if ($isSynology) {
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

	public function dontRemoveCmd() {
        return ($this->getLogicalId() == 'refresh');
    }

	public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		$paramaction = $this->getLogicalId();

		if ($this->getType() == "action") {
			switch ($paramaction) {
				case "refresh":
					$eqLogic->getInformations();
					$eqLogic->refreshWidget();
					break;
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
