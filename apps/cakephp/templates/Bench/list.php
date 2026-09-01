<?php
/**
 * @var \App\Cake\View\AppView $this
 * @var string $title
 * @var iterable<object> $items
 * @var string $baseUrl
 * @var array{currentPage: int, lastPage: int, previousPage: int, nextPage: int, totalItems: int, firstItem: int, lastItem: int, hasPrevious: bool, hasNext: bool} $pagination
 */
?>
<header>
    <h1><?= h(strtoupper($title)) ?></h1>
    <p>Showing <?= count($items) ?> items.</p>
</header>

<table>
<thead>
    <tr>
        <th>#</th>
        <th>Title</th>
        <th>Created</th>
        <th>Badge</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($items as $idx => $item): ?>
        <tr<?= ($idx % 2 === 0) ? ' class="even"' : '' ?>>
            <td><a href="<?= h($baseUrl) ?>/<?= h((string) $item->id) ?>"><?= h((string) $item->id) ?></a></td>
            <td><?= h((string) $item->title) ?></td>
            <td><?= h((string) $item->created_at) ?></td>
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
    <?php endforeach; ?>
</tbody>
</table>

<div class="pagination">
    <span class="page-info">
        Showing <?= (int) $pagination['firstItem'] ?>–<?= (int) $pagination['lastItem'] ?>
        of <?= (int) $pagination['totalItems'] ?> items
    </span>
    <span class="page-nav">
        <?php if ($pagination['hasPrevious']): ?>
            <a href="<?= h($baseUrl) ?>?page=<?= $pagination['previousPage'] ?>">← Previous</a>
        <?php else: ?>
            <span class="disabled">← Previous</span>
        <?php endif; ?>
        <span class="page-current">Page <?= $pagination['currentPage'] ?> / <?= $pagination['lastPage'] ?></span>
        <?php if ($pagination['hasNext']): ?>
            <a href="<?= h($baseUrl) ?>?page=<?= $pagination['nextPage'] ?>">Next →</a>
        <?php else: ?>
            <span class="disabled">Next →</span>
        <?php endif; ?>
    </span>
</div>