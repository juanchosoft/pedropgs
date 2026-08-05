<style>
/* =========================================================
   PGS CENTRUM - SIDEBAR PREMIUM RESPONSIVE
   Solo diseño - No afecta permisos ni funcionalidad PHP
   ========================================================= */

:root{
  --pgs-red: #ef233c;
  --pgs-red-dark: #b80f28;
  --pgs-red-soft: rgba(239,35,60,.16);
  --pgs-black: #06080d;
  --pgs-black-2: #0b0f16;
  --pgs-white: #ffffff;
  --pgs-text: rgba(255,255,255,.92);
  --pgs-muted: rgba(255,255,255,.62);
  --pgs-border: rgba(255,255,255,.10);
  --pgs-card: rgba(255,255,255,.055);
  --pgs-card-hover: rgba(255,255,255,.095);
  --pgs-shadow-red: 0 16px 35px rgba(239,35,60,.22);
}

/* Fondo general del sidebar */
.deznav{
  background:
    radial-gradient(circle at 20% 0%, rgba(239,35,60,.28), transparent 35%),
    radial-gradient(circle at 100% 30%, rgba(255,255,255,.06), transparent 28%),
    linear-gradient(180deg, #180612 0%, #07090f 48%, #030509 100%) !important;
  border-right: 1px solid rgba(255,255,255,.08) !important;
  box-shadow: 16px 0 35px rgba(0,0,0,.22);
  overflow: hidden !important;
}

/* Contenedor interno del sidebar */
.deznav-scroll{
  height: 100%;
  overflow-y: auto !important;
  overflow-x: hidden !important;
  padding-bottom: 95px !important;
  scrollbar-width: thin;
  scrollbar-color: rgba(239,35,60,.55) transparent;
}

.deznav-scroll::-webkit-scrollbar{
  width: 6px;
}

.deznav-scroll::-webkit-scrollbar-track{
  background: transparent;
}

.deznav-scroll::-webkit-scrollbar-thumb{
  background: rgba(239,35,60,.55);
  border-radius: 999px;
}

/* Lista principal */
#menu.metismenu,
#menu{
  margin: 0 !important;
  padding: 14px 12px 110px 12px !important;
  list-style: none !important;
}

/* Separación de items */
#menu > li{
  margin: 7px 0 !important;
  list-style: none !important;
}

/* Enlaces principales */
#menu > li > a.ai-icon,
#menu > li > a{
  width: 100% !important;
  min-height: 56px !important;
  padding: 9px 14px !important;
  border-radius: 18px !important;

  display: flex !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 12px !important;

  color: var(--pgs-text) !important;
  background: transparent !important;
  border: 1px solid transparent !important;

  font-size: 14px !important;
  font-weight: 800 !important;
  line-height: 1.1 !important;
  letter-spacing: -.2px !important;
  text-decoration: none !important;

  position: relative !important;
  overflow: hidden !important;
  transition: all .22s ease !important;
}

/* Brillo suave al pasar */
#menu > li > a.ai-icon::before,
#menu > li > a::before{
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255,255,255,.08),
    transparent
  );
  transform: translateX(-120%);
  transition: transform .55s ease;
  pointer-events: none;
}

#menu > li > a.ai-icon:hover::before,
#menu > li > a:hover::before{
  transform: translateX(120%);
}

/* Hover principal */
#menu > li > a.ai-icon:hover,
#menu > li > a:hover{
  background: var(--pgs-card-hover) !important;
  border-color: var(--pgs-border) !important;
  color: #fff !important;
  transform: translateY(-1px);
}

/* Íconos principales */
#menu > li > a.ai-icon > i,
#menu > li > a > i{
  width: 42px !important;
  height: 42px !important;
  min-width: 42px !important;
  min-height: 42px !important;

  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;

  position: static !important;
  left: auto !important;
  top: auto !important;
  right: auto !important;
  bottom: auto !important;

  margin: 0 !important;
  padding: 0 !important;
  transform: none !important;

  border-radius: 15px !important;
  background: rgba(255,255,255,.07) !important;
  border: 1px solid rgba(255,255,255,.12) !important;

  color: rgba(255,255,255,.94) !important;
  font-size: 18px !important;
  line-height: 1 !important;

  transition: all .22s ease !important;
  flex: 0 0 42px !important;
}

