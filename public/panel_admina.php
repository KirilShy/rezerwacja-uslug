<?php
require __DIR__ . '/../includes/admin.php';

naglowekAdmina('Panel administratora');
?>
<p>Witaj, <?= e($_SESSION['user_imie']) ?>.</p>
<?php
stopkaAdmina();
