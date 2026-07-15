# -*- coding: utf-8 -*-
# Captura screenshots das telas do TAO Lab (wp-admin), logado.
import sys, time, os
from playwright.sync_api import sync_playwright

BASE = "https://solucoesetao.com.br"
USER = "carlos.carv.almeida@gmail.com"
PWD  = "Linara.01"
OUT  = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\fc46e316-08dd-4693-9e35-805a6cb6dffe\scratchpad\prints"
os.makedirs(OUT, exist_ok=True)

# Telas do PORTAL /robos (slugs do frontend.php)
TELAS = [
    ("config",       "formula-config"),
    ("ativos",       "formula-ativos"),
    ("prescritores", "formula-prescritores"),
    ("fornecedores", "formula-fornecedores"),
    ("orc_novo",     "formula-novo-orc"),
    ("historico",    "formula-historico"),
    ("estoque_nf",   "formula-estoque-nf"),
    ("estoque_lotes","formula-estoque-lotes"),
    ("inventario",   "formula-estoque-inventario"),
    ("estoque_repo", "formula-estoque-repo"),
    ("producao",     "formula-producao"),
    ("producao_interna", "formula-producao-interna"),
    ("livro",        "formula-livro"),
    ("contas_pagar", "formula-contas-pagar"),
    ("sngpc",        "formula-sngpc"),
]
if len(sys.argv) > 1 and sys.argv[1] == "test":
    TELAS = [("config", "formula-config")]

with sync_playwright() as p:
    br = p.chromium.launch(channel="chrome", headless=True)
    pg = br.new_page(viewport={"width": 1280, "height": 900})
    # login
    pg.goto(BASE + "/wp-login.php", wait_until="domcontentloaded", timeout=60000)
    pg.fill("#user_login", USER)
    pg.fill("#user_pass", PWD)
    pg.click("#wp-submit")
    pg.wait_for_load_state("domcontentloaded", timeout=60000)
    if "wp-login" in pg.url:
        print("FALHA LOGIN — url:", pg.url); br.close(); sys.exit(1)
    print("LOGIN OK ->", pg.url)
    # telas de LISTA: captura só o topo (~10 itens), não a página inteira
    LISTAS = {"ativos","prescritores","fornecedores","historico","estoque_nf","estoque_lotes",
              "inventario","estoque_repo","producao","producao_interna","livro","contas_pagar","sngpc"}
    for nome, slug in TELAS:
        try:
            pg.goto(f"{BASE}/robos/{slug}/", wait_until="networkidle", timeout=60000)
            time.sleep(2.5)  # espera AJAX das tabelas
            path = os.path.join(OUT, f"{nome}.png")
            if nome in LISTAS:
                pg.screenshot(path=path, clip={"x":0,"y":0,"width":1280,"height":760})  # cabeçalho + ~10 linhas
            else:
                pg.screenshot(path=path, full_page=True)
            print(f"  {nome}: {os.path.getsize(path)//1024} KB")
        except Exception as e:
            print(f"  {nome}: ERRO {e}")
    br.close()
print("FIM")
