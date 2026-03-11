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
        row.className         = 'tq-choice-row';
        row.dataset.choiceId  = choice.id;
        row.innerHTML = `
          <span class="tq-choice-key">${escHtml(choice.choice_key)}</span>
          <span class="flex-1 text-slate-800 text-sm leading-relaxed">${escHtml(choice.choice_text)}</span>
          <input type="radio" name="tq-choice" value="${escHtml(String(choice.id))}" class="sr-only" />
        `;
        row.addEventListener('click', () => {
          if (submitting) return;
          choicesEl.querySelectorAll('.tq-choice-row').forEach((r) =>
            r.classList.remove('tq-selected')
          );
          row.classList.add('tq-selected');
        });
        choicesEl.appendChild(row);
      });
    }

    /* ── Choice helpers ───────────────────────────────────────────────── */
    function selectedRow() {
      return choicesEl.querySelector('.tq-choice-row.tq-selected');
    }

    function selectedChoiceId() {
      const row = selectedRow();
      return row ? Number(row.dataset.choiceId) : 0;
    }

    function disableChoices() {
      choicesEl.querySelectorAll('.tq-choice-row').forEach((r) =>
        r.classList.add('tq-disabled')
      );
    }

    /* ── Feedback strip ───────────────────────────────────────────────── */
    function showFeedback(text, type /* 'ok' | 'err' | 'info' */) {
      feedbackEl.textContent = text;
      feedbackEl.className   = `mt-4 text-sm font-medium px-3 py-2.5 rounded-md tq-feedback-${type}`;
    }

    function hideFeedback() {
      feedbackEl.className = 'hidden mt-4 text-sm font-medium px-3 py-2.5 rounded-md';
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
          disableChoices();

          if (result.is_correct) {
            picked && picked.classList.add('tq-correct');
            showFeedback('✓  Correct!', 'ok');
          } else {
            picked && picked.classList.add('tq-wrong');
            /* highlight the correct choice */
            if (result.correct_choice_id) {
              choicesEl.querySelectorAll('.tq-choice-row').forEach((r) => {
                if (Number(r.dataset.choiceId) === result.correct_choice_id) {
                  r.classList.add('tq-correct');
                }
              });
            }
            showFeedback('✗  Incorrect — the correct answer is highlighted above.', 'err');
          }
        } else {
          /* practice mode: record silently, just show Next */
          showFeedback('Answer recorded.', 'info');
          disableChoices();
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
          'inline-flex items-center justify-center w-28 h-28 rounded-full text-3xl font-extrabold mb-4 ' +
          (pct >= 70 ? 'tq-score-pass' : pct >= 50 ? 'tq-score-warn' : 'tq-score-fail');

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
          '<h4 class="text-sm font-bold text-slate-700 border-t border-slate-100 pt-4 mb-3">' +
          'Review: Missed Questions</h4>';

        result.missed.forEach((item, i) => {
          html += `<div class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-3">`;
          html += `<p class="text-xs text-slate-400 mb-1">Question ${i + 1}</p>`;
          html += `<p class="text-sm font-semibold text-slate-900 mb-3">${escHtml(item.prompt)}</p>`;
          html += `<div class="space-y-1.5">`;
          (item.choices || []).forEach((choice) => {
            let cls  = 'flex items-center gap-2 text-sm px-3 py-1.5 rounded ';
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
        'inline-flex items-center justify-center w-28 h-28 rounded-full text-4xl font-extrabold mb-4 tq-score-pass';
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
          'flex-shrink-0 text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full ' +
          (isPractice ? 'tq-badge-practice' : 'tq-badge-study');

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
