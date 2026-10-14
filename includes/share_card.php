<?php
/**
 * Share QR + link for an open play session.
 * Expects: int $sessionId
 * URL comes from config public_base_url (see config.example.php / INSTALL.md).
 */
require_once __DIR__ . '/helpers.php';

$shareUrl = share_play_url($sessionId);
$qrUrl = qr_image_url($shareUrl, 240);
$showPlayButton = !empty($shareShowPlayButton);
?>
<section class="share-card panel">
    <h2>Share with friends</h2>
    <p class="muted">Friends scan this QR (or open the link). No login — view only. They follow the live round you control.</p>

    <div class="share-card-grid">
        <div class="share-qr-frame">
            <span class="scan-bubble">Scan me!</span>
            <div class="share-qr-wrap">
                <img
                    class="share-qr"
                    src="<?= e($qrUrl) ?>"
                    width="240"
                    height="240"
                    alt="QR code to open this open play"
                >
            </div>
            <svg class="pin-icon qr-ball" style="width:22px;height:22px"><use href="#icon-ball"></use></svg>
        </div>
        <div class="share-link-block">
            <label>
                Play link
                <input type="text" readonly value="<?= e($shareUrl) ?>" onclick="this.select();" id="share-play-url-<?= (int) $sessionId ?>">
            </label>
            <div class="btn-row">
                <button type="button" class="secondary" data-copy-share="<?= e($shareUrl) ?>">Copy link</button>
                <?php if ($showPlayButton): ?>
                    <a class="btn" href="<?= e(url('play.php?session=' . (int) $sessionId)) ?>">Open play screen</a>
                <?php endif; ?>
            </div>
            <p class="muted" style="margin:0.75rem 0 0;font-size:0.85rem;">
                Tip: print or screenshot the QR and put it by the courts.
            </p>
        </div>
    </div>
</section>
<script>
(function () {
  document.querySelectorAll('[data-copy-share]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy-share') || '';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = 'Copy link'; }, 1500);
        });
      } else {
        var input = document.getElementById('share-play-url-<?= (int) $sessionId ?>');
        if (input) { input.focus(); input.select(); }
      }
    });
  });
})();
</script>
