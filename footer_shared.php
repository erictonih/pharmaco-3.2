    <!-- FIN .dv-content (ouvert dans la page) -->

    <!-- FOOTER -->
    <footer class="dv-footer">
        <div class="dv-footer-brand">
            <img src="sary/logo-ministere-sante.jpg" alt="Logo Ministère de la Santé">
            <div class="dv-footer-text">
                &copy;<span id="dvYear"></span> Ministère de la Santé Publique de Madagascar<br>
                Direction de la Veille Sanitaire, de la Surveillance Épidémiologique et Riposte
            </div>
        </div>
        <div class="dv-footer-links">
            <a href="#">Mentions légales</a>
            <a href="#">Confidentialité</a>
        </div>
    </footer>

</div><!-- /.dv-main -->

<!-- Scripts partagés -->
<script>
(function(){
    // Année
    document.getElementById('dvYear').textContent = new Date().getFullYear();

    // Thème
    var html = document.documentElement;
    var btn  = document.getElementById('dvThemeBtn');
    var icon = document.getElementById('dvThemeIcon');
    var saved = localStorage.getItem('dvsser-theme') || 'light';
    html.setAttribute('data-theme', saved);
    setIcon(saved);

    btn.addEventListener('click', function(){
        var next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('dvsser-theme', next);
        setIcon(next);
    });

    function setIcon(t){
        icon.className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }

    // Sidebar mobile
    var sidebar = document.getElementById('dvSidebar');
    var overlay = document.getElementById('dvOverlay');
    var hbg     = document.getElementById('dvHamburger');
    if(hbg){
        hbg.addEventListener('click', function(){
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
    }
    if(overlay){
        overlay.addEventListener('click', function(){
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }
})();
</script>
</body>
</html>
