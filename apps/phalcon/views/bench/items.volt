<header>
    <h1>Items</h1>
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
        <?php foreach ($items as $idx => $item) { ?>
        <tr <?php if ($idx % 2 == 0) { ?>class="even"<?php } ?>>
            <td><a href="<?= $baseUrl ?>/<?= $item->id ?>"><?= $item->id ?></a></td>
            <td><?= htmlspecialchars($item->title, ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($item->created_at, ENT_QUOTES) ?></td>
            <td>
                <?php if ($item->id % 100 == 0) { ?>
                <span class="badge">Pinned</span>
                <?php } elseif ($item->id % 10 == 0) { ?>
                <span class="badge">Featured</span>
                <?php } else { ?>
                <span class="badge">Standard</span>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<div class="pagination">
    <span class="page-info">
        Showing <?= $pagination['firstItem'] ?>–<?= $pagination['lastItem'] ?>
        of <?= $pagination['totalItems'] ?> items
    </span>
    <span class="page-nav">
        <?php if ($pagination['hasPrevious']) { ?>
        <a href="<?= $baseUrl ?>?page=<?= $pagination['previousPage'] ?>">← Previous</a>
        <?php } else { ?>
        <span class="disabled">← Previous</span>
        <?php } ?>
        <span class="page-current">Page <?= $pagination['currentPage'] ?> / <?= $pagination['lastPage'] ?></span>
        <?php if ($pagination['hasNext']) { ?>
        <a href="<?= $baseUrl ?>?page=<?= $pagination['nextPage'] ?>">Next →</a>
        <?php } else { ?>
        <span class="disabled">Next →</span>
        <?php } ?>
    </span>
</div>