/* Texto del menú */
#menu > li > a .nav-text{
  position: static !important;
  display: inline-flex !important;
  align-items: center !important;

  padding-left: 0 !important;
  margin-left: 0 !important;

  color: inherit !important;
  font-size: 14px !important;
  font-weight: 850 !important;
  line-height: 1.15 !important;

  white-space: normal !important;
  overflow-wrap: anywhere !important;
}

/* Activo principal */
#menu > li.mm-active > a,
#menu > li > a.active{
  background:
    linear-gradient(135deg, rgba(239,35,60,.30), rgba(255,255,255,.075)) !important;
  border-color: rgba(239,35,60,.38) !important;
  color: #fff !important;
  box-shadow: var(--pgs-shadow-red) !important;
}

/* Ícono activo */
#menu > li.mm-active > a > i,
#menu > li > a.active > i{
  background:
    linear-gradient(135deg, var(--pgs-red), var(--pgs-red-dark)) !important;
  border-color: rgba(255,255,255,.18) !important;
  color: #fff !important;
  box-shadow: 0 10px 24px rgba(239,35,60,.28) !important;
}

/* Barra roja izquierda del activo */
#menu > li.mm-active > a::after,
#menu > li > a.active::after{
  content: "";
  position: absolute;
  left: 0;
  top: 12px;
  bottom: 12px;
  width: 4px;
  border-radius: 999px;
  background: linear-gradient(180deg, #ff304f, #ff758f);
}

/* Flecha de submenús */
#menu a.has-arrow{
  padding-right: 38px !important;
}

#menu a.has-arrow:after{
  right: 16px !important;
  top: 50% !important;
  border-color: rgba(255,255,255,.78) !important;
  opacity: .9 !important;
  transform: translateY(-50%) rotate(0deg) !important;
  transition: all .22s ease !important;
}

#menu li.mm-active > a.has-arrow:after{
  transform: translateY(-50%) rotate(90deg) !important;
  border-color: #fff !important;
}

/* Submenús */
#menu ul{
  width: calc(100% - 6px) !important;
  margin: 8px 0 8px 6px !important;
  padding: 9px 8px 9px 15px !important;

  border-radius: 18px !important;
  background:
    linear-gradient(180deg, rgba(255,255,255,.055), rgba(255,255,255,.025)) !important;
  border: 1px solid rgba(255,255,255,.08) !important;

  box-shadow: inset 0 1px 0 rgba(255,255,255,.035);
  list-style: none !important;
  overflow: hidden !important;
}

/* Items del submenú */
#menu ul li{
  margin: 4px 0 !important;
  list-style: none !important;
}

/* Links del submenú */
#menu ul li a{
  min-height: 38px !important;
  padding: 10px 12px !important;
  border-radius: 13px !important;

  display: flex !important;
  align-items: center !important;
  gap: 9px !important;

  color: rgba(255,255,255,.80) !important;
  background: transparent !important;
  border: 1px solid transparent !important;

  font-size: 12.5px !important;
  font-weight: 800 !important;
  line-height: 1.2 !important;
  text-decoration: none !important;

  white-space: normal !important;
  overflow-wrap: anywhere !important;

  transition: all .18s ease !important;
}

/* Punto del submenú */
#menu ul li a::before{
  content: "";
  width: 8px !important;
  height: 8px !important;
  min-width: 8px !important;
  border-radius: 999px !important;
  background: rgba(255,255,255,.20) !important;
  box-shadow: 0 0 0 4px rgba(255,255,255,.025);
  transition: all .18s ease !important;
}

/* Hover submenú */
#menu ul li a:hover{
  color: #fff !important;
  background: rgba(255,255,255,.075) !important;
  border-color: rgba(255,255,255,.08) !important;
  transform: translateX(2px);
}

/* Activo submenú */
#menu ul li.mm-active > a,
#menu ul li a.active{
  color: #fff !important;
  background: rgba(239,35,60,.19) !important;
  border-color: rgba(239,35,60,.25) !important;
}

#menu ul li.mm-active > a::before,
#menu ul li a.active::before{
  background: linear-gradient(135deg, #ff304f, #ff758f) !important;
  box-shadow: 0 0 0 4px rgba(239,35,60,.10);
}

