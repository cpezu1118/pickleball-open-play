(function () {
  'use strict';

  var audioCtx = null;

  function soundEnabled() {
    try {
      return localStorage.getItem('pp-sound') !== 'off';
    } catch (e) {
      return true;
    }
  }

  function setSoundEnabled(on) {
    try {
      localStorage.setItem('pp-sound', on ? 'on' : 'off');
    } catch (e) { /* ignore */ }
  }

  function tone(ctx, freq, start, dur, type, peak) {
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = type || 'triangle';
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(peak || 0.14, start + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + dur + 0.02);
  }

  function uiTap() {
    if (!soundEnabled()) return;
    var ctx = ensureAudio();
    if (!ctx) return;
    tone(ctx, 720, ctx.currentTime, 0.07, 'triangle', 0.1);
  }

  function uiWhoosh() {
    if (!soundEnabled()) return;
    var ctx = ensureAudio();
    if (!ctx) return;
    var now = ctx.currentTime;
    tone(ctx, 500, now, 0.09, 'triangle', 0.12);
    tone(ctx, 760, now + 0.08, 0.12, 'triangle', 0.12);
  }

  function initSoundToggle() {
    var btns = document.querySelectorAll('[data-sound-toggle]');
    function render() {
      var on = soundEnabled();
      btns.forEach(function (btn) {
        btn.setAttribute('data-sound-state', on ? 'on' : 'off');
        btn.setAttribute('aria-label', on ? 'Mute sound' : 'Unmute sound');
        var use = btn.querySelector('use');
        if (use) use.setAttribute('href', on ? '#icon-sound-on' : '#icon-sound-off');
      });
    }
    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setSoundEnabled(!soundEnabled());
        render();
        if (soundEnabled()) {
          ensureAudio();
          uiTap();
        }
      });
    });
    render();
  }

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

  function spawnConfetti(container) {
    if (!container) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var colors = ['#ff6b5e', '#ffd23f', '#8b5cf6', '#aee9ff'];
    for (var i = 0; i < 14; i++) {
      var dot = document.createElement('div');
      dot.className = 'confetti-dot';
      var angle = Math.random() * Math.PI * 2;
      var dist = 50 + Math.random() * 70;
      dot.style.setProperty('--dx', (Math.cos(angle) * dist).toFixed(0) + 'px');
      dot.style.setProperty('--dy', (Math.sin(angle) * dist - 30).toFixed(0) + 'px');
      dot.style.left = (40 + Math.random() * 20) + '%';
      dot.style.top = '10%';
      dot.style.background = colors[i % colors.length];
      dot.style.animationDelay = (Math.random() * 0.12).toFixed(2) + 's';
      container.appendChild(dot);
      (function (el) {
        setTimeout(function () { el.remove(); }, 900);
      })(dot);
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

  function initPlayCarousel(root) {
    var sessionId = root.getAttribute('data-session-id') || '0';
    var roundCount = parseInt(root.getAttribute('data-round-count') || '0', 10);
    var isAdmin = root.getAttribute('data-is-admin') === '1';
    var started = root.getAttribute('data-started') === '1';
    var liveRound = parseInt(root.getAttribute('data-live-round') || '0', 10);
    var revision = root.getAttribute('data-revision') || '';
    var stateUrl = root.getAttribute('data-state-url') || '';
    var initialRound = parseInt(root.getAttribute('data-initial-round') || '1', 10);

    if (roundCount < 1) {
      // Still poll when waiting to start (viewers).
      if (!isAdmin && stateUrl) {
        startPolling(root, stateUrl, revision, false);
      }
      return;
    }

    var track = root.querySelector('[data-play-track]');
    var label = root.querySelector('[data-play-round-label]');
    var prevBtn = root.querySelector('[data-play-prev]');
    var nextBtn = root.querySelector('[data-play-next]');
    var dots = root.querySelectorAll('[data-play-dot]');
    var viewport = root.querySelector('[data-play-viewport]');
    var roundsPanel = root.querySelector('[data-play-panel="rounds"]');
    var standingsPanel = root.querySelector('[data-play-panel="standings"]');
    var tabs = root.querySelectorAll('[data-play-tab]');
    var jumpLive = root.querySelector('[data-jump-live]');

    // Host during live play: carousel locked to server live round (Next/Prev are form posts).
    var hostLocked = isAdmin && started;
    // Viewers (and admin preview): can browse locally; viewers auto-follow live on poll.
    var allowBrowse = !hostLocked;

    function clamp(n, min, max) {
      return Math.max(min, Math.min(max, n));
    }

    var followingLive = true;
    var current = clamp(hostLocked ? liveRound : initialRound, 1, roundCount);

    function goTo(round, opts) {
      opts = opts || {};
      var previous = current;
      current = clamp(round, 1, roundCount);
      if (track) {
        track.style.transform = 'translate3d(' + (-(current - 1) * 100) + '%, 0, 0)';
      }
      if (previous !== current) {
        var ballEl = root.querySelector('[data-ball-transit]');
        if (ballEl) {
          ballEl.classList.remove('is-active');
          void ballEl.offsetWidth;
          ballEl.classList.add('is-active');
        }
        uiWhoosh();
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

      if (liveRound >= 1 && allowBrowse && !isAdmin) {
        followingLive = current === liveRound;
        if (jumpLive) {
          jumpLive.classList.toggle('is-hidden', followingLive);
        }
      }

      if (opts.updateUrl !== false) {
        try {
          var url = new URL(window.location.href);
          if (url.searchParams.get('view') !== 'standings') {
            url.searchParams.delete('round');
            window.history.replaceState({}, '', url.toString());
          }
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
        window.history.replaceState({}, '', url.toString());
      } catch (e) { /* ignore */ }
    }

    if (allowBrowse) {
      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          followingLive = false;
          goTo(current - 1);
        });
      }
      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          followingLive = false;
          goTo(current + 1);
        });
      }
      dots.forEach(function (dot) {
        if (dot.tagName === 'BUTTON') {
          dot.addEventListener('click', function () {
            followingLive = false;
            goTo(parseInt(dot.getAttribute('data-play-dot'), 10));
          });
        }
      });

      document.addEventListener('keydown', function (ev) {
        if (!roundsPanel || roundsPanel.classList.contains('is-hidden')) return;
        var tag = (ev.target && ev.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (ev.key === 'ArrowLeft') {
          followingLive = false;
          goTo(current - 1);
        } else if (ev.key === 'ArrowRight') {
          followingLive = false;
          goTo(current + 1);
        }
      });

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
            followingLive = false;
            if (dx < 0) goTo(current + 1);
            else goTo(current - 1);
          }
        }

        viewport.addEventListener('pointerup', endPointer);
        viewport.addEventListener('pointercancel', function () {
          tracking = false;
        });
      }
    }

    if (jumpLive) {
      jumpLive.addEventListener('click', function () {
        followingLive = true;
        goTo(liveRound);
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        showView(tab.getAttribute('data-play-tab'));
      });
    });

    var initialView = root.getAttribute('data-view') || 'rounds';
    goTo(current, { updateUrl: false });
    if (standingsPanel) {
      showView(initialView === 'standings' ? 'standings' : 'rounds');
    }

    // Poll for host progress (everyone, especially public viewers).
    if (stateUrl) {
      startPolling(root, stateUrl, revision, true, {
        isAdmin: isAdmin,
        getFollowing: function () {
          return followingLive || hostLocked;
        },
        onLiveRound: function (round) {
          liveRound = round;
          if (hostLocked || followingLive) {
            goTo(round);
          } else if (jumpLive) {
            jumpLive.classList.remove('is-hidden');
          }
        }
      });
    }
  }

  function startPolling(root, stateUrl, revision, hasCarousel, hooks) {
    hooks = hooks || {};
    var lastRevision = revision || '';
    var wasStarted = root.getAttribute('data-started') === '1';

    function poll() {
      fetch(stateUrl, { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (!data || data.error) return;

          var nowStarted = !!data.started;
          var rev = data.revision || '';

          // Host started, or round/scores changed → reload for fresh HTML (scores, banners).
          if (nowStarted !== wasStarted || (rev && rev !== lastRevision)) {
            // Soft follow: if only browsing away and round changed, reload still so scores sync.
            var follow = !hooks.getFollowing || hooks.getFollowing();
            if (follow || nowStarted !== wasStarted || (hooks.isAdmin && rev !== lastRevision)) {
              window.location.reload();
              return;
            }
            // Viewer peeking another round: update live indicator via reload is simplest for scores.
            // Still reload so standings/scores stay honest when host saves.
            window.location.reload();
            return;
          }

          if (hasCarousel && data.current_round >= 1 && hooks.onLiveRound) {
            hooks.onLiveRound(data.current_round);
          }
        })
        .catch(function () { /* ignore transient errors */ });
    }

    setInterval(poll, 4000);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-slot-timer]').forEach(initTimer);
    initSoundToggle();

    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('button, .btn');
      if (!btn || btn.disabled) return;
      if (btn.closest('[data-play-prev],[data-play-next],[data-play-dot],[data-sound-toggle]')) return;
      uiTap();
    });

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
      if (playRoot.getAttribute('data-celebrate') === '1') {
        spawnConfetti(playRoot);
      }
    }
  });
})();
