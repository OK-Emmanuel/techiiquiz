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

  function padTwo(value) {
    return String(value).padStart(2, '0');
  }

  function monthKeyFromDate(date) {
    return date.getFullYear() + '-' + padTwo(date.getMonth() + 1);
  }

  function monthDateFromKey(key) {
    var parts = String(key || '').split('-');
    var year = Number(parts[0]);
    var month = Number(parts[1]);

    if (!year || !month) {
      return new Date();
    }

    return new Date(year, month - 1, 1);
  }

  function getFilteredInstances(instances, selectedClassId) {
    return instances.filter(function (instance) {
      var instanceClassId = String(instance.class_id || '');
      return selectedClassId === '0' || selectedClassId === instanceClassId;
    });
  }

  function buildMonthKeys(instances) {
    var keys = {};
    var todayKey = monthKeyFromDate(new Date());
    keys[todayKey] = true;

    instances.forEach(function (instance) {
      var date = toDate(instance.start_date || '');
      if (Number.isNaN(date.getTime())) {
        return;
      }
      keys[monthKeyFromDate(date)] = true;
    });

    return Object.keys(keys).sort();
  }

  function updateMonthPager(root, state, monthDate) {
    var prevButton = root.querySelector('[data-tq-booking-prev]');
    var nextButton = root.querySelector('[data-tq-booking-next]');
    var label = root.querySelector('[data-tq-booking-month-label]');

    if (label) {
      label.textContent = monthDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    }

    if (prevButton) {
      prevButton.disabled = state.monthIndex <= 0;
      prevButton.textContent = (window.TQBookingCalendar && window.TQBookingCalendar.labels && window.TQBookingCalendar.labels.previousMonth)
        ? window.TQBookingCalendar.labels.previousMonth
        : 'Previous month';
    }

    if (nextButton) {
      nextButton.disabled = state.monthIndex >= state.monthKeys.length - 1;
      nextButton.textContent = (window.TQBookingCalendar && window.TQBookingCalendar.labels && window.TQBookingCalendar.labels.nextMonth)
        ? window.TQBookingCalendar.labels.nextMonth
        : 'Next month';
    }
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
    wrapper.className = 'rounded-2xl border border-slate-200 bg-white p-4';

    var heading = document.createElement('div');
    heading.className = 'mb-3';
    heading.innerHTML = '<h3 class="text-lg font-bold text-slate-900">' + monthDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) + '</h3>';
    wrapper.appendChild(heading);

    var weekdayRow = document.createElement('div');
    weekdayRow.className = 'mb-2 hidden grid-cols-7 gap-2 md:grid';
    dayNames.forEach(function (label) {
      var cell = document.createElement('div');
      cell.className = 'text-xs font-semibold uppercase tracking-wide text-slate-500';
      cell.textContent = label;
      weekdayRow.appendChild(cell);
    });
    wrapper.appendChild(weekdayRow);

    var grid = document.createElement('div');
    grid.className = 'grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-7';

    for (var current = new Date(gridStart); current <= gridEnd; current.setDate(current.getDate() + 1)) {
      var cell = document.createElement('div');
      cell.className = 'rounded-xl border border-slate-200 bg-slate-50 p-2';

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
      dayNumber.className = 'mb-1 text-sm font-bold text-slate-700';
      dayNumber.textContent = current.getDate();
      cell.appendChild(dayNumber);

      if (dayInstances.length) {
        cell.classList.add('has-instances');
        dayInstances.forEach(function (instance) {
          var card = document.createElement('article');
          card.className = 'mt-2 grid gap-2 rounded-xl border border-brand-blue/20 bg-white p-3';
          card.dataset.classId = String(instance.class_id || '');
          card.dataset.productId = String(instance.product_id || '');

          var header = document.createElement('div');
          header.className = 'grid gap-1';
          header.innerHTML = '<strong class="text-sm font-bold text-slate-900">' + (instance.class_name || 'Class') + '</strong>' +
            '<span class="text-xs text-slate-600">' + formatDateLabel(instance.start_date) + ' to ' + formatDateLabel(instance.end_date) + '</span>';
          card.appendChild(header);

          var meta = document.createElement('div');
          meta.className = 'text-xs text-slate-600';
          meta.textContent = instance.available_seats + ' seats available';
          card.appendChild(meta);

          if (instance.description) {
            var description = document.createElement('p');
            description.className = 'm-0 text-xs leading-relaxed text-slate-500';
            description.textContent = instance.description;
            card.appendChild(description);
          }

          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'inline-flex min-h-10 items-center justify-center rounded-full bg-red-500 px-3 text-sm font-bold text-white hover:bg-brand-red disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600';
          button.textContent = instance.is_full ? 'Full' : (window.TQBookingCalendar && window.TQBookingCalendar.labels ? window.TQBookingCalendar.labels.bookNow : 'Book now');
          button.disabled = !!instance.is_full || !instance.product_id;
          button.addEventListener('click', function () {
            if (!instance.product_id) {
              return;
            }

            showClassDetailsModal(instance);
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

  function renderSchedule(root, payload, state) {
    var schedule = root.querySelector('[data-tq-booking-schedule]');
    if (!schedule) {
      return;
    }

    schedule.innerHTML = '';

    var instances = Array.isArray(payload.instances) ? payload.instances : [];
    var filteredInstances = getFilteredInstances(instances, state.selectedClassId);

    if (!filteredInstances.length) {
      var emptyState = document.createElement('div');
      emptyState.className = 'rounded-xl border border-slate-200 bg-white p-4 text-slate-600';
      emptyState.textContent = (window.TQBookingCalendar && window.TQBookingCalendar.labels ? window.TQBookingCalendar.labels.empty : 'No classes are scheduled in this window yet.');
      schedule.appendChild(emptyState);

      updateMonthPager(root, { monthIndex: 0, monthKeys: [''] }, new Date());
      return;
    }

    state.monthKeys = buildMonthKeys(filteredInstances);
    if (state.monthIndex < 0) {
      state.monthIndex = 0;
    }
    if (state.monthIndex > state.monthKeys.length - 1) {
      state.monthIndex = state.monthKeys.length - 1;
    }

    var activeMonthDate = monthDateFromKey(state.monthKeys[state.monthIndex]);
    schedule.appendChild(buildMonthGrid(activeMonthDate, filteredInstances, state.selectedClassId));
    updateMonthPager(root, state, activeMonthDate);
  }

  function bindPager(root, payload, state) {
    var prevButton = root.querySelector('[data-tq-booking-prev]');
    var nextButton = root.querySelector('[data-tq-booking-next]');
    var schedule = root.querySelector('[data-tq-booking-schedule]');
    var touchStartX = null;

    if (prevButton) {
      prevButton.addEventListener('click', function () {
        if (state.monthIndex <= 0) {
          return;
        }
        state.monthIndex -= 1;
        renderSchedule(root, payload, state);
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', function () {
        if (state.monthIndex >= state.monthKeys.length - 1) {
          return;
        }
        state.monthIndex += 1;
        renderSchedule(root, payload, state);
      });
    }

    if (schedule) {
      schedule.addEventListener('touchstart', function (event) {
        if (!event.touches || !event.touches.length) {
          return;
        }
        touchStartX = event.touches[0].clientX;
      }, { passive: true });

      schedule.addEventListener('touchend', function (event) {
        if (touchStartX === null || !event.changedTouches || !event.changedTouches.length) {
          touchStartX = null;
          return;
        }

        var touchEndX = event.changedTouches[0].clientX;
        var distance = touchEndX - touchStartX;
        touchStartX = null;

        if (Math.abs(distance) < 40) {
          return;
        }

        if (distance < 0 && state.monthIndex < state.monthKeys.length - 1) {
          state.monthIndex += 1;
          renderSchedule(root, payload, state);
        } else if (distance > 0 && state.monthIndex > 0) {
          state.monthIndex -= 1;
          renderSchedule(root, payload, state);
        }
      }, { passive: true });
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

    var state = {
      selectedClassId: '0',
      monthIndex: 0,
      monthKeys: []
    };

    bindPager(root, payload, state);

    filter.addEventListener('change', function () {
      state.selectedClassId = String(filter.value || '0');
      state.monthIndex = 0;
      renderSchedule(root, payload, state);
    });

    renderSchedule(root, payload, state);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  function showClassDetailsModal(instance) {
    // Remove existing modal if present
    var existingModal = document.getElementById('tq-class-details-modal');
    if (existingModal) {
      existingModal.remove();
    }

    // Create modal backdrop
    var modal = document.createElement('div');
    modal.id = 'tq-class-details-modal';
    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto';

    // Create modal content
    var content = document.createElement('div');
    content.className = 'bg-white rounded-2xl max-w-2xl w-full shadow-2xl my-8';
    modal.appendChild(content);

    // Header with gradient
    var header = document.createElement('div');
    header.className = 'bg-gradient-to-r from-brand-blue to-blue-600 text-white p-6 flex items-center justify-between';
    
    var headerContent = document.createElement('div');
    var title = document.createElement('h2');
    title.className = 'text-2xl text-red-500 font-bold';
    title.textContent = instance.class_name || 'Class';
    headerContent.appendChild(title);
    
    var courseCode = document.createElement('p');
    courseCode.className = 'text-blue-400 text-sm mt-1';
    courseCode.textContent = 'Course Code: ' + (instance.course_code || 'N/A');
    headerContent.appendChild(courseCode);
    header.appendChild(headerContent);

    var closeBtn = document.createElement('button');
    closeBtn.className = 'text-red-800 hover:text-blue-100 text-3xl leading-none ml-auto flex-shrink-0';
    closeBtn.textContent = '×';
    closeBtn.onclick = function() { modal.remove(); };
    header.appendChild(closeBtn);
    
    content.appendChild(header);

    // Body content
    var body = document.createElement('div');
    body.className = 'p-6 space-y-6 overflow-y-auto max-h-[calc(90vh-200px)]';

    // Description
    if (instance.description) {
      var descSection = document.createElement('section');
      var descHeading = document.createElement('h3');
      descHeading.className = 'text-lg font-bold text-slate-900 mb-2 pb-2 border-b-2 border-brand-blue';
      descHeading.textContent = 'About This Class';
      var descText = document.createElement('p');
      descText.className = 'text-slate-700 leading-relaxed';
      descText.textContent = instance.description;
      descSection.appendChild(descHeading);
      descSection.appendChild(descText);
      body.appendChild(descSection);
    }

    // Key details grid
    var detailsGrid = document.createElement('div');
    detailsGrid.className = 'grid grid-cols-2 gap-4';

    var startDiv = document.createElement('div');
    startDiv.className = 'bg-blue-50 border-2 border-brand-blue rounded-lg p-4';
    startDiv.innerHTML = '<p class="text-xs uppercase tracking-widest text-slate-600 font-bold">Start Date</p>' +
      '<p class="text-xl font-bold text-brand-blue mt-2">' + formatDateLabel(instance.start_date) + '</p>';
    detailsGrid.appendChild(startDiv);

    var endDiv = document.createElement('div');
    endDiv.className = 'bg-blue-50 border-2 border-brand-blue rounded-lg p-4';
    endDiv.innerHTML = '<p class="text-xs uppercase tracking-widest text-slate-600 font-bold">End Date</p>' +
      '<p class="text-xl font-bold text-brand-blue mt-2">' + formatDateLabel(instance.end_date) + '</p>';
    detailsGrid.appendChild(endDiv);

    var accessDate = new Date(instance.end_date + 'T00:00:00');
    accessDate.setDate(accessDate.getDate() + 45);
    var accessDiv = document.createElement('div');
    accessDiv.className = 'bg-red-50 border-2 border-brand-red rounded-lg p-4';
    accessDiv.innerHTML = '<p class="text-xs uppercase tracking-widest text-slate-600 font-bold">Access Through</p>' +
      '<p class="text-xl font-bold text-brand-red mt-2">' + accessDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + '</p>' +
      '<p class="text-xs text-slate-600 mt-1">(45 days after class ends)</p>';
    detailsGrid.appendChild(accessDiv);

    var seatsDiv = document.createElement('div');
    seatsDiv.className = 'bg-slate-50 border-2 border-slate-300 rounded-lg p-4';
    seatsDiv.innerHTML = '<p class="text-xs uppercase tracking-widest text-slate-600 font-bold">Seats Available</p>' +
      '<p class="text-xl font-bold text-slate-900 mt-2">' + instance.available_seats + ' / ' + instance.max_capacity + '</p>';
    detailsGrid.appendChild(seatsDiv);
    body.appendChild(detailsGrid);

    // Class rules section
    var rulesSection = document.createElement('section');
    var rulesHeading = document.createElement('h3');
    rulesHeading.className = 'text-lg font-bold text-slate-900 mb-4 pb-2 border-b-2 border-brand-blue';
    rulesHeading.textContent = 'Class Rules';
    rulesSection.appendChild(rulesHeading);

    var rulesList = document.createElement('ul');
    rulesList.className = 'space-y-2';
    
    var defaultRules = [
      'Well Control is a TEAM Effort',
      'Do Not Dominate',
      'Respect Opinions',
      'Respect Time',
      'Must Not Be Absent More Than 4 Hours',
      'No Phone Usage During Lectures (must be on silent/vibrate; calls returned outside lecture hall)',
      'No Electronic Recording of Lectures'
    ];

    defaultRules.forEach(function(rule) {
      var li = document.createElement('li');
      li.className = 'flex items-start';
      li.innerHTML = '<span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-brand-blue text-white flex-shrink-0 font-bold text-sm">✓</span>' +
        '<span class="ml-3 text-slate-700">' + rule + '</span>';
      rulesList.appendChild(li);
    });
    rulesSection.appendChild(rulesList);

    if (Array.isArray(instance.class_rules) && instance.class_rules.length) {
      var customWrap = document.createElement('div');
      customWrap.className = 'mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4';

      var customTitle = document.createElement('p');
      customTitle.className = 'text-sm font-semibold uppercase tracking-wide text-slate-500';
      customTitle.textContent = 'Additional Rules for This Class';
      customWrap.appendChild(customTitle);

      var customList = document.createElement('ul');
      customList.className = 'mt-3 space-y-3';

      instance.class_rules.forEach(function(rule) {
        var li = document.createElement('li');
        li.className = 'flex items-start';
        li.innerHTML = '<span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-brand-red text-white flex-shrink-0 font-bold text-sm">+</span>' +
          '<span class="ml-3 text-slate-700">' + rule + '</span>';
        customList.appendChild(li);
      });

      customWrap.appendChild(customList);
      rulesSection.appendChild(customWrap);
    }

    body.appendChild(rulesSection);

    // Pre-class resources
    var resourcesDiv = document.createElement('div');
    resourcesDiv.className = 'bg-amber-50 border-l-4 border-amber-500 p-4 rounded';
    resourcesDiv.innerHTML = '<h4 class="font-bold text-amber-900 mb-2">Before Class Starts</h4>' +
      '<p class="text-sm text-amber-800">Please review the following materials to prepare for class:</p>' +
      '<ul class="text-sm text-amber-800 list-disc list-inside mt-2 space-y-1">' +
      '<li>Workbook</li>' +
      '<li>Pocket Pro Guide</li>' +
      '<li>Killsheets</li>' +
      '<li>Formula Sheets</li>' +
      '</ul>';
    body.appendChild(resourcesDiv);

    content.appendChild(body);

    // Footer with action buttons
    var footer = document.createElement('div');
    footer.className = 'flex gap-3 p-6 border-t border-slate-200 bg-slate-50';

    var cancelBtn = document.createElement('button');
    cancelBtn.className = 'flex-1 bg-slate-300 hover:bg-slate-400 text-slate-900 font-bold py-3 rounded-lg transition';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.onclick = function() { modal.remove(); };
    footer.appendChild(cancelBtn);

    if (instance.available_seats > 0 && instance.product_id) {
      var bookBtn = document.createElement('button');
      bookBtn.className = 'flex-1 bg-brand-blue hover:bg-blue-700 text-black font-bold py-3 rounded-lg transition';
      bookBtn.textContent = 'Reserve My Seat';
      bookBtn.onclick = function() {
        var cartUrl = (window.TQBookingCalendar && window.TQBookingCalendar.cartUrl) ? window.TQBookingCalendar.cartUrl : '/cart/';
        var targetUrl = new URL(cartUrl, window.location.origin);
        targetUrl.searchParams.set('add-to-cart', String(instance.product_id));
        targetUrl.searchParams.set('quantity', '1');
        window.location.href = targetUrl.toString();
      };
      footer.appendChild(bookBtn);
    }

    content.appendChild(footer);
    document.body.appendChild(modal);

    // Close on backdrop click
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        modal.remove();
      }
    });
  }
})();