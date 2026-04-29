(function () {
  function getRoot() {
    return document.querySelector('[data-tq-booking-root]');
  }

  function parsePayload(root) {
    var raw = root.getAttribute('data-tq-booking-data');
    if (!raw) {
      return null;
    }

    try {
      return JSON.parse(raw);
    } catch (error) {
      return null;
    }
  }

  function formatDateLabel(value) {
    if (!value) {
      return '';
    }

    var date = new Date(value + 'T00:00:00');
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  }

  function toDate(value) {
    return new Date(value + 'T00:00:00');
  }

  function formatDateKey(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
  }

  function createOption(value, label) {
    var option = document.createElement('option');
    option.value = String(value);
    option.textContent = label;
    return option;
  }

  function buildMonthGrid(monthDate, instances, selectedClassId) {
    var monthStart = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1);
    var monthEnd = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0);
    var gridStart = new Date(monthStart);
    var dayOfWeek = gridStart.getDay();
    var offset = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
    gridStart.setDate(gridStart.getDate() - offset);

    var gridEnd = new Date(monthEnd);
    var endDay = gridEnd.getDay();
    var endOffset = endDay === 0 ? 0 : 7 - endDay;
    gridEnd.setDate(gridEnd.getDate() + endOffset);

    var dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    var wrapper = document.createElement('section');
    wrapper.className = 'rounded-2xl border border-slate-600/70 bg-white/5 p-4';

    var heading = document.createElement('div');
    heading.className = 'mb-3';
    heading.innerHTML = '<h3 class="text-lg font-bold text-slate-100">' + monthDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) + '</h3>';
    wrapper.appendChild(heading);

    var weekdayRow = document.createElement('div');
    weekdayRow.className = 'mb-2 hidden grid-cols-7 gap-2 md:grid';
    dayNames.forEach(function (label) {
      var cell = document.createElement('div');
      cell.className = 'text-xs font-semibold uppercase tracking-wide text-slate-400';
      cell.textContent = label;
      weekdayRow.appendChild(cell);
    });
    wrapper.appendChild(weekdayRow);

    var grid = document.createElement('div');
    grid.className = 'grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-7';

    for (var current = new Date(gridStart); current <= gridEnd; current.setDate(current.getDate() + 1)) {
      var cell = document.createElement('div');
      cell.className = 'rounded-xl border border-slate-600/60 bg-slate-900/40 p-2';

      if (current.getMonth() !== monthDate.getMonth()) {
        cell.classList.add('opacity-40');
      }

      var dateKey = formatDateKey(current);
      var dayInstances = instances.filter(function (instance) {
        var instanceClassId = String(instance.class_id || '');
        var isMatch = selectedClassId === '0' || selectedClassId === instanceClassId;
        return isMatch && instance.start_date === dateKey;
      });

      var dayNumber = document.createElement('div');
      dayNumber.className = 'mb-1 text-sm font-bold text-slate-200';
      dayNumber.textContent = current.getDate();
      cell.appendChild(dayNumber);

      if (dayInstances.length) {
        cell.classList.add('has-instances');
        dayInstances.forEach(function (instance) {
          var card = document.createElement('article');
          card.className = 'mt-2 grid gap-2 rounded-xl border border-slate-500/60 bg-slate-950/70 p-3';
          card.dataset.classId = String(instance.class_id || '');
          card.dataset.productId = String(instance.product_id || '');

          var header = document.createElement('div');
          header.className = 'grid gap-1';
          header.innerHTML = '<strong class="text-sm font-bold text-white">' + (instance.class_name || 'Class') + '</strong>' +
            '<span class="text-xs text-slate-300">' + formatDateLabel(instance.start_date) + ' to ' + formatDateLabel(instance.end_date) + '</span>';
          card.appendChild(header);

          var meta = document.createElement('div');
          meta.className = 'text-xs text-slate-300';
          meta.textContent = instance.available_seats + ' seats available';
          card.appendChild(meta);

          if (instance.description) {
            var description = document.createElement('p');
            description.className = 'm-0 text-xs leading-relaxed text-slate-400';
            description.textContent = instance.description;
            card.appendChild(description);
          }

          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'inline-flex min-h-10 items-center justify-center rounded-full bg-amber-400 px-3 text-sm font-bold text-slate-900 hover:bg-amber-300 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-300';
          button.textContent = instance.is_full ? 'Full' : (window.TQBookingCalendar && window.TQBookingCalendar.labels ? window.TQBookingCalendar.labels.bookNow : 'Book now');
          button.disabled = !!instance.is_full || !instance.product_id;
          button.addEventListener('click', function () {
            if (!instance.product_id) {
              return;
            }

            var cartUrl = (window.TQBookingCalendar && window.TQBookingCalendar.cartUrl) ? window.TQBookingCalendar.cartUrl : '/cart/';
            var targetUrl = new URL(cartUrl, window.location.origin);
            targetUrl.searchParams.set('add-to-cart', String(instance.product_id));
            targetUrl.searchParams.set('quantity', '1');
            window.location.href = targetUrl.toString();
          });
          card.appendChild(button);

          cell.appendChild(card);
        });
      }

      grid.appendChild(cell);
    }

    wrapper.appendChild(grid);
    return wrapper;
  }

  function renderSchedule(root, payload, selectedClassId) {
    var schedule = root.querySelector('[data-tq-booking-schedule]');
    if (!schedule) {
      return;
    }

    schedule.innerHTML = '';

    var instances = Array.isArray(payload.instances) ? payload.instances : [];
    if (!instances.length) {
      var emptyState = document.createElement('div');
      emptyState.className = 'rounded-xl border border-slate-600/70 bg-white/5 p-4 text-slate-300';
      emptyState.textContent = (window.TQBookingCalendar && window.TQBookingCalendar.labels ? window.TQBookingCalendar.labels.empty : 'No classes are scheduled in this window yet.');
      schedule.appendChild(emptyState);
      return;
    }

    var firstDate = new Date();
    if (Number.isNaN(firstDate.getTime())) {
      firstDate = new Date();
    }

    for (var monthIndex = 0; monthIndex < 3; monthIndex++) {
      var monthDate = new Date(firstDate.getFullYear(), firstDate.getMonth() + monthIndex, 1);
      schedule.appendChild(buildMonthGrid(monthDate, instances, selectedClassId));
    }
  }

  function init() {
    var root = getRoot();
    if (!root) {
      return;
    }

    var payload = parsePayload(root);
    if (!payload) {
      return;
    }

    var filter = root.querySelector('[data-tq-booking-filter]');
    if (!filter) {
      return;
    }

    filter.innerHTML = '';
    filter.appendChild(createOption('0', (window.TQBookingCalendar && window.TQBookingCalendar.labels ? window.TQBookingCalendar.labels.allClasses : 'All classes')));

    (Array.isArray(payload.classes) ? payload.classes : []).forEach(function (classItem) {
      var label = classItem.name || 'Class';
      if (classItem.course_code) {
        label += ' (' + classItem.course_code + ')';
      }
      filter.appendChild(createOption(String(classItem.id || 0), label));
    });

    var selectedClassId = '0';
    filter.addEventListener('change', function () {
      selectedClassId = String(filter.value || '0');
      renderSchedule(root, payload, selectedClassId);
    });

    renderSchedule(root, payload, selectedClassId);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();