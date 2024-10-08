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

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
	if (!isset(_cmd)) {
		var _cmd = { configuration: {} };
	}
	if (!isset(_cmd.configuration)) {
		_cmd.configuration = {}
	}
	let tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	tr += '<td class="hidden-xs">';
	tr += '<span class="cmdAttr" data-l1key="id"></span>';
	tr += '</td>';
	tr += '<td>';
	tr += '<div class="input-group">'
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" value="info" style="display: none">';
	tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
	tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
	tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding: 0 5px 0 5px!important;"></span>';
	tr += '</div>';
	
	if (['cron_status', 'cron_on', 'cron_off'].includes(init(_cmd.logicalId))) {
		tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">';
  		tr += '<option value="">{{Aucune}}</option>';
		tr += '</select>';
	}
	tr += '</td>';
	tr += '<td>';
	if (['load_avg_1mn', 'load_avg_5mn', 'load_avg_15mn', 'memory_used_percent', 'swap_used_percent', 'cpu_temp', 'hdd_used_percent', 'syno_hddv2_used_percent', 'syno_hddusb_used_percent', 'syno_hddesata_used_percent'].includes(init(_cmd.logicalId))) {
		tr += '<span style="color: green;font-weight: bold;">[Vert] \< <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorlow" type="text" style="margin: 1px auto;width: 60px;display: inherit" /></span><span style="color: orange;font-weight: bold;"> \u{2264} [Orange] \u{2264} </span><span style="color: red;font-weight: bold;"><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorhigh" style="margin: 1px auto;width: 60px;display: inherit" /> \< [Rouge]</span>';
	}
	if (['memory_free_percent', 'swap_free_percent', 'memory_available_percent', 'hdd_free_percent', 'syno_hddv2_free_percent', 'syno_hddusb_free_percent', 'syno_hddesata_free_percent'].includes(init(_cmd.logicalId))) {
		tr += '<span style="color: red;font-weight: bold;">[Rouge] \< <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorlow" type="text" style="margin: 1px auto;width: 60px;display: inherit" /></span><span style="color: orange;font-weight: bold;"> \u{2264} [Orange] \u{2264} </span><span style="color: green;font-weight: bold;"><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorhigh" style="margin: 1px auto;width: 60px;display: inherit" /> \< [Vert]</span>';
	}
	if (['perso1', 'perso2'].includes(init(_cmd.logicalId))) {
		tr += '<span><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '" style="margin: 1px auto;width: 70%;display: inherit" ></input></span>';
		tr += '<span> Unité : <input class="cmdAttr form-control input-sm" data-l1key="unite" style="margin: 1px auto;width: 10%;display: inherit" ></input></span>';
        tr += '<br/><span style="color: green;font-weight: bold;">[Vert] \< <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorlow" type="text" style="margin: 1px auto;width: 60px;display: inherit" /></span><span style="color: orange;font-weight: bold;"> \u{2264} [Orange] \u{2264} </span><span style="color: red;font-weight: bold;"><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorhigh" style="margin: 1px auto;width: 60px;display: inherit" /> \< [Rouge]</span>';
	}
	tr += '</td>';
	tr += '<td>';
	tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isVisible" checked /> {{Afficher}}<br/></label>';
	
	if (['cron_status', 'uptime_sec', 'load_avg_1mn', 'load_avg_5mn', 'load_avg_15mn', 'memory_total', 'memory_used', 'memory_free', 'memory_buffcache', 'memory_available', 'memory_free_percent', 'memory_used_percent', 'memory_available_percent', 'swap_free_percent', 'swap_used_percent', 'swap_total', 'swap_used', 'swap_free', 'network_tx', 'network_rx', 'hdd_total', 'hdd_used', 'hdd_free', 'hdd_used_percent', 'hdd_free_percent', 'cpu_temp', 'perso1', 'perso2', 'syno_hddv2_total', 'syno_hddv2_used', 'syno_hddv2_free', 'syno_hddv2_used_percent', 'syno_hddv2_free_percent', 'syno_hddusb_total', 'syno_hddusb_used', 'syno_hddusb_used_percent', 'syno_hddusb_free', 'syno_hddusb_free_percent', 'syno_hddesata_total', 'syno_hddesata_used', 'syno_hddesata_used_percent', 'syno_hddesata_free', 'syno_hddesata_free_percent'].includes(init(_cmd.logicalId))) {
		tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isHistorized" /> {{Historiser}}</label>';
	}
	tr += '</td>';
	tr += '<td>';
	if (['perso1', 'perso2', 'cron_status'].includes(init(_cmd.logicalId))) {
		tr += '<span class="type" type="info"></span>';
		tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
	}
	tr += '</td>';
	tr += '<td>';
	tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
	tr += '</td>';
	
	tr += '<td>';
	if (is_numeric(_cmd.id)) {
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
	}
	tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i>';
	tr += '</td>';
	tr += '</tr>';
	
	let newRow = document.createElement('tr')
	newRow.innerHTML = tr
	newRow.addClass('cmd')
	newRow.setAttribute('data-cmd_id', init(_cmd.id))
	document.getElementById('table_cmd').querySelector('tbody').appendChild(newRow)

	jeedom.eqLogic.buildSelectCmd({
		id: document.querySelector('.eqLogicAttr[data-l1key="id"]').jeeValue(),
		filter: { type: 'info' },
		error: function(error) {
		  jeedomUtils.showAlert({ message: error.message, level: 'danger' })
		},
		success: function(result) {
		  newRow.querySelector('.cmdAttr[data-l1key="value"]')?.insertAdjacentHTML('beforeend', result)
		  newRow.setJeeValues(_cmd, '.cmdAttr')
		  jeedom.cmd.changeType(newRow, init(_cmd.subType))
		}
	})
}

document.querySelectorAll('.pluginAction[data-action=openLocation]').forEach(function (element) {
	element.addEventListener('click', function () {
		window.open(this.getAttribute("data-location"), "_blank", null);
	});
});

document.querySelector(".eqLogicAttr[data-l2key='synology']").addEventListener('change', function() {
	if (this.checked) {
		document.querySelector(".syno_conf").style.display = "block";
	} else {
		document.querySelector(".syno_conf").style.display = "none";
	}
});

document.querySelector(".eqLogicAttr[data-l2key='syno_use_temp_path']").addEventListener('change', function () {
	if(this.checked){
	  document.querySelector(".syno_conf_temppath").style.display = "block";
	} else {
	  document.querySelector(".syno_conf_temppath").style.display = "none";
	}
});

document.querySelector(".eqLogicAttr[data-l2key='linux_use_temp_cmd']").addEventListener('change', function() {
	if (this.checked) {
		document.querySelector(".linux_class_temp_cmd").style.display = "block";
	} else {
		document.querySelector(".linux_class_temp_cmd").style.display = "none";
	}
});

document.querySelector(".eqLogicAttr[data-l2key='pull_use_custom']").addEventListener('change', function () {
	if(this.checked){
	  document.querySelector(".pull_class").style.display = "block";
	} else {
	  document.querySelector(".pull_class").style.display = "none";
	}
});

document.querySelector(".eqLogicAttr[data-l2key='localoudistant']").addEventListener('change', function () {
	if (this.selectedIndex == 1) {
	  document.querySelector(".distant").style.display = "block";
	  document.querySelector(".local").style.display = "none";
	} else { 
		document.querySelector(".distant").style.display = "none";
		document.querySelector(".local").style.display = "block";
	}
});

function printEqLogic(_eqLogic) {
	buildSelectHost(_eqLogic.configuration.SSHHostId);
}
