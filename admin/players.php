<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_string('action');

    if ($action === 'add') {
        $name = post_string('name');
        if ($name === '') {
            flash_set('error', 'Name is required.');
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO players (name, active) VALUES (?, 1)');
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $stmt->close();
                flash_set('success', 'Player added.');
            } catch (Throwable $e) {
                flash_set('error', 'That name already exists.');
            }
        }
    } elseif ($action === 'rename') {
        $id = post_int('id');
        $name = post_string('name');
        if ($id < 1 || $name === '') {
            flash_set('error', 'Invalid rename.');
        } else {
            try {
                $stmt = db()->prepare('UPDATE players SET name = ? WHERE id = ?');
                $stmt->bind_param('si', $name, $id);
                $stmt->execute();
                $stmt->close();
                flash_set('success', 'Player updated.');
            } catch (Throwable $e) {
                flash_set('error', 'That name already exists.');
            }
        }
    } elseif ($action === 'toggle') {
        $id = post_int('id');
        $active = post_int('active') ? 1 : 0;
        $stmt = db()->prepare('UPDATE players SET active = ? WHERE id = ?');
        $stmt->bind_param('ii', $active, $id);
        $stmt->execute();
        $stmt->close();
        flash_set('success', $active ? 'Player activated.' : 'Player deactivated.');
    }

    redirect('admin/players.php');
}

$players = db()->query(
    'SELECT id, name, active, created_at FROM players ORDER BY active DESC, name ASC'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Players';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h1>Players</h1>
    <p>Names stay on file for the next open play.</p>
    <form method="post" class="grid-2" style="align-items:end;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>
            New player name
            <input type="text" name="name" required maxlength="100" placeholder="e.g. Alex">
        </label>
        <div class="btn-row" style="margin:0;">
            <button type="submit">Add player</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($players as $p): ?>
                <tr>
                    <td>
                        <form method="post" class="btn-row" style="margin:0;align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="rename">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="text" name="name" value="<?= e($p['name']) ?>" required maxlength="100" style="min-width:10rem;">
                            <button type="submit" class="secondary">Save</button>
                        </form>
                    </td>
                    <td><?= (int) $p['active'] ? 'Active' : 'Inactive' ?></td>
                    <td>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="active" value="<?= (int) $p['active'] ? 0 : 1 ?>">
                            <button type="submit" class="secondary">
                                <?= (int) $p['active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$players): ?>
                <tr><td colspan="3" class="muted">No players yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
