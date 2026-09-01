<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<header>
    <h1><?= esc(strtoupper($title)) ?></h1>
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
            <td><a href="<?= esc($baseUrl) ?>/<?= (int) $item->id ?>"><?= (int) $item->id ?></a></td>
            <td><?= esc($item->title) ?></td>
            <td><?= esc($item->created_at) ?></td>
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
            <a href="<?= esc($baseUrl) ?>?page=<?= (int) $pagination['previousPage'] ?>">← Previous</a>
        <?php else: ?>
            <span class="disabled">← Previous</span>
        <?php endif; ?>
        <span class="page-current">Page <?= (int) $pagination['currentPage'] ?> / <?= (int) $pagination['lastPage'] ?></span>
        <?php if ($pagination['hasNext']): ?>
            <a href="<?= esc($baseUrl) ?>?page=<?= (int) $pagination['nextPage'] ?>">Next →</a>
        <?php else: ?>
            <span class="disabled">Next →</span>
        <?php endif; ?>
    </span>
</div>
<?= $this->endSection() ?>