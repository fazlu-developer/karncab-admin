(function () {
  function districtsFor(stateId) {
    const geo = window.KarnaCabGeo || { districts: {} };
    return geo.districts[String(stateId)] || [];
  }

  function fillDistrict(select, stateId, selected) {
    const placeholder = select.getAttribute('data-geo-placeholder') || 'Select district';
    const allowEmpty = select.getAttribute('data-geo-empty') !== '0';
    const rows = districtsFor(stateId);
    select.innerHTML = '';
    if (allowEmpty || !stateId) {
      const empty = document.createElement('option');
      empty.value = '';
      empty.textContent = placeholder;
      select.appendChild(empty);
    }
    rows.forEach(function (row) {
      const option = document.createElement('option');
      option.value = String(row.id);
      option.textContent = row.name;
      if (String(selected || '') === String(row.id)) {
        option.selected = true;
      }
      select.appendChild(option);
    });
  }

  function bindPair(district) {
    if (district.dataset.geoBound === '1') {
      return;
    }
    const stateName = district.getAttribute('data-geo-state') || 'state_id';
    const root = district.form || district.closest('form') || document;
    const state = root.querySelector('[name="' + stateName + '"]');
    if (!state) {
      return;
    }
    district.dataset.geoBound = '1';
    const selected = district.getAttribute('data-geo-selected') || district.value || '';
    const sync = function (keepSelected) {
      fillDistrict(district, state.value, keepSelected ? selected : '');
    };
    state.addEventListener('change', function () {
      district.setAttribute('data-geo-selected', '');
      fillDistrict(district, state.value, '');
    });
    sync(true);
  }

  function bindAll() {
    document.querySelectorAll('select[data-geo="district"]').forEach(bindPair);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindAll);
  } else {
    bindAll();
  }
})();
