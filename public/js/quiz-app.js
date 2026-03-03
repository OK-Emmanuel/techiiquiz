(function () {
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
      if (!res.ok) {
        throw new Error(data.message || 'Request failed');
      }
      return data;
    });
  }

  function initQuiz(wrapper) {
    const setId = Number(wrapper.dataset.setId);
    const mode = wrapper.dataset.mode;
    const status = wrapper.querySelector('[data-tq-status]');
    const questionBox = wrapper.querySelector('[data-tq-question]');
    const progressEl = wrapper.querySelector('[data-tq-progress]');
    const promptEl = wrapper.querySelector('[data-tq-prompt]');
    const choicesEl = wrapper.querySelector('[data-tq-choices]');
    const feedbackEl = wrapper.querySelector('[data-tq-feedback]');
    const submitBtn = wrapper.querySelector('[data-tq-submit]');
    const nextBtn = wrapper.querySelector('[data-tq-next]');
    const finishEl = wrapper.querySelector('[data-tq-finish]');
    const scoreEl = wrapper.querySelector('[data-tq-score]');
    const missedEl = wrapper.querySelector('[data-tq-missed]');

    let sessionId = 0;
    let index = 0;
    let questions = [];

    function renderQuestion() {
      const q = questions[index];
      if (!q) {
        if (mode === 'practice') {
          completePractice();
          return;
        }
        status.textContent = 'Study set complete.';
        questionBox.classList.add('hidden');
        return;
      }

      questionBox.classList.remove('hidden');
      progressEl.textContent = `Question ${index + 1} of ${questions.length}`;
      promptEl.textContent = q.prompt;
      choicesEl.innerHTML = '';
      feedbackEl.textContent = '';
      nextBtn.classList.add('hidden');

      q.choices.forEach((choice) => {
        const row = document.createElement('label');
        row.className = 'flex items-start gap-3 p-3 border border-slate-200 rounded-md cursor-pointer hover:border-slate-400';
        row.innerHTML = `
          <input type="radio" name="tq-choice" value="${choice.id}" class="mt-1" />
          <span class="text-slate-800"><strong>${choice.choice_key}.</strong> ${choice.choice_text}</span>
        `;
        choicesEl.appendChild(row);
      });
    }

    function getSelectedChoice() {
      const selected = choicesEl.querySelector('input[name="tq-choice"]:checked');
      return selected ? Number(selected.value) : 0;
    }

    async function completePractice() {
      const result = await request('session/complete', 'POST', { session_id: sessionId });
      questionBox.classList.add('hidden');
      finishEl.classList.remove('hidden');
      scoreEl.textContent = `Score: ${result.score_percent}%`;

      if (!result.missed || result.missed.length === 0) {
        missedEl.innerHTML = '<p class="text-green-700">No missed questions. Great job.</p>';
        return;
      }

      missedEl.innerHTML = '<h4 class="font-medium text-slate-900">Missed questions</h4>';
      result.missed.forEach((item) => {
        const line = document.createElement('div');
        line.className = 'text-sm text-slate-700 p-2 border border-slate-200 rounded';
        line.textContent = `${item.prompt}`;
        missedEl.appendChild(line);
      });
    }

    async function onSubmit() {
      const choiceId = getSelectedChoice();
      if (!choiceId) {
        feedbackEl.textContent = 'Please select an answer.';
        feedbackEl.className = 'text-sm text-amber-700';
        return;
      }

      const q = questions[index];
      const result = await request('session/answer', 'POST', {
        session_id: sessionId,
        question_id: Number(q.id),
        choice_id: choiceId,
      });

      if (mode === 'study') {
        feedbackEl.textContent = result.message || '';
        feedbackEl.className = result.is_correct ? 'text-sm text-green-700' : 'text-sm text-red-700';

        if (result.can_advance) {
          nextBtn.classList.remove('hidden');
        }
        return;
      }

      index += 1;
      renderQuestion();
    }

    nextBtn.addEventListener('click', () => {
      index += 1;
      renderQuestion();
    });

    submitBtn.addEventListener('click', () => {
      onSubmit().catch((err) => {
        feedbackEl.textContent = err.message;
        feedbackEl.className = 'text-sm text-red-700';
      });
    });

    Promise.all([
      request(`set/${setId}`, 'GET'),
      request('session/start', 'POST', { set_id: setId, mode: mode }),
    ])
      .then(([setPayload, sessionPayload]) => {
        questions = setPayload.questions || [];
        sessionId = Number(sessionPayload.session_id || 0);
        status.textContent = 'Quiz ready.';
        renderQuestion();
      })
      .catch((err) => {
        status.textContent = err.message;
      });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tq-quiz-wrapper').forEach(initQuiz);
  });
})();
