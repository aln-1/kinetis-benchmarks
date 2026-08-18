<?php

/**
 * @var array<int, array{id: int, message: string}> $fortunes
 */
?>
<!DOCTYPE html>
<html>
<head><title>Fortunes</title></head>
<body>
<table>
<tr><th>id</th><th>message</th></tr>
<?php foreach ($fortunes as $fortune): ?>
<tr><td><?= (int)$fortune['id'] ?></td><td><?= h($fortune['message']) ?></td></tr>
<?php endforeach; ?>
</table>
</body>
</html>
