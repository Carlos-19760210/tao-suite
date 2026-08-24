# -*- coding: utf-8 -*-
"""Captura as telas do VÍDEO DO CRM (16:9, 1920x1080) na Farmácia Modelo.
Sessão admin apontada pro workspace demo (telas de config são admin-only),
DOM sanitizado (nomes reais ocultos) e negócio-ativo do admin restaurado.
Saída: capturas-crm/*.png"""
import json, os, subprocess, sys
from playwright.sync_api import sync_playwright

sys.stdout.reconfigure(encoding="utf-8")
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "capturas-crm")
os.makedirs(OUT, exist_ok=True)
BASE = "https://solucoesetao.com.br"
IDS = json.load(open(os.path.join(HERE, "demo_ids.json")))
WS, PIPE = IDS["workspace_id"], IDS["pipeline_vendas"]
CARD = IDS["card_conversa_1"]

SSH = ["ssh", "-i", r"C:\Users\carlo\.ssh\tao_crm_deploy", "-p", "65002",
       "u178063243@solucoesetao.com.br"]
WP = "cd domains/solucoesetao.com.br/public_html && wp "

def wp_cmd(args, ok_fail=False):
    r = subprocess.run(SSH + [WP + args], capture_output=True, text=True, timeout=90, encoding="utf-8")
    if r.returncode != 0 and not ok_fail:
        raise RuntimeError(r.stderr.strip()[:200])
    return "".join(ch for ch in (r.stdout or "") if ch.isprintable()).strip()

SANEIA = r"""
(function(){
  var w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT),alvos=[];
  while(w.nextNode()){var n=w.currentNode;
    if(/carlos\.carv/.test(n.nodeValue))n.nodeValue=n.nodeValue.replace(/\S*carlos\.carv\S*/g,'gestor@farmaciamodelo.exemplo');
    if(/magis|iluminar/i.test(n.nodeValue))alvos.push(n);}
  alvos.forEach(function(n){var el=n.parentElement&&(n.parentElement.closest('a,li,option,tr')||n.parentElement);
    if(el)el.style.display='none';});
})();"""

with sync_playwright() as p:
    br = p.chromium.launch(channel="chrome", headless=True)
    admin_uid = "".join(ch for ch in wp_cmd("user list --login=carlos.carv.almeida@gmail.com --field=ID") if ch.isdigit())
    meta_antes = "".join(ch for ch in wp_cmd(f"user meta get {admin_uid} tao_crm_negocio_ativo", ok_fail=True)
                         if ch in "0123456789abcdef-")
    print(f"negócio-ativo do admin antes: {meta_antes or '(vazio)'} (será restaurado)")

    ctx = br.new_context(viewport={"width": 1920, "height": 1080}, device_scale_factor=1)
    ctx.add_init_script("try{localStorage.setItem('cbpm:sb','on')}catch(e){}")
    pg = ctx.new_page()
    pg.on("dialog", lambda d: d.accept())
    pg.goto(BASE + "/robos/login/", wait_until="domcontentloaded", timeout=60000)
    pg.fill("#login", "carlos.carv.almeida@gmail.com"); pg.fill("#password", "Linara.01")
    pg.click("button.btn-login"); pg.wait_for_load_state("networkidle", timeout=60000)
    pg.goto(f"{BASE}/robos/crm-kanban/?workspace_id={WS}", wait_until="domcontentloaded", timeout=60000)
    ctx.add_cookies([{"name": "tao_crm_last_ws", "value": WS, "domain": "solucoesetao.com.br", "path": "/"}])
    pg.wait_for_timeout(4000)

    TELAS = [
        ("crm-01-kanban",     f"/robos/crm-kanban/?workspace_id={WS}&pipeline_id={PIPE}", 5.0),
        ("crm-02-card",       f"/robos/crm-kanban/?action=card&id={CARD}", 5.0),
        ("crm-03-funis",      f"/robos/crm-settings/?tab=pipelines&workspace_id={WS}&pipeline_id={PIPE}", 4.0),
        ("crm-04-automacoes", f"/robos/crm-settings/?tab=automacoes&workspace_id={WS}&pipeline_id={PIPE}", 4.0),
        ("crm-05-campos",     f"/robos/crm-settings/?tab=campos&workspace_id={WS}&pipeline_id={PIPE}", 4.0),
        ("crm-06-painel",     f"/robos/crm-dashboard/?workspace_id={WS}&dias=30", 7.0),
        ("crm-07-analise",    f"/robos/crm-analise/?workspace_id={WS}", 9.0),
        ("crm-08-perfis",     f"/robos/crm-perfis/?workspace_id={WS}", 4.0),
    ]
    for nome, url, espera in TELAS:
        pg.goto(BASE + url, wait_until="domcontentloaded", timeout=90000)
        pg.evaluate(SANEIA)
        pg.wait_for_timeout(int(espera * 1000))
        pg.evaluate(SANEIA)
        pg.screenshot(path=os.path.join(OUT, nome + ".png"), full_page=False)
        print(f"📸 {nome}.png")

    ctx.close(); br.close()
    if meta_antes:
        wp_cmd(f"user meta update {admin_uid} tao_crm_negocio_ativo {meta_antes}")
    else:
        wp_cmd(f"user meta delete {admin_uid} tao_crm_negocio_ativo", ok_fail=True)
    print("negócio-ativo do admin restaurado.")
print("OK ->", OUT)
