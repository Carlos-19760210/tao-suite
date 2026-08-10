# -*- coding: utf-8 -*-
import os
from playwright.sync_api import sync_playwright

OUT = r"C:\Users\carlo\tao-crm\_manuais\cot"
os.makedirs(OUT, exist_ok=True)
BASE = "https://solucoesetao.com.br"
ROBO = BASE + "/robos/"
COT  = "adde65e8-e992-48fb-989c-76db9f65a341"

HIDE = "#wpadminbar{display:none!important} html{margin-top:0!important} html.wp-toolbar{padding-top:0!important}"
def prep(pg):
    try: pg.add_style_tag(content=HIDE); pg.wait_for_timeout(200)
    except Exception: pass

def shot(pg, name):
    prep(pg)
    pg.screenshot(path=os.path.join(OUT, f"real_cot_{name}.png"), full_page=False)
    print("OK", name)

def shot_el(pg, sel, name, timeout=9000):
    try:
        el = pg.locator(sel).first
        el.wait_for(state="visible", timeout=timeout)
        prep(pg); el.scroll_into_view_if_needed(); pg.wait_for_timeout(500)
        el.screenshot(path=os.path.join(OUT, f"real_cot_{name}.png"))
        print("OK", name)
    except Exception as e:
        print("warn", name, str(e)[:140])

with sync_playwright() as p:
    try:
        br = p.chromium.launch(channel="chrome", headless=True)
    except Exception:
        br = p.chromium.launch(headless=True)
    pg = br.new_context(viewport={"width":1460,"height":900}).new_page()
    # login PORTAL /robos
    pg.goto(ROBO+"login/", wait_until="domcontentloaded", timeout=60000)
    pg.fill("#login","carlos.carv.almeida@gmail.com")
    pg.fill("#password","Linara.01")
    pg.click("button.btn-login")
    pg.wait_for_load_state("networkidle", timeout=60000); pg.wait_for_timeout(1800)
    print("URL pós-login:", pg.url)

    # 1) Fornecedores
    pg.goto(ROBO+"cotacoes-fornecedores/", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1500)
    shot(pg, "forn_lista")
    try:
        pg.click('[data-cot-new][data-modal="taocot-forn-modal"]', timeout=5000); pg.wait_for_timeout(700)
        shot_el(pg, "#taocot-forn-modal .taocot-box", "forn_form")
    except Exception as e: print("warn forn_form", str(e)[:140])

    # 2) Lista de cotações
    pg.goto(ROBO+"cotacoes/", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1300)
    shot(pg, "lista")

    # 3) Nova cotação
    pg.goto(ROBO+"cotacoes-nova/", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1300)
    shot(pg, "nova")

    # 4) Modelos (pode não existir no portal)
    pg.goto(ROBO+"cotacoes-modelos/", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1300)
    shot(pg, "modelos")

    # 5) Detalhe (com dados)
    pg.goto(ROBO+"cotacoes/?cot="+COT, wait_until="networkidle", timeout=60000); pg.wait_for_timeout(2800)
    shot(pg, "detalhe_topo")
    shot_el(pg, '.taocot-card:has(h2:has-text("Itens ("))', "itens")
    shot_el(pg, '.taocot-card:has(h2:has-text("Comparativo"))', "comparativo")
    shot_el(pg, '.taocot-card:has(h2:has-text("Sugestão de pedido"))', "sugestao")

    # 6) Modais
    try:
        pg.click("#taocot-conf-abrir", timeout=5000); pg.wait_for_timeout(800)
        shot_el(pg, "#taocot-conf-modal .taocot-box", "conferencia")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    except Exception as e: print("warn conferencia", str(e)[:140])
    try:
        pg.reload(wait_until="networkidle"); pg.wait_for_timeout(2200)
        pg.click(".taocot-ret-edit", timeout=5000); pg.wait_for_timeout(800)
        shot_el(pg, "#taocot-editret-modal .taocot-box", "editret")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    except Exception as e: print("warn editret", str(e)[:140])
    try:
        pg.reload(wait_until="networkidle"); pg.wait_for_timeout(2200)
        pg.click("#taocot-btn-retorno", timeout=5000); pg.wait_for_timeout(800)
        shot_el(pg, "#taocot-retorno-modal .taocot-box", "registrar_retorno")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    except Exception as e: print("warn registrar_retorno", str(e)[:140])
    try:
        pg.reload(wait_until="networkidle"); pg.wait_for_timeout(2200)
        pg.click("#taocot-btn-enviar", timeout=4000); pg.wait_for_timeout(1200)
        shot_el(pg, "#taocot-envio-modal .taocot-box", "envio")
    except Exception as e: print("warn envio", str(e)[:140])

    br.close()
print("fim")
