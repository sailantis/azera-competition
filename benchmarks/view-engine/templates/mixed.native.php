<?php ob_start();
// Native PHP mixed page: 20 flat variables plus 20 rows, every value carrying
// HTML-special characters so the escape path does real work. Written plainly, so
// a difference between engines is about the engine and not about PHP fluency.
echo $this->renderPartial('partials/header', ['title' => $title, 'items' => $items]);
?>
<dl class="settings">
    <dt>theme</dt>
    <dd data-value="<?php echo esc_html($theme); ?>"><?php echo esc_html($theme); ?></dd>
    <dt>locale</dt>
    <dd data-value="<?php echo esc_html($locale); ?>"><?php echo esc_html($locale); ?></dd>
    <dt>region</dt>
    <dd data-value="<?php echo esc_html($region); ?>"><?php echo esc_html($region); ?></dd>
    <dt>currency</dt>
    <dd data-value="<?php echo esc_html($currency); ?>"><?php echo esc_html($currency); ?></dd>
    <dt>plan</dt>
    <dd data-value="<?php echo esc_html($plan); ?>"><?php echo esc_html($plan); ?></dd>
    <dt>channel</dt>
    <dd data-value="<?php echo esc_html($channel); ?>"><?php echo esc_html($channel); ?></dd>
    <dt>tier</dt>
    <dd data-value="<?php echo esc_html($tier); ?>"><?php echo esc_html($tier); ?></dd>
    <dt>status</dt>
    <dd data-value="<?php echo esc_html($status); ?>"><?php echo esc_html($status); ?></dd>
    <dt>build</dt>
    <dd data-value="<?php echo esc_html($build); ?>"><?php echo esc_html($build); ?></dd>
    <dt>stage</dt>
    <dd data-value="<?php echo esc_html($stage); ?>"><?php echo esc_html($stage); ?></dd>
    <dt>cluster</dt>
    <dd data-value="<?php echo esc_html($cluster); ?>"><?php echo esc_html($cluster); ?></dd>
    <dt>node</dt>
    <dd data-value="<?php echo esc_html($node); ?>"><?php echo esc_html($node); ?></dd>
    <dt>release</dt>
    <dd data-value="<?php echo esc_html($release); ?>"><?php echo esc_html($release); ?></dd>
    <dt>subtitle</dt>
    <dd data-value="<?php echo esc_html($subtitle); ?>"><?php echo esc_html($subtitle); ?></dd>
    <dt>tagline</dt>
    <dd data-value="<?php echo esc_html($tagline); ?>"><?php echo esc_html($tagline); ?></dd>
    <dt>generated</dt>
    <dd data-value="<?php echo esc_html($generated); ?>"><?php echo esc_html($generated); ?></dd>
    <dt>owner</dt>
    <dd data-value="<?php echo esc_html($owner); ?>"><?php echo esc_html($owner); ?></dd>
    <dt>checksum</dt>
    <dd data-value="<?php echo esc_html($checksum); ?>"><?php echo esc_html($checksum); ?></dd>
</dl>
<p class="total" data-value="<?php echo esc_html($total); ?>"><?php echo esc_html($total); ?></p>
<p class="count" data-value="<?php echo esc_html($rowCount); ?>"><?php echo esc_html($rowCount); ?></p>
<table>
    <?php foreach ($items as $idx => $row): ?>
        <tr<?php if ($idx % 2 === 0) 
            echo ' class="even"'; ?>>
            <td><?php echo esc_html($row['id']); ?></td>
            <td><?php echo esc_html(mb_strtoupper($row['label'])); ?></td>
            <td data-sku="<?php echo esc_html($row['sku']); ?>"><?php echo esc_html($row['sku']); ?></td>
            <td><?php echo esc_html($row['city']); ?></td>
            <td><?php echo esc_html($row['category']); ?></td>
            <td><?php echo esc_html($row['status']); ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php $content = ob_get_clean();
echo $this->renderPartial('layouts/main', ['title' => $title, 'content' => $content]);