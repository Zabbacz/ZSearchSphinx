<?php
defined('_JEXEC') or die('Restricted access');

if (empty($docs) || !is_array($docs)) {
    return;
}

// Poslední prvek v $docs má být total_array podle helperu
$lastIndex = count($docs) - 1;
if ($lastIndex < 0 || !isset($docs[$lastIndex]['total_found'])) {
    return;
}

$summary = $docs[$lastIndex];
$total = (int) ($summary['total'] ?? 0);
$offset = (int) ($summary['offset'] ?? 24);
$current = (int) ($summary['current'] ?? 1);
$query = isset($summary['query']) ? urlencode($summary['query']) : '';
$pageCount = ($offset > 0) ? (int) ceil($total / $offset) : 0;
$url = 'obchod/';

if ($pageCount <= 1) {
    return;
}

$range = 3;
$firstPage = 1;
$lastPage = $pageCount;
$previous = ($current > 1) ? $current - 1 : null;
$next = ($current < $pageCount) ? $current + 1 : null;
?>
<div class="paginationControl">
  <!-- First page link -->
  <?php if ($previous): ?>
    <a href="<?php echo $url; ?>?search_box=<?php echo $query; ?>&start=<?php echo ($firstPage - 1) * $offset; ?>">
      <i>◀◀</i>
    </a> |
  <?php endif; ?>

  <!-- Previous page link -->
  <?php if ($previous): ?>
    <a href="<?php echo $url; ?>?search_box=<?php echo $query; ?>&start=<?php echo ($previous - 1) * $offset; ?>">
      <i>◀</i>
    </a> |
  <?php endif; ?>

  <!-- Numbered page links -->
  <?php for ($page = max(1, $current - $range); $page <= min($pageCount, $current + $range); $page++): ?>
    <?php if ($page !== $current): ?>
      <a href="<?php echo $url; ?>?search_box=<?php echo $query; ?>&start=<?php echo ($page - 1) * $offset; ?>">
        <?php echo $page; ?>
      </a> |
    <?php else: ?>
      <strong><?php echo $page; ?></strong> |
    <?php endif; ?>
  <?php endfor; ?>

  <!-- Next page link -->
  <?php if ($next): ?>
    <a href="<?php echo $url; ?>?search_box=<?php echo $query; ?>&start=<?php echo ($next - 1) * $offset; ?>">
      <i>▶</i>
    </a> |
  <?php endif; ?>

  <!-- Last page link -->
  <?php if ($next): ?>
    <a href="<?php echo $url; ?>?search_box=<?php echo $query; ?>&start=<?php echo ($lastPage - 1) * $offset; ?>">
      <i>▶▶</i>
    </a>
  <?php endif; ?>
</div>
