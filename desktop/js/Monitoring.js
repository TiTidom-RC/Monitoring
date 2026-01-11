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

// Protect against multiple script loads (Jeedom SPA navigation, cache, etc.)
(() => {
'use strict'

// DOM Selectors constants (better minification + no string repetition + immutable)
const SELECTORS = Object.freeze({
  TABLE_CMD: '#table_cmd',
  EQ_ID: '.eqLogicAttr[data-l1key=id]',
  SYNO_CHECKBOX: '.eqLogicAttr[data-l2key=synology]',
  QNAP_CHECKBOX: '.eqLogicAttr[data-l2key=qnap]',
  ASUS_CHECKBOX: '.eqLogicAttr[data-l2key=asuswrt]',
  SYNO_CONF: '.syno_conf',
  ASUS_CONF: '.asuswrt_conf'
})

// Liste des commandes pouvant être historisées (en constant pour performance)
const HISTORIZED_COMMANDS = Object.freeze([
  'cron_status', 'uptime_sec', 'load_avg_1mn', 'load_avg_5mn', 'load_avg_15mn',
  'memory_total', 'memory_used', 'memory_free', 'memory_buffcache', 'memory_available',
  'memory_free_percent', 'memory_used_percent', 'memory_available_percent',
  'swap_free_percent', 'swap_used_percent', 'swap_total', 'swap_used', 'swap_free',
  'network_tx', 'network_rx', 'hdd_total', 'hdd_used', 'hdd_free',
  'hdd_used_percent', 'hdd_free_percent', 'cpu_temp',
  'perso1', 'perso2', 'perso3', 'perso4',
  'syno_hddv2_total', 'syno_hddv2_used', 'syno_hddv2_free', 'syno_hddv2_used_percent', 'syno_hddv2_free_percent',
  'syno_hddusb_total', 'syno_hddusb_used', 'syno_hddusb_used_percent', 'syno_hddusb_free', 'syno_hddusb_free_percent',
  'syno_hddesata_total', 'syno_hddesata_used', 'syno_hddesata_used_percent', 'syno_hddesata_free', 'syno_hddesata_free_percent',
  'asus_clients_total', 'asus_clients_wifi24', 'asus_clients_wifi5', 'asus_clients_wired',
  'asus_fw_check', 'asus_wifi2g_temp', 'asus_wifi5g_temp'
])

// Commandes avec seuils verts (bas) vers rouges (haut)
const GREEN_TO_RED_COMMANDS = Object.freeze([
  'load_avg_1mn', 'load_avg_5mn', 'load_avg_15mn',
  'memory_used_percent', 'swap_used_percent', 'cpu_temp',
  'hdd_used_percent', 'syno_hddv2_used_percent', 'syno_hddusb_used_percent', 'syno_hddesata_used_percent'
])

// Commandes avec seuils rouges (bas) vers verts (haut)
const RED_TO_GREEN_COMMANDS = Object.freeze([
  'memory_free_percent', 'swap_free_percent', 'memory_available_percent',
  'hdd_free_percent', 'syno_hddv2_free_percent', 'syno_hddusb_free_percent', 'syno_hddesata_free_percent'
])

// Commandes personnalisables
const CUSTOM_COMMANDS = Object.freeze(['perso1', 'perso2', 'perso3', 'perso4'])

// Commandes liées (pour select value)
const LINKED_COMMANDS = Object.freeze(['cron_status', 'cron_on', 'cron_off'])

/**
 * Helper pour générer les champs de seuils de couleurs
 */
const buildColorThresholds = (logicalId, reverseColors = false) => {
  if (reverseColors) {
    return `<span style="color:red;font-weight:bold;">[{{Rouge}}] &#60; <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="${logicalId}_colorlow" type="text" style="margin:1px auto;width:60px;display:inherit"/></span><span style="color:orange;font-weight:bold;"> ≤ [{{Orange}}] ≤ </span><span style="color:green;font-weight:bold;"><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="${logicalId}_colorhigh" style="margin:1px auto;width:60px;display:inherit"/> &#60; [{{Vert}}]</span>`
  }
  return `<span style="color:green;font-weight:bold;">[{{Vert}}] &#60; <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="${logicalId}_colorlow" type="text" style="margin:1px auto;width:60px;display:inherit"/></span><span style="color:orange;font-weight:bold;"> ≤ [{{Orange}}] ≤ </span><span style="color:red;font-weight:bold;"><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="${logicalId}_colorhigh" style="margin:1px auto;width:60px;display:inherit"/> &#60; [{{Rouge}}]</span>`
}

/**
 * Fonction permettant l'affichage des commandes dans l'équipement
 * @param {Object} _cmd - Commande à ajouter
 */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} }
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }

  const logicalId = init(_cmd.logicalId)
  
  // Vérifier si c'est une commande standard ou une commande de carte réseau supplémentaire
  const canBeHistorized = HISTORIZED_COMMANDS.includes(logicalId) || 
                        logicalId.startsWith('network_tx_') || 
                        logicalId.startsWith('network_rx_')
  
  // Déterminer le type de configuration
  const hasGreenToRed = GREEN_TO_RED_COMMANDS.includes(logicalId)
  const hasRedToGreen = RED_TO_GREEN_COMMANDS.includes(logicalId)
  const isCustom = CUSTOM_COMMANDS.includes(logicalId)
  const isLinked = LINKED_COMMANDS.includes(logicalId)
  const hasTypeSubType = isCustom || logicalId === 'cron_status'
  
  // Générer les différentes parties du HTML
  let configCell = ''
  if (hasGreenToRed) {
    configCell = buildColorThresholds(logicalId, false)
  } else if (hasRedToGreen) {
    configCell = buildColorThresholds(logicalId, true)
  } else if (isCustom) {
    configCell = `<span><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="${logicalId}" style="margin:1px auto;width:70%;display:inherit"></span>
      <span> {{Unité}} : <input class="cmdAttr form-control input-sm" title="{{Unité}}" data-l1key="unite" style="margin:1px auto;width:10%;display:inherit"></span>
      <br/>${buildColorThresholds(logicalId, false)}`
  }
  
  // Construction du HTML avec template literals (optimal V8 performance)
  const testButtons = is_numeric(_cmd.id) 
    ? '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> <a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
    : ''
  
  const rowHtml = `
    <td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span></td>
    <td>
      <div class="input-group">
        <input class="cmdAttr form-control input-sm" data-l1key="type" value="info" style="display:none">
        <input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">
        <span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>
        <span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 5px!important;"></span>
      </div>
      ${isLinked ? '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}"><option value="">{{Aucune}}</option></select>' : ''}
    </td>
    <td>${configCell}</td>
    <td>
      <label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isVisible" checked/> {{Afficher}}<br/></label>
      ${canBeHistorized ? '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-size="mini" data-l1key="isHistorized"/> {{Historiser}}</label>' : ''}
    </td>
    <td>
      ${hasTypeSubType ? `<span class="type" type="info"></span><span class="subType" subType="${init(_cmd.subType)}"></span>` : ''}
    </td>
    <td><span class="cmdAttr" data-l1key="htmlstate"></span></td>
    <td>
      ${testButtons}
      <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i>
    </td>`
  
  // Create and configure row element (optimal: Object.assign for batch properties)
  const newRow = Object.assign(document.createElement('tr'), {
    className: 'cmd',
    innerHTML: rowHtml
  })
  newRow.setAttribute('data-cmd_id', init(_cmd.id))
  
  // Cache table body for performance
  const tableBody = document.querySelector(`${SELECTORS.TABLE_CMD} tbody`)
  if (!tableBody) return console.error('Table body not found')
  
  tableBody.appendChild(newRow)

  // Cache eqLogic ID to avoid multiple DOM queries
  const eqLogicIdElement = document.querySelector(SELECTORS.EQ_ID)
  if (!eqLogicIdElement) return console.error('Equipment ID element not found')

  jeedom.eqLogic.buildSelectCmd({
    id: eqLogicIdElement.jeeValue(),
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

// Helper functions pour une meilleure réutilisation
const updateCheckboxGroup = (checkbox, showElements = [], hideElements = [], uncheckElements = []) => {
  if (!checkbox) return
  
  if (checkbox.checked) {
    showElements.forEach(el => el?.seen())
    hideElements.forEach(el => el?.unseen())  // Masquer les blocs des autres options
    uncheckElements.forEach(el => el?.jeeValue(0))  // Décocher les autres options
  } else {
    showElements.forEach(el => el?.unseen())  // Masquer le bloc de cette option
  }
}

// Handlers nommés pour pouvoir les remove/add proprement (spécifiques à chaque équipement)
const handleSynologyChange = function(event) {
  updateCheckboxGroup(
    event.currentTarget,
    [document.querySelector(SELECTORS.SYNO_CONF)],
    [document.querySelector(SELECTORS.ASUS_CONF)],
    [document.querySelector(SELECTORS.ASUS_CHECKBOX), document.querySelector(SELECTORS.QNAP_CHECKBOX)]
  )
}

const handleQnapChange = function(event) {
  if (event.currentTarget.checked) {
    const asusCheckbox = document.querySelector(SELECTORS.ASUS_CHECKBOX)
    const synoCheckbox = document.querySelector(SELECTORS.SYNO_CHECKBOX)
    const synoConf = document.querySelector(SELECTORS.SYNO_CONF)
    const asusConf = document.querySelector(SELECTORS.ASUS_CONF)
    
    asusCheckbox?.jeeValue(0)
    synoCheckbox?.jeeValue(0)
    synoConf?.unseen()
    asusConf?.unseen()
  }
}

const handleAsusChange = function(event) {
  updateCheckboxGroup(
    event.currentTarget,
    [document.querySelector(SELECTORS.ASUS_CONF)],
    [document.querySelector(SELECTORS.SYNO_CONF)],
    [document.querySelector(SELECTORS.QNAP_CHECKBOX), document.querySelector(SELECTORS.SYNO_CHECKBOX)]
  )
}

const handleSynoTempPath = function(event) {
  const tempPath = document.querySelector('.syno_conf_temppath')
  if (event.currentTarget.checked) {
    tempPath?.seen()
  } else {
    tempPath?.unseen()
  }
}

const handleLinuxTempCmd = function(event) {
  const tempCmd = document.querySelector('.linux_class_temp_cmd')
  if (event.currentTarget.checked) {
    tempCmd?.seen()
  } else {
    tempCmd?.unseen()
  }
}

const handlePullCustom = function(event) {
  const pullClass = document.querySelector('.pull_class')
  if (event.currentTarget.checked) {
    pullClass?.seen()
  } else {
    pullClass?.unseen()
  }
}

const handleMultiIf = function(event) {
  const multiIfConf = document.querySelector('.multi_if_conf')
  if (event.currentTarget.checked) {
    multiIfConf?.seen()
  } else {
    multiIfConf?.unseen()
  }
}

const handleLocalDistant = function(event) {
  const distantDiv = document.querySelector('.distant')
  const localDiv = document.querySelector('.local')
  const selectedValue = event.currentTarget.value
  
  if (selectedValue === 'distant') {
    distantDiv?.seen()
    localDiv?.unseen()
  } else {
    distantDiv?.unseen()
    localDiv?.seen()
  }
}

// Event delegation pour openLocation (global, attaché une seule fois)
if (!window.monitoringOpenLocationAttached) {
  window.monitoringOpenLocationAttached = true
  
  document.addEventListener('click', (event) => {
    const target = event.target.closest('.pluginAction[data-action=openLocation]')
    if (target) {
      event.preventDefault()
      window.open(target.getAttribute('data-location'), '_blank', null)
    }
  })
}

function printEqLogic(_eqLogic) {
  if (!_eqLogic) return
  
  // Cache DOM elements once
  const elements = {
    synoCheckbox: document.querySelector(SELECTORS.SYNO_CHECKBOX),
    qnapCheckbox: document.querySelector(SELECTORS.QNAP_CHECKBOX),
    asusCheckbox: document.querySelector(SELECTORS.ASUS_CHECKBOX),
    synoConf: document.querySelector(SELECTORS.SYNO_CONF),
    asusConf: document.querySelector(SELECTORS.ASUS_CONF),
    synoTempPath: document.querySelector('.eqLogicAttr[data-l2key="syno_use_temp_path"]'),
    synoTempPathDiv: document.querySelector('.syno_conf_temppath'),
    linuxTempCmd: document.querySelector('.eqLogicAttr[data-l2key="linux_use_temp_cmd"]'),
    linuxTempCmdDiv: document.querySelector('.linux_class_temp_cmd'),
    pullCustom: document.querySelector('.eqLogicAttr[data-l2key="pull_use_custom"]'),
    pullDiv: document.querySelector('.pull_class'),
    multiIf: document.querySelector('.eqLogicAttr[data-l2key="multi_if"]'),
    multiIfDiv: document.querySelector('.multi_if_conf'),
    localDistant: document.querySelector('.eqLogicAttr[data-l2key="localoudistant"]'),
    distantDiv: document.querySelector('.distant'),
    localDiv: document.querySelector('.local')
  }
  
  // Attach event listeners for equipment-specific checkboxes (re-attached on each equipment load)
  if (elements.synoCheckbox) {
    elements.synoCheckbox.removeEventListener('change', handleSynologyChange)
    elements.synoCheckbox.addEventListener('change', handleSynologyChange)
    // Initialiser l'affichage au chargement
    if (elements.synoCheckbox.checked) {
      elements.synoConf?.seen()
      elements.asusConf?.unseen()
      elements.asusCheckbox?.jeeValue(0)
      elements.qnapCheckbox?.jeeValue(0)
    } else {
      elements.synoConf?.unseen()
    }
  }
  
  if (elements.qnapCheckbox) {
    elements.qnapCheckbox.removeEventListener('change', handleQnapChange)
    elements.qnapCheckbox.addEventListener('change', handleQnapChange)
  }
  
  if (elements.asusCheckbox) {
    elements.asusCheckbox.removeEventListener('change', handleAsusChange)
    elements.asusCheckbox.addEventListener('change', handleAsusChange)
    // Initialiser l'affichage au chargement
    if (elements.asusCheckbox.checked) {
      elements.asusConf?.seen()
      elements.synoConf?.unseen()
      elements.qnapCheckbox?.jeeValue(0)
      elements.synoCheckbox?.jeeValue(0)
    } else {
      elements.asusConf?.unseen()
    }
  }
  
  if (elements.synoTempPath) {
    elements.synoTempPath.removeEventListener('change', handleSynoTempPath)
    elements.synoTempPath.addEventListener('change', handleSynoTempPath)
    // Initialiser l'affichage au chargement
    if (elements.synoTempPath.checked) {
      elements.synoTempPathDiv?.seen()
    } else {
      elements.synoTempPathDiv?.unseen()
    }
  }
  
  if (elements.linuxTempCmd) {
    elements.linuxTempCmd.removeEventListener('change', handleLinuxTempCmd)
    elements.linuxTempCmd.addEventListener('change', handleLinuxTempCmd)
    // Initialiser l'affichage au chargement
    if (elements.linuxTempCmd.checked) {
      elements.linuxTempCmdDiv?.seen()
    } else {
      elements.linuxTempCmdDiv?.unseen()
    }
  }
  
  if (elements.pullCustom) {
    elements.pullCustom.removeEventListener('change', handlePullCustom)
    elements.pullCustom.addEventListener('change', handlePullCustom)
    // Initialiser l'affichage au chargement
    if (elements.pullCustom.checked) {
      elements.pullDiv?.seen()
    } else {
      elements.pullDiv?.unseen()
    }
  }
  
  if (elements.multiIf) {
    elements.multiIf.removeEventListener('change', handleMultiIf)
    elements.multiIf.addEventListener('change', handleMultiIf)
    // Initialiser l'affichage au chargement
    if (elements.multiIf.checked) {
      elements.multiIfDiv?.seen()
    } else {
      elements.multiIfDiv?.unseen()
    }
  }
  
  if (elements.localDistant) {
    elements.localDistant.removeEventListener('change', handleLocalDistant)
    elements.localDistant.addEventListener('change', handleLocalDistant)
    // Initialiser l'affichage au chargement
    if (elements.localDistant.value === 'distant') {
      elements.distantDiv?.seen()
      elements.localDiv?.unseen()
    } else {
      elements.distantDiv?.unseen()
      elements.localDiv?.seen()
    }
  }
  
  // Build SSH host select
  const buildPromise = buildSelectHost(_eqLogic.configuration.SSHHostId)
  
  // Toggle add/edit button based on SSH host selection
  const sshHostSelect = document.querySelector('.sshmanagerHelper[data-helper="list"]')
  if (sshHostSelect) {
    // Remove existing listener to avoid duplicates
    sshHostSelect.removeEventListener('change', toggleSSHButtons)
    // Attach listener
    sshHostSelect.addEventListener('change', toggleSSHButtons)
    
    // Initialize button display - pass the value directly instead of waiting
    if (buildPromise && buildPromise.then) {
      buildPromise.then(() => {
        toggleSSHButtons(_eqLogic.configuration.SSHHostId)
      })
    } else {
      // Fallback if buildSelectHost didn't return a promise
      toggleSSHButtons(_eqLogic.configuration.SSHHostId)
    }
  }
}

/**
 * Toggle between add and edit SSH buttons based on selection
 * @param {Event|string|number} eventOrValue - Either a change event or a direct value (SSHHostId)
 */
function toggleSSHButtons(eventOrValue) {
  let selectedValue
  
  // Check if it's a direct value (string/number) or an event object
  if (typeof eventOrValue === 'string' || typeof eventOrValue === 'number') {
    selectedValue = eventOrValue
  } else if (eventOrValue?.target || eventOrValue?.currentTarget) {
    // It's an event, extract value from it
    selectedValue = eventOrValue.target?.value ?? eventOrValue.currentTarget?.value ?? eventOrValue.value
  }
  
  // If still no value, read directly from the select element as fallback
  if (!selectedValue) {
    const sshHostSelect = document.querySelector('.sshmanagerHelper[data-helper="list"]')
    selectedValue = sshHostSelect?.value
  }
  
  const addBtn = document.querySelector('.sshmanagerHelper[data-helper="add"]')
  const editBtn = document.querySelector('.sshmanagerHelper[data-helper="edit"]')
  
  if (selectedValue && selectedValue !== '') {
    // Host selected → show edit, hide add
    if (addBtn) addBtn.style.display = 'none'
    if (editBtn) editBtn.style.display = 'block'
  } else {
    // No host selected → show add, hide edit
    if (addBtn) addBtn.style.display = 'block'
    if (editBtn) editBtn.style.display = 'none'
  }
}

// Health button click handler
const healthButton = document.querySelector('#bt_healthMonitoring')
if (healthButton) {
  healthButton.addEventListener('click', function() {
    jeeDialog.dialog({
      id: 'md_healthMonitoring',
      title: '{{Santé des équipements Monitoring}}',
      width: '95%',
      height: '90%',
      top: '5vh',
      contentUrl: 'index.php?v=d&plugin=Monitoring&modal=health.monitoring',
      defaultButtons: {},
      buttons: {
        close: {
          label: '<i class="fas fa-times"></i> {{Fermer}}',
          className: 'success',
          callback: {
            click: function(event) {
              event.target.closest('div.jeeDialog')._jeeDialog.close()
            }
          }
        }
      },
      callback: function() {
        if (typeof initModalHealthMonitoring === 'function') {
          initModalHealthMonitoring()
        }
      },
      onClose: function() {
        // Clean up resources when modal is closed
        if (typeof cleanupHealthMonitoring === 'function') {
          cleanupHealthMonitoring()
        }
      }
    })
  })
}

// Expose functions globally for Jeedom to call them
window.addCmdToTable = addCmdToTable
window.printEqLogic = printEqLogic

})() // End of IIFE protection

