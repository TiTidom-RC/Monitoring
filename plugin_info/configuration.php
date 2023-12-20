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
?>

<form class="form-horizontal">
  <fieldset>
    <div>
      <legend><i class="fas fa-clock"></i> {{Cron::Pull}}</legend>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Mises à jour Auto de l'équipement local (1 min)}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Active ou désactive les mises à jour automatiques toutes les minutes de l'équipement local}}"></i></sup>
        </label>
        <div class="col-md-4">
          <input type="checkbox" class="configKey form-control" data-l1key="configPullLocal" checked />
        </div>
      </div>  
      <div class="form-group">
        <label class="col-md-4 control-label">{{Mises à jour Auto des équipements distants (15 min)}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Active ou désactive les mises à jour automatiques toutes les 15 minutes des équipements distants}}"></i></sup>
        </label>
        <div class="col-md-4">
          <input type="checkbox" class="configKey form-control" data-l1key="configPull" checked />
        </div>
      </div>
    </div>
  </fieldset>
</form>