(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var table = document.querySelector('#src-county-table tbody');
    var addButton = document.getElementById('src-add-county');
    var template = document.getElementById('src-county-row-template');
    var emptyState = document.querySelector('[data-src-empty]');
    if (!table || !addButton || !template) return;

    function updateEmptyState() {
      if (emptyState) emptyState.hidden = table.querySelectorAll('.src-county-row').length > 0;
    }

    function slugify(value) {
      return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    addButton.addEventListener('click', function () {
      var index = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
      var html = template.innerHTML.replaceAll('__INDEX__', index);
      table.insertAdjacentHTML('beforeend', html);
      var row = table.lastElementChild;
      row.dataset.srcNew = 'true';
      row.querySelector('.src-county-name').focus();
      updateEmptyState();
    });

    table.addEventListener('click', function (event) {
      var button = event.target.closest('.src-remove-county');
      if (!button) return;
      button.closest('tr').remove();
      updateEmptyState();
    });

    table.addEventListener('input', function (event) {
      var row = event.target.closest('.src-county-row');
      if (!row || row.dataset.srcNew !== 'true') return;
      var slug = row.querySelector('.src-county-slug');
      if (event.target.matches('.src-county-slug')) {
        slug.dataset.srcEdited = slug.value ? 'true' : 'false';
      } else if (event.target.matches('.src-county-name') && slug.dataset.srcEdited !== 'true') {
        slug.value = slugify(event.target.value);
      }
    });

    updateEmptyState();
  });
})();
