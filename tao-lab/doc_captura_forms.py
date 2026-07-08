# -*- coding: utf-8 -*-
# Captura os FORMULÁRIOS de cadastro (modais "Novo...") das telas do portal /robos.
import time, os
from playwright.sync_api import sync_playwright

BASE = "https://solucoesetao.com.br"
USER = "carlos.carv.almeida@gmail.com"
PWD  = "Linara.01"
OUT  = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\fc46e316-08dd-4693-9e35-805a6cb6dffe\scratchpad\prints"
os.makedirs(OUT, exist_ok=True)

# (nome, slug_portal, seletor_do_botao_que_abre_o_form, seletor_a_esperar)
FORMS = [
    ("form_ativo",       "formula-ativos",        "#taof-ativo-novo-btn",  "#taof-ativo-form"),
    ("form_prescritor",  "formula-prescritores",  "#taof-pr-novo",         "#taof-pr-form"),
    ("form_cliente",     "formula-historico",     "#taof-cli-novo",        "#taof-cli-form"),
    ("form_fornecedor",  "cotacoes-fornecedores", "[data-cot-new]",        "form[data-action='tao_cot_save_fornecedor']"),
    ("form_reposicao",   "formula-estoque-repo",  "#taof-rp-def",          "#taof-rp-min"),
]

with sync_playwright() as p:
    br = p.chromium.launch(channel="chrome", headless=True)
    pg = br.new_page(viewport={"width": 1280, "height": 950})
    pg.goto(BASE + "/wp-login.php", wait_until="domcontentloaded", timeout=60000)
    pg.fill("#user_login", USER); pg.fill("#user_pass", PWD); pg.click("#wp-submit")
    pg.wait_for_load_state("domcontentloaded", timeout=60000)
    print("LOGIN OK")
    for nome, slug, btn, esperar in FORMS:
        try:
            pg.goto(f"{BASE}/robos/{slug}/", wait_until="networkidle", timeout=60000)
            time.sleep(2)
            pg.click(btn, timeout=15000)
            pg.wait_for_selector(esperar, timeout=15000, state="visible")
            time.sleep(1.2)
            path = os.path.join(OUT, nome + ".png")
            pg.screenshot(path=path, full_page=True)
            print(f"  {nome}: {os.path.getsize(path)//1024} KB")
        except Exception as e:
            print(f"  {nome}: ERRO {str(e)[:80]}")
    br.close()
print("FIM")