/* Evita que texto del footer o copyright se monte encima del menú */
.deznav .copyright,
.deznav .footer,
.deznav-footer,
.sidebar-footer{
  position: relative !important;
  z-index: 1 !important;
  padding: 14px 14px 18px !important;
  margin-top: 10px !important;
  color: rgba(255,255,255,.68) !important;
  font-size: 11px !important;
  line-height: 1.35 !important;
  text-align: center !important;
  background: linear-gradient(180deg, transparent, rgba(0,0,0,.22)) !important;
}

.sidebar-footer a{
  transition: transform .18s ease, box-shadow .18s ease;
}
.sidebar-footer a:hover{
  transform: translateY(-1px);
  box-shadow: 0 18px 32px rgba(225,29,46,.26);
}

/* Estilos para menú mini */
[data-sidebar-style="mini"] #menu{
  padding-left: 8px !important;
  padding-right: 8px !important;
}

[data-sidebar-style="mini"] #menu > li > a.ai-icon,
[data-sidebar-style="mini"] #menu > li > a{
  justify-content: center !important;
  padding: 10px !important;
}

[data-sidebar-style="mini"] #menu > li > a .nav-text{
  display: none !important;
}

[data-sidebar-style="mini"] #menu > li > a.has-arrow:after{
  display: none !important;
}

[data-sidebar-style="mini"] #menu ul{
  padding: 8px !important;
  margin-left: 0 !important;
}

/* Tablet */
@media (max-width: 1199px){
  #menu{
    padding: 12px 10px 115px 10px !important;
  }

  #menu > li > a.ai-icon,
  #menu > li > a{
    min-height: 54px !important;
    padding: 9px 12px !important;
  }

  #menu > li > a.ai-icon > i,
  #menu > li > a > i{
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    min-height: 40px !important;
    flex-basis: 40px !important;
  }
}

/* Celular */
@media (max-width: 768px){
  .deznav{
    max-width: 285px !important;
  }

  .deznav-scroll{
    padding-bottom: 130px !important;
  }

  #menu{
    padding: 12px 10px 135px 10px !important;
  }

  #menu > li{
    margin: 7px 0 !important;
  }

  #menu > li > a.ai-icon,
  #menu > li > a{
    min-height: 58px !important;
    padding: 10px 13px !important;
    border-radius: 18px !important;
    gap: 12px !important;
  }

  #menu > li > a.ai-icon > i,
  #menu > li > a > i{
    width: 43px !important;
    height: 43px !important;
    min-width: 43px !important;
    min-height: 43px !important;
    flex-basis: 43px !important;
    font-size: 18px !important;
  }

  #menu > li > a .nav-text{
    font-size: 14px !important;
  }

  #menu ul{
    width: 100% !important;
    margin: 8px 0 8px 0 !important;
    padding: 9px 8px !important;
    border-radius: 17px !important;
  }

  #menu ul li a{
    min-height: 42px !important;
    padding: 11px 12px !important;
    font-size: 12.8px !important;
  }

  .deznav .copyright,
  .deznav .footer,
  .deznav-footer,
  .sidebar-footer{
    font-size: 10.5px !important;
    padding-bottom: 22px !important;
  }
}

/* Celulares pequeños */
@media (max-width: 420px){
  .deznav{
    max-width: 270px !important;
  }

  #menu > li > a.ai-icon,
  #menu > li > a{
    min-height: 56px !important;
    padding: 9px 11px !important;
  }

  #menu > li > a.ai-icon > i,
  #menu > li > a > i{
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    min-height: 40px !important;
    flex-basis: 40px !important;
  }

  #menu > li > a .nav-text{
    font-size: 13px !important;
  }

  #menu ul li a{
    font-size: 12.2px !important;
  }
}

/* Mejora visual del overlay mobile si la plantilla lo usa */
.nav-control,
.hamburger{
  position: relative;
  z-index: 9999;
}

/* Evita desbordes raros */
#menu *,
.deznav *{
  box-sizing: border-box;
}
/* =========================================================
   FIX MOBILE - HAMBURGUESA SIEMPRE VISIBLE ENCIMA DEL BODY
   ========================================================= */

