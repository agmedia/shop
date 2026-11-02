(function () {
  'use strict';

  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function normalizePostal(value) {
    return String(value || '').replace(/\D+/g, '');
  }

  function normalizeLabel(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-zA-Z0-9]+/g, ' ')
      .trim()
      .toLowerCase();
  }

  function setSelectValue(select, rawValue) {
    if (!select) {
      return false;
    }

    var target = String(rawValue || '').trim();
    if (!target) {
      return false;
    }

    select.value = target;
    if (select.value === target) {
      return true;
    }

    var normalizedTarget = normalizeLabel(target);
    var options = Array.prototype.slice.call(select.options || []);
    var matched = options.find(function (option) {
      return normalizeLabel(option.value) === normalizedTarget || normalizeLabel(option.textContent) === normalizedTarget;
    });

    if (matched) {
      select.value = matched.value;
      return true;
    }

    return false;
  }

  function hydrateScope(scope, places) {
    var cityInput = scope.querySelector('[data-address-city]');
    var postalInput = scope.querySelector('[data-address-postal]');
    var countySelect = scope.querySelector('[data-address-county]');
    var countrySelect = scope.querySelector('[data-address-country]');

    if (!cityInput || !postalInput) {
      return;
    }

    var lock = false;
    var autoFilledFields = [];

    function isCroatianAddress() {
      return countrySelect && String(countrySelect.value || '').trim().toUpperCase() === 'HR';
    }

    function applyPlace(place) {
      if (!isCroatianAddress() || !place || lock) {
        return;
      }

      lock = true;

      if (cityInput) {
        cityInput.value = place.city || cityInput.value;
      }

      if (postalInput) {
        postalInput.value = place.postal_code || postalInput.value;
      }

      autoFilledFields = [
        { node: cityInput, value: cityInput.value },
        { node: postalInput, value: postalInput.value }
      ];

      if (countySelect && place.county) {
        if (setSelectValue(countySelect, place.county)) {
          autoFilledFields.push({ node: countySelect, value: countySelect.value });
        }
        countySelect.dispatchEvent(new Event('change', { bubbles: true }));
      }

      countrySelect.dispatchEvent(new Event('change', { bubbles: true }));
      lock = false;
    }

    function findByCity() {
      var cityKey = normalize(cityInput.value);
      if (!cityKey) {
        return null;
      }

      return places.find(function (row) {
        return normalize(row.city) === cityKey;
      }) || null;
    }

    function findByPostal() {
      var postalKey = normalizePostal(postalInput.value);
      if (!postalKey) {
        return null;
      }

      return places.find(function (row) {
        return normalizePostal(row.postal_code) === postalKey;
      }) || null;
    }

    function clearStaleAutofill() {
      if (isCroatianAddress()) {
        return;
      }

      var staleFields = autoFilledFields;
      autoFilledFields = [];
      staleFields.forEach(function (field) {
        // Remove only untouched suggestions; preserve addresses entered by the customer.
        if (field.node.value === field.value) {
          field.node.value = '';
          field.node.dispatchEvent(new Event('input', { bubbles: true }));
          field.node.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    }

    cityInput.addEventListener('change', function () {
      applyPlace(findByCity());
    });

    cityInput.addEventListener('blur', function () {
      applyPlace(findByCity());
    });

    postalInput.addEventListener('change', function () {
      applyPlace(findByPostal());
    });

    postalInput.addEventListener('input', function () {
      applyPlace(findByPostal());
    });

    postalInput.addEventListener('blur', function () {
      applyPlace(findByPostal());
    });

    if (countrySelect) {
      countrySelect.addEventListener('change', function () {
        clearStaleAutofill();
      });
    }
  }

  function init() {
    var roots = document.querySelectorAll('[data-address-autofill]');
    if (!roots.length) {
      return;
    }

    roots.forEach(function (root) {
      var sourceUrl = root.getAttribute('data-address-source');
      if (!sourceUrl) {
        return;
      }

      fetch(sourceUrl, { credentials: 'same-origin' })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (payload) {
          if (!payload || !Array.isArray(payload.places)) {
            return;
          }

          var scopes = root.querySelectorAll('[data-address-scope]');
          scopes.forEach(function (scope) {
            hydrateScope(scope, payload.places);
          });
        })
        .catch(function () {
          // Fail silently: form stays fully manual.
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
