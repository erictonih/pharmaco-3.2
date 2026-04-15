<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accès refusé — DVSSER</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{
    min-height:100vh;display:flex;align-items:center;justify-content:center;
    background:#f0f4f9;font-family:'DM Sans',sans-serif;
  }
  .card{
    background:#fff;border-radius:24px;padding:56px 48px;
    text-align:center;max-width:420px;width:90%;
    box-shadow:0 8px 40px rgba(0,0,0,.08);
  }
  .icon{font-size:4rem;margin-bottom:20px}
  h1{font-family:'Syne',sans-serif;font-size:1.6rem;color:#0a1628;margin-bottom:12px}
  p{color:#64748b;line-height:1.6;font-size:.95rem;margin-bottom:28px}
  .role-badge{
    display:inline-block;background:#fff4e6;color:#c47d00;
    border:1px solid #fcd29e;border-radius:8px;
    padding:4px 12px;font-size:.8rem;font-weight:600;margin-bottom:24px;
  }
  .btn{
    display:inline-block;background:#0a6cbc;color:#fff;
    padding:12px 28px;border-radius:12px;text-decoration:none;
    font-weight:600;font-size:.9rem;transition:background .2s;
  }
  .btn:hover{background:#0556a4}
  .btn-ghost{
    display:inline-block;color:#64748b;padding:12px 20px;
    text-decoration:none;font-size:.85rem;margin-left:8px;
  }
</style>
</head>
<body>
<div class="card">
  <div class="icon">🔒</div>
  <div class="role-badge">Accès restreint</div>
  <h1>Page non autorisée</h1>
  <p>Votre rôle <strong><?= htmlspecialchars($_SESSION['role'] ?? 'inconnu') ?></strong>
     ne dispose pas des droits nécessaires pour accéder à cette section.</p>
  <a href="dashboard.php" class="btn">← Tableau de bord</a>
  <a href="javascript:history.back()" class="btn-ghost">Retour</a>
</div>
</body>
</html>
