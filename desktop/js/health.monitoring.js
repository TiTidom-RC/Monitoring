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
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> ${error.message}</td></tr>`
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
    tbody.innerHTML = '<tr><td colspan="10" class="text-center">{{Aucun équipement trouvé}}</td></tr>'
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
        <td><span class="cmd" data-cmd_id="${eqLogic.commands?.sshStatus?.id || ''}">${formatCmdValue(eqLogic.commands?.sshStatus, 'ssh')}</span></td>
        <td><span class="cmd" data-cmd_id="${eqLogic.commands?.cronStatus?.id || ''}">${formatCmdValue(eqLogic.commands?.cronStatus, 'cron', eqLogic.type, eqLogic.commands?.cronCustom)}</span></td>
        <td><span class="cmd" data-cmd_id="${eqLogic.commands?.uptime?.id || ''}">${formatCmdValue(eqLogic.commands?.uptime)}</span></td>
        <td><span class="cmd" data-cmd_id="${eqLogic.commands?.loadAvg1?.id || ''}">${formatCmdValue(eqLogic.commands?.loadAvg1)}</span></td>
        <td><span class="cmd" data-cmd_id="${eqLogic.commands?.ip?.id || ''}">${formatCmdValue(eqLogic.commands?.ip)}</span></td>
      </tr>
    `
  }).join('')

  tbody.innerHTML = html

  // Initialize Jeedom's automatic command update system for dynamically inserted elements
  jeedom.cmd.refreshValue(tbody.querySelectorAll('.cmd[data-cmd_id]'))
}

// ========================================
// === HELPER FUNCTIONS ===
// ========================================

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

// ========================================
// === GLOBAL EXPOSURE ===
// ========================================

// Expose function globally for modal to call
window.initModalHealthMonitoring = initModalHealthMonitoring

})() // End of IIFE protection

