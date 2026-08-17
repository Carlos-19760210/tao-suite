# -*- coding: utf-8 -*-
"""
CAPTURA das 12 telas do TAO Neo com o negócio demo "Farmácia Modelo".

- Sessão principal: usuário gestor.demo (visão do CLIENTE, não Master).
- Telas que o produto restringe a admin (etapas do funil; e, se bloqueadas ao
  gestor, histórico do agente e configurações) são capturadas numa 2ª sessão
  admin APONTADA para o negócio demo — e o negócio-ativo do admin é salvo
  antes e RESTAURADO depois (não deixamos rastro na sessão do Carlos).
- Saída: capturas/*.png 1920x1080 (viewport, não full-page).

Pré-requisito: seed_demo.py executado (demo_ids.json + demo_credentials.json).
"""
import json, os, subprocess, sys
from urllib.parse import quote
from playwright.sync_api import sync_playwright

sys.stdout.reconfigure(encoding="utf-8")
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "capturas")
os.makedirs(OUT, exist_ok=True)

BASE = "https://solucoesetao.com.br"
IDS = json.load(open(os.path.join(HERE, "demo_ids.json")))
CRED = json.load(open(os.path.join(HERE, "demo_credentials.json")))
WS, CLI = IDS["workspace_id"], IDS["cliente_id"]
PIPE, CARD, CAMP = IDS["pipeline_vendas"], IDS["card_conversa_1"], IDS["campanha"]
PHONE = "5511960000001"  # Maria Exemplo (historico do agente)

ADMIN_EMAIL = "carlos.carv.almeida@gmail.com"
ADMIN_SENHA = "Linara.01"

SSH = ["ssh", "-i", r"C:\Users\carlo\.ssh\tao_crm_deploy", "-p", "65002",
       "u178063243@solucoesetao.com.br"]
WP_PATH = "domains/solucoesetao.com.br/public_html"

def wp(args, ok_fail=False):
    r = subprocess.run(SSH + [f"cd {WP_PATH} && wp " + args],
                       capture_output=True, text=True, timeout=90, encoding="utf-8")
    if r.returncode != 0 and not ok_fail:
        raise RuntimeError(f"wp {args}: {r.stderr.strip()[:200]}")
    out = (r.stdout or "")
    return "".join(ch for ch in out if ch.isprintable()).strip()

def login(ctx, usuario, senha):
    pg = ctx.new_page()
    pg.on("dialog", lambda d: d.accept())
    pg.goto(BASE + "/robos/login/", wait_until="domcontentloaded", timeout=60000)
    pg.fill("#login", usuario)
    pg.fill("#password", senha)
    pg.click("button.btn-login")
    pg.wait_for_load_state("networkidle", timeout=60000)
    pg.wait_for_timeout(1500)
    if "/login" in pg.url:
        raise RuntimeError(f"login falhou para {usuario}")
    return pg

def shot(pg, nome, url, espera=3.5, pre_js=None):
    # domcontentloaded + espera fixa: kanban/card têm polling (networkidle nunca chega)
    pg.goto(BASE + url, wait_until="domcontentloaded", timeout=60000)
    if pre_js:
        pg.evaluate(pre_js)
        pg.wait_for_timeout(400)
    pg.wait_for_timeout(int(espera * 1000))
    path = os.path.join(OUT, nome + ".png")
    pg.screenshot(path=path, full_page=False)
    print(f"  📸 {nome}.png  ({url})")
    return path

def tem(pg, seletor):
    try:
        return pg.locator(seletor).count() > 0
    except Exception:
        return False

pend_admin = []  # telas que o gestor não conseguiu ver

