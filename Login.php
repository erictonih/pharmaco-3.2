<?php
session_start();
if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <title>Connexion — DVSSER</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="sary/dvsser.jpg" type="image/jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    :root {
        --accent: #0a6cbc;
        --accent-soft: #e8f2fb;
        --text-primary: #0d1b2a;
        --text-secondary: #4a5568;
        --text-muted: #8a99aa;
        --border: #e2eaf2;
        --bg: #f0f4f8;
        --bg-card: #ffffff;
    }
    [data-theme="dark"] {
        --accent: #3b9ede;
        --accent-soft: #1a2f45;
        --text-primary: #e8f0fb;
        --text-secondary: #a0b4cc;
        --text-muted: #5a7080;
        --border: #1e2e42;
        --bg: #0f1923;
        --bg-card: #1a2535;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DM Sans', sans-serif;
        background: radial-gradient(ellipse at bottom, #0a0a1f 0%, #061525 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow: hidden;
    }

    /* Étoiles */
    .stars, .stars2 {
        position: fixed; top:0; left:0; width:1px; height:1px; z-index:0;
        background:transparent;
        animation: animStar 60s linear infinite;
    }
    .stars {
        box-shadow: 120px 200px #e0e0ff, 350px 80px #e0e0ff, 600px 300px #e0e0ff,
                    900px 150px #e0e0ff, 200px 500px #e0e0ff, 700px 600px #e0e0ff,
                    400px 750px #e0e0ff, 1100px 400px #e0e0ff, 50px 900px #e0e0ff,
                    850px 850px #e0e0ff, 1300px 200px #e0e0ff, 1500px 650px #e0e0ff;
    }
    .stars2 {
        box-shadow: 80px 350px #b3b3ff, 450px 150px #b3b3ff, 700px 450px #b3b3ff,
                    1000px 250px #b3b3ff, 300px 650px #b3b3ff, 550px 900px #b3b3ff,
                    1200px 100px #b3b3ff, 150px 800px #b3b3ff;
        animation-duration: 90s;
    }
    @keyframes animStar {
        from { transform: translateY(0); }
        to   { transform: translateY(-1000px); }
    }

    .login-wrap {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 420px;
    }

    .login-card {
        background: rgba(26, 37, 53, 0.92);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(59, 158, 222, 0.2);
        border-radius: 20px;
        padding: 40px 36px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }

    .login-logo {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        margin-bottom: 32px;
        text-align: center;
    }

    .login-logo img {
        width: 64px; height: 64px;
        object-fit: contain;
        border-radius: 16px;
        background: rgba(59,158,222,0.15);
        padding: 10px;
    }

    .login-logo .app-name {
        font-family: 'Syne', sans-serif;
        font-weight: 800;
        font-size: 1.3rem;
        color: #3b9ede;
        letter-spacing: -0.02em;
    }

    .login-logo .app-sub {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.45);
        line-height: 1.4;
    }

    .error-msg {
        background: rgba(229,83,83,0.15);
        border: 1px solid rgba(229,83,83,0.3);
        border-radius: 10px;
        padding: 10px 14px;
        color: #f87171;
        font-size: 0.85rem;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: rgba(255,255,255,0.5);
        margin-bottom: 6px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .form-input {
        width: 100%;
        padding: 12px 14px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 10px;
        color: #e8f0fb;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.9rem;
        transition: border-color 0.2s, box-shadow 0.2s;
        outline: none;
    }

    .form-input:focus {
        border-color: #3b9ede;
        box-shadow: 0 0 0 3px rgba(59,158,222,0.15);
    }

    .form-input::placeholder { color: rgba(255,255,255,0.25); }

    .forgot-link {
        display: block;
        text-align: right;
        font-size: 0.78rem;
        color: rgba(59,158,222,0.7);
        text-decoration: none;
        margin-top: -8px;
        margin-bottom: 22px;
        transition: color 0.2s;
    }
    .forgot-link:hover { color: #3b9ede; }

    .submit-btn {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #0a6cbc, #3b9ede);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-family: 'Syne', sans-serif;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 20px rgba(10,108,188,0.4);
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 28px rgba(10,108,188,0.55);
    }

    .theme-toggle-login {
        position: fixed;
        top: 16px; right: 16px;
        z-index: 20;
        width: 38px; height: 38px;
        border-radius: 9px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(26,37,53,0.8);
        backdrop-filter: blur(8px);
        color: rgba(255,255,255,0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.2s;
    }
    .theme-toggle-login:hover { border-color: #3b9ede; color: #3b9ede; }

    .login-footer {
        text-align: center;
        margin-top: 28px;
        font-size: 0.72rem;
        color: rgba(255,255,255,0.25);
        line-height: 1.6;
    }
    </style>
</head>
<body>

<div class="stars"></div>
<div class="stars2"></div>

<button class="theme-toggle-login" id="themeBtn" title="Changer le thème">
    <i class="bi bi-moon-stars" id="themeIcon"></i>
</button>

<div class="login-wrap">
    <div class="login-card">

        <div class="login-logo">
            <img src="sary/icone1.png" alt="DVSSER">
            <span class="app-name">DVSSER</span>
            <span class="app-sub">Surveillance au niveau pharmacie<br>Ministère de la Santé Publique de Madagascar</span>
        </div>

        <?php if (!empty($error_msg)): ?>
        <div class="error-msg">
            <i class="bi bi-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
        <?php endif; ?>

        <form method="post" action="utilisateur.php">

            <div class="form-group">
                <label class="form-label" for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" class="form-input"
                       placeholder="Entrez votre identifiant" required autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="Entrez votre mot de passe" required autocomplete="current-password">
            </div>

            <a href="#" class="forgot-link" onclick="alert('Veuillez contacter votre administrateur système : 034 03 887 72'); return false;">
                Mot de passe oublié ?
            </a>

            <button type="submit" class="submit-btn">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>

        </form>

    </div>

    <div class="login-footer">
        &copy;<span id="yr"></span> Ministère de la Santé Publique de Madagascar<br>
        Direction de la Veille Sanitaire, de la Surveillance Épidémiologique et Riposte
    </div>
</div>

<script>
document.getElementById('yr').textContent = new Date().getFullYear();
var html = document.documentElement;
var btn  = document.getElementById('themeBtn');
var icon = document.getElementById('themeIcon');
var t = localStorage.getItem('dvsser-theme') || 'dark';
html.setAttribute('data-theme', t);
icon.className = t==='dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
btn.addEventListener('click', function(){
    var next = html.getAttribute('data-theme')==='light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    localStorage.setItem('dvsser-theme', next);
    icon.className = next==='dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
});
</script>
</body>
</html>
