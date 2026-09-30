<?php ob_start(); ?>
<?php echo $this->insert('partials/header', ['title' => $title, 'items' => $items]); ?>
<ul>
    <?php foreach ($items as $i => $item): ?>
        <li<?php if ($i % 2 === 0)
            echo ' class="even"'; ?>>
            <span class="name"><?php echo $this->e(mb_strtoupper($item->label)); ?></span>
            <span class="id" data-id="<?php echo $this->e($item->id); ?>"><?php echo $this->e($user->nickname ?? 'anonymous'); ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<p class="city"><?php echo $this->e($user->address->city); ?>, <?php echo $this->e($user->address->country); ?></p>
<p class="roles">
    <?php foreach ($user->roles as $role): ?>
        <span class="role"><?php echo $this->e($role); ?></span>
    <?php endforeach; ?>
</p>
<?php $content = ob_get_clean();
echo $this->insert('layouts/main', ['title' => $title, 'content' => $content]); ?>