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
		var _cmd = {configuration: {}};
	}
	let tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	tr += '<td class="hidden-xs">';
	tr += '<span class="cmdAttr" data-l1key="id"></span>';
	tr += '</td>';
	tr += '<td>';
	if (_cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2' || _cmd.logicalId == 'cron_on' || _cmd.logicalId == 'cron_off') {
		tr += '<div class="input-group">'
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" value="info" style="display: none">';
		tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
		tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
		tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding: 0 5px 0 5px!important;"></span>';
		tr += '</div>';
	}
	else
	{
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" value="info" style="display: none">';
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}" style="margin: 1px auto;">';
	}
	if (_cmd.logicalId == 'cron_status' || _cmd.logicalId == 'cron_on' || _cmd.logicalId == 'cron_off') {
		tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">';
  		tr += '<option value="">{{Aucune}}</option>';
		tr += '</select>';
	}
	tr += '</td>';
	tr += '<td>';
	if (_cmd.logicalId == 'loadavg1mn' || _cmd.logicalId == 'loadavg5mn' || _cmd.logicalId == 'loadavg15mn' || _cmd.logicalId == 'cpu_temp' || _cmd.logicalId == 'hddpourcused' || _cmd.logicalId == 'hddpourcusedv2' || _cmd.logicalId == 'hddpourcusedusb' || _cmd.logicalId == 'hddpourcusedesata') {
		tr += '<span class="cmdAttr" style="color: green;font-weight: bold;">[Vert] \< <input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorlow" type="text" style="margin: 1px auto;width: 60px;display: inherit" /></span><span class="cmdAttr" style="color: orange;font-weight: bold;"> \u{2264} [Orange] \u{2264} </span><span class="cmdAttr" style="color: red;font-weight: bold;"><input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorhigh" style="margin: 1px auto;width: 60px;display: inherit" /> \< [Rouge]</span>';
	}
	if (_cmd.logicalId == 'Mempourc' || _cmd.logicalId == 'Swappourc') {
		tr += '<span class="cmdAttr" style="color: red;font-weight: bold;">[Rouge] \< <input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorlow" type="text" style="margin: 1px auto;width: 60px;display: inherit" /></span><span class="cmdAttr" style="color: orange;font-weight: bold;"> \u{2264} [Orange] \u{2264} </span><span class="cmdAttr" style="color: green;font-weight: bold;"><input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorhigh" style="margin: 1px auto;width: 60px;display: inherit" /> \< [Vert]</span>';
	}
	if (_cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2') {
		tr += '<span><input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '" style="margin: 1px auto;width: 70%;display: inherit" ></input></span>';
		tr += '<span class="cmdAttr"> Unité : <input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_unite" style="margin: 1px auto;width: 10%;display: inherit" ></input></span>';
        tr += '<br/><span class="cmdAttr" style="color: green;font-weight: bold;">[Vert] \< <input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorlow" type="text" style="margin: 1px auto;width: 60px;display: inherit" /></span><span class="cmdAttr" style="color: orange;font-weight: bold;"> \u{2264} [Orange] \u{2264} </span><span class="cmdAttr" style="color: red;font-weight: bold;"><input class="cmdAttr eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="' + init(_cmd.logicalId) + '_colorhigh" style="margin: 1px auto;width: 60px;display: inherit" /> \< [Rouge]</span>';
	}
	tr += '</td>';
	
	tr += '<td>';
	if (_cmd.logicalId == 'reboot' || _cmd.logicalId == 'poweroff' || _cmd.logicalId == 'namedistri' || _cmd.logicalId == 'uptime' || _cmd.logicalId == 'loadavg1mn' || _cmd.logicalId == 'Mem' || _cmd.logicalId == 'Mem_swap' || _cmd.logicalId == 'ethernet0' || _cmd.logicalId == 'hddtotal' || _cmd.logicalId == 'cpu_temp' || _cmd.logicalId == 'hddtotalv2' || _cmd.logicalId == 'hddtotalusb' || _cmd.logicalId == 'hddtotalesata' || _cmd.logicalId == 'cpu' || _cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2' || _cmd.logicalId == 'cron_status' || _cmd.logicalId == 'cron_on' || _cmd.logicalId == 'cron_off') {
		tr += '<span><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isVisible" checked/> {{Afficher}}<br/></span>';
	}
	if (_cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2' || _cmd.logicalId == 'loadavg1mn' || _cmd.logicalId == 'loadavg5mn' || _cmd.logicalId == 'loadavg15mn' || _cmd.logicalId == 'Mempourc' || _cmd.logicalId == 'Swappourc' || _cmd.logicalId == 'cpu_temp' || _cmd.logicalId == 'hddpourcused' || _cmd.logicalId == 'hddpourcusedv2' || _cmd.logicalId == 'hddpourcusedusb' || _cmd.logicalId == 'hddpourcusedesata' || _cmd.logicalId == 'cron_status') {
		tr += '<span><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isHistorized"/> {{Historiser}}</span>';
	}
	tr += '</td>';

	tr += '<td>';
	if (_cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2' || _cmd.logicalId == 'cron_status') {
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
		newRow.querySelector('.cmdAttr[data-l1key="value"]').insertAdjacentHTML('beforeend', result)
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
	} else { 
		document.querySelector(".distant").style.display = "none";
	}
});

function printEqLogic(_eqLogic) {
	buildSelectHost(_eqLogic.configuration.SSHHostId);
}
