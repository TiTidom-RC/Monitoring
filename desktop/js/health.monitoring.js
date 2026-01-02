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
        tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> ${error.message}</td></tr>`
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
    tbody.innerHTML = '<tr><td colspan="12" class="text-center">{{Aucun équipement trouvé}}</td></tr>'
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
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.sshStatus?.id || ''}" title="${formatTooltip('SSH Status', eqLogic.commands?.sshStatus)}">${formatCmdValue(eqLogic.commands?.sshStatus, 'ssh')}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.cronStatus?.id || ''}" title="${formatTooltip('Cron Status', eqLogic.commands?.cronStatus)}">${formatCmdValue(eqLogic.commands?.cronStatus, 'cron', eqLogic.type, eqLogic.commands?.cronCustom)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.uptime?.id || ''}" title="${formatTooltip('Uptime', eqLogic.commands?.uptime)}">${formatCmdValue(eqLogic.commands?.uptime)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.loadAvg1?.id || ''}" title="${formatTooltip('Charge 1min', eqLogic.commands?.loadAvg1)}">${formatCmdValue(eqLogic.commands?.loadAvg1)}</span></td>
        <td><span class="cmd tooltips" data-cmd_id="${eqLogic.commands?.ip?.id || ''}" title="${formatTooltip('Adresse IP', eqLogic.commands?.ip)}">${formatCmdValue(eqLogic.commands?.ip)}</span></td>
        <td>${formatDate(eqLogic.lastRefresh)}</td>
        <td>${getLastValueDate(eqLogic.commands)}</td>
      </tr>
    `
  }).join('')

  tbody.innerHTML = html

  // Initialize Jeedom's automatic command update system for dynamically inserted elements
  const cmdElements = tbody.querySelectorAll('.cmd[data-cmd_id]')
  if (cmdElements.length > 0) {
    jeedom.cmd.refreshValue(cmdElements)
    
    // Register update listeners for real-time updates via WebSocket
    cmdElements.forEach(element => {
      const cmdId = element.getAttribute('data-cmd_id')
      if (cmdId && cmdId !== '') {
        jeedom.cmd.update[cmdId] = function(event) {
          element.textContent = event.display_value || event.value || '-'
        }
      }
    })
  }
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
  
  return `${label}\nDate de valeur : ${valueDate}\nDate de collecte : ${collectDate}`
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
 * @returns {string} Formatted date or dash if invalid
 */
const formatDate = (dateStr) => {
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
    
    // Less than 1 minute
    if (diffMins < 1) {
      return '<span class="text-success">{{À l\'instant}}</span>'
    }
    // Less than 60 minutes
    else if (diffMins < 60) {
      return `<span class="text-success">{{Il y a}} ${diffMins} {{min}}</span>`
    }
    // Less than 24 hours
    else if (diffMins < 1440) {
      const hours = Math.floor(diffMins / 60)
      return `<span class="text-warning">{{Il y a}} ${hours} {{h}}</span>`
    }
    // More than 24 hours
    else {
      const days = Math.floor(diffMins / 1440)
      return `<span class="text-danger">{{Il y a}} ${days} {{j}}</span>`
    }
  } catch (e) {
    return '<span class="text-muted">-</span>'
  }
}

/**
 * Get the most recent valueDate from all commands
 * @param {Object} commands - Commands object
 * @returns {string} Formatted most recent date
 */
const getLastValueDate = (commands) => {
  if (!commands) {
    return '<span class="text-muted">-</span>'
  }
  
  let mostRecentDate = null
  
  // Check all commands for the most recent valueDate
  Object.values(commands).forEach(cmd => {
    if (cmd && cmd.valueDate && cmd.valueDate !== '' && cmd.valueDate !== '0000-00-00 00:00:00') {
      const cmdDate = new Date(cmd.valueDate)
      if (!isNaN(cmdDate.getTime())) {
        if (!mostRecentDate || cmdDate > mostRecentDate) {
          mostRecentDate = cmdDate
        }
      }
    }
  })
  
  if (!mostRecentDate) {
    return '<span class="text-muted">-</span>'
  }
  
  return formatDate(mostRecentDate.toISOString().slice(0, 19).replace('T', ' '))
}

// ========================================
// === GLOBAL EXPOSURE ===
// ========================================

// Expose function globally for modal to call
window.initModalHealthMonitoring = initModalHealthMonitoring

})() // End of IIFE protection

