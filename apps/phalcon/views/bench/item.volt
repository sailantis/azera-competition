<?php if (!empty($flash)) { ?>
<div class="flash">Item <?= $flash ?> (id <?= $item->id ?>)</div>
<?php } ?>

<h1>Item <?= $item->id ?></h1>

<table>
    <tbody>
        <tr>
            <th>#</th>
            <td><?= $item->id ?></td>
        </tr>
        <tr>
            <th>Title</th>
            <td><?= htmlspecialchars($item->title, ENT_QUOTES) ?></td>
        </tr>
        <tr>
            <th>Created</th>
            <td><?= htmlspecialchars($item->created_at, ENT_QUOTES) ?></td>
        </tr>
    </tbody>
</table>

<p><a href="/items">← Back to items</a></p>
