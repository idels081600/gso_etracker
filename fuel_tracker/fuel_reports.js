(function () {
    'use strict';

    var periodType = document.getElementById('periodType');
    var fields = document.querySelectorAll('[data-period-field]');

    function syncPeriodFields() {
        var selectedType = periodType ? periodType.value : 'monthly';
        fields.forEach(function (field) {
            var active = field.getAttribute('data-period-field') === selectedType;
            field.classList.toggle('d-none', !active);
            var input = field.querySelector('input');
            if (input) {
                input.disabled = !active;
                input.required = active;
            }
        });
    }

    if (periodType) {
        periodType.addEventListener('change', syncPeriodFields);
        syncPeriodFields();
    }
})();
