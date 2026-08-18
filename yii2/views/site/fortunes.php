<?php

use yii\helpers\Html;

?>
<!DOCTYPE html>
<html>
<head><title>Fortunes</title></head>
<body>
<table>
    <tr><th>id</th><th>message</th></tr>
    <?php foreach ($fortunes as $fortune): ?>
    <tr>
        <td><?= Html::encode($fortune['id']) ?></td>
        <td><?= Html::encode($fortune['message']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
