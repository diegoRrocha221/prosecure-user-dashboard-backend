<?php
require_once('./database_connection.php');


function ocultarEmail($email) {
    // Verifique se o email é válido
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Divida o email em duas partes: nome de usuário e domínio
        list($nomeUsuario, $dominio) = explode('@', $email);

        // Obtenha o tamanho do nome de usuário
        $tamanhoNomeUsuario = strlen($nomeUsuario);

        // Mantenha os primeiros 2 caracteres do nome de usuário visíveis
        $parteVisivel = substr($nomeUsuario, 0, 2);

        // Preencha o restante com asteriscos
        $parteOculta = str_repeat('*', $tamanhoNomeUsuario - 2);

        // Recomponha o endereço de email oculto
        $emailOculto = $parteVisivel . $parteOculta . '@' . $dominio;

        return $emailOculto;
    } else {
        return 'Endereço de e-mail inválido';
    }
}

function getForm($username){

    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $sql = "SELECT email from users WHERE username = '$username'";
    $result = $conn->query($sql);
    $email = $result->fetch_assoc();
    $hiddenEmail = ocultarEmail($email['email']);
    echo $hiddenEmail;

}

?>