@media (max-width: 768px){

  /* Header siempre por encima del contenido */
  .header,
  .nav-header,
  .header-left,
  .nav-control{
    position: relative !important;
    z-index: 99999 !important;
  }

  /* Botón hamburguesa flotante y visible */
  .nav-control{
    width: 48px !important;
    height: 48px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    position: absolute !important;
    top: 28px !important;
    left: 76px !important;

    background: rgba(10, 12, 18, .92) !important;
    border: 1px solid rgba(255,255,255,.18) !important;
    border-radius: 14px !important;

    box-shadow: 0 12px 28px rgba(0,0,0,.35) !important;
    backdrop-filter: blur(10px) !important;

    cursor: pointer !important;
    overflow: visible !important;
  }

  /* Ícono hamburguesa */
  .hamburger{
    width: 26px !important;
    height: 22px !important;
    position: relative !important;
    z-index: 100000 !important;
  }

  .hamburger .line{
    width: 26px !important;
    height: 3px !important;
    display: block !important;
    margin: 5px auto !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    transition: all .25s ease !important;
  }

  /* Cuando se pasa o toca */
  .nav-control:hover,
  .nav-control:active{
    background: linear-gradient(135deg, #ef233c, #9b1025) !important;
    border-color: rgba(255,255,255,.28) !important;
    transform: translateY(-1px);
  }

  /* Evita que el body o hero se suba debajo del header */
  .content-body{
    position: relative !important;
    z-index: 1 !important;
    padding-top: 12px !important;
  }

  /* El contenido principal nunca debe tapar el menú */
  .page-titles,
  .container-fluid,
  .dashboard-header,
  .card,
  .welcome-card,
  .hero,
  .main-content{
    position: relative !important;
    z-index: 1 !important;
  }

  /* Header compacto en móvil */
  .header{
    min-height: 78px !important;
    height: 78px !important;
    overflow: visible !important;
  }

  .nav-header{
    min-height: 78px !important;
    height: 78px !important;
    overflow: visible !important;
  }
}
/* =========================================================
   FIX MOBILE - MENU ABIERTO POR ENCIMA DEL HEADER
   ========================================================= */

@media (max-width: 768px){

  /* Cuando el sidebar esté abierto, debe quedar por encima del header */
  .deznav{
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    z-index: 100200 !important;
    padding-top: 88px !important;
    overflow: visible !important;
  }

  /* El contenido interno empieza debajo de la parte superior */
  .deznav-scroll{
    height: calc(100vh - 88px) !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding-top: 12px !important;
    padding-bottom: 130px !important;
  }

  /* El menú no debe arrancar pegado arriba */
  #menu.metismenu,
  #menu{
    padding-top: 10px !important;
  }

  /* Header queda debajo del menú cuando está abierto */
  .header{
    z-index: 9990 !important;
  }

  .nav-header{
    z-index: 9991 !important;
  }

  /* Botón hamburguesa sigue visible encima de todo */
  .nav-control{
    z-index: 100500 !important;
  }

  .hamburger{
    z-index: 100501 !important;
  }

  /* Cuando el menú está abierto, el botón queda como cerrar */
  body.menu-toggle .nav-control,
  body.nav-open .nav-control,
  .menu-toggle .nav-control{
    background: rgba(10, 12, 18, .98) !important;
    border-color: rgba(255,255,255,.25) !important;
    box-shadow: 0 14px 32px rgba(0,0,0,.45) !important;
  }
}
/* =========================================================
   FIX FINAL MOBILE MENU PGS
   Corrige:
   - Menú no queda abierto permanentemente
   - Hamburguesa visible
   - Sidebar se puede abrir/cerrar
   - El header no tapa el menú
   ========================================================= */

