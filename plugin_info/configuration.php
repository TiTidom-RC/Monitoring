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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}

if (version_compare(jeedom::version(), '4.4', '<')) {
    $updateMon = update::byLogicalId('Monitoring');
    if (is_object($updateMon)) {
        $_doNotUpdate = $updateMon->getConfiguration('doNotUpdate', 0);
        if ($_doNotUpdate == 0) {
            event::add('jeedom::alert', array(
                'level' => 'danger',
                'title' => __('[Plugin :: Monitoring] Attention - Version Jeedom !', __FILE__),
                'message' => __('[ATTENTION] La prochaine version du plugin Monitoring ne supportera plus les versions de Jeedom < "4.4".<br />Veuillez mettre à jour Jeedom pour bénéficier des dernières fonctionnalités.<br /><br />En attendant, il est conseillé de bloquer les mises à jour du plugin Monitoring.', __FILE__),
            ));
            log::add('Monitoring', 'warning', __('[ATTENTION] La prochaine version du plugin Monitoring ne supportera plus les versions de Jeedom < "4.4". Veuillez mettre à jour Jeedom pour bénéficier des dernières fonctionnalités. En attendant, il est conseillé de bloquer les mises à jour du plugin Monitoring.', __FILE__));
        }
    }
}

$_versionSSHManager = config::byKey('pluginVersion', 'sshmanager', 'N/A');

$_branchSSHManager = config::byKey('pluginBranch', 'sshmanager', 'N/A');
$_branchMonitoring = config::byKey('pluginBranch', 'Monitoring', 'N/A');

if (strpos($_branchMonitoring, 'stable') !== false) {
    $_labelBranchMon = '<span class="label label-success text-capitalize">' . $_branchMonitoring . '</span>';
} elseif (strpos($_branchMonitoring, 'beta') !== false) {
    $_labelBranchMon = '<span class="label label-warning text-capitalize">' . $_branchMonitoring . '</span>';
} elseif (strpos($_branchMonitoring, 'dev') !== false) {
    $_labelBranchMon = '<span class="label label-danger text-capitalize">' . $_branchMonitoring . '</span>';
} else {
    $_labelBranchMon = '<span class="label label-info">N/A</span>';
}

if (strpos($_branchSSHManager, 'stable') !== false) {
    $_labelBranchSSHM = '<span class="label label-success text-capitalize">' . $_branchSSHManager . '</span>';
} elseif (strpos($_branchSSHManager, 'beta') !== false) {
    $_labelBranchSSHM = '<span class="label label-warning text-capitalize">' . $_branchSSHManager . '</span>';
} elseif (strpos($_branchSSHManager, 'dev') !== false) {
    $_labelBranchSSHM = '<span class="label label-danger text-capitalize">' . $_branchSSHManager . '</span>';
} else {
    $_labelBranchSSHM = '<span class="label label-info">N/A</span>';
}

?>

<form class="form-horizontal">
    <fieldset>
        <div>
            <legend><i class="fas fa-info"></i> {{Plugin(s)}}</legend>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Version Monitoring}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Version du plugin Monitoring à indiquer sur Community}}"></i></sup>
                </label>
                <div class="col-md-1">
                    <input class="configKey form-control" data-l1key="pluginVersion" readonly />
                </div>
                <div class="col-md-1">
                    <?php echo $_labelBranchMon ?>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Version SSH Manager}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Version du plugin SSH Manager à indiquer sur Community}}"></i></sup>
                </label>
                <div class="col-md-1">
                    <input class="form-control" value="<?php echo $_versionSSHManager ?>" readonly />
                </div>
                <div class="col-md-1">
                    <?php echo $_labelBranchSSHM ?>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Désactiver les messages de MàJ}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Cocher cette case désactivera les messages de mise à jour du plugin dans le centre de message}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <input type="checkbox" class="configKey" data-l1key="disableUpdateMsg" />
                </div>
            </div>
            <legend><i class="fas fa-tasks"></i> {{Mises à jour Automatiques}} :</legend>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Equipement Local (1 min)}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Activer ou Désactiver les MàJ auto (toutes les minutes) de l'équipement local}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <input type="checkbox" class="configKey form-control" data-l1key="configPullLocal" />
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Equipements Distants (15 min)}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Activer ou Désactiver les MàJ auto (toutes les 15 minutes) des équipements distants}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <input type="checkbox" class="configKey form-control" data-l1key="configPull" checked />
                </div>
            </div>
            <legend><i class="fas fa-clipboard-check"></i> {{Migration v2.5 -> v3.0}}</legend>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Statut Migration}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Si cette case est cochée, alors la migration de la version 2.5 vers 3.0 a déjà été effectuée au moins une fois}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <input type="checkbox" class="configKey" data-l1key="isMigrated" disabled />
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Migrer les paramètres SSH des équipements}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Migrer les paramètres des hôtes distants de vos équipements (v2.5) vers SSH Manager}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <a class="btn btn-warning customclass-migrate"><i class="fas fa-play-circle"></i> {{Migrer (v2.5 -> v3.0)}}</a>
                </div>
            </div>
            <legend><i class="fas fa-chart-line"></i> {{Statistiques Mémoire}} :</legend>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Stats Mémoire Equipement Local}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Activer ou Désactiver l'affichage des statistiques mémoire de l'équipement local dans les logs}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <input type="checkbox" class="configKey form-control" data-l1key="configStatsMemLocal" />
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Stats Mémoire Equipements Distants}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Activer ou Désactiver l'affichage des statistiques mémoire des équipements distants dans les logs}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <input type="checkbox" class="configKey form-control" data-l1key="configStatsMemDistants" />
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-3 control-label">{{Stats Mémoire Cron Personnalisés}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Activer ou Désactiver l'affichage des statistiques mémoire des cron personnalisés dans les logs}}"></i></sup>
                </label>
                <div class="col-md-3">
                    <input type="checkbox" class="configKey form-control" data-l1key="configStatsMemCustom" />
                </div>
            </div>
        </div>
    </fieldset>
</form>

<script>
    document.querySelector('.customclass-migrate').addEventListener('click', function() {
        jeeDialog.confirm({
            title: '<i class="warning fas fa-question-circle"></i> Migration v2.5 -> v3.0',
            message: 'Etes-vous sûr de vouloir lancer la migration de la configuration des équipements distants vers SSH Manager ?',
            buttons: {
                confirm: {
                    label: 'Migrer',
                    className: 'warning'
                },
                cancel: {
                    label: 'Annuler',
                    className: 'info'
                }
            },
            callback: function (result) {
                if (result) {
                    domUtils.ajax({
                        type: 'POST',
                        url: 'plugins/Monitoring/core/ajax/Monitoring.ajax.php',
                        data: {
                            action: "doMigration",
                        },
                        dataType: 'json',
                        async: true,
                        global: false,
                        error: function (request, status, error) {
                            handleAjaxError(request, status, error);
                        },
                        success: function (data) {
                            if (data.state != 'ok') {
                                jeedomUtils.showAlert({
                                    title: "Monitoring - Migration v2.5 -> v3.0",
                                    message: data.result,
                                    level: 'danger',
                                    emptyBefore: false
                                });
                                return;
                            } else {
                                jeedomUtils.showAlert({
                                    title: "Monitoring - Migration v2.5 -> v3.0",
                                    message: data.result,
                                    level: 'success',
                                    emptyBefore: false
                                });
                            }
                        }
                    });
                }
            }
        });
    });
</script>