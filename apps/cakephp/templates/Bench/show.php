<?php
/**
 * @var \App\Cake\View\AppView $this
 * @var string $title
 * @var object $item
 */
?>
<h1>Item <?= h((string) $item->id) ?></h1>

<?php if (!empty($flash)): ?>
    <div class="flash"><?= h($flash) ?></div>
<?php endif; ?>

<table>
<tbody>
    <tr>
        <th>ID</th>
        <td><?= h((string) $item->id) ?></td>
    </tr>
    <tr>
        <th>Title</th>
        <td><?= h((string) $item->title) ?></td>
    </tr>
    <tr>
        <th>Created</th>
        <td><?= h((string) $item->created_at) ?></td>
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
    <p class="meta">ID #<?= h((string) $item->id) ?> — created <?= h((string) $item->created_at) ?></p>
</footer>

<a href="/items">« Back to list</a>