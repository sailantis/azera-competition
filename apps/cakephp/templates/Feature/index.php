<?php
/**
 * @var \App\Cake\View\AppView $this
 * @var string $title
 * @var list<array{url: string, title: string, desc: string}> $features
 */
?>
<h1>Enterprise Feature Demos</h1>

<p>
    These endpoints exercise CakePHP 5's native enterprise subsystems
    (Cache pools, EventManager, Validation, driver query logging)
    on a real Cake app — without touching the production sailantis-homepage.
</p>

<table>
<thead>
    <tr>
        <th>Feature</th>
        <th>Description</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($features as $feature): ?>
        <tr>
            <td>
                <a href="<?= h($feature['url']) ?>"><?= h($feature['title']) ?></a>
            </td>
            <td><?= h($feature['desc']) ?></td>
        </tr>
    <?php endforeach; ?>
</tbody>
</table>

<p class="meta">
    All endpoints return JSON for easy inspection. Click a link to try one.
</p>