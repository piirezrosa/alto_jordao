<?php
session_start();
session_destroy(); // Limpa todos os dados da sessão
header("Location: index.php"); // Volta para a página inicial
exit();
?>