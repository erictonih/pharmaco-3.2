<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="dv-page-header"><h1><i class="bi bi-pencil-square" style="color:var(--accent);margin-right:8px"></i>Saisie de données</h1><div class="dv-breadcrumb"><a href="<?= site_url('/') ?>">Accueil</a><i class="bi bi-chevron-right"></i><span>Saisie de données</span></div></div>
<div class="dv-alert dv-alert-info"><i class="bi bi-info-circle"></i>Veuillez remplir les informations concernant le client. Les champs marqués <strong>*</strong> sont obligatoires.</div>
<?php if(session('errors')): ?><div class="notice error"><?= implode('<br>', array_map('esc', session('errors'))) ?></div><?php endif; ?>
<form method="post" action="<?= site_url('patients') ?>"><?= csrf_field() ?><div class="dv-card"><div class="dv-card-header"><i class="bi bi-person"></i>Informations du client</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px"><div class="dv-form-group"><label class="dv-label">Identifiant</label><input class="dv-input" name="identifiant" placeholder="Ex : 110923001" value="<?= esc(old('identifiant')) ?>"></div><div class="dv-form-group"><label class="dv-label">Nom <span style="color:#e55353">*</span></label><input class="dv-input" name="Nom" placeholder="Nom de famille" required value="<?= esc(old('Nom')) ?>"></div><div class="dv-form-group"><label class="dv-label">Prénom <span style="color:#e55353">*</span></label><input class="dv-input" name="Prenom" placeholder="Prénom(s)" required value="<?= esc(old('Prenom')) ?>"></div><div class="dv-form-group"><label class="dv-label">Âge <span style="color:#e55353">*</span></label><input class="dv-input" type="number" min="0" max="150" name="age" placeholder="Ex : 32" required value="<?= esc(old('age')) ?>"></div><div class="dv-form-group"><label class="dv-label">Genre <span style="color:#e55353">*</span></label><select class="dv-select" name="genre" required><option value="">— Sélectionner —</option><option value="Masculin">Masculin</option><option value="Feminin">Féminin</option><option value="Inconnue">Inconnu(e)</option></select></div><div class="dv-form-group"><label class="dv-label">Résidence <span style="color:#e55353">*</span></label><input class="dv-input" name="adresse" placeholder="Adresse / Quartier" required value="<?= esc(old('adresse')) ?>"></div><div class="dv-form-group"><label class="dv-label">Région</label><select class="dv-select" id="region"><option value="">— Sélectionner une région —</option></select></div><div class="dv-form-group"><label class="dv-label">District <span style="color:#e55353">*</span></label><select class="dv-select" id="district" name="district" required><option value="">— Sélectionner un district —</option></select></div></div><div class="dv-form-group"><label class="dv-label">Type de client <span style="color:#e55353">*</span></label><div class="dv-radio-group"><label class="dv-radio-label"><input type="radio" name="type_client" value="Ambulatoire" required> Ambulatoire</label><label class="dv-radio-label"><input type="radio" name="type_client" value="Hospitalisé"> Hospitalisé(e)</label></div></div></div><div style="display:flex;justify-content:flex-end;gap:12px"><a class="dv-btn dv-btn-outline" href="<?= site_url('/') ?>"><i class="bi bi-x-lg"></i>Annuler</a><button class="dv-btn dv-btn-primary"><i class="bi bi-send"></i>Enregistrer</button></div></form>
<script>
const regionSelect = document.getElementById('region');
const districtSelect = document.getElementById('district');

fetch('<?= site_url('api/regions') ?>')
  .then(response => response.ok ? response.json() : Promise.reject(response))
  .then(regions => regions.forEach(region => regionSelect.add(new Option(region.nom, region.id))))
  .catch(() => regionSelect.add(new Option('Impossible de charger les régions', '')));

regionSelect.addEventListener('change', () => {
  districtSelect.innerHTML = '<option value="">— Sélectionner un district —</option>';
  if (!regionSelect.value) return;
  fetch('<?= site_url('api/regions') ?>/' + encodeURIComponent(regionSelect.value) + '/districts')
    .then(response => response.ok ? response.json() : Promise.reject(response))
    .then(districts => districts.forEach(district => districtSelect.add(new Option(district.nom, district.nom))));
});
</script>
<?= $this->endSection() ?>