with sync_playwright() as p:
    br = p.chromium.launch(channel="chrome", headless=True)

    # ---------------- SESSÃO 1: GESTOR DEMO (visão do cliente) ----------------
    ctx = br.new_context(viewport={"width": 1920, "height": 1080}, device_scale_factor=1)
    ctx.add_init_script("try{localStorage.setItem('cbpm:sb','on')}catch(e){}")
    pg = login(ctx, CRED.get("email", "gestor.demo@farmaciamodelo.exemplo"), CRED["senha"])
    print("Gestor logado:", pg.url)

    # fixa o negócio demo (persiste em user_meta) + cookie do kanban
    pg.goto(f"{BASE}/robos/crm-kanban/?workspace_id={WS}", wait_until="networkidle", timeout=60000)
    ctx.add_cookies([{"name": "tao_crm_last_ws", "value": WS,
                      "domain": "solucoesetao.com.br", "path": "/"}])

    # 01 — conversa do robô (histórico do agente)
    shot(pg, "01_agente_conversa", f"/robos/historico/?phone={quote(PHONE)}&cliente_id={CLI}", 3.5)
    if not tem(pg, ".cbpm-msg-user, .cbpm-msg-bot"):
        print("  ⚠ 01 sem bolhas de conversa na visão gestor -> refazer como admin")
        pend_admin.append(("01_agente_conversa", f"/robos/historico/?phone={quote(PHONE)}&cliente_id={CLI}", 3.5, None))

    # 02 — lista do histórico
    shot(pg, "02_agente_historico", f"/robos/historico/?cliente_id={CLI}", 3.5)
    if not tem(pg, "table, .cbpm-card"):
        print("  ⚠ 02 vazia na visão gestor -> refazer como admin")
        pend_admin.append(("02_agente_historico", f"/robos/historico/?cliente_id={CLI}", 3.5, None))

    # 03 — kanban
    shot(pg, "03_crm_funil", f"/robos/crm-kanban/?workspace_id={WS}&pipeline_id={PIPE}", 4.5)

    # 04 — card aberto (chat interno)
    shot(pg, "04_crm_card_aberto", f"/robos/crm-kanban/?action=card&id={CARD}", 4.0)

    # 06/07 — campanha
    shot(pg, "06_campanha_edicao", f"/robos/campanhas/?action=edit&id={CAMP}", 3.5)
    shot(pg, "07_campanha_acompanhamento", f"/robos/campanhas/?action=view&id={CAMP}", 4.0)

    # 08/09 — caixa
    shot(pg, "08_caixa_painel", "/robos/caixa/?p=hoje", 4.0)
    shot(pg, "09_caixa_fechamento", "/robos/caixa-sessao/", 4.0)

    # 10 — persona do robô
    shot(pg, "10_config_persona", f"/robos/negocios/?action=edit&id={CLI}", 3.5)
    if not tem(pg, "textarea"):
        print("  ⚠ 10 sem formulário na visão gestor -> refazer como admin")
        pend_admin.append(("10_config_persona", f"/robos/negocios/?action=edit&id={CLI}", 3.5, None))

    # 11 — campos extras
    shot(pg, "11_config_campos_extras", "/robos/campos-extras/", 3.5)
    if not tem(pg, "table, input"):
        pend_admin.append(("11_config_campos_extras", f"/robos/campos-extras/?cliente_id={CLI}", 3.5, None))

    # 12 — configurações de módulos, visão do cliente (NUNCA /robos/configuracoes/:
    # a tela Plataforma expõe URLs internas de Supabase/N8N — proibido no vídeo)
    exp_js = ("try{localStorage.setItem('cg:config','1');"
              "localStorage.setItem('cs:cfg-geral','1');"
              "localStorage.setItem('cs:cfg-caixa','1')}catch(e){}")
    pg.evaluate(exp_js)
    shot(pg, "12_config_modulos", "/robos/caixa-formas/", 3.5, pre_js=exp_js)

    ctx.close()

    # ------------- SESSÃO 2: ADMIN (só o que o produto restringe) -------------
    print("== Sessão admin p/ telas restritas ==")
    admin_uid = "".join(ch for ch in wp(f"user list --login={ADMIN_EMAIL} --field=ID") if ch.isdigit())
    meta_antes = wp(f"user meta get {admin_uid} tao_crm_negocio_ativo", ok_fail=True)
    meta_antes = "".join(ch for ch in meta_antes if ch in "0123456789abcdef-")  # só UUID (wp-cli vaza BOM)
    print(f"  negócio-ativo do admin antes: '{meta_antes or '(vazio)'}' (será restaurado)")

    ctx2 = br.new_context(viewport={"width": 1920, "height": 1080}, device_scale_factor=1)
    ctx2.add_init_script("try{localStorage.setItem('cbpm:sb','on')}catch(e){}")
    pg2 = login(ctx2, ADMIN_EMAIL, ADMIN_SENHA)
    ctx2.add_cookies([{"name": "tao_crm_last_ws", "value": WS,
                       "domain": "solucoesetao.com.br", "path": "/"}])

    # sanitização das capturas admin: nomes de negócios reais e e-mail do admin
    # não podem aparecer em material comercial
    SANEIA = r"""
    (function(){
      var w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT), alvos = [];
      while (w.nextNode()) {
        var n = w.currentNode;
        if (/carlos\.carv/.test(n.nodeValue))
          n.nodeValue = n.nodeValue.replace(/\S*carlos\.carv\S*/g, 'gestor.demo@farmaciamodelo.exemplo');
        if (/magis|iluminar/i.test(n.nodeValue)) alvos.push(n);
      }
      alvos.forEach(function(n){
        var el = n.parentElement && (n.parentElement.closest('a,li,option,tr') || n.parentElement);
        if (el) el.style.display = 'none';
      });
    })();"""
    SCROLL_TOPO = "var c=document.getElementById('cbpm-chat-msgs'); if(c) c.scrollTop=0;"

    # 05 — edição das etapas do funil (admin-only por design)
    shot(pg2, "05_crm_etapas",
         f"/robos/crm-settings/?tab=pipelines&workspace_id={WS}&pipeline_id={PIPE}", 4.0,
         pre_js=SANEIA)

    for nome, url, espera, pre in pend_admin:
        js = (pre or "") + SANEIA + (SCROLL_TOPO if nome.startswith("01") else "")
        shot(pg2, nome, url, espera, pre_js=js)

    ctx2.close()
    br.close()

    # restaura o negócio-ativo do admin
    if meta_antes:
        wp(f"user meta update {admin_uid} tao_crm_negocio_ativo '{meta_antes}'")
    else:
        wp(f"user meta delete {admin_uid} tao_crm_negocio_ativo", ok_fail=True)
    print("  negócio-ativo do admin restaurado.")

print("\nCAPTURA COMPLETA ✔  ->", OUT)
