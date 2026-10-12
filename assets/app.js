(function () {
  'use strict';

  var audioCtx = null;

  function ensureAudio() {
    if (!audioCtx) {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return null;
      audioCtx = new Ctx();
    }
    if (audioCtx.state === 'suspended') {
      audioCtx.resume();
    }
    return audioCtx;
  }

  function buzz() {
    var ctx = ensureAudio();
    if (!ctx) return;

    var now = ctx.currentTime;
    for (var i = 0; i < 3; i++) {
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = 'square';
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.0001, now + i * 0.28);
      gain.gain.exponentialRampToValueAtTime(0.2, now + i * 0.28 + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.28 + 0.2);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(now + i * 0.28);
      osc.stop(now + i * 0.28 + 0.22);
    }
  }

  function formatTime(totalSeconds) {
    var m = Math.floor(totalSeconds / 60);
    var s = totalSeconds % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
  }

  function initTimer(root) {
    var display = root.querySelector('[data-timer-display]');
    var startBtn = root.querySelector('[data-timer-start]');
    var pauseBtn = root.querySelector('[data-timer-pause]');
    var resetBtn = root.querySelector('[data-timer-reset]');
    var soundToggle = root.querySelector('[data-timer-sound]');
    var duration = parseInt(root.getAttribute('data-duration') || '900', 10);
    var remaining = duration;
    var intervalId = null;
    var running = false;

    function render() {
      if (display) display.textContent = formatTime(remaining);
    }

    function stopTick() {
      if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
      }
      running = false;
    }

    function onDone() {
      stopTick();
      remaining = 0;
      render();
      if (!soundToggle || soundToggle.checked) {
        buzz();
      }
    }

    function tick() {
      if (remaining <= 1) {
        onDone();
        return;
      }
      remaining -= 1;
      render();
    }

    if (startBtn) {
      startBtn.addEventListener('click', function () {
        ensureAudio();
        if (running) return;
        if (remaining <= 0) remaining = duration;
        running = true;
        intervalId = setInterval(tick, 1000);
      });
    }

    if (pauseBtn) {
      pauseBtn.addEventListener('click', function () {
        stopTick();
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        stopTick();
        remaining = duration;
        render();
      });
    }

    render();
  }

  function storageKey(sessionId) {
    return 'play-round-' + sessionId;
  }

  function initPlayCarousel(root) {
    var sessionId = root.getAttribute('data-session-id') || '0';
    var roundCount = parseInt(root.getAttribute('data-round-count') || '0', 10);
    if (roundCount < 1) return;

    var track = root.querySelector('[data-play-track]');
    var label = root.querySelector('[data-play-round-label]');
    var prevBtn = root.querySelector('[data-play-prev]');
    var nextBtn = root.querySelector('[data-play-next]');
    var dots = root.querySelectorAll('[data-play-dot]');
    var viewport = root.querySelector('[data-play-viewport]');
    var roundsPanel = root.querySelector('[data-play-panel="rounds"]');
    var standingsPanel = root.querySelector('[data-play-panel="standings"]');
    var tabs = root.querySelectorAll('[data-play-tab]');

    var fromQuery = parseInt(root.getAttribute('data-initial-round') || '1', 10);
    var hasRoundParam = new URLSearchParams(window.location.search).has('round');
    var stored = 0;
    try {
      stored = parseInt(sessionStorage.getItem(storageKey(sessionId)) || '0', 10);
    } catch (e) {
      stored = 0;
    }

    function clamp(n, min, max) {
      return Math.max(min, Math.min(max, n));
    }

    // URL ?round= wins; otherwise restore last local round from sessionStorage.
    var current = hasRoundParam ? fromQuery : (stored >= 1 && stored <= roundCount ? stored : fromQuery);
    current = clamp(current, 1, roundCount);

    function goTo(round, persist) {
      current = clamp(round, 1, roundCount);
      if (track) {
        track.style.transform = 'translate3d(' + (-(current - 1) * 100) + '%, 0, 0)';
      }
      if (label) {
        label.textContent = 'Round ' + current;
      }
      dots.forEach(function (dot) {
        var n = parseInt(dot.getAttribute('data-play-dot'), 10);
        dot.classList.toggle('is-active', n === current);
      });
      if (prevBtn) prevBtn.disabled = current <= 1;
      if (nextBtn) nextBtn.disabled = current >= roundCount;
      if (persist !== false) {
        try {
          sessionStorage.setItem(storageKey(sessionId), String(current));
        } catch (e) { /* ignore */ }
      }
    }

    function showView(view) {
      if (roundsPanel) roundsPanel.classList.toggle('is-hidden', view !== 'rounds');
      if (standingsPanel) standingsPanel.classList.toggle('is-hidden', view !== 'standings');
      tabs.forEach(function (tab) {
        tab.classList.toggle('is-active', tab.getAttribute('data-play-tab') === view);
      });
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('view', view);
        if (view === 'rounds') {
          url.searchParams.set('round', String(current));
        }
        window.history.replaceState({}, '', url.toString());
      } catch (e) { /* ignore */ }
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goTo(current - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        goTo(current + 1);
      });
    }
    dots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        goTo(parseInt(dot.getAttribute('data-play-dot'), 10));
      });
    });

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        showView(tab.getAttribute('data-play-tab'));
      });
    });

    document.addEventListener('keydown', function (ev) {
      if (!roundsPanel || roundsPanel.classList.contains('is-hidden')) return;
      var tag = (ev.target && ev.target.tagName) || '';
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      if (ev.key === 'ArrowLeft') {
        goTo(current - 1);
      } else if (ev.key === 'ArrowRight') {
        goTo(current + 1);
      }
    });

    // Swipe / pointer drag
    if (viewport) {
      var startX = 0;
      var startY = 0;
      var tracking = false;
      var locked = false;

      viewport.addEventListener('pointerdown', function (ev) {
        if (ev.target.closest('input, button, a, form, label')) return;
        tracking = true;
        locked = false;
        startX = ev.clientX;
        startY = ev.clientY;
        try {
          viewport.setPointerCapture(ev.pointerId);
        } catch (e) { /* ignore */ }
      });

      viewport.addEventListener('pointermove', function (ev) {
        if (!tracking) return;
        var dx = ev.clientX - startX;
        var dy = ev.clientY - startY;
        if (!locked && Math.abs(dx) > 12 && Math.abs(dx) > Math.abs(dy)) {
          locked = true;
        }
        if (locked) {
          ev.preventDefault();
        }
      }, { passive: false });

      function endPointer(ev) {
        if (!tracking) return;
        tracking = false;
        var dx = ev.clientX - startX;
        if (Math.abs(dx) > 50) {
          if (dx < 0) goTo(current + 1);
          else goTo(current - 1);
        }
      }

      viewport.addEventListener('pointerup', endPointer);
      viewport.addEventListener('pointercancel', function () {
        tracking = false;
      });
    }

    var initialView = root.getAttribute('data-view') || 'rounds';
    goTo(current, true);
    if (standingsPanel) {
      showView(initialView === 'standings' ? 'standings' : 'rounds');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-slot-timer]').forEach(initTimer);

    var unlock = document.querySelector('[data-unlock-audio]');
    if (unlock) {
      unlock.addEventListener('click', function () {
        ensureAudio();
        unlock.textContent = 'Sound ready';
        unlock.disabled = true;
      });
    }

    var playRoot = document.querySelector('[data-play-carousel]');
    if (playRoot) {
      initPlayCarousel(playRoot);
    }
  });
})();
