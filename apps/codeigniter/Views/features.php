<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<h1>Enterprise Feature Demos</h1>

<p>
    These endpoints exercise CodeIgniter 4's native enterprise subsystems
    (cache service, Events, Validation, DBQuery event)
    on a real CI4 app — without touching the production sailantis-homepage.
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
                <a href="<?= esc($feature['url']) ?>"><?= esc($feature['title']) ?></a>
            </td>
            <td><?= esc($feature['desc']) ?></td>
        </tr>
    <?php endforeach; ?>
</tbody>
</table>

<p class="meta">
    All endpoints return JSON for easy inspection. Click a link to try one.
</p>
<?= $this->endSection() ?>