@media (max-width: 768px){

  /* Header móvil */
  .header,
  .header--saas{
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 78px !important;
    min-height: 78px !important;
    z-index: 9000 !important;
    overflow: visible !important;
    background: rgba(12, 12, 16, .88) !important;
    backdrop-filter: blur(14px) !important;
    -webkit-backdrop-filter: blur(14px) !important;
    border-bottom: 1px solid rgba(225,29,46,.18) !important;
  }

  .nav-header,
  .nav-header--saas{
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 82px !important;
    height: 78px !important;
    min-height: 78px !important;
    z-index: 9100 !important;
    overflow: visible !important;
    background: rgba(12, 12, 16, .92) !important;
    border-right: 1px solid rgba(225,29,46,.22) !important;
  }

  /* Logo pequeño */
  .brand-logo--saas{
    height: 78px !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
  }

  .brand-logo--saas .brand-word{
    display: none !important;
  }

  .brand-logo--saas .brand-mark{
    display: grid !important;
    place-items: center !important;
    width: 46px !important;
    height: 46px !important;
    border-radius: 16px !important;
    background: rgba(255,255,255,.06) !important;
    border: 1px solid rgba(225,29,46,.30) !important;
    box-shadow: 0 12px 30px rgba(0,0,0,.28) !important;
  }

  .brand-logo--saas .logo-abbr{
    display: block !important;
    max-width: 28px !important;
    height: auto !important;
  }

  /* Hamburguesa siempre visible */
  .nav-control,
  .nav-control--saas{
    position: fixed !important;
    top: 15px !important;
    left: 94px !important;

    width: 50px !important;
    height: 50px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    z-index: 120000 !important;

    background: rgba(12, 12, 16, .96) !important;
    border: 1px solid rgba(255,255,255,.20) !important;
    border-radius: 16px !important;

    box-shadow: 0 14px 32px rgba(0,0,0,.38) !important;
    backdrop-filter: blur(12px) !important;
    -webkit-backdrop-filter: blur(12px) !important;

    cursor: pointer !important;
    overflow: visible !important;
  }

  .nav-control:hover,
  .nav-control:active{
    background: linear-gradient(135deg, #ef233c, #9b1025) !important;
    border-color: rgba(255,255,255,.35) !important;
  }

  .hamburger,
  .hamburger--saas{
    width: 26px !important;
    height: 22px !important;
    display: block !important;
    position: relative !important;
    z-index: 120001 !important;
  }

  .hamburger .line,
  .hamburger--saas .line{
    width: 26px !important;
    height: 3px !important;
    display: block !important;
    margin: 5px auto !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    opacity: 1 !important;
    visibility: visible !important;
    transition: all .25s ease !important;
  }

  /* Contenido baja para no meterse debajo del header fijo */
  .content-body{
    padding-top: 88px !important;
    position: relative !important;
    z-index: 1 !important;
  }

  /* Sidebar cerrado por defecto en celular */
  .deznav{
    position: fixed !important;
    top: 78px !important;
    left: 0 !important;

    width: 285px !important;
    max-width: 82vw !important;
    height: calc(100vh - 78px) !important;

    z-index: 100000 !important;

    padding-top: 0 !important;
    overflow: hidden !important;

    transform: translateX(-105%) !important;
    transition: transform .28s ease !important;

    background:
      radial-gradient(circle at 20% 0%, rgba(239,35,60,.30), transparent 35%),
      linear-gradient(180deg, #180612 0%, #07090f 50%, #030509 100%) !important;

    border-right: 1px solid rgba(255,255,255,.10) !important;
    box-shadow: 25px 0 55px rgba(0,0,0,.42) !important;
  }

  /* Sidebar abierto según clases comunes de la plantilla */
  body.menu-toggle .deznav,
  body.nav-open .deznav,
  body.sidebar-open .deznav,
  body.show-sidebar .deznav,
  .menu-toggle .deznav,
  .nav-open .deznav,
  .sidebar-open .deznav,
  .show-sidebar .deznav{
    transform: translateX(0) !important;
  }

  /* Scroll interno del menú */
  .deznav-scroll{
    height: 100% !important;
    max-height: calc(100vh - 78px) !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding-top: 18px !important;
    padding-bottom: 120px !important;
  }

  /* El menú no debe quedar pegado arriba */
  #menu.metismenu,
  #menu{
    padding: 18px 12px 120px 12px !important;
    margin: 0 !important;
  }

  /* Cuando el menú está abierto, mover la hamburguesa para que sirva como cerrar */
  body.menu-toggle .nav-control,
  body.nav-open .nav-control,
  body.sidebar-open .nav-control,
  body.show-sidebar .nav-control,
  .menu-toggle .nav-control,
  .nav-open .nav-control,
  .sidebar-open .nav-control,
  .show-sidebar .nav-control{
    left: 222px !important;
    background: rgba(12, 12, 16, .98) !important;
    border-color: rgba(255,255,255,.26) !important;
  }

  /* Icono tipo X cuando el menú está abierto */
  body.menu-toggle .hamburger .line:nth-child(1),
  body.nav-open .hamburger .line:nth-child(1),
  body.sidebar-open .hamburger .line:nth-child(1),
  body.show-sidebar .hamburger .line:nth-child(1){
    transform: translateY(8px) rotate(45deg) !important;
  }

  body.menu-toggle .hamburger .line:nth-child(2),
  body.nav-open .hamburger .line:nth-child(2),
  body.sidebar-open .hamburger .line:nth-child(2),
  body.show-sidebar .hamburger .line:nth-child(2){
    opacity: 0 !important;
  }

  body.menu-toggle .hamburger .line:nth-child(3),
  body.nav-open .hamburger .line:nth-child(3),
  body.sidebar-open .hamburger .line:nth-child(3),
  body.show-sidebar .hamburger .line:nth-child(3){
    transform: translateY(-8px) rotate(-45deg) !important;
  }

  /* Evita que cards o hero tapen el menú */
  .page-titles,
  .container-fluid,
  .dashboard-header,
  .card,
  .welcome-card,
  .hero,
  .main-content{
    position: relative !important;
    z-index: 1 !important;
  }
}

/* Celulares muy pequeños */
@media (max-width: 420px){
  .deznav{
    width: 270px !important;
    max-width: 84vw !important;
  }

  .nav-control,
  .nav-control--saas{
    left: 88px !important;
  }

  body.menu-toggle .nav-control,
  body.nav-open .nav-control,
  body.sidebar-open .nav-control,
  body.show-sidebar .nav-control,
  .menu-toggle .nav-control,
  .nav-open .nav-control,
  .sidebar-open .nav-control,
  .show-sidebar .nav-control{
    left: 208px !important;
  }
}
</style>

<ul class="metismenu" id="menu">

   <li>
      <a href="./main.php" class="ai-icon" aria-expanded="false">
         <i class="fa fa-home"></i>
         <span class="nav-text">Home</span>
      </a>
   </li>

   <?php
   $userUnidades = SessionData::getUnidadesUser();
   $esSuperAdmin = SessionData::superAdministrador();
   $canReports = SessionData::getPermission(7)
      || SessionData::getPermission(9)
      || SessionData::getPermission(21)
      || SessionData::getPermission(22);
   $canEmployees = SessionData::getPermission(27)
      || SessionData::getPermission(33)
      || SessionData::getPermission(45);
   $canConfig = SessionData::getPermission(40)
      || SessionData::hasPermission('configuracion.roles.view')
      || SessionData::hasPermission('configuracion.roles.manage')
      || $esSuperAdmin;
   ?>

   <?php if ($canReports): ?>
   <li>
      <a class="has-arrow ai-icon" href="javascript:void(0)" aria-expanded="false">
         <i class="fa fa-hand-pointer-o"></i>
         <span class="nav-text">Reports</span>
      </a>
      <ul aria-expanded="false">
         <?php if (SessionData::getPermission(7)): ?>
            <li><a href="./report.php">Enter Report</a></li>
         <?php endif; ?>
         <?php if (SessionData::getPermission(9)): ?>
            <li><a href="./report-list.php">Edit Report</a></li>
         <?php endif; ?>
         <?php if (SessionData::getPermission(21)): ?>
            <li><a href="./check_list.php">Check List Report</a></li>
         <?php endif; ?>
         <?php if (($esSuperAdmin || in_array(7, $userUnidades, true)) && SessionData::getPermission(21)): ?>
            <li><a href="./check_list_villasol.php">Check List Report Villasol</a></li>
         <?php endif; ?>
         <?php if (($esSuperAdmin || in_array(7, $userUnidades, true)) && SessionData::getPermission(22)): ?>
            <li><a href="./check_report_list_villasol.php">Show Check List Report Villasol</a></li>
         <?php endif; ?>
         <?php if (SessionData::getPermission(22)): ?>
            <li><a href="./check_report_list.php">Show Check List Report</a></li>
            <li><a href="./calendar.php">Calendar</a></li>
            <li><a href="./report-list-group.php">Report List Group Download</a></li>
         <?php endif; ?>
      </ul>
   </li>
   <?php endif; ?>

   <?php if (SessionData::getPermission(12)): ?>
      <li>
         <a class="has-arrow ai-icon" href="javascript:void(0)" aria-expanded="false">
            <i class="fa fa-address-book-o"></i>
            <span class="nav-text">Customers</span>
         </a>
         <ul aria-expanded="false">
            <li><a href="./places_customers.php">New Customer</a></li>
         </ul>
      </li>
   <?php endif; ?>

   <?php if (SessionData::getPermission(1)): ?>
      <li>
         <a href="./usuarios.php" class="ai-icon" aria-expanded="false">
            <i class="fa fa-user-circle-o"></i>
            <span class="nav-text">User</span>
         </a>
      </li>
   <?php endif; ?>

   <?php if ($canEmployees): ?>
      <li>
         <a class="has-arrow ai-icon" href="javascript:void(0)" aria-expanded="false">
            <i class="fa fa-address-card-o"></i>
            <span class="nav-text">Employees</span>
         </a>
         <ul aria-expanded="false">
            <?php if (SessionData::getPermission(27)): ?>
            <li><a href="./empleados.php">View Employees</a></li>
            <?php endif; ?>
            <?php if (SessionData::getPermission(33)): ?>
            <li><a href="./reloj.php">Check-in - Check-out</a></li>
            <?php endif; ?>
            <?php if (SessionData::getPermission(45)): ?>
            <li><a href="./informe_salidas.php">Entry - Exit</a></li>
            <li><a href="./uniformes.php">Uniforms</a></li>
            <?php endif; ?>
         </ul>
      </li>
   <?php endif; ?>

   <?php if ($canConfig): ?>
      <li>
         <a class="has-arrow ai-icon" href="javascript:void(0)" aria-expanded="false">
            <i class="fa fa-cog"></i>
            <span class="nav-text">Configuration</span>
         </a>
         <ul aria-expanded="false">
            <?php if (SessionData::getPermission(40)): ?>
            <li><a href="./configuracion.php">Configuration</a></li>
            <?php endif; ?>
            <?php if (SessionData::hasPermission('configuracion.roles.view') || SessionData::hasPermission('configuracion.roles.manage') || $esSuperAdmin): ?>
            <li><a href="./roles_permisos.php">Roles & Permissions</a></li>
            <?php endif; ?>
         </ul>
      </li>
   <?php endif; ?>

</ul>

<div class="sidebar-footer">
  <a href="logout.php" style="
    display:flex; align-items:center; justify-content:center; gap:10px;
    padding:12px 14px; border-radius:16px;
    background:linear-gradient(135deg, #e11d2e, #ff2a3d);
    border:none; color:#fff !important; font-weight:950;
    text-decoration:none !important;
  ">
    <i class="fa fa-power-off"></i> Logout
  </a>
</div>

<script>
/* =========================================================
   Activa visualmente el item actual sin afectar MetisMenu
   ========================================================= */
document.addEventListener('DOMContentLoaded', function () {
  const currentPage = window.location.pathname.split('/').pop();
  const menuLinks = document.querySelectorAll('#menu a[href]');

  menuLinks.forEach(function(link){
    const href = link.getAttribute('href');

    if (!href || href === 'javascript:void(0)' || href === 'javascript:void()') {
      return;
    }

    const linkPage = href.replace('./', '').split('/').pop();

    if (linkPage === currentPage) {
      link.classList.add('active');

      const parentUl = link.closest('ul');
      const parentLi = link.closest('li');

      if (parentLi) {
        parentLi.classList.add('mm-active');
      }

      if (parentUl && parentUl.id !== 'menu') {
        parentUl.classList.add('mm-show');
        parentUl.style.height = 'auto';

        const mainLi = parentUl.closest('li');
        if (mainLi) {
          mainLi.classList.add('mm-active');

          const mainA = mainLi.querySelector(':scope > a');
          if (mainA) {
            mainA.classList.add('active');
            mainA.setAttribute('aria-expanded', 'true');
          }
        }
      }
    }
  });
});
</script>