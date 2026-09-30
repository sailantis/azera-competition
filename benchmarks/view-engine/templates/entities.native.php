<?php ob_start();
// Native PHP entities page: object property access, a nested object, a nullable
// property and a list of strings inside an object. The PHP is written plainly
// (no null-coalescing shortcut beyond ??), so the comparison is about the
// engines' property access rather than about PHP syntax fluency.
echo $this->renderPartial('partials/header', ['title' => $title, 'items' => $items]);
?>
<ul>
    <?php foreach ($items as $i => $item): ?>
        <li<?php if ($i % 2 === 0)
            echo ' class="even"'; ?>>
            <span class="name"><?php echo esc_html(mb_strtoupper($item->label)); ?></span>
            <span class="id" data-id="<?php echo esc_html($item->id); ?>"><?php echo esc_html($user->nickname ?? 'anonymous'); ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<p class="city"><?php echo esc_html($user->address->city); ?>, <?php echo esc_html($user->address->country); ?></p>
<p class="roles">
    <?php foreach ($user->roles as $role): ?>
        <span class="role"><?php echo esc_html($role); ?></span>
    <?php endforeach; ?>
</p>
<?php $content = ob_get_clean();
echo $this->renderPartial('layouts/main', ['title' => $title, 'content' => $content]);