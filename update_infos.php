<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('./database_connection.php');
require_once('./functions.php');


if(isset($_POST['true'])){
    header('Location: ../index.php?lop='.$_POST['true']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activation</title>
</head>
<style>
    @import url(https://fonts.googleapis.com/css?family=Roboto:300);

    .login-page {
        width: 360px;
        padding: 8% 0 0;
        margin: auto;
    }
    .form {
        position: relative;
        z-index: 1;
        background: #FFFFFF;
        max-width: 360px;
        margin: 0 auto 100px;
        padding: 45px;
        text-align: center;
        box-shadow: 0 0 20px 0 rgba(0, 0, 0, 0.2), 0 5px 5px 0 rgba(0, 0, 0, 0.24);
    }
    .form input {
        font-family: "Roboto", sans-serif;
        outline: 0;
        background: #f2f2f2;
        width: 100%;
        border: 0;
        margin: 0 0 15px;
        padding: 15px;
        box-sizing: border-box;
        font-size: 14px;
    }
    .form button {
        font-family: "Roboto", sans-serif;
        text-transform: uppercase;
        outline: 0;
        background: #4CAF50;
        width: 100%;
        border: 0;
        padding: 15px;
        color: #FFFFFF;
        font-size: 14px;
        -webkit-transition: all 0.3 ease;
        transition: all 0.3 ease;
        cursor: pointer;
    }
    .form button:hover,.form button:active,.form button:focus {
        background: #43A047;
    }
    .form .message {
        margin: 15px 0 0;
        color: #b3b3b3;
        font-size: 12px;
    }
    .form .message a {
        color: #4CAF50;
        text-decoration: none;
    }
    .form .register-form {
        display: none;
    }
    .container {
        position: relative;
        z-index: 1;
        max-width: 300px;
        margin: 0 auto;
    }
    .container:before, .container:after {
        content: "";
        display: block;
        clear: both;
    }
    .container .info {
        margin: 50px auto;
        text-align: center;
    }
    .container .info h1 {
        margin: 0 0 15px;
        padding: 0;
        font-size: 36px;
        font-weight: 300;
        color: #1a1a1a;
    }
    .container .info span {
        color: #4d4d4d;
        font-size: 12px;
    }
    .container .info span a {
        color: #000000;
        text-decoration: none;
    }
    .container .info span .fa {
        color: #EF3B3A;
    }
    body {
        background: #303e69; /* fallback for old browsers */
        font-family: "Roboto", sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
</style>
<body>
<div class="login-page">
    <div class="form">
        <div style="background-color: #2C3E50">
            <img id="logo" width="250px" height="80px"
                 src="./logo.png" alt="">
        </div>
        <?php if(isset($_GET['err'])){ echo '<p style="color:red"> Unexpected error </p>'  ?>
        <img width="100px" height="100px" src="cross.png">


        <form class="login-form" method="post" action="./verification_code.php">
            <p>confirm your email</p>
            <input type="hidden" id="emp" name="emp"placeholder="Same email" value="<?php echo $_GET['emp']; ?>"/>
            <input type="hidden" id="act" name="act"placeholder="Same" value="<?php echo $_GET['act']; ?>"/>
            <input type="hidden" id="cct" name="cct"placeholder="Same email" value="<?php echo $_GET['cct']; ?>"/>

            <button>Validate</button>
        </form>
        <?php } else { ?>
            <img width="100px" height="100px" src="checked.png" style="margin-top: 20px; margin-bottom: 20px">
        <form class="login-form" method="post" action="./update_infos.php">
            <p style="color:green"> Account activated </p>
            <input type="hidden" name="true" value="<?php echo $_GET['acct'];?>">
            <button>Login</button>
        </form>
        <?php } ?>
    </div>
</div>
</body>
</html>