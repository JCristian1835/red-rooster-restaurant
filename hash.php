<?php
$password = "Abundance26!";
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
?>