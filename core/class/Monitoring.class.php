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
require_once __DIR__ . '/../../vendor/autoload.php';

use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

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

	public static function pull() {
		log::add('Monitoring', 'debug', '[PULL] Config Pull :: '. config::byKey('configPull', 'Monitoring'));
		if (config::byKey('configPull', 'Monitoring') == '1') {
			foreach (eqLogic::byType('Monitoring', true) as $Monitoring) {
				if ($Monitoring->getConfiguration('maitreesclave') != 'local' || config::byKey('configPullLocal', 'Monitoring') == '0') {
					log::add('Monitoring', 'debug', '[PULL] Lancement (15min) :: '. $Monitoring->getName());
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

	public static function pullLocal() {
		log::add('Monitoring', 'debug', '[PULLLOCAL] Config PullLocal :: '. config::byKey('configPullLocal', 'Monitoring'));
		if (config::byKey('configPullLocal', 'Monitoring') == '1') {
			foreach (eqLogic::byType('Monitoring', true) as $Monitoring) {
				if ($Monitoring->getConfiguration('maitreesclave') == 'local') {
					log::add('Monitoring', 'debug', '[PULLLOCAL] Lancement (1min) :: '. $Monitoring->getName());
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

  	public static function postConfig_configPullLocal($value) {
	    log::add('Monitoring', 'debug', '[CONFIG-SAVE] Configuration PullLocal :: '. $value);
  	}
  	
	public static function postConfig_configPull($value) {
	    log::add('Monitoring', 'debug', '[CONFIG-SAVE] Configuration Pull :: '. $value);
  	}

	public function postUpdate() {
		/* log::add('Monitoring', 'debug', '[PostUpdate] Fonction PostUpdate :: DEBUT');
		$Perso1Visible = (is_object($this->getCmd(null,'perso1')) && $this->getCmd(null,'perso1')->getIsVisible() == '1') ? 'OK' : '';
		log::add('Monitoring', 'debug', '[PostUpdate][Perso1Visible] Perso1 :: '. $Perso1Visible);
		$Perso2Visible = (is_object($this->getCmd(null,'perso2')) && $this->getCmd(null,'perso2')->getIsVisible()) ? 'OK' : '';
		log::add('Monitoring', 'debug', '[PostUpdate][Perso2Visible] Perso2 :: '. $Perso2Visible);
		log::add('Monitoring', 'debug', '[PostUpdate] Fonction PostUpdate :: FIN'); */
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
			$MonitoringCmd->setName(__('Statut Cnx SSH', __FILE__));
			$MonitoringCmd->setEqLogic_id($this->getId());
			$MonitoringCmd->setLogicalId('cnx_ssh');
			$MonitoringCmd->setType('info');
			$MonitoringCmd->setSubType('string');
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

		$this->getInformations();
	}

	public static $_widgetPossibility = array('custom' => true, 'custom::layout' => false);

	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$_version = jeedom::versionAlias($_version);

		$replace ['#loadavg1mn_colorlow#'] = $this->getConfiguration('loadavg1mn_colorlow');
		$replace ['#loadavg1mn_colorhigh#'] = $this->getConfiguration('loadavg1mn_colorhigh');

		$replace ['#loadavg5mn_colorlow#'] = $this->getConfiguration('loadavg5mn_colorlow');
		$replace ['#loadavg5mn_colorhigh#'] = $this->getConfiguration('loadavg5mn_colorhigh');

		$replace ['#loadavg15mn_colorlow#'] = $this->getConfiguration('loadavg15mn_colorlow');
		$replace ['#loadavg15mn_colorhigh#'] = $this->getConfiguration('loadavg15mn_colorhigh');
		
		$replace ['#Mempourc_colorhigh#'] = $this->getConfiguration('Mempourc_colorhigh');
		$replace ['#Mempourc_colorlow#'] = $this->getConfiguration('Mempourc_colorlow');
		
		$replace ['#Swappourc_colorhigh#'] = $this->getConfiguration('Swappourc_colorhigh');
		$replace ['#Swappourc_colorlow#'] = $this->getConfiguration('Swappourc_colorlow');
		
		$replace ['#cpu_temp_colorlow#'] = $this->getConfiguration('cpu_temp_colorlow');
		$replace ['#cpu_temp_colorhigh#'] = $this->getConfiguration('cpu_temp_colorhigh');
		
		$replace ['#hddpourcused_colorlow#'] = $this->getConfiguration('hddpourcused_colorlow');
		$replace ['#hddpourcused_colorhigh#'] = $this->getConfiguration('hddpourcused_colorhigh');

		$replace ['#hddpourcusedv2_colorlow#'] = $this->getConfiguration('hddpourcusedv2_colorlow');
		$replace ['#hddpourcusedv2_colorhigh#'] = $this->getConfiguration('hddpourcusedv2_colorhigh');

		$replace ['#hddpourcusedusb_colorlow#'] = $this->getConfiguration('hddpourcusedusb_colorlow');
		$replace ['#hddpourcusedusb_colorhigh#'] = $this->getConfiguration('hddpourcusedusb_colorhigh');

		$replace ['#hddpourcusedesata_colorlow#'] = $this->getConfiguration('hddpourcusedesata_colorlow');
		$replace ['#hddpourcusedesata_colorhigh#'] = $this->getConfiguration('hddpourcusedesata_colorhigh');

		$replace ['#perso1_colorlow#'] = $this->getConfiguration('perso1_colorlow');
		$replace ['#perso1_colorhigh#'] = $this->getConfiguration('perso1_colorhigh');

		$replace ['#perso2_colorlow#'] = $this->getConfiguration('perso2_colorlow');
		$replace ['#perso2_colorhigh#'] = $this->getConfiguration('perso2_colorhigh');

		$namedistri = $this->getCmd(null,'namedistri');
		$replace['#namedistri#'] = (is_object($namedistri)) ? $namedistri->execCmd() : '';
		$replace['#namedistriid#'] = is_object($namedistri) ? $namedistri->getId() : '';
		$replace['#namedistri_display#'] = (is_object($namedistri) && $namedistri->getIsVisible()) ? "#namedistri_display#" : "none";
		$replace['#namedistri_collect#'] = (is_object($namedistri) && $namedistri->getIsVisible()) ? $namedistri->getCollectDate() : "-";
        $replace['#namedistri_value#'] = (is_object($namedistri) && $namedistri->getIsVisible()) ? $namedistri->getValueDate() : "-";

		$loadavg1mn = $this->getCmd(null,'loadavg1mn');
		$replace['#loadavg1mn#'] = (is_object($loadavg1mn)) ? $loadavg1mn->execCmd() : '';
		$replace['#loadavg1mnid#'] = is_object($loadavg1mn) ? $loadavg1mn->getId() : '';
		$replace['#loadavg_display#'] = (is_object($loadavg1mn) && $loadavg1mn->getIsVisible()) ? "#loadavg_display#" : "none";
		$replace['#loadavg_collect#'] = (is_object($loadavg1mn) && $loadavg1mn->getIsVisible()) ? $loadavg1mn->getCollectDate() : "-";
        $replace['#loadavg_value#'] = (is_object($loadavg1mn) && $loadavg1mn->getIsVisible()) ? $loadavg1mn->getValueDate() : "-";

		$loadavg5mn = $this->getCmd(null,'loadavg5mn');
		$replace['#loadavg5mn#'] = (is_object($loadavg5mn)) ? $loadavg5mn->execCmd() : '';
		$replace['#loadavg5mnid#'] = is_object($loadavg5mn) ? $loadavg5mn->getId() : '';

		$loadavg15mn = $this->getCmd(null,'loadavg15mn');
		$replace['#loadavg15mn#'] = (is_object($loadavg15mn)) ? $loadavg15mn->execCmd() : '';
		$replace['#loadavg15mnid#'] = is_object($loadavg15mn) ? $loadavg15mn->getId() : '';

		$uptime = $this->getCmd(null,'uptime');
		$replace['#uptime#'] = (is_object($uptime)) ? $uptime->execCmd() : '';
		$replace['#uptimeid#'] = is_object($uptime) ? $uptime->getId() : '';
		$replace['#uptime_display#'] = (is_object($uptime) && $uptime->getIsVisible()) ? "#uptime_display#" : "none";
		$replace['#uptime_collect#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getCollectDate() : "-";
        $replace['#uptime_value#'] = (is_object($uptime) && $uptime->getIsVisible()) ? $uptime->getValueDate() : "-";
		
		$Mem = $this->getCmd(null,'Mem');
		$replace['#Mem#'] = (is_object($Mem)) ? $Mem->execCmd() : '';
		$replace['#Memid#'] = is_object($Mem) ? $Mem->getId() : '';
		$replace['#Mem_display#'] = (is_object($Mem) && $Mem->getIsVisible()) ? "#Mem_display#" : "none";
		$replace['#Mem_collect#'] = (is_object($Mem) && $Mem->getIsVisible()) ? $Mem->getCollectDate() : "-";
        $replace['#Mem_value#'] = (is_object($Mem) && $Mem->getIsVisible()) ? $Mem->getValueDate() : "-";

		$Mem_swap = $this->getCmd(null,'Mem_swap');
		$replace['#Mem_swap#'] = (is_object($Mem_swap)) ? $Mem_swap->execCmd() : '';
		$replace['#Mem_swapid#'] = is_object($Mem_swap) ? $Mem_swap->getId() : '';
		$replace['#Mem_swap_display#'] = (is_object($Mem_swap) && $Mem_swap->getIsVisible()) ? "#Mem_swap_display#" : "none";
		$replace['#Mem_swap_collect#'] = (is_object($Mem_swap) && $Mem_swap->getIsVisible()) ? $Mem_swap->getCollectDate() : "-";
        $replace['#Mem_swap_value#'] = (is_object($Mem_swap) && $Mem_swap->getIsVisible()) ? $Mem_swap->getValueDate() : "-";

		$ethernet0 = $this->getCmd(null,'ethernet0');
		$replace['#ethernet0#'] = (is_object($ethernet0)) ? $ethernet0->execCmd() : '';
		$replace['#ethernet0id#'] = is_object($ethernet0) ? $ethernet0->getId() : '';
		$replace['#ethernet0_display#'] = (is_object($ethernet0) && $ethernet0->getIsVisible()) ? "#ethernet0_display#" : "none";
		$replace['#ethernet0_collect#'] = (is_object($ethernet0) && $ethernet0->getIsVisible()) ? $ethernet0->getCollectDate() : "-";
        $replace['#ethernet0_value#'] = (is_object($ethernet0) && $ethernet0->getIsVisible()) ? $ethernet0->getValueDate() : "-";

		$ethernet0_name = $this->getCmd(null,'ethernet0_name');
		$replace['#ethernet0_name#'] = (is_object($ethernet0_name)) ? $ethernet0_name->execCmd() : '';
		$replace['#ethernet0_nameid#'] = is_object($ethernet0_name) ? $ethernet0_name->getId() : '';

		$hddused = $this->getCmd(null,'hddused');
		$replace['#hddused#'] = (is_object($hddused)) ? $hddused->execCmd() : '';
		$replace['#hddusedid#'] = is_object($hddused) ? $hddused->getId() : '';

		$hddused_pourc = $this->getCmd(null,'hddpourcused');
		$replace['#hddpourcused#'] = (is_object($hddused_pourc)) ? $hddused_pourc->execCmd() : '';
		$replace['#hddpourcusedid#'] = is_object($hddused_pourc) ? $hddused_pourc->getId() : '';

		$hddtotal = $this->getCmd(null,'hddtotal');
		$replace['#hddtotal#'] = (is_object($hddtotal)) ? $hddtotal->execCmd() : '';
		$replace['#hddtotalid#'] = is_object($hddtotal) ? $hddtotal->getId() : '';
		$replace['#hddused_display#'] = (is_object($hddtotal) && $hddtotal->getIsVisible()) ? "#hddused_display#" : "none";
		$replace['#hddtotal_collect#'] = (is_object($hddtotal) && $hddtotal->getIsVisible()) ? $hddtotal->getCollectDate() : "-";
        $replace['#hddtotal_value#'] = (is_object($hddtotal) && $hddtotal->getIsVisible()) ? $hddtotal->getValueDate() : "-";

		$cpu = $this->getCmd(null,'cpu');
		$replace['#cpu#'] = (is_object($cpu)) ? $cpu->execCmd() : '';
		$replace['#cpuid#'] = is_object($cpu) ? $cpu->getId() : '';
		$replace['#cpu_display#'] = (is_object($cpu) && $cpu->getIsVisible()) ? "#cpu_display#" : "none";
		$replace['#cpu_collect#'] = (is_object($cpu) && $cpu->getIsVisible()) ? $cpu->getCollectDate() : "-";
        $replace['#cpu_value#'] = (is_object($cpu) && $cpu->getIsVisible()) ? $cpu->getValueDate() : "-";

		// Syno Volume 2
		$SynoV2Visible = (is_object($this->getCmd(null,'hddtotalv2')) && $this->getCmd(null,'hddtotalv2')->getIsVisible()) ? 'OK' : '';

		if($this->getConfiguration('synology') == '1' && $SynoV2Visible == 'OK' && $this->getConfiguration('synologyv2') == '1'){
			$hddusedv2 = $this->getCmd(null,'hddusedv2');
			$replace['#hddusedv2#'] = (is_object($hddusedv2)) ? $hddusedv2->execCmd() : '';
			$replace['#hddusedv2id#'] = is_object($hddusedv2) ? $hddusedv2->getId() : '';

			$hddusedv2_pourc = $this->getCmd(null,'hddpourcusedv2');
			$replace['#hddpourcusedv2#'] = (is_object($hddusedv2_pourc)) ? $hddusedv2_pourc->execCmd() : '';
			$replace['#hddpourcusedv2id#'] = is_object($hddusedv2_pourc) ? $hddusedv2_pourc->getId() : '';

			$hddtotalv2 = $this->getCmd(null,'hddtotalv2');
			$replace['#hddtotalv2#'] = (is_object($hddtotalv2)) ? $hddtotalv2->execCmd() : '';
			$replace['#hddtotalv2id#'] = is_object($hddtotalv2) ? $hddtotalv2->getId() : '';
			$replace['#hddusedv2_display#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? "#hddusedv2_display#" : "none";
			$replace['#synovolume2_display#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? 'OK' : '';
			$replace['#hddtotalv2_collect#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? $hddtotalv2->getCollectDate() : "-";
        	$replace['#hddtotalv2_value#'] = (is_object($hddtotalv2) && $hddtotalv2->getIsVisible()) ? $hddtotalv2->getValueDate() : "-";
		}

		// Syno Volume USB
		$SynoUSBVisible = (is_object($this->getCmd(null,'hddtotalusb')) && $this->getCmd(null,'hddtotalusb')->getIsVisible()) ? 'OK' : '';

		if($this->getConfiguration('synology') == '1' && $SynoUSBVisible == 'OK' && $this->getConfiguration('synologyusb') == '1'){
			$hddusedusb = $this->getCmd(null,'hddusedusb');
			$replace['#hddusedusb#'] = (is_object($hddusedusb)) ? $hddusedusb->execCmd() : '';
			$replace['#hddusedusbid#'] = is_object($hddusedusb) ? $hddusedusb->getId() : '';

			$hddusedusb_pourc = $this->getCmd(null,'hddpourcusedusb');
			$replace['#hddpourcusedusb#'] = (is_object($hddusedusb_pourc)) ? $hddusedusb_pourc->execCmd() : '';
			$replace['#hddpourcusedusbid#'] = is_object($hddusedusb_pourc) ? $hddusedusb_pourc->getId() : '';

			$hddtotalusb = $this->getCmd(null,'hddtotalusb');
			$replace['#hddtotalusb#'] = (is_object($hddtotalusb)) ? $hddtotalusb->execCmd() : '';
			$replace['#hddtotalusbid#'] = is_object($hddtotalusb) ? $hddtotalusb->getId() : '';
			$replace['#hddusedusb_display#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? "#hddusedusb_display#" : "none";
			$replace['#synovolumeusb_display#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? 'OK' : '';
			$replace['#hddtotalusb_collect#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? $hddtotalusb->getCollectDate() : "-";
        	$replace['#hddtotalusb_value#'] = (is_object($hddtotalusb) && $hddtotalusb->getIsVisible()) ? $hddtotalusb->getValueDate() : "-";
		}

		// Syno Volume eSATA
		$SynoeSATAVisible = (is_object($this->getCmd(null,'hddtotalesata')) && $this->getCmd(null,'hddtotalesata')->getIsVisible()) ? 'OK' : '';

		if($this->getConfiguration('synology') == '1' && $SynoeSATAVisible == 'OK' && $this->getConfiguration('synologyesata') == '1'){
			$hddusedesata = $this->getCmd(null,'hddusedesata');
			$replace['#hddusedesata#'] = (is_object($hddusedesata)) ? $hddusedesata->execCmd() : '';
			$replace['#hddusedesataid#'] = is_object($hddusedesata) ? $hddusedesata->getId() : '';

			$hddusedesata_pourc = $this->getCmd(null,'hddpourcusedesata');
			$replace['#hddpourcusedesata#'] = (is_object($hddusedesata_pourc)) ? $hddusedesata_pourc->execCmd() : '';
			$replace['#hddpourcusedesataid#'] = is_object($hddusedesata_pourc) ? $hddusedesata_pourc->getId() : '';

			$hddtotalesata = $this->getCmd(null,'hddtotalesata');
			$replace['#hddtotalesata#'] = (is_object($hddtotalesata)) ? $hddtotalesata->execCmd() : '';
			$replace['#hddtotalesataid#'] = is_object($hddtotalesata) ? $hddtotalesata->getId() : '';
			$replace['#hddusedesata_display#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? "#hddusedesata_display#" : "none";
			$replace['#synovolumeesata_display#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? 'OK' : '';
			$replace['#hddtotalesata_collect#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? $hddtotalesata->getCollectDate() : "-";
        	$replace['#hddtotalesata_value#'] = (is_object($hddtotalesata) && $hddtotalesata->getIsVisible()) ? $hddtotalesata->getValueDate() : "-";
		}

		$cnx_ssh = $this->getCmd(null,'cnx_ssh');
		$replace['#cnx_ssh#'] = (is_object($cnx_ssh)) ? $cnx_ssh->execCmd() : '';
		$replace['#cnx_sshid#'] = is_object($cnx_ssh) ? $cnx_ssh->getId() : '';

		$Mempourc = $this->getCmd(null,'Mempourc');
		$replace['#Mempourc#'] = (is_object($Mempourc)) ? $Mempourc->execCmd() : '';
		$replace['#Mempourcid#'] = is_object($Mempourc) ? $Mempourc->getId() : '';

		$Swappourc = $this->getCmd(null,'Swappourc');
		$replace['#Swappourc#'] = (is_object($Swappourc)) ? $Swappourc->execCmd() : '';
		$replace['#Swappourcid#'] = is_object($Swappourc) ? $Swappourc->getId() : '';

		$cpu_temp = $this->getCmd(null,'cpu_temp');
		$replace['#cpu_temp#'] = (is_object($cpu_temp)) ? $cpu_temp->execCmd() : '';
		$replace['#cpu_tempid#'] = is_object($cpu_temp) ? $cpu_temp->getId() : '';
		$replace['#cpu_temp_display#'] = (is_object($cpu_temp) && $cpu_temp->getIsVisible()) ? 'OK' : '';

		$perso1 = $this->getCmd(null,'perso1');
		$replace['#perso1#'] = (is_object($perso1)) ? $perso1->execCmd() : '';
		$replace['#perso1id#'] = is_object($perso1) ? $perso1->getId() : '';
		$replace['#perso1_display#'] = (is_object($perso1) && $perso1->getIsVisible()) ? "#perso1_display#" : "none";
		$replace['#perso1_collect#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getCollectDate() : "-";
        $replace['#perso1_value#'] = (is_object($perso1) && $perso1->getIsVisible()) ? $perso1->getValueDate() : "-";
		
		$nameperso_1 = (is_object($perso1)) ? $this->getCmd(null,'perso1')->getName() : '';
		$iconeperso_1 = (is_object($perso1)) ? $this->getCmd(null,'perso1')->getdisplay('icon') : '';
		$replace['#nameperso1#'] = (is_object($perso1)) ? $nameperso_1 : '';
		$replace['#iconeperso1#'] = (is_object($perso1)) ? $iconeperso_1 : '';
		
		$perso_1unite = $this->getConfiguration('perso1_unite');
		$replace['#uniteperso1#'] = (is_object($perso1)) ? $perso_1unite : '';

		$perso2 = $this->getCmd(null,'perso2');
		$replace['#perso2#'] = (is_object($perso2)) ? $perso2->execCmd() : '';
		$replace['#perso2id#'] = is_object($perso2) ? $perso2->getId() : '';
		$replace['#perso2_display#'] = (is_object($perso2) && $perso2->getIsVisible()) ? "#perso2_display#" : "none";
		$replace['#perso2_collect#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getCollectDate() : "-";
        $replace['#perso2_value#'] = (is_object($perso2) && $perso2->getIsVisible()) ? $perso2->getValueDate() : "-";
		
		$nameperso_2 = (is_object($perso2)) ? $this->getCmd(null,'perso2')->getName() : '';
		$iconeperso_2 = (is_object($perso2)) ? $this->getCmd(null,'perso2')->getdisplay('icon') : '';
		$replace['#nameperso2#'] = (is_object($perso2)) ? $nameperso_2 : '';
		$replace['#iconeperso2#'] = (is_object($perso2)) ? $iconeperso_2 : '';
		
		$perso_2unite = $this->getConfiguration('perso2_unite');
		$replace['#uniteperso2#'] = (is_object($perso2)) ? $perso_2unite : '';

		foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
			$replace['#cmd_' . $cmd->getLogicalId() . '_display#'] = (is_object($cmd) && $cmd->getIsVisible()) ? "#cmd_" . $cmd->getLogicalId() . "_display#" : "none";
		}

		$html = template_replace($replace, getTemplate('core', $_version, 'Monitoring','Monitoring'));
		cache::set('MonitoringWidget' . $_version . $this->getId(), $html, 0);
		return $html;
	}


	public static function getPluginVersion()
    {
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
			log::add('Monitoring', 'debug', '[VERSION] Get ERROR :: ' . $e->getMessage());
		}
		log::add('Monitoring', 'info', '[VERSION] PluginVersion :: ' . $pluginVersion);
        return $pluginVersion;
    }

	public function getInformations() {
		try {
			define('NET_SSH2_LOGGING', 2);
			$bitdistri_cmd = '';
			$uname = "Inconnu";
			$Mem = '';
			$memorylibre_pourc = '';
			$ethernet0 = '';
			$ethernet0_name = '';

			if ($this->getConfiguration('cartereseau') == 'netautre'){
				$cartereseau = $this->getConfiguration('cartereseauautre');
			}
			elseif ($this->getConfiguration('cartereseau') == 'netauto') {
				$cartereseau = "$(ip a | awk '/^[^ ]/ && NR!=1 {print \"\"} {printf \"%s\", $0} END {print \"\"}' | awk '!/master|docker/ && /state UP/ && /inet/' | awk -F': ' '{ print $2 }' | head -1)";
			} else {
				$cartereseau = $this->getConfiguration('cartereseau');
			}

			/* $SynoV2Visible = (is_object($this->getCmd(null,'hddtotalv2')) && $this->getCmd(null,'hddtotalv2')->getIsVisible()) ? 'OK' : '';
			log::add('Monitoring', 'debug', '[GetInfo][SynoV2Visible] SynoV2 :: '. $SynoV2Visible);
			$SynoUSBVisible = (is_object($this->getCmd(null,'hddtotalusb')) && $this->getCmd(null,'hddtotalusb')->getIsVisible()) ? 'OK' : '';
			log::add('Monitoring', 'debug', '[GetInfo][SynoUSBVisible] SynoUSB :: '. $SynoUSBVisible);

			$Perso1Visible = ((is_object($this->getCmd(null,'perso1')) && $this->getCmd(null,'perso1')->getIsVisible())) ? 'OK' : '';
			log::add('Monitoring', 'debug', '[GetInfo][Perso1Visible] Perso1 :: '. $Perso1Visible);
			$Perso2Visible = ((is_object($this->getCmd(null,'perso2')) && $this->getCmd(null,'perso2')->getIsVisible())) ? 'OK' : '';
			log::add('Monitoring', 'debug', '[GetInfo][Perso2Visible] Perso2 :: '. $Perso2Visible); */

			$confLocalOrRemote = $this->getConfiguration('maitreesclave');
			if (($confLocalOrRemote == 'deporte' || $confLocalOrRemote == 'deporte-key') && $this->getIsEnable()) {
				$ip = $this->getConfiguration('addressip');
				$port = $this->getConfiguration('portssh');
				$user = $this->getConfiguration('user');
				$pass = $this->getConfiguration('password');
				$sshkey = $this->getConfiguration('ssh-key');
				$sshpassphrase = $this->getConfiguration('ssh-passphrase');
				$equipement = $this->getName();
				$cnx_ssh = '';

				try {
					$sshconnection = new SSH2($ip,$port);
					log::add('Monitoring', 'debug', '[SSH-New] Connexion SSH :: '. $equipement .' :: OK');
				} catch (Exception $e) {
					log::add('Monitoring', 'error', '[SSH-New] Connexion SSH :: '. $equipement .' :: '. $e->getMessage());
					$cnx_ssh = 'KO';
				}
				if ($cnx_ssh != 'KO') {
					if ($confLocalOrRemote == 'deporte-key') {
						try {
							$keyOrPwd = PublicKeyLoader::load($sshkey, $sshpassphrase);
							log::add('Monitoring', 'debug', '[SSH-Key] PublicKeyLoader :: '. $equipement .' :: OK');
						} catch (Exception $e) {
							log::add('Monitoring', 'error', '[SSH-Key] PublicKeyLoader :: '. $equipement .' :: '. $e->getMessage());
							$keyOrPwd = '';
						}
					}
					else {
						$keyOrPwd = $pass;
						log::add('Monitoring', 'debug', '[SSH-Pwd] Authentification SSH par Mot de passe :: '. $equipement);
					}

					try {
						if (!$sshconnection->login($user, $keyOrPwd)) {
							log::add('Monitoring', 'error', '[SSH-Login] Login ERROR :: '. $equipement . ' :: ' . $user);
						}
					} catch (Exception $e) {
						log::add('Monitoring', 'error', '[SSH-Login] Authentification SSH :: '. $equipement .' :: '. $e->getMessage());
						$cnx_ssh = 'KO';
					}
					if ($cnx_ssh != 'KO') {
						$cnx_ssh = 'OK';
						log::add('Monitoring', 'debug', '[SSH-Login] Authentification SSH :: '. $equipement .' :: OK');

						$ARMv_cmd = "lscpu 2>/dev/null | grep Architecture | awk '{ print $2 }'";
						$uptime_cmd = "uptime";

						if($this->getConfiguration('synology') == '1') {
							if ($this->getConfiguration('syno_alt_name') == '1') {
								$namedistri_cmd = "cat /proc/sys/kernel/syno_hw_version 2>/dev/null";
							}
							else {
								$namedistri_cmd = "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null";
							}
							$VersionID_cmd = "awk -F'=' '/productversion/ {print $2}' /etc.defaults/VERSION 2>/dev/null | tr -d '\"'";
						}
						else {
							$namedistri_cmd = "cat /etc/*-release 2>/dev/null | grep ^PRETTY_NAME=";
							$VersionID_cmd = "awk -F'=' '/VERSION_ID/ {print $2}' /etc/*-release 2>/dev/null | tr -d '\"'";
							$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
						}

						$memory_cmd = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$7 }'";
						$swap_cmd = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
						$loadavg_cmd = "cat /proc/loadavg 2>/dev/null";
						$ReseauRXTX_cmd = "cat /proc/net/dev 2>/dev/null | grep ".$cartereseau." | awk '{print $1,$2,$10}' | tr -d ':'";

						try {
							$ARMv = trim($sshconnection->exec($ARMv_cmd));
							log::add('Monitoring', 'debug', '[SSH-CMD] Armv Log :: '. $equipement .' :: ' . $sshconnection->getLog());
						} catch (Exception $e) {
							log::add('Monitoring', 'debug', '[SSH-CMD] Armv Exception Log :: '. $equipement .' :: ' . $sshconnection->getLog());
							log::add('Monitoring', 'debug', '[SSH-CMD] Armv Exception :: '. $equipement .' :: ' . $e->getMessage());
						}
						try {
							$uptime = $sshconnection->exec($uptime_cmd);
							log::add('Monitoring', 'debug', '[SSH-CMD] Uptime Log :: '. $equipement .' :: ' . $sshconnection->getLog());
						} catch (Exception $e) {
							log::add('Monitoring', 'debug', '[SSH-CMD] Uptime Exception Log :: '. $equipement .' :: ' . $sshconnection->getLog());
							log::add('Monitoring', 'debug', '[SSH-CMD] Uptime Exception :: '. $equipement .' :: ' . $e->getMessage());
						}
						
						$VersionID = trim($sshconnection->exec($VersionID_cmd));
						$namedistri = $sshconnection->exec($namedistri_cmd);
						$bitdistri = $sshconnection->exec($bitdistri_cmd);
						$loadav = $sshconnection->exec($loadavg_cmd);
						$ReseauRXTX = $sshconnection->exec($ReseauRXTX_cmd);

						$memory = $sshconnection->exec($memory_cmd);
						$swap = $sshconnection->exec($swap_cmd);

						$perso_1cmd = $this->getConfiguration('perso1');
						$perso_2cmd = $this->getConfiguration('perso2');

						if ($perso_1cmd != '' /* && $Perso1Visible == 'OK' */) {
							$perso_1 = $sshconnection->exec($perso_1cmd);
							log::add('Monitoring', 'debug', '[SSH] Perso1 :: '.$perso_1);
						}
						if ($perso_2cmd != '' /* && $Perso2Visible == 'OK' */) {
							$perso_2 = $sshconnection->exec($perso_2cmd);
							log::add('Monitoring', 'debug', '[SSH] Perso2 :: '.$perso_2);
						}
						
						if($this->getConfiguration('synology') == '1') {
							$platform_cmd = "get_key_value /etc/synoinfo.conf unique | cut -d'_' -f2";
							$synoplatform = $sshconnection->exec($platform_cmd);

							$nbcpuARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_core";
							$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));

							$cpufreq0ARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_clock";
							$cpufreq0 = trim($sshconnection->exec($cpufreq0ARM_cmd));
							
							$hdd_cmd = "df -h 2>/dev/null | grep 'vg1000\|volume1' | head -1 | awk '{ print $2,$3,$5 }'";
							$hdd = $sshconnection->exec($hdd_cmd);

							// $versionsyno_cmd = "cat /etc.defaults/VERSION | tr -d '\"' | paste -s -d '&'"; // Cette version est bien mais 'parse' n'est pas une commande dispo sur SRM (routeurs Syno)
							$versionsyno_cmd = "cat /etc.defaults/VERSION | tr -d '\"' | awk NF=NF RS='\r\n' OFS='&'"; // Récupération de tout le fichier de version pour le parser et récupérer le nom des champs
							$versionsyno = $sshconnection->exec($versionsyno_cmd);

							if ($this->getconfiguration('syno_use_temp_path')) {
								$cputemp0_cmd=$this->getconfiguration('syno_temp_path');
								log::add("Monitoring","debug", "[SYNO-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);
							} 
							else {
								$cputemp0_cmd="timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1)";
								log::add("Monitoring","debug", "[SYNO-TEMP] Commande Température :: ".$cputemp0_cmd);
							}
							$cputemp0 = $sshconnection->exec($cputemp0_cmd);
						
							if($this->getConfiguration('synology') == '1' /* && $SynoV2Visible == 'OK' */ && $this->getConfiguration('synologyv2') == '1') {
								$hddv2cmd = "df -h 2>/dev/null | grep 'vg1001\|volume2' | head -1 | awk '{ print $2,$3,$5 }'"; // DSM 5.x / 6.x / 7.x
								$hddv2 = $sshconnection->exec($hddv2cmd);
							}

							if($this->getConfiguration('synology') == '1' /* && $SynoUSBVisible == 'OK' */ && $this->getConfiguration('synologyusb') == '1') {
								$hddusbcmd = "df -h 2>/dev/null | grep 'usb1p1\|volumeUSB1' | head -1 | awk '{ print $2,$3,$5 }'"; // DSM 5.x / 6.x / 7.x
								$hddusb = $sshconnection->exec($hddusbcmd);
							}

							if($this->getConfiguration('synology') == '1' /* && $SynoeSATAVisible == 'OK' */ && $this->getConfiguration('synologyesata') == '1') {
								$hddesatacmd = "df -h 2>/dev/null | grep 'sdf1\|volumeSATA' | head -1 | awk '{ print $2,$3,$5 }'"; // DSM 5.x / 6.x / 7.x
								$hddesata = $sshconnection->exec($hddesatacmd);
							}
						}	
						elseif ($ARMv == 'armv6l') {
							$nbcpuARM_cmd = "lscpu 2>/dev/null | grep 'CPU(s):' | awk '{ print $2 }'";
							$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));
							
							$uname = '.';

							$hdd_cmd = "df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
							$hdd = $sshconnection->exec($hdd_cmd);

							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
							$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);						
							if ($cpufreq0 == '') {
								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
								$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);
							}

							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0armv6l_cmd=$this->getconfiguration('linux_temp_cmd');
									log::add("Monitoring","debug", "[ARM6L-TEMP] Commande Température (Custom) :: ".$cputemp0armv6l_cmd);	
								} 
								else {
									$cputemp0armv6l_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
									log::add("Monitoring","debug", "[ARM6L-TEMP] Commande Température :: ".$cputemp0armv6l_cmd);
								}
								$cputemp0 = $sshconnection->exec($cputemp0armv6l_cmd);
							}

						}
						elseif ($ARMv == 'armv7l' || $ARMv == 'aarch64' || $ARMv == 'mips64'){
							$nbcpuARM_cmd = "lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'";
							$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));
							
							$uname = '.';

							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
							$cpufreq0 = trim($sshconnection->exec($cpufreq0ARM_cmd));

							if ($cpufreq0 == '') {
								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
								$cpufreq0 = trim($sshconnection->exec($cpufreq0ARM_cmd));
							}

							$hdd_cmd = "df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
							$hdd = $sshconnection->exec($hdd_cmd);

							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
									log::add("Monitoring","debug", "[AARCH64-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);	
								} 
								else {
									$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";	// OK RPi2
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
									
									if ($cputemp0 == '') {
										$cputemp0_cmd = "cat /sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1_input 2>/dev/null"; // OK Banana Pi (Cubie surement un jour...)
										$cputemp0 = $sshconnection->exec($cputemp0_cmd);
									}
									log::add("Monitoring","debug", "[AARCH64-TEMP] Commande Température :: ".$cputemp0_cmd);
								}							
							}
						}
						elseif ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386'){
							$NF = '';
							$cputemp0 ='';
							$uname = '.';
							
							$nbcpuVM_cmd = "lscpu 2>/dev/null | grep 'Processeur(s)' | awk '{ print $NF }'"; // OK pour Debian
							$nbcpu = $sshconnection->exec($nbcpuVM_cmd);

							if ($nbcpu == '') {
								$nbcpuVMbis_cmd = "lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'"; // OK pour LXC Linux/Ubuntu
								$nbcpu = $sshconnection->exec($nbcpuVMbis_cmd);
							}
							$nbcpu = preg_replace("/[^0-9]/","",$nbcpu);

							$hdd_cmd = "df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
							$hdd = $sshconnection->exec($hdd_cmd);

							$cpufreqVM_cmd = "lscpu 2>/dev/null | grep 'Vitesse du processeur en MHz' | awk '{print $NF}'"; // OK pour Debian/Ubuntu, mais pas Ubuntu 22.04
							$cpufreq = $sshconnection->exec($cpufreqVM_cmd);

							if ($cpufreq == '') {
								$cpufreqVMbis_cmd = "lscpu 2>/dev/null | grep '^CPU max MHz' | awk '{ print $NF }'";	// OK pour LXC Linux, Proxmox
								$cpufreq = $sshconnection->exec($cpufreqVMbis_cmd);
							}

							if ($cpufreq == '') {
								$cpufreqVMbis_cmd = "lscpu 2>/dev/null | grep '^CPU MHz' | awk '{ print $NF }'";	// OK pour LXC Linux
								$cpufreq = $sshconnection->exec($cpufreqVMbis_cmd);
							}
							if ($cpufreq == '') {
								$cpufreqVMbis_cmd = "cat /proc/cpuinfo 2>/dev/null | grep '^cpu MHz' | head -1 | cut -d':' -f2 | awk '{ print $NF }'";	// OK pour Debian 10/11, Ubuntu 22.04
								$cpufreq = $sshconnection->exec($cpufreqVMbis_cmd);
							}
							$cpufreq=preg_replace("/[^0-9.]/","",$cpufreq);

							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
								if ($this->getconfiguration('linux_use_temp_cmd')) {
									$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
									log::add("Monitoring","debug", "[X86-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);	
								}
								else {
									$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone0/temp 2>/dev/null";	// Default
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
									
									if ($cputemp0 == '') {
										$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone1/temp 2>/dev/null"; // Default Zone 1
										$cputemp0 = $sshconnection->exec($cputemp0_cmd);		
									}
									if ($cputemp0 == '') {
										$cputemp0_cmd = "cat /sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input 2>/dev/null";	// OK AOpen DE2700
										$cputemp0 = $sshconnection->exec($cputemp0_cmd);		
									}
									if ($cputemp0 == '') {
										// $cputemp0AMD_cmd = "cat /sys/devices/pci0000:00/0000:00:18.3/hwmon/hwmon0/temp1_input 2>/dev/null";	// OK AMD Ryzen
										$cputemp0AMD_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"; // OK Search temp?_input
										$cputemp0 = $sshconnection->exec($cputemp0AMD_cmd);
									}
									if ($cputemp0 == '') {
										$cputemp0_cmd = "sensors 2>/dev/null | awk '{if (match($0, \"Package\")){printf(\"%f\",$4);} }'"; // OK by sensors
										$cputemp0 = $sshconnection->exec($cputemp0_cmd);
									}
									if ($cputemp0 == '') {
										$cputemp0_cmd = "sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")){printf(\"%f\",$3);} }'"; // OK by sensors
										$cputemp0 = $sshconnection->exec($cputemp0_cmd);
									}
									log::add("Monitoring","debug", "[X86-TEMP] Commande Température :: ".$cputemp0_cmd);
								}
							}
						}
						elseif ($ARMv == '' & $this->getConfiguration('synology') != '1') {
							$unamecmd = "uname -a 2>/dev/null | awk '{print $2,$1}'";
							$unamedata = $sshconnection->exec($unamecmd);
							$uname = $unamedata;

							if (preg_match("#RasPlex|OpenELEC|LibreELEC#", $namedistri)) {
								$bitdistri = '32';
								$ARMv = 'arm';

								$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
								$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));

								$hdd_cmd = "df -h 2>/dev/null | grep '/dev/mmcblk0p2' | head -1 | awk '{ print $2,$3,$5 }'";
								$hdd = $sshconnection->exec($hdd_cmd);

								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
								$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);

								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
									if ($this->getconfiguration('linux_use_temp_cmd')) {
										$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
										log::add("Monitoring","debug", "[ARM-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);
									} 
									else
									{
										$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
										log::add("Monitoring","debug", "[ARM-TEMP] Commande Température :: ".$cputemp0_cmd);
									}
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
								}
							}
							elseif (preg_match("#osmc#", $namedistri)) {
								$bitdistri = '32';
								$ARMv = 'arm';

								$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
								$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));

								$hdd_cmd = "df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
								$hdd = $sshconnection->exec($hdd_cmd);

								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
								$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);

								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
									if ($this->getconfiguration('linux_use_temp_cmd')) {
										$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
										log::add("Monitoring","debug", "[ARM-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);
									} 
									else
									{
										$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
										log::add("Monitoring","debug", "[ARM-TEMP] Commande Température :: ".$cputemp0_cmd);
									}
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
								}
							}
							elseif (preg_match("#piCorePlayer#", $uname)) {
								$bitdistri = '32';
								$ARMv = 'arm';
								$namedistri_cmd = "uname -a 2>/dev/null | awk '{print $2,$3}'";
								$namedistri = $sshconnection->exec($namedistri_cmd);

								$nbcpuARM_cmd = "grep 'model name' /proc/cpuinfo 2>/dev/null | wc -l";
								$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));

								$hdd_cmd = "df -h 2>/dev/null | grep /dev/mmcblk0p | head -1 | awk '{print $2,$3,$5 }'";
								$hdd = $sshconnection->exec($hdd_cmd);

								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
								$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);

								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
									if ($this->getconfiguration('linux_use_temp_cmd')) {
										$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
										log::add("Monitoring","debug", "[ARM-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);
									} 
									else
									{
										$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null";
										log::add("Monitoring","debug", "[ARM-TEMP] Commande Température :: ".$cputemp0_cmd);
									}
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
								}
							}
							elseif (preg_match("#FreeBSD#", $uname)) {
								$namedistri_cmd = "uname -a 2>/dev/null | awk '{ print $1,$3}'";
								$namedistri = $sshconnection->exec($namedistri_cmd);

								$ARMv_cmd = "sysctl hw.machine | awk '{ print $2}'";
								$ARMv = trim($sshconnection->exec($ARMv_cmd));

								$loadavg_cmd = "uptime | awk '{print $8,$9,$10}'";
								$loadav = $sshconnection->exec($loadavg_cmd);

								$memory_cmd = "dmesg | grep Mem | tr '\n' ' ' | awk '{print $4,$10}'";
								$memory = $sshconnection->exec($memory_cmd);

								$bitdistri_cmd = "sysctl kern.smp.maxcpus | awk '{ print $2}'";
								$bitdistri = $sshconnection->exec($bitdistri_cmd);

								$nbcpuARM_cmd = "sysctl hw.ncpu | awk '{ print $2}'";
								$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));

								$hdd_cmd = "df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
								$hdd = $sshconnection->exec($hdd_cmd);

								$cpufreq0ARM_cmd = "sysctl -a | egrep -E 'cpu.0.freq' | awk '{ print $2}'";
								$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);

								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
									if ($this->getconfiguration('linux_use_temp_cmd')) {
										$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
										log::add("Monitoring","debug", "[BSD-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);
									} 
									else
									{
										$cputemp0_cmd = "sysctl -a | egrep -E 'cpu.0.temp' | awk '{ print $2}'";
										log::add("Monitoring","debug", "[BSD-TEMP] Commande Température :: ".$cputemp0_cmd);
									}
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
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
								
								$namedistri = $sshconnection->exec($namedistri_cmd);
								$VersionID = trim($sshconnection->exec($VersionID_cmd));
								$bitdistri = $sshconnection->exec($bitdistri_cmd);
								$hdd = $sshconnection->exec($hdd_cmd);

								if (isset($namedistri) && isset($VersionID)) {
									$namedistri = "Medion/Linux " . $VersionID . " (" . $namedistri . ")";
									log::add('Monitoring', 'debug', '[MEDION] Distribution :: ' . $namedistri);
								}

								$nbcpuARM_cmd = "cat /proc/cpuinfo 2>/dev/null | awk -F':' '/^Processor/ { print $2}'";
								$nbcpu = trim($sshconnection->exec($nbcpuARM_cmd));

								$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq 2>/dev/null";
								$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);						
								if ($cpufreq0 == '') {
									$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq 2>/dev/null";
									$cpufreq0 = $sshconnection->exec($cpufreq0ARM_cmd);
								}

								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
									if ($this->getconfiguration('linux_use_temp_cmd')) {
										$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
										log::add("Monitoring","debug", "[MEDION-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);
									} 
									else
									{
										$cputemp0_cmd = "sysctl -a | egrep -E 'cpu.0.temp' | awk '{ print $2 }'";
										log::add("Monitoring","debug", "[MEDION-TEMP] Commande Température :: ".$cputemp0_cmd);
									}
									$cputemp0 = $sshconnection->exec($cputemp0_cmd);
								}
							}
						}
					}
				}
			}
			elseif ($this->getConfiguration('maitreesclave') == 'local' && $this->getIsEnable()) {
				$cnx_ssh = 'No';
				
				if ($this->getConfiguration('synology') == '1') {
					if ($this->getConfiguration('syno_alt_name') == '1') {
						$namedistri_cmd = "cat /proc/sys/kernel/syno_hw_version 2>/dev/null";
					}
					else {
						$namedistri_cmd = "get_key_value /etc/synoinfo.conf upnpmodelname 2>/dev/null";
					}
					$hdd_cmd = "df -h 2>/dev/null | grep 'vg1000\|volume1' | head -1 | awk '{ print $2,$3,$5 }'";
					$VersionID_cmd = "awk -F'=' '/productversion/ {print $2}' /etc.defaults/VERSION 2>/dev/null | tr -d '\"'";
				}
				else {
					$ARMv_cmd = "lscpu 2>/dev/null | grep Architecture | awk '{ print $2 }'";
					$namedistri_cmd = "cat /etc/*-release 2>/dev/null | grep ^PRETTY_NAME=";
					$VersionID_cmd = "awk -F'=' '/VERSION_ID/ {print $2}' /etc/*-release 2>/dev/null | tr -d '\"'";
					
					$hdd_cmd = "df -h 2>/dev/null | grep '/$' | head -1 | awk '{ print $2,$3,$5 }'";
					$bitdistri_cmd = "getconf LONG_BIT 2>/dev/null";
					
					$ARMv = exec($ARMv_cmd);
					$bitdistri = exec($bitdistri_cmd);
				}

				$uptime_cmd = "uptime";
				$memory_cmd = "LC_ALL=C free 2>/dev/null | grep 'Mem' | head -1 | awk '{ print $2,$3,$4,$7 }'";
				$swap_cmd = "LC_ALL=C free 2>/dev/null | awk -F':' '/Swap/ { print $2 }' | awk '{ print $1,$2,$3}'";
				$loadavg_cmd = "cat /proc/loadavg 2>/dev/null";
				$ReseauRXTX_cmd = "cat /proc/net/dev 2>/dev/null | grep ".$cartereseau." | awk '{print $1,$2,$10}' | tr -d ':'"; // on récupère le nom de la carte en plus pour l'afficher dans les infos

				$uptime = exec($uptime_cmd);
				$namedistri = exec($namedistri_cmd);
				$VersionID = trim(exec($VersionID_cmd));
				$loadav = exec($loadavg_cmd);
				$ReseauRXTX = exec($ReseauRXTX_cmd);
				$hdd = exec($hdd_cmd);
				$memory = exec($memory_cmd);
				$swap = exec($swap_cmd);
				
				$perso_1cmd = $this->getConfiguration('perso1');
				$perso_2cmd = $this->getConfiguration('perso2');

				if ($perso_1cmd != '' /* && $Perso1Visible == 'OK' */) {
					$perso_1 = exec($perso_1cmd);
					log::add('Monitoring', 'debug', '[LOCAL] Perso1 :: '.$perso_1);
				}
				if ($perso_2cmd != '' /* && $Perso2Visible == 'OK' */) {
					$perso_2 = exec($perso_2cmd);
					log::add('Monitoring', 'debug', '[LOCAL] Perso2 :: '.$perso_2);
				}

				if ($this->getConfiguration('synology') == '1'){
					$uname = '.';
					$nbcpuARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_core 2>/dev/null";
					$cpufreq0ARM_cmd = "cat /proc/sys/kernel/syno_CPU_info_clock 2>/dev/null";
					$versionsyno_cmd = "cat /etc.defaults/VERSION 2>/dev/null | tr -d '\"' | awk NF=NF RS='\r\n' OFS='&'"; // on récupère le fichier entier pour avoir le nom des champs

					$nbcpu = exec($nbcpuARM_cmd);
					$cpufreq0 = exec($cpufreq0ARM_cmd);
					$versionsyno = exec($versionsyno_cmd);

					if ($this->getconfiguration('syno_use_temp_path')) {
						$cputemp0_cmd=$this->getconfiguration('syno_temp_path');
						log::add("Monitoring","debug", "[SYNO-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);
					} 
					else {
						$cputemp0_cmd="timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null";
						log::add("Monitoring","debug", "[SYNO-TEMP] Commande Température :: ".$cputemp0_cmd);
					}
					$cputemp0 = exec($cputemp0_cmd);

					if($this->getConfiguration('synology') == '1' /* && $SynoV2Visible == 'OK' */ && $this->getConfiguration('synologyv2') == '1') {
						$hddv2cmd = "df -h 2>/dev/null | grep 'vg1001\|volume2' | head -1 | awk '{ print $2,$3,$5 }'";
						$hddv2 = exec($hddv2cmd);
					}

					if($this->getConfiguration('synology') == '1' /* && $SynoUSBVisible == 'OK' */ && $this->getConfiguration('synologyusb') == '1') {
						$hddusbcmd = "df -h 2>/dev/null | grep 'usb1p1\|volumeUSB1' | head -1 | awk '{ print $2,$3,$5 }'";
						$hddusb = exec($hddusbcmd);
					}

					if($this->getConfiguration('synology') == '1' /* && $SynoeSATAVisible == 'OK' */ && $this->getConfiguration('synologyesata') == '1') {
						$hddesatacmd = "df -h 2>/dev/null | grep 'sdf1\|volumeSATA' | head -1 | awk '{ print $2,$3,$5 }'";
						$hddesata = exec($hddesatacmd);
					}
				}
				elseif ($ARMv == 'armv6l') {
					$uname = '.';
					$cpufreq0 = '';
					$cputemp0 = '';

					$nbcpuARM_cmd = "lscpu 2>/dev/null | grep 'CPU(s):' | awk '{ print $2 }'";
					$nbcpu = exec($nbcpuARM_cmd);
					
					if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq')) {
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq";
						$cpufreq0 = exec($cpufreq0ARM_cmd);
					}
					if ($cpufreq0 == '') {
						if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq')) {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq";
							$cpufreq0 = exec($cpufreq0ARM_cmd);
						}
					}
					$cputemp_cmd = $this->getCmd(null,'cpu_temp');
					if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
							$cputemp0 = exec($cputemp0_cmd);
							log::add("Monitoring","debug", "[ARM6L-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);	
						} 
						elseif (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
								$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp";
								$cputemp0 = exec($cputemp0_cmd);
								log::add("Monitoring","debug", "[ARM6L-TEMP] Commande Température :: ".$cputemp0_cmd);
						}
					}
				}
				elseif ($ARMv == 'armv7l' || $ARMv == 'aarch64') {
					$uname = '.';
					$cputemp0 = '';
					$cpufreq0 = '';

					$nbcpuARM_cmd = "lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $2 }'";
					$nbcpu = exec($nbcpuARM_cmd);
					
					if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq')) {
						$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq";
						$cpufreq0 = exec($cpufreq0ARM_cmd);
					}
					if ($cpufreq0 == '') {
						if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq')) {
							$cpufreq0ARM_cmd = "cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq";
							$cpufreq0 = exec($cpufreq0ARM_cmd);
						}
					}	
					
					$cputemp_cmd = $this->getCmd(null,'cpu_temp');
					if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
							$cputemp0 = exec($cputemp0_cmd);
							log::add("Monitoring","debug", "[AARCH64-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);	
						} 
						else {
							if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
								$cputemp0_cmd = "cat /sys/class/thermal/thermal_zone0/temp"; // OK RPi2/3, Odroid
								$cputemp0 = exec($cputemp0_cmd);
							}
							if ($cputemp0 == '') {
								if (file_exists('/sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1')) {
									$cputemp0_cmd = "cat /sys/devices/platform/sunxi-i2c.0/i2c-0/0-0034/temp1"; // OK Banana Pi (Cubie surement un jour...)
									$cputemp0 = exec($cputemp0_cmd);
								}
							}
							log::add("Monitoring","debug", "[AARCH64-TEMP] Commande Température :: ".$cputemp0_cmd);
						}
					}
				}
				elseif ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386') {
					$NF = '';
					$uname = '.';
					$cputemp0 = '';
					$cpufreq = '';

					$nbcpuVM_cmd = "lscpu 2>/dev/null | grep 'Processeur(s)' | awk '{ print $NF }'"; // OK pour Debian
					$nbcpu = exec($nbcpuVM_cmd);

					if ($nbcpu == ''){
						$nbcpuVMbis_cmd = "lscpu 2>/dev/null | grep '^CPU(s):' | awk '{ print $NF }'"; // OK pour LXC Linux/Ubuntu
						$nbcpu = exec($nbcpuVMbis_cmd);
					}
					$nbcpu = preg_replace("/[^0-9]/","",$nbcpu);
					
					$cpufreqVM_cmd = "lscpu 2>/dev/null | grep 'Vitesse du processeur en MHz' | awk '{print $NF}'"; // OK pour Debian/Ubuntu, mais pas Ubuntu 22.04
					$cpufreq = exec($cpufreqVM_cmd);
					
					if ($cpufreq == ''){
						$cpufreqVMbis_cmd = "lscpu 2>/dev/null | grep '^CPU max MHz' | awk '{ print $NF }'";	// OK pour LXC Linux, Proxmox
						$cpufreq = exec($cpufreqVMbis_cmd);
					}
					if ($cpufreq == ''){
						$cpufreqVMbis_cmd = "lscpu 2>/dev/null | grep '^CPU MHz' | awk '{ print $NF }'";	// OK pour LXC Linux
						$cpufreq = exec($cpufreqVMbis_cmd);
					}
					if ($cpufreq == ''){
						$cpufreqVMbis_cmd = "cat /proc/cpuinfo 2>/dev/null | grep '^cpu MHz' | head -1 | cut -d':' -f2 | awk '{ print $NF }'";	// OK pour Debian 10/11, Ubuntu 22.04
						$cpufreq = exec($cpufreqVMbis_cmd);
					}
					$cpufreq = preg_replace("/[^0-9.]/","",$cpufreq);
					
					$cputemp_cmd = $this->getCmd(null,'cpu_temp');
					if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
						if ($this->getconfiguration('linux_use_temp_cmd')) {
							$cputemp0_cmd=$this->getconfiguration('linux_temp_cmd');
							$cputemp0 = exec($cputemp0_cmd);
							log::add("Monitoring","debug", "[X86-TEMP] Commande Température (Custom) :: ".$cputemp0_cmd);	
						} 
						else {
							if (file_exists('/sys/devices/virtual/thermal/thermal_zone0/temp')) {
								$cputemp0_cmd = "cat /sys/devices/virtual/thermal/thermal_zone0/temp"; // OK Dell Whyse
								$cputemp0 = exec($cputemp0_cmd);
							}					
							if ($cputemp0 == '') {
								if (file_exists('/sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input')) {
									$cputemp0_cmd = "cat /sys/devices/platform/coretemp.0/hwmon/hwmon0/temp?_input";	// OK AOpen DE2700
									$cputemp0 = exec($cputemp0_cmd);
								}
							}
							if ($cputemp0 == '') {
								$cputemp0_cmd = "timeout 3 cat $(find /sys/devices/* -name temp*_input | head -1) 2>/dev/null"; // OK AMD Ryzen
								$cputemp0 = exec($cputemp0_cmd); 
							}
							if ($cputemp0 == '') {
								$cputemp0_cmd = "sensors 2>/dev/null | awk '{if (match($0, \"Package\")){printf(\"%f\",$4);} }'"; // OK by sensors
								$cputemp0 = exec($cputemp0_cmd);
							}
							if ($cputemp0 == '') {
								$cputemp0_cmd = "sensors 2>/dev/null | awk '{if (match($0, \"MB Temperature\")){printf(\"%f\",$3);} }'"; // OK by sensors MB
								$cputemp0 = exec($cputemp0_cmd);
							}
							log::add("Monitoring","debug", "[X86-TEMP] Commande Température :: ".$cputemp0_cmd);
						}
					}
				}
			}

			if (isset($cnx_ssh)) {
				if($this->getConfiguration('maitreesclave') == 'local' || $cnx_ssh == 'OK') {
					if($this->getConfiguration('synology') == '1'){
						if (isset($versionsyno)) {
							parse_str($versionsyno, $versionsyno_DSM);
							log::add('Monitoring', 'debug', '[DSM] Parse version :: OK');

							if (isset($versionsyno_DSM['productversion']) && isset($versionsyno_DSM['buildnumber']) && isset($versionsyno_DSM['smallfixnumber'])) {
								log::add('Monitoring', 'debug', '[DSM/SRM] Version :: DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'].' Update '.$versionsyno_DSM['smallfixnumber']);
								$versionsyno_TXT = 'DSM '.$versionsyno_DSM['productversion'].'-'.$versionsyno_DSM['buildnumber'].' Update '.$versionsyno_DSM['smallfixnumber'];
							}
							else {
								log::add('Monitoring', 'debug', '[DSM/SRM] Version :: KO');
								$versionsyno_TXT = '';
							}

							if (isset($namedistri) && isset($versionsyno_TXT)) {
								$namedistri = trim($namedistri);
								$namedistri = $versionsyno_TXT.' ('.$namedistri.')';
							}
						}
					}
					else {
						if (isset($namedistri)) {
							$namedistrifin = str_ireplace('PRETTY_NAME="', '', $namedistri);
							$namedistrifin = str_ireplace('"', '', $namedistrifin);
							if (isset($namedistri) && isset($namedistrifin) && isset($bitdistri) && isset($ARMv)) {
								$namedistri = $namedistrifin.' '.$bitdistri.'bits ('.$ARMv.')';
							}
						}
					}
					
					// Syno Volume 2
					if(/* $SynoV2Visible == 'OK' && */ $this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyv2') == '1'){
						if (isset($hddv2)) {
							$hdddatav2 = explode(' ', $hddv2);
							if (isset($hdddatav2[0]) && isset($hdddatav2[1]) && isset($hdddatav2[2])) {
								$hddtotalv2 = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatav2[0]);
								$hddusedv2 = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatav2[1]);
								$hddusedv2_pourc = preg_replace("/[^0-9.]/","",$hdddatav2[2]);
								$hddusedv2_pourc = trim($hddusedv2_pourc);
							} else {
								$hddtotalv2 = '';
								$hddusedv2 = '';
								$hddusedv2_pourc = '';
							}
						}
					}

					// Syno Volume USB 
					if(/* $SynoUSBVisible == 'OK' && */ $this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyusb') == '1'){
						if (isset($hddusb)) {
							$hdddatausb = explode(' ', $hddusb);
							if (isset($hdddatausb[0]) && isset($hdddatausb[1]) && isset($hdddatausb[2])) {
								$hddtotalusb = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatausb[0]);
								$hddusedusb = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddatausb[1]);
								$hddusedusb_pourc = preg_replace("/[^0-9.]/","",$hdddatausb[2]);
								$hddusedusb_pourc = trim($hddusedusb_pourc);
							} else {
								$hddtotalusb = '';
								$hddusedusb = '';
								$hddusedusb_pourc = '';
							}
						}
					}

					// Syno Volume eSATA 
					if(/* $SynoeSATAVisible == 'OK' && */ $this->getConfiguration('synology') == '1' && $this->getConfiguration('synologyesata') == '1'){
						if (isset($hddesata)) {
							$hdddataesata = explode(' ', $hddesata);
							if (isset($hdddataesata[0]) && isset($hdddataesata[1]) && isset($hdddataesata[2])) {
								$hddtotalesata = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddataesata[0]);
								$hddusedesata = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddataesata[1]);
								$hddusedesata_pourc = preg_replace("/[^0-9.]/","",$hdddataesata[2]);
								$hddusedesata_pourc = trim($hddusedesata_pourc);
							} else {
								$hddtotalesata = '';
								$hddusedesata = '';
								$hddusedesata_pourc = '';
							}
						}
					}

					if (isset($uptime)) {
						$datauptime = explode(' up ', $uptime);
						if (isset($datauptime[0]) && isset($datauptime[1])) {
							$datauptime = explode(', ', $datauptime[1]);
							$datauptime = str_replace("days", "jour(s)", $datauptime);
							$datauptime = str_replace("day", "jour(s)", $datauptime);
							$datauptime = str_replace(":", "h", $datauptime);
							if (strpos($datauptime[0], 'jour(s)') === false){
								$uptime = $datauptime[0];
							}
							else {
								if (isset($datauptime[0]) && isset($datauptime[1])) {
									$uptime = $datauptime[0].' et '.$datauptime[1];
								}
							}
						}
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
							if($this->getConfiguration('synology') == '1'){
								if (isset($memory[3])) {
									$memorylibre = intval($memory[3]);
									log::add('Monitoring', 'debug', '[Memory] Version Syno ('.$VersionID.') / Mémoire Libre :: '.$memorylibre);
								}
							}
							else {
								if (isset($memory[3])) {
									$memorylibre = intval($memory[3]);
									log::add('Monitoring', 'debug', '[Memory] Version Linux ('.$VersionID.') / Mémoire Libre :: '.$memorylibre);
								}
							}
							
							if (isset($memory[0]) && isset($memorylibre)) {
								if (intval($memory[0]) != 0) {
									$memorylibre_pourc = round(intval($memorylibre) / intval($memory[0]) * 100);
									log::add('Monitoring', 'debug', '[Memory] Memorylibre% :: '.$memorylibre_pourc);
								}
								else {
									$memorylibre_pourc = 0;
								}
							}

							if (isset($memorylibre)) {
								if ((intval($memorylibre) / 1024) >= 1024) {
									$memorylibre = round(intval($memorylibre) / 1048576, 2) . " Go";
								}
								else {
									$memorylibre = round(intval($memorylibre) / 1024) . " Mo";
								}
							}
							if (isset($memory[0])) {
								if ((intval($memory[0]) / 1024) >= 1024) {
									$memtotal = round(intval($memory[0]) / 1048576, 2) . " Go";
								}
								else {
									$memtotal = round(intval($memory[0]) / 1024, 2) . " Mo";
								}
							}
							if (isset($memtotal) && isset($memorylibre)) {
								$Mem = 'Total : '.$memtotal.' - Libre : '.$memorylibre;
							}
						}
						elseif (preg_match("#FreeBSD#", $uname)) {
							$memory = explode(' ', $memory);
							if (isset($memory[0]) && isset($memory[1])) {
								if (intval($memory[0]) != 0) {
									$memorylibre_pourc = round(intval($memory[1]) / intval($memory[0]) * 100);
								}
								else {
									$memorylibre_pourc = 0;
								}
							}
							if ((intval($memory[1]) / 1024) >= 1024) {
								$memorylibre = round(intval($memory[1]) / 1048576, 2) . " Go";
							}
							else{
								$memorylibre = round(intval($memory[1]) / 1024) . " Mo";
							}
							if (($memory[0] / 1024) >= 1024) {
								$memtotal = round(intval($memory[0]) / 1048576, 2) . " Go";
							}
							else{
								$memtotal = round(intval($memory[0]) / 1024) . " Mo";
							}
							$Mem = 'Total : '.$memtotal.' - Libre : '.$memorylibre;
						}
					}
					else {
						$Mem = '';
					}

					if (isset($swap)) {
						$swap = explode(' ', $swap);

						if(isset($swap[0]) && isset($swap[2])) {
							if (intval($swap[0]) != 0) {
								$swaplibre_pourc = round(intval($swap[2]) / intval($swap[0]) * 100);
							}
							else {
								$swaplibre_pourc = 0;
							}
						}

						if(isset($swap[0])){
							if ((intval($swap[0]) / 1024) >= 1024) {
								$swap[0] = round(intval($swap[0]) / 1048576, 1) . " Go";
							}
							else {
								$swap[0] = round(intval($swap[0]) / 1024, 1) . " Mo";
							}
						}
						if(isset($swap[1])) {
							if ((intval($swap[1]) / 1024) >= 1024) {
								$swap[1] = round(intval($swap[1]) / 1048576, 1) . " Go";
							}
							else {
								$swap[1] = round(intval($swap[1]) / 1024, 1) . " Mo";
							}
						}
						if(isset($swap[2])){
							if ((intval($swap[2]) / 1024) >= 1024) {
								$swap[2] = round(intval($swap[2]) / 1048576, 1) . " Go";
							}
							else {
								$swap[2] = round(intval($swap[2]) / 1024, 1) . " Mo";
							}
						}

						if(isset($swap[0]) && isset($swap[1]) && isset($swap[2])){
							$swap[0] = str_replace("B"," o", $swap[0]);
							$swap[1] = str_replace("B"," o", $swap[1]);
							$swap[2] = str_replace("B"," o", $swap[2]);
							$Memswap = 'Total : '.$swap[0].' - Utilisé : '.$swap[1].' - Libre : '.$swap[2];
						}
					} 
					else {
						$Memswap = '';
					}

					if (isset($ReseauRXTX)) {
						$ReseauRXTX = explode(' ', $ReseauRXTX);
						if(isset($ReseauRXTX[0]) && isset($ReseauRXTX[1]) && isset($ReseauRXTX[2])){
							if ((intval($ReseauRXTX[2]) / 1024) >= 1073741824) {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1099511627776, 2) . " To";
							}
							elseif ((intval($ReseauRXTX[2]) / 1024) >= 1048576) {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1073741824, 2) . " Go";
							}
							elseif ((intval($ReseauRXTX[2]) / 1024) >= 1024) {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1048576, 2) . " Mo";
							}
							else {
								$ReseauTX = round(intval($ReseauRXTX[2]) / 1024) . " Ko";
							}
							
							if ((intval($ReseauRXTX[1]) / 1024) >= 1073741824) {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1099511627776, 2) . " To";
							}
							elseif ((intval($ReseauRXTX[1]) / 1024) >= 1048576) {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1073741824, 2) . " Go";
							}
							elseif ((intval($ReseauRXTX[1]) / 1024) >= 1024) {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1048576, 2) . " Mo";
							}
							else {
								$ReseauRX = round(intval($ReseauRXTX[1]) / 1024) . " Ko";
							}
							$ethernet0 = 'TX : '.$ReseauTX.' - RX : '.$ReseauRX;
							$ethernet0_name = $ReseauRXTX[0];
							log::add('Monitoring', 'debug', '[RESEAU] Nom de la carte réseau (RX / TX) :: '.$ethernet0_name.' (RX= '.$ReseauRX.' / TX= '.$ReseauTX.')');
						}
						else {
							log::add('Monitoring', 'debug', '[RESEAU] Nom de la carte réseau :: KO');
						}
					}

					$hddtotal = '';
					$hddused = '';
					$hddused_pourc = '';
					if (isset($hdd)) {
						$hdddata = explode(' ', $hdd);
						if(isset($hdddata[0]) && isset($hdddata[1]) && isset($hdddata[2])){
							$hddtotal = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddata[0]);
							$hddused = str_replace(array("K","M","G","T"),array(" Ko"," Mo"," Go"," To"), $hdddata[1]);
							$hddused_pourc = preg_replace("/[^0-9.]/","",$hdddata[2]);
							$hddused_pourc = trim($hddused_pourc);
						}
					}

					if (isset($ARMv)) {
						if ($ARMv == 'i686' || $ARMv == 'x86_64' || $ARMv == 'i386'){
							if ((floatval($cpufreq) / 1000) > 1) {
								$cpufreq = round(floatval($cpufreq) / 1000, 1, PHP_ROUND_HALF_UP) . " GHz";
							}
							else {
								$cpufreq = $cpufreq . " MHz";
							}
							
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
								if (floatval($cputemp0) > 200){
									$cputemp0 = floatval($cputemp0) / 1000;
									$cputemp0 = round(floatval($cputemp0), 1);
								}
							}
							$cpu = $nbcpu.' - '.$cpufreq;
						}
						elseif ($ARMv == 'armv6l' || $ARMv == 'armv7l' || $ARMv == 'aarch64' || $ARMv == 'mips64'){
							if ((floatval($cpufreq0) / 1000) > 1000) {
								$cpufreq0 = round(floatval($cpufreq0) / 1000000, 1, PHP_ROUND_HALF_UP) . " GHz";
							}
							else {
								$cpufreq0 = round(floatval($cpufreq0) / 1000) . " MHz";
							}
							
							$cputemp_cmd = $this->getCmd(null,'cpu_temp');
							if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
								if (floatval($cputemp0) > 200){
									$cputemp0 = floatval($cputemp0) / 1000;
									$cputemp0 = round(floatval($cputemp0), 1);
								}
							}
							if (floatval($cpufreq0) == 0) {
								$cpu = $nbcpu.' Socket(s) ';
								$cpufreq0 = '';
							}
							else {
								$cpu = $nbcpu.' - '.$cpufreq0;
							}
						}
						elseif ($ARMv == 'arm') {
							if (preg_match("#RasPlex|OpenELEC|osmc|LibreELEC#", $namedistri) || preg_match("#piCorePlayer#", $uname) || preg_match("#medion#", $uname)) {
								if ((floatval($cpufreq0) / 1000) > 1000) {
									$cpufreq0 = round(floatval($cpufreq0) / 1000000, 1, PHP_ROUND_HALF_UP) . " GHz";
								}
								else {
									$cpufreq0 = round(floatval($cpufreq0) / 1000) . " MHz";
								}
								$cputemp_cmd = $this->getCmd(null,'cpu_temp');
								if (is_object($cputemp_cmd) /* && $cputemp_cmd->getIsVisible() == 1 */) {
									if (floatval($cputemp0) > 200){
										$cputemp0 = floatval($cputemp0) / 1000;
										$cputemp0 = round(floatval($cputemp0), 1);
									}
								}
								$cpu = $nbcpu.' - '.$cpufreq0;
							}
						}
					}

					if($this->getConfiguration('synology') == '1'){
						if ((floatval($cpufreq0) / 1000) > 1) {
							$cpufreq0 = round(floatval($cpufreq0) / 1000, 1, PHP_ROUND_HALF_UP) . " GHz";
						}
						else{
							$cpufreq0 = $cpufreq0 . " MHz";
						}
						if (floatval($cputemp0) > 200){
							$cputemp0 = floatval($cputemp0) / 1000;
							$cputemp0 = round(floatval($cputemp0), 1);
						}
						$cpu = $nbcpu.' - '.$cpufreq0;
					}
					if (empty($cputemp0)) {$cputemp0 = '';}
					if (empty($perso_1)) {$perso_1 = '';}
					if (empty($perso_2)) {$perso_2 = '';}
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
						'hddtotal' => $hddtotal,
						'hddused' => $hddused,
						'hddpourcused' => $hddused_pourc,
						'cpu' => $cpu,
						'cpu_temp' => $cputemp0,
						'cnx_ssh' => $cnx_ssh,
						'Mem_swap' => $Memswap,
						'Mempourc' => $memorylibre_pourc,
						'Swappourc' => $swaplibre_pourc,
						'perso1' => $perso_1,
						'perso2' => $perso_2,
					);
					if($this->getConfiguration('synology') == '1' /* && $SynoV2Visible == 'OK' */ && $this->getConfiguration('synologyv2') == '1'){
						$dataresultv2 = array(
							'hddtotalv2' => $hddtotalv2,
							'hddusedv2' => $hddusedv2,
							'hddpourcusedv2' => $hddusedv2_pourc,
						);
					}

					// Syno Volume USB
					if($this->getConfiguration('synology') == '1' /* && $SynoUSBVisible == 'OK' */ && $this->getConfiguration('synologyusb') == '1'){
						$dataresultusb = array(
							'hddtotalusb' => $hddtotalusb,
							'hddusedusb' => $hddusedusb,
							'hddpourcusedusb' => $hddusedusb_pourc,
						);
					}

					// Syno Volume eSATA
					if($this->getConfiguration('synology') == '1' /* && $SynoeSATAVisible == 'OK' */ && $this->getConfiguration('synologyesata') == '1'){
						$dataresultesata = array(
							'hddtotalesata' => $hddtotalesata,
							'hddusedesata' => $hddusedesata,
							'hddpourcusedesata' => $hddusedesata_pourc,
						);
					}

					$namedistri = $this->getCmd(null,'namedistri');
					if(is_object($namedistri)){
						$namedistri->event($dataresult['namedistri']);
					}

					$uptime = $this->getCmd(null,'uptime');
					if(is_object($uptime)){
						$uptime->event($dataresult['uptime']);
					}

					$loadavg1mn = $this->getCmd(null,'loadavg1mn');
					if(is_object($loadavg1mn)){
						$loadavg1mn->event($dataresult['loadavg1mn']);
					}

					$loadavg5mn = $this->getCmd(null,'loadavg5mn');
					if(is_object($loadavg5mn)){
						$loadavg5mn->event($dataresult['loadavg5mn']);
					}

					$loadavg15mn = $this->getCmd(null,'loadavg15mn');
					if(is_object($loadavg15mn)){
						$loadavg15mn->event($dataresult['loadavg15mn']);
					}

					$Mem = $this->getCmd(null,'Mem');
					if(is_object($Mem)){
						$Mem->event($dataresult['Mem']);
					}

					$Mem_swap = $this->getCmd(null,'Mem_swap');
					if(is_object($Mem_swap)){
						$Mem_swap->event($dataresult['Mem_swap']);
					}

					$ethernet0 = $this->getCmd(null,'ethernet0');
					if(is_object($ethernet0)){
						$ethernet0->event($dataresult['ethernet0']);
					}

					$ethernet0_name = $this->getCmd(null,'ethernet0_name');
					if(is_object($ethernet0_name)){
						$ethernet0_name->event($dataresult['ethernet0_name']);
					}

					$hddtotal = $this->getCmd(null,'hddtotal');
					if(is_object($hddtotal)){
						$hddtotal->event($dataresult['hddtotal']);
					}

					$hddused = $this->getCmd(null,'hddused');
					if(is_object($hddused)){
						$hddused->event($dataresult['hddused']);
					}

					$hddused_pourc = $this->getCmd(null,'hddpourcused');
					if(is_object($hddused_pourc)){
						$hddused_pourc->event($dataresult['hddpourcused']);
					}

					if($this->getConfiguration('synology') == '1' /* && $SynoV2Visible == 'OK' */ && $this->getConfiguration('synologyv2') == '1'){
						$hddtotalv2 = $this->getCmd(null,'hddtotalv2');
						if(is_object($hddtotalv2)){
							$hddtotalv2->event($dataresultv2['hddtotalv2']);
						}
						$hddusedv2 = $this->getCmd(null,'hddusedv2');
						if(is_object($hddusedv2)){
							$hddusedv2->event($dataresultv2['hddusedv2']);
						}
						$hddusedv2_pourc = $this->getCmd(null,'hddpourcusedv2');
						if(is_object($hddusedv2_pourc)){
							$hddusedv2_pourc->event($dataresultv2['hddpourcusedv2']);
						}
					}

					if($this->getConfiguration('synology') == '1' /* && $SynoUSBVisible == 'OK' */ && $this->getConfiguration('synologyusb') == '1'){
						$hddtotalusb = $this->getCmd(null,'hddtotalusb');
						if(is_object($hddtotalusb)){
							$hddtotalusb->event($dataresultusb['hddtotalusb']);
						}
						$hddusedusb = $this->getCmd(null,'hddusedusb');
						if(is_object($hddusedusb)){
							$hddusedusb->event($dataresultusb['hddusedusb']);
						}
						$hddusedusb_pourc = $this->getCmd(null,'hddpourcusedusb');
						if(is_object($hddusedusb_pourc)){
							$hddusedusb_pourc->event($dataresultusb['hddpourcusedusb']);
						}
					}

					if($this->getConfiguration('synology') == '1' /* && $SynoeSATAVisible == 'OK' */ && $this->getConfiguration('synologyesata') == '1'){
						$hddtotalesata = $this->getCmd(null,'hddtotalesata');
						if(is_object($hddtotalesata)){
							$hddtotalesata->event($dataresultesata['hddtotalesata']);
						}
						$hddusedesata = $this->getCmd(null,'hddusedesata');
						if(is_object($hddusedesata)){
							$hddusedesata->event($dataresultesata['hddusedesata']);
						}
						$hddusedesata_pourc = $this->getCmd(null,'hddpourcusedesata');
						if(is_object($hddusedesata_pourc)){
							$hddusedesata_pourc->event($dataresultesata['hddpourcusedesata']);
						}
					}

					$cpu = $this->getCmd(null,'cpu');
					if(is_object($cpu)){
						$cpu->event($dataresult['cpu']);
					}

					$cpu_temp = $this->getCmd(null,'cpu_temp');
					if(is_object($cpu_temp)){
						$cpu_temp->event($dataresult['cpu_temp']);
					}

					$cnx_ssh = $this->getCmd(null,'cnx_ssh');
					if(is_object($cnx_ssh)){
						$cnx_ssh->event($dataresult['cnx_ssh']);
					}

					$Mempourc = $this->getCmd(null,'Mempourc');
					if(is_object($Mempourc)){
						$Mempourc->event($dataresult['Mempourc']);
					}

					$Swappourc = $this->getCmd(null,'Swappourc');
					if(is_object($Swappourc)){
						$Swappourc->event($dataresult['Swappourc']);
					}

					$perso1 = $this->getCmd(null,'perso1');
					if(is_object($perso1)){
						$perso1->event($dataresult['perso1']);
					}

					$perso2 = $this->getCmd(null,'perso2');
					if(is_object($perso2)){
						$perso2->event($dataresult['perso2']);
					}
				}
			}
			if (isset($cnx_ssh)) {
				if($cnx_ssh == 'KO'){
					$dataresult = array(
						'namedistri' => 'Connexion SSH KO',
						'cnx_ssh' => $cnx_ssh
					);
					$namedistri = $this->getCmd(null,'namedistri');
					if(is_object($namedistri)){
						$namedistri->event($dataresult['namedistri']);
					}
					$cnx_ssh = $this->getCmd(null,'cnx_ssh');
					if(is_object($cnx_ssh)){
						$cnx_ssh->event($dataresult['cnx_ssh']);
					}
				}
			}
		} catch (Exception $e) {
			log::add('Monitoring', 'error', '[GetInfos] Exception (Line ' . $e->getLine() . ') :: '. $e->getMessage());
			log::add('Monitoring', 'error', '[GetInfos] Exception Trace :: '. json_encode($e->getTrace()));
		}
	}

	function getCaseAction($paramaction) {
		$confLocalOrRemote = $this->getConfiguration('maitreesclave');
		if (($confLocalOrRemote == 'deporte' || $confLocalOrRemote == 'deporte-key') && $this->getIsEnable()) {
			$ip = $this->getConfiguration('addressip');
			$port = $this->getConfiguration('portssh');
			$user = $this->getConfiguration('user');
			$pass = $this->getConfiguration('password');
			$sshkey = $this->getConfiguration('ssh-key');
			$sshpassphrase = $this->getConfiguration('ssh-passphrase');
			$equipement = $this->getName();
			$cnx_ssh = '';

			try {
				$sshconnection = new SSH2($ip,$port);
				log::add('Monitoring', 'debug', '[SSH-New] Connexion SSH :: '. $equipement .' :: OK');
			} catch (Exception $e) {
				log::add('Monitoring', 'error', '[SSH-New] Connexion SSH :: '. $equipement .' :: '. $e->getMessage());
				$cnx_ssh = 'KO';
			}
			if ($cnx_ssh != 'KO') {
				if ($confLocalOrRemote == 'deporte-key') {
					try {
						$keyOrPwd = PublicKeyLoader::load($sshkey, $sshpassphrase);
						log::add('Monitoring', 'debug', '[SSH-Key] PublicKeyLoader :: '. $equipement .' :: OK');
					} catch (Exception $e) {
						log::add('Monitoring', 'error', '[SSH-Key] PublicKeyLoader :: '. $equipement .' :: '. $e->getMessage());
						$keyOrPwd = '';
					}
				}
				else {
					$keyOrPwd = $pass;
					log::add('Monitoring', 'debug', '[SSH-Pwd] Authentification SSH par Mot de passe :: '. $equipement);
				}

				try {
					$sshconnection->login($user, $keyOrPwd);			
				} catch (Exception $e) {
					log::add('Monitoring', 'debug', '[SSH-Login] Authentification SSH :: '. $equipement .' :: '. $e->getMessage());
					$cnx_ssh = 'KO';
				}		
				if ($cnx_ssh != 'KO') {
					log::add('Monitoring', 'debug', '[SSH-Login] Authentification SSH :: '. $equipement .' :: OK');
					if($this->getConfiguration('synology') == '1'){
						switch ($paramaction) {
							case "reboot":
								try {
									$rebootcmd = "sudo /sbin/shutdown -r now >/dev/null & /sbin/shutdown -r now >/dev/null";
									$sshconnection->exec($rebootcmd);
								} catch (Exception $e) {
									log::add('Monitoring','debug','[SYNO-REBOOT] Exception [REBOOT] :: '. $equipement .' :: '. $e->getMessage());	
								}
								log::add('Monitoring','info','[SYNO-REBOOT] Lancement commande distante REBOOT :: '. $equipement);
								break;
							case "poweroff":
								try {
									// $poweroffcmd = 'sudo /sbin/shutdown -P now >/dev/null & /sbin/shutdown -P now >/dev/null';
									$poweroffcmd = 'sudo /sbin/shutdown -h now >/dev/null & /sbin/shutdown -h now >/dev/null';
									$sshconnection->exec($poweroffcmd);
								} catch (Exception $e) {
									log::add('Monitoring','debug','[SYNO-OFF] Exception [POWEROFF] :: '. $equipement .' :: '. $e->getMessage());	
								}
								log::add('Monitoring','info','[SYNO-OFF] Lancement commande distante POWEROFF :: '. $equipement);
								break;
						}
					}
					else {
						switch ($paramaction) {
							case "reboot":
								log::add('Monitoring','info','[SSH-REBOOT] Lancement commande distante REBOOT :: '. $equipement);
								try {
									// $rebootcmd = "sudo shutdown -r now >/dev/null & shutdown -r now >/dev/null";
									$rebootcmd = "sudo reboot >/dev/null & reboot >/dev/null";
									$sshconnection->exec($rebootcmd);
								} catch (Exception $e) {
									log::add('Monitoring','debug','[SSH-REBOOT] Exception [REBOOT] :: '. $equipement .' :: '. $e->getMessage());	
								}
								break;
							case "poweroff":
								log::add('Monitoring','info','[SSH-OFF] Lancement commande distante POWEROFF :: '. $equipement);
								try {
									// $poweroffcmd = 'sudo shutdown -h now >/dev/null & shutdown -h now >/dev/null';
									$poweroffcmd = "sudo poweroff >/dev/null & poweroff >/dev/null";
									$sshconnection->exec($poweroffcmd);
								} catch (Exception $e) {
									log::add('Monitoring','debug','[SSH-OFF] Exception [POWEROFF] :: '. $equipement .' :: '. $e->getMessage());	
								}
								log::add('Monitoring','info','[SSH-OFF] Lancement commande distante POWEROFF :: '. $equipement);
								break;
						}
					}
				}
			}
		}
		elseif ($this->getConfiguration('maitreesclave') == 'local' && $this->getIsEnable()) {
			$equipement = $this->getName();
			if($this->getConfiguration('synology') == '1'){
				switch ($paramaction) {
					case "reboot":
						$rebootcmd = "sudo /sbin/shutdown -r now >/dev/null & /sbin/shutdown -r now >/dev/null";
						log::add('Monitoring','info','[SYNO-REBOOT] Lancement commande locale REBOOT :: '. $equipement);
						exec($rebootcmd);
						break;
					case "poweroff":
						$poweroffcmd = 'sudo /sbin/shutdown -h now >/dev/null & /sbin/shutdown -h now >/dev/null';
						log::add('Monitoring','info','[SYNO-OFF] Lancement commande locale POWEROFF :: '. $equipement);
						exec($poweroffcmd);
						break;
				}
			}
			else {
				switch ($paramaction) {
					case "reboot":
						// $rebootcmd = "sudo shutdown -r now >/dev/null & shutdown -r now >/dev/null";
						$rebootcmd = "sudo reboot >/dev/null & reboot >/dev/null";
						log::add('Monitoring','debug','[LINUX-REBOOT] Lancement commande locale REBOOT :: '. $equipement);
						exec($rebootcmd);
						break;
					case "poweroff":
						// $poweroffcmd = 'sudo shutdown -h now >/dev/null & shutdown -h now >/dev/null';
						$poweroffcmd = "sudo poweroff >/dev/null & poweroff >/dev/null";
						log::add('Monitoring','debug','[LINUX-OFF] Lancement commande locale POWEROFF :: '. $equipement);
						exec($poweroffcmd);
						break;
				}
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
			$eqLogic->getCmd();
			$eqLogic->getCaseAction($paramaction);
		} else {
			throw new Exception(__('Commande non implémentée actuellement', __FILE__));
		}
		return true;
	}
}

?>
