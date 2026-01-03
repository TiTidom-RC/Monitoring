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

(() => {
'use strict'

// ========================================
// === SELECTORS ===
// ========================================

const SELECTORS = {
  TABLE_BODY: '#table_healthMonitoring tbody'
}

// Global handler reference to ensure single event listener
let healthCmdUpdateHandler = null

// ========================================
// === CORE FUNCTIONS ===
// ========================================

/**
 * Initialize health monitoring modal
 * Called from modal PHP file
 */
const initModalHealthMonitoring = () => {
  loadHealthData()
}

/**
 * Load health data from backend
 */
const loadHealthData = () => {
  domUtils.ajax({
    type: 'POST',
    url: 'plugins/Monitoring/core/ajax/Monitoring.ajax.php',
    data: { action: 'getHealthData' },
    dataType: 'json',
    error: (error) => {
      const tbody = document.querySelector(SELECTORS.TABLE_BODY)
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="14" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> ${error.message}</td></tr>`
      }
    },
    success: (data) => {
      displayHealthData(data.result)
    }
  })
}

/**
 * Display health data in table
 * @param {Array} healthData - Array of equipment health data
 */
const displayHealthData = (healthData) => {
  const tbody = document.querySelector(SELECTORS.TABLE_BODY)
  if (!tbody) return

  if (!healthData || healthData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="14" class="text-center">{{Aucun équipement trouvé}}</td></tr>'
    return
  }

  const html = healthData.map(eqLogic => {
    const isActive = eqLogic.isEnable === 1
    const isVisible = eqLogic.isVisible === 1
    
    let typeLabel = ''
    switch (eqLogic.type) {
      case 'local':
        typeLabel = '<span class="label label-info">Local</span>'
        break
      case 'distant':
        typeLabel = '<span class="label label-warning">Distant</span>'
        break
      case 'unconfigured':
        typeLabel = '<span class="label label-danger">{{Non configuré}}</span>'
        break
      default:
        typeLabel = '<span class="text-muted">-</span>'
    }

    return `
      <tr>
        <td><a href="index.php?v=d&p=Monitoring&m=Monitoring&id=${eqLogic.id}" target="_blank">${eqLogic.name}</a></td>
        <td style="text-align:center;">${isActive ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
        <td style="text-align:center;">${isVisible ? '<i class="fas fa-eye text-success"></i>' : '<i class="fas fa-eye-slash text-muted"></i>'}</td>
        <td style="text-align:center;">${typeLabel}</td>
        <td>${eqLogic.sshHostName || '<span class="text-muted">-</span>'}</td>
        <td style="text-align:center;"><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.sshStatus?.id || ''}" data-eq-id="${eqLogic.id}" data-cmd-type="ssh" title="${formatTooltip('SSH Status', eqLogic.commands?.sshStatus)}">${formatCmdValue(eqLogic.commands?.sshStatus, 'ssh')}</span></td>
        <td style="text-align:center;"><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.cronStatus?.id || ''}" data-eq-id="${eqLogic.id}" data-cmd-type="cron" data-eq-type="${eqLogic.type}" data-cron-custom="${eqLogic.commands?.cronCustom?.value || '0'}" title="${formatTooltip('Cron Status', eqLogic.commands?.cronStatus)}">${formatCmdValue(eqLogic.commands?.cronStatus, 'cron', eqLogic.type, eqLogic.commands?.cronCustom)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.uptime?.id || ''}" data-eq-id="${eqLogic.id}" title="${formatTooltip('Uptime', eqLogic.commands?.uptime)}">${formatCmdValue(eqLogic.commands?.uptime)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.loadAvg1?.id || ''}" data-eq-id="${eqLogic.id}" title="${formatTooltip('Charge 1min', eqLogic.commands?.loadAvg1)}">${formatCmdValue(eqLogic.commands?.loadAvg1)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.loadAvg5?.id || ''}" data-eq-id="${eqLogic.id}" title="${formatTooltip('Charge 5min', eqLogic.commands?.loadAvg5)}">${formatCmdValue(eqLogic.commands?.loadAvg5)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.loadAvg15?.id || ''}" data-eq-id="${eqLogic.id}" title="${formatTooltip('Charge 15min', eqLogic.commands?.loadAvg15)}">${formatCmdValue(eqLogic.commands?.loadAvg15)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.ip?.id || ''}" data-eq-id="${eqLogic.id}" title="${formatTooltip('Adresse IP', eqLogic.commands?.ip)}">${formatCmdValue(eqLogic.commands?.ip)}</span></td>
        <td class="lastComm" data-eq-id="${eqLogic.id}" data-eq-type="${eqLogic.type}">${formatDate(eqLogic.lastRefresh, eqLogic.type)}</td>
      </tr>
    `
  }).join('')

  tbody.innerHTML = html

  // Initialize Jeedom tooltips with HTML support
  initTooltips()
  
  // Initialize DataTables for sorting and search (Jeedom native)
  jeedomUtils.initDataTables('.healthMonitoring')

  // Initialize Jeedom's automatic command update system for dynamically inserted elements
  const cmdElements = tbody.querySelectorAll('.cmd[data-cmd_id]')
  
  // Create mappings for efficient updates
  const cmdMap = new Map()  // cmd_id -> element
  const eqLastCommMap = new Map()  // eq_id -> last comm cell
  
  cmdElements.forEach(element => {
    const cmdId = element.getAttribute('data-cmd_id')
    if (cmdId && cmdId !== '') {
      cmdMap.set(cmdId, element)
    }
  })
  
  tbody.querySelectorAll('.lastComm[data-eq-id]').forEach(cell => {
    const eqId = cell.getAttribute('data-eq-id')
    if (eqId) {
      eqLastCommMap.set(eqId, cell)
    }
  })
  
  if (cmdElements.length > 0) {
    jeedom.cmd.refreshValue(cmdElements)
  }
  
  // Remove previous event listener if exists to avoid duplicates
  if (healthCmdUpdateHandler) {
    document.body.removeEventListener('cmd::update', healthCmdUpdateHandler)
  }
  
  // Single global event listener for cmd::update (performance optimization)
  healthCmdUpdateHandler = (e) => {
    if (!e.detail) return
    
    const updates = Array.isArray(e.detail) ? e.detail : [e.detail]
    
    updates.forEach(event => {
      const cmdId = String(event.cmd_id || event.id)
      const element = cmdMap.get(cmdId)
      
      if (!element) return
      
      const cmdType = element.getAttribute('data-cmd-type')
      const value = event.display_value || event.value
      
      // Format value based on command type
      if (cmdType === 'ssh') {
        element.innerHTML = formatCmdValue({ value: value }, 'ssh')
      } else if (cmdType === 'cron') {
        const eqType = element.getAttribute('data-eq-type')
        const cronCustomValue = element.getAttribute('data-cron-custom')
        const cronCustomData = cronCustomValue ? { value: cronCustomValue } : null
        element.innerHTML = formatCmdValue({ value: value }, 'cron', eqType, cronCustomData)
      } else {
        element.innerHTML = formatCmdValue({ value: value })
      }
      
      // Add visual feedback for update
      element.classList.remove('cmd-updated')
      void element.offsetWidth
      element.classList.add('cmd-updated')
      
      // Update last communication date using direct mapping
      if (event.collectDate) {
        const eqId = element.getAttribute('data-eq-id')
        if (eqId) {
          const lastCommCell = eqLastCommMap.get(eqId)
          if (lastCommCell) {
            const eqType = lastCommCell.getAttribute('data-eq-type')
            lastCommCell.innerHTML = formatDate(event.collectDate, eqType)
            
            lastCommCell.classList.remove('cmd-updated')
            void lastCommCell.offsetWidth
            lastCommCell.classList.add('cmd-updated')
          }
        }
      }
    })
  }
  
  document.body.addEventListener('cmd::update', healthCmdUpdateHandler)
}

// ========================================
// === HELPER FUNCTIONS ===
// ========================================

/**
 * Format tooltip with command dates
 * @param {string} label - Label for the command
 * @param {Object} cmdData - Command data object
 * @returns {string} Formatted tooltip text
 */
const formatTooltip = (label, cmdData) => {
  if (!cmdData) {
    return label
  }
  
  const valueDate = cmdData.valueDate || '-'
  const collectDate = cmdData.collectDate || '-'
  
  return `${label}<br><i>Date de valeur : ${valueDate}<br>Date de collecte : ${collectDate}</i>`
}

/**
 * Format command value for display
 * @param {Object} cmdData - Command data object
 * @param {string} type - Type of command (optional, e.g., 'cron')
 * @param {string} eqType - Equipment type (optional, e.g., 'local', 'distant')
 * @param {Object} cronCustomData - Cron custom status data (optional)
 * @returns {string} Formatted HTML
 */
const formatCmdValue = (cmdData, type = null, eqType = null, cronCustomData = null) => {
  if (!cmdData || cmdData.value === null || cmdData.value === undefined || cmdData.value === '') {
    return '<span class="text-muted">-</span>'
  }

  const value = cmdData.value
  const unit = cmdData.unit || ''

  // Special handling for SSH Status
  if (type === 'ssh') {
    if (value === 'OK') {
      return '<span class="label label-success"><i class="fas fa-check"></i> OK</span>'
    } else if (value === 'KO') {
      return '<span class="label label-danger"><i class="fas fa-times-circle"></i> KO</span>'
    } else if (value === 'No') {
      return '<span class="text-muted">-</span>'
    }
  }

  // Special handling for Cron Status
  if (type === 'cron') {
    const isOn = value === '1' || value === 1 || value === 'Yes'
    const isCustom = cronCustomData && (cronCustomData.value === '1' || cronCustomData.value === 1)
    
    // Custom ON = orange badge with play icon
    if (isCustom && isOn) {
      return '<span class="label label-warning"><i class="fas fa-play-circle"></i> ON <small>(Custom)</small></span>'
    }
    // Custom OFF = orange badge with pause icon
    else if (isCustom && !isOn) {
      return '<span class="label label-warning"><i class="fas fa-pause-circle"></i> OFF <small>(Custom)</small></span>'
    }
    // Default ON = green badge with play icon
    else if (isOn) {
      return '<span class="label label-success"><i class="fas fa-play-circle"></i> ON</span>'
    }
    // Default OFF = red badge with pause icon
    else {
      return '<span class="label label-danger"><i class="fas fa-pause-circle"></i> OFF</span>'
    }
  }

  // Format other special values
  if (value === 'OK' || value === 'Running') {
    return `<span class="label label-success">${value}</span>`
  } else if (value === 'KO' || value === 'Stopped') {
    return `<span class="label label-danger">${value}</span>`
  }

  return `${value}${unit ? ' ' + unit : ''}`
}

/**
 * Format date for display
 * @param {string} dateStr - Date string to format
 * @param {string} eqType - Equipment type ('local' or 'distant')
 * @returns {string} Formatted date or dash if invalid
 */
const formatDate = (dateStr, eqType = null) => {
  if (!dateStr || dateStr === '' || dateStr === '0000-00-00 00:00:00') {
    return '<span class="text-muted">-</span>'
  }
  
  try {
    const date = new Date(dateStr)
    if (isNaN(date.getTime())) {
      return '<span class="text-muted">-</span>'
    }
    
    const now = new Date()
    const diffMs = now - date
    const diffMins = Math.floor(diffMs / 60000)
    
    // Color based on age and equipment type
    const formattedDate = dateStr.slice(0, 19).replace('T', ' ')
    
    // Green threshold varies: local <= 5min, distant <= 15min
    const greenThreshold = (eqType === 'local') ? 5 : 15
    
    if (diffMins <= greenThreshold) {
      return `<span class="text-success">${formattedDate}</span>`
    } else if (diffMins <= 30) {
      return `<span class="text-warning">${formattedDate}</span>`
    } else {
      return `<span class="text-danger">${formattedDate}</span>`
    }
  } catch (e) {
    return '<span class="text-muted">-</span>'
  }
}

// ========================================
// === GLOBAL EXPOSURE ===
// ========================================

// Expose function globally for modal to call
window.initModalHealthMonitoring = initModalHealthMonitoring

})() // End of IIFE protection

