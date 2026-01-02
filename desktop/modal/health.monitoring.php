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

if (!isConnect()) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

?>

<div style="display: none;" id="md_modal"></div>

<div class="healthMonitoring" style="width:100%;height:100%;overflow:auto;">
    <legend><i class="fas fa-heartbeat"></i> {{Santé des équipements Monitoring}}</legend>
    <br/>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> {{Résumé de l'état de santé de tous vos équipements Monitoring}}
    </div>
    
    <table class="table table-condensed table-bordered" id="table_healthMonitoring">
        <thead>
            <tr>
                <th style="white-space:nowrap;">{{Nom}}</th>
                <th style="text-align:center;">{{Actif}}</th>
                <th style="text-align:center;">{{Visible}}</th>
                <th style="text-align:center;">{{Type}}</th>
                <th style="white-space:nowrap;">{{Hôte SSH}}</th>
                <th>{{SSH Status}}</th>
                <th>{{Cron Status}}</th>
                <th style="white-space:nowrap;">{{Uptime}}</th>
                <th>{{Charge 1min}}</th>
                <th>{{Charge 5min}}</th>
                <th>{{Charge 15min}}</th>
                <th style="white-space:nowrap;">{{Adresse IP}}</th>
                <th style="white-space:nowrap;">{{Dernière Communication}}</th>
                <th style="white-space:nowrap;">{{Dernière Valeur}}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="14" class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> {{Chargement des données...}}
                </td>
            </tr>
        </tbody>
    </table>
</div>

<?php
include_file('desktop', 'health.monitoring', 'js', 'Monitoring');
?>

<script>
    initModalHealthMonitoring();
</script>
