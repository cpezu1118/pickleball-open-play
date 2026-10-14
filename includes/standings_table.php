<?php
/**
 * Shared standings table with rank badges, a trophy for 1st, and a win-rate bar.
 * Expects: array $standings (session_standings() rows: name,w,l,pf,pa,diff)
 */
?>
<table class="standings-enhanced">
        <thead>
            <tr>
                <th>#</th>
                <th>Player</th>
                <th>W-L</th>
                <th>Win%</th>
                <th>PF</th>
                <th>PA</th>
                <th>Diff</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($standings as $i => $row): ?>
            <?php
            $games = (int) $row['w'] + (int) $row['l'];
            $winPct = $games > 0 ? (int) round($row['w'] / $games * 100) : 0;
            $rankClass = $i === 0 ? 'rank-gold' : ($i === 1 ? 'rank-silver' : ($i === 2 ? 'rank-bronze' : ''));
            ?>
            <tr class="<?= $i === 0 ? 'standings-top' : '' ?>">
                <td>
                    <span class="rank-badge <?= $rankClass ?>">
                        <?php if ($i === 0): ?>
                            <svg class="pin-icon icon-sm"><use href="#icon-trophy"></use></svg>
                        <?php else: ?>
                            <?= $i + 1 ?>
                        <?php endif; ?>
                    </span>
                </td>
                <td><?= e($row['name']) ?></td>
                <td><?= (int) $row['w'] ?>-<?= (int) $row['l'] ?></td>
                <td>
                    <span class="win-bar-wrap" aria-hidden="true"><span class="win-bar" style="width:<?= $winPct ?>%"></span></span>
                    <span class="win-bar-label"><?= $winPct ?>%</span>
                </td>
                <td><?= (int) $row['pf'] ?></td>
                <td><?= (int) $row['pa'] ?></td>
                <td><?= (int) $row['diff'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$standings): ?>
            <tr><td colspan="7" class="muted">No games scored yet.</td></tr>
        <?php endif; ?>
        </tbody>
</table>
