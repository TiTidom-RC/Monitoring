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
?>

<form class="form-horizontal">
    <fieldset>
        <div>
            <legend><i class="fas fa-info"></i> {{Plugin}}</legend>
            <div class="form-group">
                <label class="col-md-4 control-label">{{Version}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Version du plugin à indiquer sur Community}}"></i></sup>
                </label>
                <div class="col-md-1">
                    <input class="configKey form-control" data-l1key="pluginVersion" readonly />
                </div>
            </div>
            <legend><i class="fas fa-tasks"></i> {{Mises à jour Automatiques}} :</legend>
            <div class="form-group">
                <label class="col-md-4 control-label">{{Equipement Local (1 min)}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Activer ou Désactiver les MàJ auto (toutes les minutes) de l'équipement local}}"></i></sup>
                </label>
                <div class="col-md-4">
                    <input type="checkbox" class="configKey form-control" data-l1key="configPullLocal" />
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 control-label">{{Equipements Distants (15 min)}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Activer ou Désactiver les MàJ auto (toutes les 15 minutes) des équipements distants}}"></i></sup>
                </label>
                <div class="col-md-4">
                    <input type="checkbox" class="configKey form-control" data-l1key="configPull" checked />
                </div>
            </div>
            <legend><i class="fas fa-clipboard-check"></i> {{Migration (Conf Monitoring => SSH-Manager)}}</legend>
            <div class="form-group">
                <label class="col-lg-3 control-label">{{Statut Migration}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Si ce paramètre est coché, alors la migration a déjà été effectuée}}"></i></sup>
                </label>
                <div class="col-lg-1">
                    <input type="checkbox" class="configKey" data-l1key="isMigrated" disabled />
                </div>
            </div>
            <div class="form-group">
                <label class="col-lg-3 control-label">{{Migrer la configuration des hôtes}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Sauvegardez bien votre configuration AVANT d'utiliser le bouton (GENERER + DIFFUSER)}}"></i></sup>
                </label>
                <div class="col-lg-1">
                    <a class="btn btn-warning customclass-migrate"><i class="fas fa-play-circle"></i> {{Générer + Diffuser}}</a>
                </div>
            </div>
        </div>
    </fieldset>
</form>