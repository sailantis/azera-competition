<?php ob_start(); ?>
<?php echo $this->insert('partials/header', ['title' => $title, 'items' => $items]); ?>
<dl class="settings">
    <dt>theme</dt>
    <dd data-value="<?php echo $this->e($theme); ?>"><?php echo $this->e($theme); ?></dd>
    <dt>locale</dt>
    <dd data-value="<?php echo $this->e($locale); ?>"><?php echo $this->e($locale); ?></dd>
    <dt>region</dt>
    <dd data-value="<?php echo $this->e($region); ?>"><?php echo $this->e($region); ?></dd>
    <dt>currency</dt>
    <dd data-value="<?php echo $this->e($currency); ?>"><?php echo $this->e($currency); ?></dd>
    <dt>plan</dt>
    <dd data-value="<?php echo $this->e($plan); ?>"><?php echo $this->e($plan); ?></dd>
    <dt>channel</dt>
    <dd data-value="<?php echo $this->e($channel); ?>"><?php echo $this->e($channel); ?></dd>
    <dt>tier</dt>
    <dd data-value="<?php echo $this->e($tier); ?>"><?php echo $this->e($tier); ?></dd>
    <dt>status</dt>
    <dd data-value="<?php echo $this->e($status); ?>"><?php echo $this->e($status); ?></dd>
    <dt>build</dt>
    <dd data-value="<?php echo $this->e($build); ?>"><?php echo $this->e($build); ?></dd>
    <dt>stage</dt>
    <dd data-value="<?php echo $this->e($stage); ?>"><?php echo $this->e($stage); ?></dd>
    <dt>cluster</dt>
    <dd data-value="<?php echo $this->e($cluster); ?>"><?php echo $this->e($cluster); ?></dd>
    <dt>node</dt>
    <dd data-value="<?php echo $this->e($node); ?>"><?php echo $this->e($node); ?></dd>
    <dt>release</dt>
    <dd data-value="<?php echo $this->e($release); ?>"><?php echo $this->e($release); ?></dd>
    <dt>subtitle</dt>
    <dd data-value="<?php echo $this->e($subtitle); ?>"><?php echo $this->e($subtitle); ?></dd>
    <dt>tagline</dt>
    <dd data-value="<?php echo $this->e($tagline); ?>"><?php echo $this->e($tagline); ?></dd>
    <dt>generated</dt>
    <dd data-value="<?php echo $this->e($generated); ?>"><?php echo $this->e($generated); ?></dd>
    <dt>owner</dt>
    <dd data-value="<?php echo $this->e($owner); ?>"><?php echo $this->e($owner); ?></dd>
    <dt>checksum</dt>
    <dd data-value="<?php echo $this->e($checksum); ?>"><?php echo $this->e($checksum); ?></dd>
</dl>
<p class="total" data-value="<?php echo $this->e($total); ?>"><?php echo $this->e($total); ?></p>
<p class="count" data-value="<?php echo $this->e($rowCount); ?>"><?php echo $this->e($rowCount); ?></p>
<table>
    <?php foreach ($items as $idx => $row): ?>
        <tr<?php if ($idx % 2 === 0) 
            echo ' class="even"'; ?>>
            <td><?php echo $this->e($row['id']); ?></td>
            <td><?php echo $this->e(mb_strtoupper($row['label'])); ?></td>
            <td data-sku="<?php echo $this->e($row['sku']); ?>"><?php echo $this->e($row['sku']); ?></td>
            <td><?php echo $this->e($row['city']); ?></td>
            <td><?php echo $this->e($row['category']); ?></td>
            <td><?php echo $this->e($row['status']); ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php $content = ob_get_clean();
echo $this->insert('layouts/main', ['title' => $title, 'content' => $content]); ?>