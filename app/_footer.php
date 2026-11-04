    </div><!-- /.dv-content -->
    <footer class="dv-footer">
        <div class="dv-footer-brand">
            <img src="sary/logo-ministere-sante.jpg" alt="Logo Ministère de la Santé">
            <div class="dv-footer-text">&copy;<span id="dvYear"></span> Ministère de la Santé Publique de Madagascar<br>Direction de la Veille Sanitaire, de la Surveillance Épidémiologique et Riposte</div>
        </div>
    </footer>
</div>
<script>
(function(){
  document.getElementById('dvYear').textContent = new Date().getFullYear();
  var html=document.documentElement,btn=document.getElementById('dvThemeBtn'),icon=document.getElementById('dvThemeIcon');
  function setIcon(t){icon.className=t==='dark'?'bi bi-sun':'bi bi-moon-stars';}
  var saved='light';try{saved=localStorage.getItem('dvsser-theme')||'light';}catch(e){}
  html.setAttribute('data-theme',saved);setIcon(saved);
  btn.addEventListener('click',function(){var n=html.getAttribute('data-theme')==='light'?'dark':'light';html.setAttribute('data-theme',n);try{localStorage.setItem('dvsser-theme',n);}catch(e){}setIcon(n);window.dispatchEvent(new Event('themechange'));});
  var sb=document.getElementById('dvSidebar'),ov=document.getElementById('dvOverlay'),hb=document.getElementById('dvHamburger');
  if(hb)hb.addEventListener('click',function(){sb.classList.toggle('open');ov.classList.toggle('open');});
  if(ov)ov.addEventListener('click',function(){sb.classList.remove('open');ov.classList.remove('open');});
})();
</script>
</body>
</html>
