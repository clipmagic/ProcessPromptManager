(function () {
  function pageName(value) {
    return value
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9_.-]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .replace(/-{2,}/g, '-');
  }

  function initPromptKeyFromTitle() {
    var title = document.querySelector('input[name="prompt_name"]');
    var key = document.querySelector('input[name="prompt_key"]');

    if (!title || !key) return;

    var isAutomatic = !key.value || key.value === pageName(title.value);

    title.addEventListener('input', function () {
      if (!isAutomatic) return;
      key.value = pageName(title.value);
      key.dispatchEvent(new Event('change', {bubbles: true}));
    });

    key.addEventListener('input', function () {
      isAutomatic = !key.value || key.value === pageName(title.value);
      if (!key.value) key.value = pageName(title.value);
    });
  }

  function initFieldCheckAll(control) {
    if (control.dataset.promptManagerCheckAll === '1') return;

    var checkboxes = document.querySelectorAll('input[name="prompt_fields[]"], input[name="prompt_fields"]');
    if (!checkboxes.length) return;

    function setChecked(checked) {
      checkboxes.forEach(function (checkbox) {
        checkbox.checked = checked;
        checkbox.dispatchEvent(new Event('change', {bubbles: true}));
      });
    }

    function updateControl() {
      var allChecked = true;

      checkboxes.forEach(function (checkbox) {
        if (!checkbox.checked) allChecked = false;
      });

      control.checked = allChecked;
    }

    control.addEventListener('change', function () {
      setChecked(control.checked);
    });

    checkboxes.forEach(function (checkbox) {
      checkbox.addEventListener('change', updateControl);
    });

    updateControl();
    control.dataset.promptManagerCheckAll = '1';
  }

  function initDeleteChecked() {
    var deleteButtons = document.querySelectorAll('.btn-delete-checked');
    var addButtons = document.querySelectorAll('.btn-add-prompt-manager');
    var checkboxes = document.querySelectorAll('.delete-checkbox');
    var checkAll = document.querySelector('#ProcessPromptManagerDeleteAll');

    if (!deleteButtons.length || !checkboxes.length) return;

    function setDisplay(items, display) {
      items.forEach(function (item) {
        item.style.display = display;
      });
    }

    function updateDeleteState() {
      var checkedCount = 0;

      checkboxes.forEach(function (checkbox) {
        if (checkbox.checked) checkedCount++;
      });

      setDisplay(deleteButtons, checkedCount > 0 ? '' : 'none');
      setDisplay(addButtons, checkedCount > 0 ? 'none' : '');
      if (checkAll) checkAll.checked = checkedCount === checkboxes.length;
    }

    if (checkAll) {
      checkAll.addEventListener('change', function () {
        checkboxes.forEach(function (checkbox) {
          checkbox.checked = checkAll.checked;
          checkbox.dispatchEvent(new Event('input', {bubbles: true}));
        });

        updateDeleteState();
      });
    }

    checkboxes.forEach(function (checkbox) {
      checkbox.addEventListener('input', updateDeleteState);
      checkbox.addEventListener('change', updateDeleteState);
    });

    updateDeleteState();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initPromptKeyFromTitle();

    var control = document.querySelector('#ProcessPromptManagerCheckAll');
    if (control) initFieldCheckAll(control);

    initDeleteChecked();
  });
})();
