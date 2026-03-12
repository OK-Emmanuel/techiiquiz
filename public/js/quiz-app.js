(function () {
  /* ── REST helper ──────────────────────────────────────────────────────── */
  function request(path, method, body) {
    return fetch(TQQuiz.restBase + path, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': TQQuiz.nonce,
      },
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin',
    }).then(async (res) => {
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Request failed');
      return data;
    });
  }

  /* ── Utility ──────────────────────────────────────────────────────────── */
  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
  }

  function setClasses(node, classes) {
    node.className = classes;
  }

  /* ── Per-wrapper initialisation ───────────────────────────────────────── */
  function initQuiz(wrapper) {
    const setId = Number(wrapper.dataset.setId);
    const mode  = wrapper.dataset.mode;

    /* DOM refs */
    const loadingEl     = wrapper.querySelector('[data-tq-loading]');
    const errorEl       = wrapper.querySelector('[data-tq-error]');
    const cardEl        = wrapper.querySelector('[data-tq-card]');
    const setTitleEl    = wrapper.querySelector('[data-tq-set-title]');
    const setSubEl      = wrapper.querySelector('[data-tq-set-sub]');
    const modeBadgeEl   = wrapper.querySelector('[data-tq-mode-badge]');
    const progressBarEl = wrapper.querySelector('[data-tq-progress-bar]');
    const progressTextEl= wrapper.querySelector('[data-tq-progress-text]');
    const questionEl    = wrapper.querySelector('[data-tq-question]');
    const qNumEl        = wrapper.querySelector('[data-tq-q-num]');
    const promptEl      = wrapper.querySelector('[data-tq-prompt]');
    const choicesEl     = wrapper.querySelector('[data-tq-choices]');
    const feedbackEl    = wrapper.querySelector('[data-tq-feedback]');
    const submitBtn     = wrapper.querySelector('[data-tq-submit]');
    const nextBtn       = wrapper.querySelector('[data-tq-next]');
    const actionsEl     = wrapper.querySelector('[data-tq-actions]');
    const finishEl      = wrapper.querySelector('[data-tq-finish]');
    const scoreCircleEl = wrapper.querySelector('[data-tq-score-circle]');
    const finishTitleEl = wrapper.querySelector('[data-tq-finish-title]');
    const scoreLabelEl  = wrapper.querySelector('[data-tq-score-label]');
    const missedEl      = wrapper.querySelector('[data-tq-missed]');

    let sessionId  = 0;
    let index      = 0;
    let questions  = [];
    let submitting = false;

    const choiceBaseClass =
      'group flex cursor-pointer items-start gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 transition duration-150 hover:border-brand-blue/40 hover:bg-indigo-50/50';
    const choiceSelectedClass =
      ' border-brand-blue bg-indigo-50 shadow-sm';
    const choiceCorrectClass =
      ' border-green-600 bg-green-50';
    const choiceWrongClass =
      ' border-red-600 bg-red-50';
    const choiceDisabledClass =
      ' cursor-default opacity-60';
    const keyBaseClass =
      'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-bold uppercase text-slate-500 transition';

    /* ── Error screen ─────────────────────────────────────────────────── */
    function showError(msg) {
      loadingEl.classList.add('hidden');
      cardEl.classList.add('hidden');
      errorEl.textContent = msg;
      errorEl.classList.remove('hidden');
    }

    /* ── Progress bar ─────────────────────────────────────────────────── */
    function updateProgress() {
      const total = questions.length;
      const done  = Math.min(index, total);
      const pct   = total > 0 ? Math.round((done / total) * 100) : 0;
      progressBarEl.style.width = pct + '%';
      progressTextEl.textContent = `Question ${done + 1} of ${total}`;
    }

    /* ── Render one question ──────────────────────────────────────────── */
    function renderQuestion() {
      const q = questions[index];
      if (!q) {
        mode === 'practice' ? completePractice() : showStudyComplete();
        return;
      }

      submitting = false;
      qNumEl.textContent  = index + 1;
      promptEl.textContent = q.prompt;
      hideFeedback();
      nextBtn.classList.add('hidden');
      submitBtn.classList.remove('hidden');
      submitBtn.disabled = false;
      updateProgress();

      /* build choice rows */
      choicesEl.innerHTML = '';
      q.choices.forEach((choice) => {
        const row = document.createElement('label');
        row.className         = choiceBaseClass;
        row.dataset.choiceId  = String(choice.id);
        row.dataset.state     = 'base';
        row.innerHTML = `
          <span data-tq-choice-key class="${keyBaseClass}">${escHtml(choice.choice_key)}</span>
          <span class="flex-1 pt-1 text-sm leading-relaxed text-slate-800 sm:text-[15px]">${escHtml(choice.choice_text)}</span>
          <input type="radio" name="tq-choice" value="${escHtml(String(choice.id))}" class="sr-only" />
        `;
        row.addEventListener('click', () => {
          if (submitting) return;
          choicesEl.querySelectorAll('label').forEach((r) => updateChoiceVisual(r, 'base'));
          updateChoiceVisual(row, 'selected');
        });
        choicesEl.appendChild(row);
      });
    }

    function updateChoiceVisual(row, state) {
      const key = row.querySelector('[data-tq-choice-key]');
      row.dataset.state = state;
      let rowClasses = choiceBaseClass;
      let keyClasses = keyBaseClass;

      if (state === 'selected') {
        rowClasses += choiceSelectedClass;
        keyClasses = 'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border border-brand-blue bg-red-600 text-[11px] font-bold uppercase text-white transition';
      } else if (state === 'correct') {
        rowClasses += choiceCorrectClass;
        keyClasses = 'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border border-green-600 bg-green-600 text-[11px] font-bold uppercase text-white transition';
      } else if (state === 'wrong') {
        rowClasses += choiceWrongClass;
        keyClasses = 'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border border-red-600 bg-red-600 text-[11px] font-bold uppercase text-white transition';
      } else if (state === 'disabled') {
        rowClasses += choiceDisabledClass;
      }

      setClasses(row, rowClasses);
      if (key) {
        setClasses(key, keyClasses);
      }
    }

    /* ── Choice helpers ───────────────────────────────────────────────── */
    function selectedRow() {
      return choicesEl.querySelector('label[data-state="selected"]');
    }

    function selectedChoiceId() {
      const row = selectedRow();
      return row ? Number(row.dataset.choiceId) : 0;
    }

    function disableChoices(preserveSelected) {
      choicesEl.querySelectorAll('label').forEach((row) => {
        if (row.dataset.state === 'correct' || row.dataset.state === 'wrong') {
          setClasses(row, row.className + ' cursor-default');
          return;
        }

        if (preserveSelected && row.dataset.state === 'selected') {
          setClasses(row, row.className + ' cursor-default');
          return;
        }

        updateChoiceVisual(row, 'disabled');
      });
    }

    /* ── Feedback strip ───────────────────────────────────────────────── */
    function showFeedback(text, type /* 'ok' | 'err' | 'info' */) {
      feedbackEl.textContent = text;
      const tone = type === 'ok'
        ? 'border border-green-200 bg-green-50 text-green-700'
        : type === 'err'
          ? 'border border-red-200 bg-red-50 text-red-700'
          : 'border border-indigo-200 bg-indigo-50 text-brand-blue';
      feedbackEl.className = `mt-5 rounded-2xl px-4 py-3 text-sm font-medium ${tone}`;
    }

    function hideFeedback() {
      feedbackEl.className = 'hidden mt-5 rounded-2xl px-4 py-3 text-sm font-medium';
    }

    /* ── Submit handler ───────────────────────────────────────────────── */
    async function onSubmit() {
      const choiceId = selectedChoiceId();
      if (!choiceId) {
        showFeedback('Please select an answer before submitting.', 'err');
        return;
      }

      submitting         = true;
      submitBtn.disabled = true;
      submitBtn.classList.add('hidden');

      const q = questions[index];

      try {
        const result = await request('session/answer', 'POST', {
          session_id:  sessionId,
          question_id: Number(q.id),
          choice_id:   choiceId,
        });

        if (mode === 'study') {
          const picked = selectedRow();
          disableChoices(false);

          if (result.is_correct) {
            picked && updateChoiceVisual(picked, 'correct');
            showFeedback('✓  Correct!', 'ok');
          } else {
            picked && updateChoiceVisual(picked, 'wrong');
            /* highlight the correct choice */
            if (result.correct_choice_id) {
              choicesEl.querySelectorAll('label').forEach((r) => {
                if (Number(r.dataset.choiceId) === result.correct_choice_id) {
                  updateChoiceVisual(r, 'correct');
                }
              });
            }
            showFeedback('✗  Incorrect — the correct answer is highlighted above.', 'err');
          }
        } else {
          /* practice mode: record silently, just show Next */
          showFeedback('Answer recorded.', 'info');
          disableChoices(true);
        }

        nextBtn.classList.remove('hidden');
      } catch (err) {
        submitting         = false;
        submitBtn.disabled = false;
        submitBtn.classList.remove('hidden');
        showFeedback(err.message || 'Submission error — please try again.', 'err');
      }
    }

    /* ── Completion screens ───────────────────────────────────────────── */
    async function completePractice() {
      questionEl.classList.add('hidden');
      actionsEl.classList.add('hidden');
      finishEl.classList.remove('hidden');

      /* progress → 100 % */
      progressBarEl.style.width   = '100%';
      progressTextEl.textContent  = 'Complete';

      scoreCircleEl.textContent = '…';
      finishTitleEl.textContent = 'Calculating score…';

      try {
        const result = await request('session/complete', 'POST', { session_id: sessionId });
        const pct    = result.score_percent ?? 0;

        scoreCircleEl.textContent = pct + '%';
        scoreCircleEl.className   =
          'mb-4 inline-flex h-28 w-28 items-center justify-center rounded-full text-3xl font-extrabold ' +
          (pct >= 70
            ? 'bg-green-100 text-green-700'
            : pct >= 50
              ? 'bg-amber-100 text-amber-700'
              : 'bg-red-100 text-red-700');

        if (pct >= 70) {
          finishTitleEl.textContent = 'Well done!';
        } else if (pct >= 50) {
          finishTitleEl.textContent = 'Keep practising';
        } else {
          finishTitleEl.textContent = 'More revision needed';
        }

        const total   = questions.length;
        const correct = Math.round((pct / 100) * total);
        scoreLabelEl.textContent = `${correct} correct out of ${total} questions`;

        /* missed questions review */
        if (!result.missed || result.missed.length === 0) {
          missedEl.innerHTML =
            '<p class="text-green-700 font-medium text-sm">✓ No missed questions — excellent work!</p>';
          return;
        }

        let html =
          '<h4 class="mb-4 border-t border-slate-200 pt-5 text-sm font-bold uppercase tracking-[0.18em] text-slate-500">' +
          'Review: Missed Questions</h4>';

        result.missed.forEach((item, i) => {
          html += `<div class="mb-4 rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">`;
          html += `<p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.18em] bg-indigo-900 text-slate-400">Question ${i + 1}</p>`;
          html += `<p class="mb-4 text-sm font-semibold leading-relaxed text-slate-900 sm:text-base">${escHtml(item.prompt)}</p>`;
          html += `<div class="space-y-1.5">`;
          (item.choices || []).forEach((choice) => {
            let cls  = 'flex items-center gap-2 rounded-xl px-3 py-2 text-sm ';
            let icon = '';
            if (choice.is_correct) {
              cls  += 'bg-green-50 text-green-800 font-semibold';
              icon  = '✓ ';
            } else if (choice.is_wrong_selected) {
              cls  += 'bg-red-50 text-red-700';
              icon  = '✗ ';
            } else {
              cls  += 'text-slate-500';
            }
            html +=
              `<div class="${cls}">${icon}` +
              `<strong>${escHtml(choice.choice_key)}.</strong>&nbsp;${escHtml(choice.choice_text)}</div>`;
          });
          html += `</div></div>`;
        });

        missedEl.innerHTML = html;
      } catch (err) {
        finishTitleEl.textContent = 'Could not load score.';
        scoreLabelEl.textContent  = err.message || '';
      }
    }

    function showStudyComplete() {
      questionEl.classList.add('hidden');
      actionsEl.classList.add('hidden');
      finishEl.classList.remove('hidden');
      progressBarEl.style.width  = '100%';
      progressTextEl.textContent = 'Complete';

      scoreCircleEl.className   =
        'mb-4 inline-flex h-28 w-28 items-center justify-center rounded-full bg-green-100 text-4xl font-extrabold text-green-700';
      scoreCircleEl.textContent = '✓';
      finishTitleEl.textContent = 'Study Set Complete';
      scoreLabelEl.textContent  = 'You have reviewed all questions in this set.';
    }

    /* ── Button listeners ─────────────────────────────────────────────── */
    nextBtn.addEventListener('click', () => {
      index += 1;
      renderQuestion();
    });

    submitBtn.addEventListener('click', () => {
      if (submitting) return;
      onSubmit().catch((err) => {
        submitting         = false;
        submitBtn.disabled = false;
        submitBtn.classList.remove('hidden');
        showFeedback(err.message || 'Submission error.', 'err');
      });
    });

    /* ── Bootstrap ────────────────────────────────────────────────────── */
    Promise.all([
      request(`set/${setId}`, 'GET'),
      request('session/start', 'POST', { set_id: setId, mode: mode }),
    ])
      .then(([setPayload, sessionPayload]) => {
        questions  = setPayload.questions || [];
        sessionId  = Number(sessionPayload.session_id || 0);
        index      = Number(sessionPayload.current_index || 0);
        if (index < 0 || index >= questions.length) index = 0;

        /* populate header */
        setTitleEl.textContent = setPayload.set_title || 'Quiz';
        setSubEl.textContent   = [setPayload.course_title, setPayload.day_label]
          .filter(Boolean).join(' · ');

        const isPractice       = mode === 'practice';
        modeBadgeEl.textContent = isPractice ? 'Practice Test' : 'Study Mode';
        modeBadgeEl.className   =
          'flex-shrink-0 rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.22em] ' +
          (isPractice ? 'bg-red-50 text-red-600' : 'bg-indigo-100 text-brand-blue');

        loadingEl.classList.add('hidden');
        cardEl.classList.remove('hidden');
        renderQuestion();
      })
      .catch((err) => showError(err.message || 'Failed to load quiz.'));
  }

  /* ── Boot all wrappers on page ────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tq-quiz-wrapper').forEach(initQuiz);
  });
})();
