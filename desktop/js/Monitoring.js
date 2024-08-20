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

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
	axis: "y",
	cursor: "move",
	items: ".cmd",
	placeholder: "ui-state-highlight",
	tolerance: "intersect",
	forcePlaceholderSize: true
  });

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
	if (!isset(_cmd)) {
		var _cmd = {configuration: {}};
	}
	var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	tr += '<td class="hidden-xs">';
	tr += '<span class="cmdAttr" data-l1key="id"></span>';
	tr += '</td>';
	tr += '<td>';
	if (_cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2') {
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
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="margin: 1px auto;">';
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
	if (_cmd.logicalId == 'reboot' || _cmd.logicalId == 'poweroff' || _cmd.logicalId == 'namedistri' || _cmd.logicalId == 'uptime' || _cmd.logicalId == 'loadavg1mn' || _cmd.logicalId == 'Mem' || _cmd.logicalId == 'Mem_swap' || _cmd.logicalId == 'ethernet0' || _cmd.logicalId == 'hddtotal' || _cmd.logicalId == 'cpu_temp' || _cmd.logicalId == 'hddtotalv2' || _cmd.logicalId == 'hddtotalusb' || _cmd.logicalId == 'hddtotalesata' || _cmd.logicalId == 'cpu' || _cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2') {
		tr += '<span><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isVisible" checked/> {{Afficher}}<br/></span>';
	}
	if (_cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2' || _cmd.logicalId == 'loadavg1mn' || _cmd.logicalId == 'loadavg5mn' || _cmd.logicalId == 'loadavg15mn' || _cmd.logicalId == 'Mempourc' || _cmd.logicalId == 'Swappourc' || _cmd.logicalId == 'cpu_temp' || _cmd.logicalId == 'hddpourcused' || _cmd.logicalId == 'hddpourcusedv2' || _cmd.logicalId == 'hddpourcusedusb' || _cmd.logicalId == 'hddpourcusedesata') {
		tr += '<span><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isHistorized"/> {{Historiser}}</span>';
	}
	tr += '</td>';

	tr += '<td>';
	if (_cmd.logicalId == 'perso1' || _cmd.logicalId == 'perso2') {
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
	tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
	tr += '</td>';
	
	tr += '</tr>';
	$('#table_cmd tbody').append(tr);
	$('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
	jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}

$('.pluginAction[data-action=openLocation]').on('click', function () {
	window.open($(this).attr("data-location"), "_blank", null);
});

$(".eqLogicAttr[data-l2key='synology']").on('change', function () {
	if(this.checked){
	  $(".syno_conf").show();
	} else {
	  $(".syno_conf").hide();
	}
});

$(".eqLogicAttr[data-l2key='syno_use_temp_path']").on('change', function () {
	if(this.checked){
	  $(".syno_conf_temppath").show();
	} else {
	  $(".syno_conf_temppath").hide();
	}
});

$(".eqLogicAttr[data-l2key='linux_use_temp_cmd']").on('change', function () {
	if(this.checked){
	  $(".linux_class_temp_cmd").show();
	} else {
	  $(".linux_class_temp_cmd").hide();
	}
});

$(".eqLogicAttr[data-l2key='maitreesclave']").on('change', function () {
	if (this.selectedIndex == 1) {
	  $(".distant").show();
	  $(".distant-password").show();
	  $(".distant-key").hide();
	} else if (this.selectedIndex == 2) {
		$(".distant").show();
		$(".distant-password").hide();
		$(".distant-key").show();
	} else { 
		$(".distant").hide();
	}
});

function toggleSSHPassword() {
	var sshPasswordIcon = document.getElementById("btnToggleSSHPasswordIcon");
	var sshPasswordField = document.getElementById("ssh-password");
	if (sshPasswordField.type === "password") {
		sshPasswordIcon.classList.toggle("fas fa-eye-slash");
		sshPasswordField.type = "text";
	} else {
		sshPasswordIcon.classList.toggle("fas fa-eye");
		sshPasswordField.type = "password";
	}
}

function toggleSSHPassphrase() {
	var sshPassphraseIcon = document.getElementById("btnToggleSSHPassphraseIcon");
	var sshPassphraseField = document.getElementById("ssh-passphrase");
	if (sshPassphraseField.type === "password") {
		sshPassphraseIcon.className = "fas fa-eye-slash";
		sshPassphraseField.type = "text";

	} else {
		sshPassphraseIcon.className = "fas fa-eye";
		sshPassphraseField.type = "password";
	}
}