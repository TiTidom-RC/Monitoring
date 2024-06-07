<?php
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

//Get higher object to show:
if (init('object_id') == '') {
	$object = jeeObject::byId($_SESSION['user']->getOptions('defaultDashboardObject'));
} else {
	$object = jeeObject::byId(init('object_id'));
}

//Check for object found:
$mbState = config::byKey('mbState');
if (!is_object($object)) {
	$object = jeeObject::rootObject();
	if (!is_object($object)) {
		$alert = '{{Aucun objet racine trouvé. Pour en créer un, allez dans Outils -> Objets}}.<br/>';
		if ($mbState == 0) {
			if (config::byKey('doc::base_url', 'core') != '') {
				$alert .= '{{Documentation}} : <a href="' . config::byKey('doc::base_url', 'core') . '/fr_FR/concept/" class="cursor label alert-info" target="_blank">{{Concepts}}</a>';
				$alert .= ' | <a href="' . config::byKey('doc::base_url', 'core') . '/fr_FR/premiers-pas/" class="cursor label alert-info" target="_blank">{{Premiers pas}}</a>';
			}
		}
		echo '<div class="alert alert-warning">' . $alert . '</div>';
		return;
	}
}

//Get all object in right order, coming from Dashboard or Synthesis, showing childs or not, or by summaries:
$objectTree = jeeObject::buildTree($object, true);
sendVarToJs('jeephp2js.rootObjectId', $object->getId());
if (init('childs', 1) == 1) {
	$allObject = $objectTree;
} else {
	$allObject = array();
}

if (!$object->hasRight('r') && count($allObject) > 0) {
	$object = $allObject[0];
}

//cache object summaries to not duplicate calls:
global $summaryCache;
$summaryCache = [];
foreach ($objectTree as $_object) {
	$summaryCache[$_object->getId()] = $_object->getHtmlSummary();
}

global $columns;
$columns = config::byKey('dahsboard::column::size');
?>

<div class="row row-overflow">
</div>
<div id="div_displayObject">
	<?php
		function formatJeedomObjectDiv($object, $toSummary = false) {
			global $columns;
			global $summaryCache;
			$objectId =  $object->getId();
			$divClass = 'div_object';
			if ($toSummary) $divClass .= ' hidden';
			$div =  '<div class="' . $columns . '" >';
			$div .= '<div data-object_id="' . $objectId . '" data-father_id="' . $object->getFather_id() . '" class="' . $divClass . '">';
			$div .= '<legend><span class="objectDashLegend fullCorner">';
			if (init('childs', 1) == 0) {
				$div .= '<a href="index.php?v=d&p=dashboard&object_id=' . $objectId . '&childs=0&btover=1"><i class="icon jeedomapp-fleche-haut-line"></i></a>';
			} else {
				$div .= '<a href="index.php?v=d&p=dashboard&object_id=' . $objectId . '&childs=0"><i class="icon jeedomapp-fleche-haut-line"></i></a>';
			}
			$div .= '<a href="index.php?v=d&p=object&id=' . $objectId . '">' . $object->getDisplay('icon') . ' ' . ucfirst($object->getName()) . '</a>';
			if (isset($summaryCache[$objectId])) {
				$div .= '<span>' . $summaryCache[$objectId] . '</span>';
			}
			$div .= '<i class="fas fa-compress pull-right cursor bt_editDashboardTilesAutoResizeDown" title="{{Régler toutes les tuiles à la hauteur de la moins haute.}}" data-obecjtId="' . $objectId . '" style="display: none;"></i>
			<i class="fas fa-expand pull-right cursor bt_editDashboardTilesAutoResizeUp" title="{{Régler toutes les tuiles à la hauteur de la plus haute.}}" data-obecjtId="' . $objectId . '" style="display: none;"></i>
			</span>
			</legend>';
			$div .= '<div class="div_displayEquipement posEqWidthRef" id="div_ob' . $objectId . '">';
			$div .= '</div></div></div>';
			echo $div;
		}
	?>
	<div class="row">
		<?php
			//show root object and all its childs:
			$childs = array();
			if (count($allObject) == 1) {
				$columns = 'col-xs-12';
			}
			foreach ($allObject as $thisObject) {
				if ($thisObject->getId() != $object->getId()) {
					continue;
				}
				foreach (($thisObject->getChilds()) as $child) {
					if ($child->getConfiguration('hideOnDashboard', 0) == 1 || !$child->hasRight('r')) {
						continue;
					}
					$childs[] = $child;
				}
			}
			if (count($childs) == 0) {
				$columns = 'col-xs-12';
			}
			if ($object->hasRight('r')) {
				formatJeedomObjectDiv($object);
			}
			foreach ($childs as $child) {
				formatJeedomObjectDiv($child);
			}
		?>
	</div>
</div>
<?php
include_file('desktop/common', 'ui', 'js');
include_file('desktop', 'panel', 'js', 'Monitoring');
?>