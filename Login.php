<?php
session_start(); // Démarrer la session

// Afficher le message d'erreur s'il est présent
if (isset($_SESSION['error'])) {
    echo '<p style="color: red;">' . $_SESSION['error'] . '</p>';
    unset($_SESSION['error']); //Supprimer le message d'erreur de la session
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Connexion</title>
  <link rel="icon" href="sary/dvsser.jpg" type="image/jpg">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="bootstrap.min.css">
	<link rel="stylesheet" href="style.css">
  <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/scripts-page.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 0;
      overflow: hidden;
      background: radial-gradient(ellipse at bottom, #0a0a1f 0%, #000000 100%);
    }

    .stars, .stars2, .stars3 {
      position: absolute;
      top: 0;
      left: 0;
      width: 1px;
      height: 1px;
      background: transparent;
      z-index: -1;
    }

    .stars {
      box-shadow:
        100px 200px #e0e0ff,
        300px 400px #e0e0ff,
        500px 100px #e0e0ff,
        700px 300px #e0e0ff,
        900px 600px #e0e0ff,
        200px 700px #e0e0ff,
        400px 800px #e0e0ff,
        600px 900px #e0e0ff,
        800px 200px #e0e0ff,
        1000px 500px #e0e0ff;
      animation: animStar 50s linear infinite;
    
    }

    .stars2 {
      box-shadow:
        150px 250px #ccccff,
        350px 450px #ccccff,
        550px 150px #ccccff,
        750px 350px #ccccff,
        950px 650px #ccccff,
        250px 750px #ccccff,
        450px 850px #ccccff,
        650px 950px #ccccff,
        850px 250px #ccccff,
        1050px 550px #ccccff;
      animation: animStar 80s linear infinite;
    
    }

    .stars3 {
      box-shadow:
        50px 150px #b3b3ff,
        250px 350px #b3b3ff,
        450px 50px #b3b3ff,
        650px 250px #b3b3ff,
        850px 550px #b3b3ff,
        150px 650px #b3b3ff,
        350px 750px #b3b3ff,
        550px 850px #b3b3ff,
        750px 150px #b3b3ff,
        950px 450px #b3b3ff;
      animation: animStar 100s linear infinite;
    }

    @keyframes animStar {
      from {
        transform: translateY(0px);
      }
      to {
        transform: translateY(-2000px);
      }
    }

    .supernova {
      position: absolute;
      width: 4px;
      height: 4px;
      border-radius: 50%;
      background: radial-gradient(circle, #ffffff, #ffffcc, #ffcc99, #ff9999);
      animation: supernovaAnim 4s ease-in-out infinite alternate;
      top: 500px;
      left: 600px;
      z-index: -1;
    }

    @keyframes supernovaAnim {
      0% { transform: scale(1); opacity: 0.8; }
      50% { transform: scale(3); opacity: 1; }
      100% { transform: scale(1); opacity: 0.8; }
    }

    .nebula {
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(255, 100, 200, 0.2), transparent 70%);
      filter: blur(60px);
      animation: floatNebula 60s ease-in-out infinite alternate;
      z-index: -1;
    }

    .nebula:nth-child(5) {
      top: 200px;
      left: 300px;
    }

    .nebula:nth-child(6) {
      top: 600px;
      left: 800px;
    }

    @keyframes floatNebula {
      0% { transform: scale(1) translateY(0); }
      50% { transform: scale(1.2) translateY(-20px); }
      100% { transform: scale(1) translateY(0); }
    }
  </style>
</head>
<body>
<div class="stars"></div>
  <div class="stars2"></div>
  <div class="stars3"></div>
  <div class="supernova"></div>
  <div class="nebula"></div>
  <div class="nebula"></div>
<h1 style="text-decoration-color: green;">SURVEILLANCE AU NIVEAU PHARMACIE<br>
<img src="sary\icone1.png"
style="text-align: center;
width: 70px;
height: 70px;
border-radius: 10px;" id="myImage" name="myImage">
</h1>  
<script>
  function mdp(){
    alert("veuiller contacter votre administrateur système<br> 034 03 887 72");
     window.location.href ="login.php";
  }
  </script>
  <form method="post" action="utilisateur.php" style="margin-top: 50px;">
    <label for="username">Nom d'utilisateur:</label>
    <input type="text" id="username" name="username"><br><br>
    <label for="password">Mot de passe:</label>
    <input type="password" id="password" name="password">
    <a href="#" style="text-align: right;" id="mdpoublier" onclick="mdp()">mot de passe oublier?</a>
    <br><br>
    <input type="submit" value="Se connecter">
    
  </form>
  <br><br>
  <footer>
  <img src= "sary\Logo.png" alt="Logo-Ministere">
  <p> 
©<span id="currentYear"></span>
  Ministère de la Santé Pulblidue de Madagascar<br>
<br>Direction de la Veille Sanitaire, de la Surveillance Epidémiologique et Riposte</p>
    <p><a href="#">Mentions légales</a> | <a href="#">Politique de confidentialité</a></p>
</footer>
</body>
</html>
