<?php ob_start();
// Native PHP entities page over ARRAY data: identical structure and values to
// entities.native.php, but every access is an array KEY rather than an object
// property. Written as a separate template (not a conditional) so what is on the
// clock is the access mechanism and nothing else.
echo $this->renderPartial('partials/header', ['title' => $title, 'items' => $items]);
?>
<ul>
    <?php foreach ($items as $i => $item): ?>
        <li<?php if ($i % 2 === 0)
            echo ' class="even"'; ?>>
            <span class="name"><?php echo esc_html(mb_strtoupper($item['label'])); ?></span>
            <span class="id" data-id="<?php echo esc_html($item['id']); ?>"><?php echo esc_html($user['nickname'] ?? 'anonymous'); ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<p class="city"><?php echo esc_html($user['address']['city']); ?>, <?php echo esc_html($user['address']['country']); ?></p>
<p class="roles">
    <?php foreach ($user['roles'] as $role): ?>
        <span class="role"><?php echo esc_html($role); ?></span>
    <?php endforeach; ?>
</p>
<?php $content = ob_get_clean();
echo $this->renderPartial('layouts/main', ['title' => $title, 'content' => $content]);
