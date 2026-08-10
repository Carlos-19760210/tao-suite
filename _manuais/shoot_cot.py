# -*- coding: utf-8 -*-
import os
from playwright.sync_api import sync_playwright

OUT = r"C:\Users\carlo\tao-crm\_manuais\cot"
os.makedirs(OUT, exist_ok=True)
BASE = "https://solucoesetao.com.br"
COT  = "adde65e8-e992-48fb-989c-76db9f65a341"
ADM  = BASE + "/wp-admin/admin.php?page="

HIDE = "#wpadminbar{display:none!important} html{margin-top:0!important} html.wp-toolbar{padding-top:0!important}"
def prep(pg):
    try: pg.add_style_tag(content=HIDE); pg.wait_for_timeout(200)
    except Exception: pass

def shot(pg, name):
    prep(pg)
    pg.screenshot(path=os.path.join(OUT, f"real_cot_{name}.png"), full_page=False)
    print("OK", name)

def shot_el(pg, sel, name, timeout=8000):
    try:
        el = pg.locator(sel).first
        el.wait_for(state="visible", timeout=timeout)
        prep(pg); el.scroll_into_view_if_needed(); pg.wait_for_timeout(400)
        el.screenshot(path=os.path.join(OUT, f"real_cot_{name}.png"))
        print("OK", name)
    except Exception as e:
        print("warn", name, str(e)[:120])

with sync_playwright() as p:
    try:
        br = p.chromium.launch(channel="chrome", headless=True)
    except Exception:
        br = p.chromium.launch(headless=True)
    pg = br.new_context(viewport={"width":1460,"height":900}).new_page()
    # login wp-admin
    pg.goto(BASE+"/wp-login.php", wait_until="domcontentloaded", timeout=60000)
    pg.fill("#user_login","carlos.carv.almeida@gmail.com")
    pg.fill("#user_pass","Linara.01")
    pg.click("#wp-submit")
    pg.wait_for_load_state("networkidle", timeout=60000); pg.wait_for_timeout(1500)
    print("URL pós-login:", pg.url)

    # 1) Fornecedores (lista) + form
    pg.goto(ADM+"tao-cotacoes-fornecedores", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1500)
    shot(pg, "forn_lista")
    try:
        pg.click('[data-cot-new][data-modal="taocot-forn-modal"]', timeout=5000); pg.wait_for_timeout(600)
        shot_el(pg, "#taocot-forn-modal .taocot-box", "forn_form")
    except Exception as e: print("warn forn_form", str(e)[:120])

    # 2) Lista de cotações
    pg.goto(ADM+"tao-cotacoes", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1200)
    shot(pg, "lista")

    # 3) Nova cotação
    pg.goto(ADM+"tao-cotacoes-nova", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1200)
    shot(pg, "nova")

    # 4) Modelos de proposta
    pg.goto(ADM+"tao-cotacoes-modelos", wait_until="networkidle", timeout=60000); pg.wait_for_timeout(1200)
    shot(pg, "modelos")

    # 5) Detalhe da cotação (com dados)
    pg.goto(ADM+"tao-cotacoes&cot="+COT, wait_until="networkidle", timeout=60000); pg.wait_for_timeout(2500)
    shot_el(pg, '.taocot-card:has(h2:has-text("Itens ("))', "itens")
    shot_el(pg, '.taocot-card:has(h2:has-text("Comparativo"))', "comparativo")
    shot_el(pg, '.taocot-card:has(h2:has-text("Sugestão de pedido"))', "sugestao")

    # 6) Modais no detalhe
    try:
        pg.click("#taocot-conf-abrir", timeout=5000); pg.wait_for_timeout(700)
        shot_el(pg, "#taocot-conf-modal .taocot-box", "conferencia")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    except Exception as e: print("warn conferencia", str(e)[:120])
    try:
        pg.reload(wait_until="networkidle"); pg.wait_for_timeout(2000)
        pg.click(".taocot-ret-edit", timeout=5000); pg.wait_for_timeout(700)
        shot_el(pg, "#taocot-editret-modal .taocot-box", "editret")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    except Exception as e: print("warn editret", str(e)[:120])
    try:
        pg.reload(wait_until="networkidle"); pg.wait_for_timeout(2000)
        pg.click("#taocot-btn-retorno", timeout=5000); pg.wait_for_timeout(700)
        shot_el(pg, "#taocot-retorno-modal .taocot-box", "registrar_retorno")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    except Exception as e: print("warn registrar_retorno", str(e)[:120])
    try:
        pg.reload(wait_until="networkidle"); pg.wait_for_timeout(2000)
        pg.click("#taocot-btn-enviar", timeout=4000); pg.wait_for_timeout(1000)
        shot_el(pg, "#taocot-envio-modal .taocot-box", "envio")
    except Exception as e: print("warn envio(sem pendentes?)", str(e)[:120])

    br.close()
print("fim")
