<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<h1>Item <?= (int) $item->id ?></h1>

<?php if (!empty($flash)): ?>
    <div class="flash"><?= esc($flash) ?></div>
<?php endif; ?>

<table>
<tbody>
    <tr>
        <th>ID</th>
        <td><?= (int) $item->id ?></td>
    </tr>
    <tr>
        <th>Title</th>
        <td><?= esc($item->title) ?></td>
    </tr>
    <tr>
        <th>Created</th>
        <td><?= esc($item->created_at) ?></td>
    </tr>
    <tr>
        <th>Status</th>
        <td>
            <?php if ($item->id % 100 === 0): ?>
                <span class="badge">Pinned</span>
            <?php elseif ($item->id % 10 === 0): ?>
                <span class="badge">Featured</span>
            <?php else: ?>
                <span class="badge">Standard</span>
            <?php endif; ?>
        </td>
    </tr>
</tbody>
</table>

<footer>
    <p class="meta">ID #<?= (int) $item->id ?> — created <?= esc($item->created_at) ?></p>
</footer>
<?= $this->endSection() ?>