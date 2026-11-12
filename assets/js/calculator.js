(function () {
  'use strict';

  var config = window.SRC_CALCULATOR || {};

  function init(root) {
    var form = root.querySelector('.src-form');
    var address = form.elements.address;
    var county = form.elements.county;
    var valueInput = form.elements.current_value_display;
    var submitButton = root.querySelector('[data-src-submit]');
    var status = root.querySelector('[data-src-status]');
    var progress = root.querySelector('[data-src-progress]');
    var currentStep = 1;

    function setError(field, message) {
      var error = root.querySelector('[data-src-error="' + field.name.replace('_display', '') + '"]');
      field.setAttribute('aria-invalid', message ? 'true' : 'false');
      if (error) error.textContent = message || '';
    }

    function showStep(step) {
      root.querySelectorAll('[data-src-step]').forEach(function (section) {
        section.hidden = Number(section.dataset.srcStep) !== step;
      });
      currentStep = step;
      progress.style.width = (step / 3 * 100) + '%';
      var heading = root.querySelector('[data-src-step="' + step + '"] .src-title');
      if (heading) {
        heading.setAttribute('tabindex', '-1');
        heading.focus();
      }
    }

    function validateFirstStep() {
      var valid = true;
      setError(address, '');
      setError(county, '');
      if (!address.value.trim()) {
        setError(address, 'Please enter the property address.');
        valid = false;
      }
      if (!county.value) {
        setError(county, 'Please select a county.');
        valid = false;
      }
      if (!valid) root.querySelector('[aria-invalid="true"]').focus();
      return valid;
    }

    function numericValue() {
      var cleaned = valueInput.value.replace(/[^0-9.]/g, '');
      if ((cleaned.match(/\./g) || []).length > 1) return NaN;
      return Number(cleaned);
    }

    function validateValue() {
      var value = numericValue();
      setError(valueInput, '');
      if (!Number.isFinite(value) || value <= 0) {
        setError(valueInput, 'Please enter an appraised value greater than zero.');
        valueInput.focus();
        return false;
      }
      if (value > Number(config.maxValue || 1000000000)) {
        setError(valueInput, 'Please enter an appraised value no greater than $1,000,000,000.');
        valueInput.focus();
        return false;
      }
      return true;
    }

    root.querySelector('[data-src-next]').addEventListener('click', function () {
      if (validateFirstStep()) showStep(2);
    });

    root.querySelector('[data-src-back]').addEventListener('click', function () {
      showStep(1);
    });

    [address, county, valueInput].forEach(function (field) {
      field.addEventListener('input', function () { setError(field, ''); });
      field.addEventListener('change', function () { setError(field, ''); });
    });

    valueInput.addEventListener('input', function () {
      var cleaned = valueInput.value.replace(/[^0-9.]/g, '');
      var parts = cleaned.split('.');
      parts[0] = parts[0].replace(/^0+(?=\d)/, '').slice(0, 10);
      if (parts.length > 1) parts[1] = parts.slice(1).join('').slice(0, 2);
      valueInput.value = parts[0] ? Number(parts[0]).toLocaleString('en-US') + (parts.length > 1 ? '.' + parts[1] : '') : '';
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (currentStep !== 2 || !validateValue() || !validateFirstStep()) return;

      var payload = new FormData();
      payload.append('action', config.action);
      payload.append('nonce', config.nonce);
      payload.append('address', address.value.trim());
      payload.append('county', county.value);
      payload.append('current_value', String(numericValue()));

      submitButton.disabled = true;
      submitButton.classList.add('is-loading');
      submitButton.setAttribute('aria-busy', 'true');
      status.classList.remove('src-status--error');
      status.textContent = 'Calculating your estimate…';

      fetch(config.ajaxUrl, { method: 'POST', body: payload, credentials: 'same-origin' })
        .then(function (response) {
          return response.json().catch(function () { throw new Error('We could not complete the calculation. Please try again.'); });
        })
        .then(function (response) {
          if (!response.success || !response.data) {
            throw new Error(response.data && response.data.message ? response.data.message : 'We could not complete the calculation. Please try again.');
          }
          ['current_value', 'value_reduction', 'new_value', 'annual_savings'].forEach(function (key) {
            root.querySelector('[data-src-result="' + key + '"]').textContent = response.data[key];
          });
          root.querySelector('[data-src-basis]').textContent = 'Based on Southland Tax’s configured residential estimate for ' + response.data.county_name + '.';
          status.textContent = 'Your estimate is ready.';
          showStep(3);
        })
        .catch(function (error) {
          status.textContent = error.message;
          status.classList.add('src-status--error');
        })
        .finally(function () {
          submitButton.disabled = false;
          submitButton.classList.remove('is-loading');
          submitButton.removeAttribute('aria-busy');
        });
    });

    progress.style.width = '33.333%';
  }

  document.querySelectorAll('[data-src-calculator]').forEach(init);
})();
